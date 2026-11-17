<?php

namespace App\Services;

use App\Models\PeriodeCutoff;
use App\Models\SnapshotPsn;
use App\Models\User;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard "Ikhtisar Portofolio": angka gaya paparan Bahan Rapat Tim Koordinasi PSN (16 Nov 2026).
 *  - Perjalanan daftar PSN dalam RKP (slide 4): tahap bersejarah dari config, dua tahap terakhir dari basis data.
 *  - PKPN, sebaran per wilayah, porsi skema pendanaan 3 kelompok (slide 6).
 *  - PSN Kumulatif Selesai menurut kebutuhan status, status & target selesai terhadap 2029 (slide 13, 21, 25-31).
 * Semua angka dibaca dari snapshot cut-off terbit (isian SIRENDA PEKS3), dengan filter global yang sama
 * seperti panel dashboard lain, kecuali perjalanan daftar RKP (kebijakan seluruh daftar, tidak difilter).
 */
class IkhtisarPortofolioService
{
    public function __construct(protected DashboardService $dashboard, protected StatusResolver $status) {}

    public function ringkasan(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->dashboard->cacheIkhtisar('ikhtisar.v1', $c, $f, function () use ($c, $f) {
            $semua = $this->dashboard->baris($c, $f);
            $aktif = $semua->where('is_aktif', true);
            $sebelumnya = ($p = $c->sebelumnya()) ? $this->dashboard->baris($p, $f)->where('is_aktif', true) : null;

            return [
                'pkpn' => $this->pkpn($aktif, $sebelumnya),
                'wilayah' => $this->wilayah($aktif),
                'skema' => $this->skema($aktif),
                'selesai' => $this->selesai($aktif),
                'status' => $this->statusTarget($semua),
                'rkp' => $this->riwayatRkp($c),
            ];
        });
    }

    // ------------------------------------------------------------------ kartu PKPN

    protected function pkpn(Collection $aktif, ?Collection $sebelumnya): array
    {
        $pkpn = $aktif->where('kategori', 'PKPN');
        $total = $aktif->sum(fn ($r) => (float) $r->nilai_investasi_rp);
        $investasi = $pkpn->sum(fn ($r) => (float) $r->nilai_investasi_rp);
        $lalu = $sebelumnya?->where('kategori', 'PKPN')->count();

        return [
            'jumlah' => $pkpn->count(),
            'investasi_triliun' => round($investasi / 1e12, 1),
            'persen_investasi' => $total > 0 ? round($investasi / $total * 100, 1) : null,
            'dari_psn' => $aktif->count(),
            'sebelumnya' => $lalu,
            'delta' => $this->status->delta((float) $pkpn->count(), $lalu === null ? null : (float) $lalu, 'jumlah', 'naik'),
        ];
    }

    // ------------------------------------------------------------------ wilayah

    /** Setiap PSN tepat di satu kelompok: satu wilayah, atau "Nasional & Lintas Pulau" (≥ 2 wilayah / tanpa lokasi). */
    protected function wilayah(Collection $aktif): array
    {
        $cfg = config('psn_dashboard.ikhtisar');
        $prov = DB::table('ref_wilayah')->where('level', 1)->get(['kode', 'nama', 'lat', 'lng'])->keyBy('kode');
        // Loop biasa: flatMap/array_merge menomori ulang kunci numerik ("11" -> 0).
        $keWilayah = [];
        foreach ($cfg['wilayah'] as $nama => $kode) {
            foreach ($kode as $k) {
                $keWilayah[(string) $k] = $nama;
            }
        }
        $lintas = $cfg['wilayah_lintas'];

        $kelompok = collect(array_keys($cfg['wilayah']))->push($lintas)
            ->mapWithKeys(fn ($n) => [$n => ['jumlah' => 0, 'investasi' => 0.0, 'pkpn' => 0]]);
        foreach ($aktif as $r) {
            $w = collect((array) $r->provinsi_kode)->map(fn ($k) => $keWilayah[$k] ?? null)->filter()->unique();
            $k = $w->count() === 1 ? $w->first() : $lintas;
            $kelompok[$k] = ['jumlah' => $kelompok[$k]['jumlah'] + 1, 'investasi' => $kelompok[$k]['investasi'] + (float) $r->nilai_investasi_rp,
                'pkpn' => $kelompok[$k]['pkpn'] + ($r->kategori === 'PKPN' ? 1 : 0)];
        }

        return [
            'kelompok' => $kelompok->map(function ($h, $nama) use ($cfg, $prov, $lintas) {
                $p = $prov->only($cfg['wilayah'][$nama] ?? []);

                return ['label' => $nama, 'lintas' => $nama === $lintas, 'jumlah' => $h['jumlah'], 'pkpn' => $h['pkpn'],
                    'investasi_triliun' => round($h['investasi'] / 1e12, 1),
                    'lat' => $p->isEmpty() ? null : round($p->avg('lat'), 3), 'lng' => $p->isEmpty() ? null : round($p->avg('lng'), 3)];
            })->values()->all(),
            // Titik latar peta (semua provinsi), tanpa angka.
            'provinsi' => $prov->filter(fn ($p) => $p->lat !== null)->map(fn ($p) => ['kode' => $p->kode, 'label' => $p->nama, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng])->values()->all(),
        ];
    }

    // ------------------------------------------------------------------ skema pendanaan 3 kelompok

    protected function skema(Collection $aktif): array
    {
        $cfg = config('psn_dashboard.ikhtisar');
        $kodeDana = DB::table('ref_sumber_dana')->pluck('kode', 'id')->all();
        $hasil = collect($cfg['skema_tiga'])->map(fn ($l, $k) => ['kode' => strtolower($k), 'label' => $l, 'investasi' => 0.0, 'jumlah' => 0, 'pkpn' => 0]);

        foreach ($aktif as $r) {
            $kode = collect((array) $r->sumber_dana_id)->map(fn ($id) => $kodeDana[$id] ?? null)->filter()->unique();
            $pemerintah = $kode->intersect($cfg['dana_pemerintah'])->isNotEmpty();
            $lain = $kode->diff($cfg['dana_pemerintah'])->isNotEmpty();
            $k = match (true) {
                $kode->isEmpty() => 'TANPA',
                $pemerintah && $lain => 'CAMPURAN',
                $pemerintah => 'APBN_APBD',
                default => 'NON_APBN',
            };
            $hasil[$k] = ['investasi' => $hasil[$k]['investasi'] + (float) $r->nilai_investasi_rp, 'jumlah' => $hasil[$k]['jumlah'] + 1,
                'pkpn' => $hasil[$k]['pkpn'] + ($r->kategori === 'PKPN' ? 1 : 0)] + $hasil[$k];
        }
        $total = $hasil->sum('investasi');

        return $hasil->map(fn ($h) => ['kode' => $h['kode'], 'label' => $h['label'], 'jumlah' => $h['jumlah'], 'pkpn' => $h['pkpn'],
            'investasi_triliun' => round($h['investasi'] / 1e12, 1),
            'persen' => $total > 0 ? round($h['investasi'] / $total * 100, 1) : null])->values()->all();
    }

    // ------------------------------------------------------------------ PSN selesai x kebutuhan status

    protected function selesai(Collection $aktif): array
    {
        $idSelesai = DB::table('ref_status_psn')->where('kode', '1')->value('id');
        $rows = $aktif->where('status_psn_id', $idSelesai);
        $psn = DB::table('psn')->whereIn('id', $rows->pluck('psn_id'))->get(['id', 'nama', 'kode_psn', 'legacy_id'])->keyBy('id');
        $teks = DB::table('psn_evaluasi_status')->whereIn('psn_id', $rows->pluck('psn_id'))->whereNull('deleted_at')
            ->orderBy('psn_id')->orderByDesc('tahun')->orderBy('id')->get(['psn_id', 'kebutuhan_status'])
            ->groupBy('psn_id')->map(fn ($g) => $g->pluck('kebutuhan_status')->filter()->implode(' | '));
        $d1 = DB::table('evaluasi_psn')->whereIn('psn_id', $rows->pluck('psn_id'))->whereNull('deleted_at')->pluck('d1_kebutuhan', 'psn_id');

        $daftar = $rows->map(function ($r) use ($psn, $teks, $d1) {
            $isian = trim((string) ($teks[$r->psn_id] ?? ''));
            [$kategori, $sumber] = $isian !== '' ? [$this->kategoriKebutuhan($isian), 'SIRENDA'] : [null, null];
            if (($kategori === null || $kategori === 'KONFIRMASI') && in_array($d1[$r->psn_id] ?? null, ['MASIH', 'TIDAK'], true)) {
                [$kategori, $sumber] = [$d1[$r->psn_id], 'EVALUASI'];
            }
            $p = $psn[$r->psn_id] ?? null;

            return ['psn_id' => $r->psn_id, 'kode' => $p?->kode_psn, 'nama' => $p?->nama, 'urut' => $p?->legacy_id ?? PHP_INT_MAX,
                'kategori' => $kategori ?? 'KONFIRMASI', 'sumber' => $sumber, 'isian' => $isian !== '' ? mb_strimwidth($isian, 0, 240, '…') : null];
        })->sortBy([['urut', 'asc'], ['psn_id', 'asc']])->values();

        return [
            'total' => $daftar->count(),
            'kategori' => collect(config('psn_dashboard.ikhtisar.label_kebutuhan'))->map(fn ($l, $k) => [
                'kode' => $k, 'label' => $l, 'jumlah' => $daftar->where('kategori', $k)->count(),
                'psn' => $daftar->where('kategori', $k)->map(fn ($x) => collect($x)->except(['urut', 'kategori'])->all())->values()->all(),
            ])->values()->all(),
        ];
    }

    /** TIDAK / MASIH / KONFIRMASI dari teks isian K/L (pola di config, TIDAK diuji lebih dulu). */
    public function kategoriKebutuhan(string $teks): string
    {
        foreach (config('psn_dashboard.ikhtisar.kebutuhan_status') as $kategori => $pola) {
            foreach ($pola as $p) {
                if (preg_match($p, $teks)) {
                    return $kategori;
                }
            }
        }

        return 'KONFIRMASI';
    }

    // ------------------------------------------------------------------ status & target selesai

    protected function statusTarget(Collection $semua): array
    {
        $ref = DB::table('ref_status_psn')->get(['id', 'kode', 'is_aktif'])->keyBy('id');
        $label = config('psn_dashboard.evaluasi_status.tahapan');
        $peta = config('psn_dashboard.form_pengendalian.tahapan');
        $aktif = $semua->where('is_aktif', true);
        $batas = (int) config('psn_dashboard.ikhtisar.tahun_batas');

        $tahap = $aktif->countBy(fn ($r) => $peta[$ref[$r->status_psn_id]->kode ?? ''] ?? 'TANPA');
        $belumSelesai = $aktif->filter(fn ($r) => ($peta[$ref[$r->status_psn_id]->kode ?? ''] ?? null) !== 'KUMULATIF_SELESAI');
        $target = $belumSelesai->countBy(fn ($r) => match (true) {
            $r->tahun_selesai === null => 'TANPA',
            (int) $r->tahun_selesai <= $batas - 2 => 'SEBELUM',
            (int) $r->tahun_selesai <= $batas => 'BATAS',
            default => 'SETELAH',
        });

        return [
            'tahap' => collect($label)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l, 'jumlah' => $tahap[$k] ?? 0])->values()
                ->push(['kode' => 'TANPA', 'label' => 'Belum ada data', 'jumlah' => $tahap['TANPA'] ?? 0])->all(),
            'keluar' => $semua->where('is_aktif', false)->count(),
            'belum_selesai' => $belumSelesai->count(),
            'target' => [
                ['kode' => 'SEBELUM', 'label' => 's.d. '.($batas - 2), 'jumlah' => $target['SEBELUM'] ?? 0],
                ['kode' => 'BATAS', 'label' => ($batas - 1).'–'.$batas, 'jumlah' => $target['BATAS'] ?? 0],
                ['kode' => 'SETELAH', 'label' => '> '.$batas, 'jumlah' => $target['SETELAH'] ?? 0],
                ['kode' => 'TANPA', 'label' => 'Belum diisi', 'jumlah' => $target['TANPA'] ?? 0],
            ],
            'tahun_batas' => $batas,
        ];
    }

    // ------------------------------------------------------------------ perjalanan daftar RKP

    /**
     * Tahap bersejarah dari config; "daftar" = PSN bersumber RKP pada cut-off (termasuk yang kini keluar);
     * "proyeksi" = daftar - PSN keluar di SIRENDA - indikasi Dikeluarkan (Evaluasi) + usulan baru direkomendasikan (FINAL).
     * Tidak mengikuti filter global (menggambarkan seluruh daftar) dan tidak dibatasi cakupan unit.
     */
    protected function riwayatRkp(PeriodeCutoff $c): array
    {
        $rkp = SnapshotPsn::withoutGlobalScopes()->where('periode_cutoff_id', $c->id)->where('sumber', 'RKP')->get(['psn_id', 'is_aktif']);
        $keluar = $rkp->where('is_aktif', false);
        $dikeluarkan = DB::table('evaluasi_psn')->whereNull('deleted_at')->where('indikasi_status', 'DIKELUARKAN')
            ->whereIn('psn_id', $rkp->where('is_aktif', true)->pluck('psn_id'))->pluck('psn_id');
        $tahunRkp = (int) config('psn_dashboard.evaluasi_status.tahun_rkp');
        $baru = DB::table('usulan_rekomendasi as r')->join('usulan_psn as u', 'u.id', '=', 'r.usulan_id')
            ->where('r.status', 'FINAL')->where('r.rekomendasi', 'DIREKOMENDASIKAN')->whereNull('r.deleted_at')->whereNull('u.deleted_at')
            ->where('u.tahun_rkp', $tahunRkp)->orderBy('u.nama')->pluck('u.nama');
        // Nama hanya untuk pengguna berakses penuh (cakupan unit ditegakkan di query; jumlah tetap ditampilkan).
        $user = Auth::user();
        $namaKeluar = $user instanceof User && $user->lihatTerbatas() ? collect()
            : DB::table('psn')->whereIn('id', $keluar->pluck('psn_id')->merge($dikeluarkan))->orderBy('legacy_id')->pluck('nama');
        $namaBaru = $user instanceof User && $user->lihatTerbatas() ? collect() : $baru;

        return collect(config('psn_dashboard.ikhtisar.riwayat_rkp'))->map(function ($t) use ($rkp, $keluar, $dikeluarkan, $baru, $namaBaru, $namaKeluar) {
            $live = $t['live'] ?? null;
            $t['perubahan'] = collect($t['perubahan'])->map(fn ($p) => ['arah' => $p[0], 'jumlah' => $p[1], 'label' => $p[2], 'rincian' => $p[3] ?? null])->all();
            if ($live === 'daftar') {
                $t['dokumen'] = $t['jumlah'];
                $t['jumlah'] = $rkp->count();
            } elseif ($live === 'proyeksi') {
                $kurang = $keluar->count() + $dikeluarkan->count();
                $t['jumlah'] = $rkp->count() - $kurang + $baru->count();
                $t['perubahan'] = [
                    ['arah' => '+', 'jumlah' => $baru->count(), 'label' => 'Usulan PSN baru', 'rincian' => $namaBaru->take(8)->implode(', ') ?: 'Usulan berekomendasi final dari menu Perencanaan'],
                    ['arah' => '-', 'jumlah' => $kurang, 'label' => 'PSN dikeluarkan', 'rincian' => $namaKeluar->take(8)->implode(', ') ?: 'Keluar di SIRENDA atau indikasi Dikeluarkan di menu Evaluasi'],
                ];
            }
            $t['live'] = $live !== null;

            return $t;
        })->all();
    }
}
