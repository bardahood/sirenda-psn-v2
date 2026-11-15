<?php

namespace App\Services;

use App\Models\EvaluasiPsn;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi keberlanjutan status PSN (kertas kerja "Input Form Pengendalian PSN 2026"):
 *  - kelengkapan: aturan wajib isi (sheet "Aturan Wajib Isi") dengan data A-C dibaca dari profil PSN
 *    (snapshot cut-off terbit terakhir, isu, kegiatan, manfaat) dan D1-D3 dari isian evaluasi;
 *  - indikator & rekomendasi sistem sebagai alat bantu analis (keputusan tetap pada Indikasi Status);
 *  - status akhir, riwayat, dan data visual pemantauan. Lihat docs/evaluasi-status-psn.md.
 */
class EvaluasiStatusService
{
    protected ?PeriodeCutoff $cutoff = null;

    protected bool $cutoffDimuat = false;

    public function tahun(): int
    {
        return (int) config('psn_dashboard.evaluasi_status.tahun_rkp');
    }

    public function cutoff(): ?PeriodeCutoff
    {
        if (! $this->cutoffDimuat) {
            $this->cutoff = PeriodeCutoff::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff')->first();
            $this->cutoffDimuat = true;
        }

        return $this->cutoff;
    }

    /**
     * Semua PSN aktif pada cut-off terbit terakhir (dalam cakupan pengguna) beserta hasil analisisnya.
     *
     * @return Collection<int, array>
     */
    public function daftar(): Collection
    {
        $c = $this->cutoff();
        if (! $c) {
            return collect();
        }
        $tahapan = config('psn_dashboard.form_pengendalian.tahapan');

        $psn = Psn::query()
            ->join('snapshot_psn as s', fn ($j) => $j->on('s.psn_id', '=', 'psn.id')->where('s.periode_cutoff_id', $c->id))
            ->leftJoin('ref_status_psn as st', 'st.id', '=', 's.status_psn_id')
            ->leftJoin('ref_klaster as k', 'k.id', '=', 's.klaster_id')
            ->where('s.is_aktif', true)
            ->orderBy('psn.nama')->orderBy('psn.id')
            ->get(['psn.id', 'psn.nama', 'psn.kode_psn', 'psn.tahun_selesai', 's.sumber', 's.progres_rencana_persen', 's.progres_realisasi_persen',
                's.pagu_rp', 's.realisasi_anggaran_rp', 's.jumlah_ro', 's.jumlah_ro_tercapai', 's.unit_kerja_id', 'st.kode as status_kode', 'k.nama as klaster']);
        $id = $psn->pluck('id')->all();
        if (! $id) {
            return collect();
        }

        $isu = DB::table('isu as i')->leftJoin('kegiatan as kg', 'kg.id', '=', 'i.kegiatan_id')
            ->whereIn('i.psn_id', $id)->whereNull('i.deleted_at')->where('i.status', '<>', 'SELESAI')
            ->groupBy('i.psn_id')
            ->selectRaw("i.psn_id, COUNT(*) AS terbuka, SUM(COALESCE(TRIM(i.kebutuhan_dukungan), '') <> '') AS dengan_dukungan,
                SUM(COALESCE(kg.is_critical_path, 0) = 1) AS jalur_kritis")
            ->get()->keyBy('psn_id');
        $kegiatan = DB::table('kegiatan')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')
            ->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id');
        $manfaat = DB::table('penerima_manfaat')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id');
        foreach (DB::table('indikator')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id') as $p => $n) {
            $manfaat[$p] = ($manfaat[$p] ?? 0) + $n;
        }
        $regulasi = DB::table('regulasi')->whereIn('psn_id', $id)->whereNull('deleted_at')->where('tahap', '<>', 'DITETAPKAN')
            ->groupBy('psn_id')->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id');
        $evaluasi = EvaluasiPsn::where('tahun_rkp', $this->tahun())->whereIn('psn_id', $id)->get()->keyBy('psn_id');

        return $psn->map(function ($p) use ($tahapan, $isu, $kegiatan, $manfaat, $regulasi, $evaluasi) {
            $data = [
                'tahapan' => $tahapan[(string) $p->status_kode] ?? null,
                'tahun_selesai' => $p->tahun_selesai ? (int) $p->tahun_selesai : null,
                'progres_rencana' => $p->progres_rencana_persen !== null ? (float) $p->progres_rencana_persen : null,
                'progres_realisasi' => $p->progres_realisasi_persen !== null ? (float) $p->progres_realisasi_persen : null,
                'pagu_rp' => $p->pagu_rp !== null ? (float) $p->pagu_rp : null,
                'realisasi_anggaran_rp' => $p->realisasi_anggaran_rp !== null ? (float) $p->realisasi_anggaran_rp : null,
                'ro' => [(int) $p->jumlah_ro_tercapai, (int) $p->jumlah_ro],
                'isu_terbuka' => (int) ($isu[$p->id]->terbuka ?? 0),
                'isu_dengan_dukungan' => (int) ($isu[$p->id]->dengan_dukungan ?? 0),
                'isu_jalur_kritis' => (int) ($isu[$p->id]->jalur_kritis ?? 0),
                'kegiatan' => (int) ($kegiatan[$p->id] ?? 0),
                'manfaat' => (int) ($manfaat[$p->id] ?? 0),
                'regulasi_belum' => (int) ($regulasi[$p->id] ?? 0),
            ];
            $e = $evaluasi[$p->id] ?? null;

            return ['psn_id' => $p->id, 'nama' => $p->nama, 'kode' => $p->kode_psn, 'sumber' => $p->sumber, 'klaster' => $p->klaster,
                'evaluasi' => $e, 'status_akhir' => $e?->status_akhir ?? 'BELUM_DITERIMA'] + $this->analisis($data, $e);
        });
    }

    /** Analisis satu PSN untuk halaman detail. */
    public function satu(Psn $psn): ?array
    {
        return $this->daftar()->firstWhere('psn_id', $psn->id);
    }

    /**
     * @param  array  $d  data profil (lihat daftar())
     * @return array{data:array, kurang:string[], lengkap:bool, indikator:array, rekomendasi:string, sinyal:string[]}
     */
    public function analisis(array $d, ?EvaluasiPsn $e): array
    {
        $t = $d['tahapan'];
        $berjalan = in_array($t, ['PENYIAPAN', 'TRANSAKSI', 'KONSTRUKSI', 'BEROPERASI_SEBAGIAN'], true);
        $kurang = [];

        // Aturan wajib isi (kertas kerja sheet "Aturan Wajib Isi"), dipetakan ke data profil.
        $e?->a6_tahun_penetapan || $kurang[] = 'A6 Tahun penetapan PSN';
        $d['tahun_selesai'] || $kurang[] = 'A7 Target tahun selesai';
        $t || $kurang[] = 'B1 Tahapan';
        if (in_array($t, ['KONSTRUKSI', 'BEROPERASI_SEBAGIAN', 'KUMULATIF_SELESAI'], true)) {
            ($d['progres_rencana'] !== null && $d['progres_realisasi'] !== null) || $kurang[] = 'B2 Progres fisik/output';
            ($d['pagu_rp'] && $d['realisasi_anggaran_rp'] !== null) || $kurang[] = 'B2 Pembiayaan';
        }
        if (in_array($t, ['PENYIAPAN', 'TRANSAKSI'], true) && ! $d['kegiatan']) {
            $kurang[] = 'B3 Milestone (KP/RO)';
        }
        if (in_array($t, ['BEROPERASI_SEBAGIAN', 'KUMULATIF_SELESAI'], true) && ! $d['manfaat']) {
            $kurang[] = 'B4 Manfaat';
        }
        if ($berjalan && ! $e?->tanpa_isu) {
            $d['isu_terbuka'] || $kurang[] = 'C1 Tipologi isu';
            $d['isu_dengan_dukungan'] || $kurang[] = 'C2 Isu dan dukungan yang dibutuhkan';
        }
        $e?->d1_kebutuhan || $kurang[] = 'D1 Kebutuhan status PSN';
        if ($e?->d1_kebutuhan === 'MASIH') {
            $e->d2_fasilitas || $kurang[] = 'D2 Fasilitas';
            if ($berjalan && ! ($e->d3_tahapan && $e->d3_target && $e->d3_tahun_selesai)) {
                $kurang[] = 'D3 Target selanjutnya';
            }
        }

        // Indikator.
        $a = config('psn_dashboard.evaluasi_status.ambang');
        $deviasi = $d['progres_rencana'] !== null && $d['progres_realisasi'] !== null ? round($d['progres_realisasi'] - $d['progres_rencana'], 1) : null;
        $serapan = $d['pagu_rp'] ? round(100 * ($d['realisasi_anggaran_rp'] ?? 0) / $d['pagu_rp'], 1) : null;
        $mundur = $e?->d3_tahun_selesai && $d['tahun_selesai'] ? $e->d3_tahun_selesai - $d['tahun_selesai'] : null;
        $umur = $e?->a6_tahun_penetapan ? $this->tahun() - $e->a6_tahun_penetapan : null;
        $indikator = ['deviasi_fisik' => $deviasi, 'serapan' => $serapan, 'mundur_tahun' => $mundur, 'umur_psn' => $umur,
            'isu_terbuka' => $d['isu_terbuka'], 'isu_jalur_kritis' => $d['isu_jalur_kritis'], 'regulasi_belum' => $d['regulasi_belum']];

        // Rekomendasi sistem dan sinyal pendukungnya.
        $sinyal = [];
        $deviasi !== null && $deviasi <= -$a['deviasi_fisik_poin'] && $sinyal[] = "Progres fisik tertinggal {$this->angka(-$deviasi)} poin dari rencana";
        $serapan !== null && $serapan < $a['serapan_min_persen'] && $sinyal[] = "Serapan anggaran {$this->angka($serapan)}% dari pagu";
        $mundur !== null && $mundur >= $a['mundur_tahun'] && $sinyal[] = "Proyeksi selesai mundur {$mundur} tahun dari target";
        $umur !== null && in_array($t, ['PENYIAPAN', 'TRANSAKSI'], true) && $umur >= $a['penyiapan_maks_tahun']
            && $sinyal[] = 'Masih tahap '.mb_strtolower(config("psn_dashboard.evaluasi_status.tahapan.{$t}"))." {$umur} tahun sejak ditetapkan";
        $d['isu_jalur_kritis'] && $sinyal[] = "{$d['isu_jalur_kritis']} isu terbuka menghambat jalur kritis";
        $e?->d1_kebutuhan === 'MASIH' && ! $e->d2_fasilitas && $sinyal[] = 'Membutuhkan status PSN tanpa fasilitas yang dipilih';

        $rekomendasi = match (true) {
            $e?->d1_kebutuhan === 'TIDAK' => 'DIKELUARKAN',
            $t === 'KUMULATIF_SELESAI' && $e?->d1_kebutuhan !== 'MASIH' => 'DIKELUARKAN',
            $kurang !== [] => 'BELUM_CUKUP_DATA',
            $sinyal !== [] => 'PERLU_PENDALAMAN',
            default => 'DIPERTAHANKAN',
        };
        if ($e?->d1_kebutuhan === 'TIDAK') {
            array_unshift($sinyal, 'PJ menyatakan tidak lagi membutuhkan status PSN'.($e->d1_alasan ? ': '.config("psn_dashboard.evaluasi_status.d1_alasan.{$e->d1_alasan}") : ''));
        } elseif ($t === 'KUMULATIF_SELESAI') {
            array_unshift($sinyal, 'Tahapan kumulatif selesai');
        }

        return ['data' => $d, 'kurang' => $kurang, 'lengkap' => $kurang === [], 'indikator' => $indikator, 'rekomendasi' => $rekomendasi, 'sinyal' => $sinyal];
    }

    /** Simpan isian evaluasi; status akhir dihitung ulang dan setiap perubahannya dicatat di riwayat. */
    public function simpan(EvaluasiPsn $e, array $isian, ?string $keterangan = null): EvaluasiPsn
    {
        return DB::transaction(function () use ($e, $isian, $keterangan) {
            $e->fill($isian);
            if ($e->isDirty('hasil_pengecekan') && ! array_key_exists('tindak_lanjut', $isian)) {
                $e->tindak_lanjut = config("psn_dashboard.evaluasi_status.tindak_lanjut_otomatis.{$e->hasil_pengecekan}");
            }
            $lama = $e->exists ? $e->getOriginal('status_akhir') : null;
            $e->status_akhir = $e->hitungStatusAkhir();
            $e->save();
            if ($lama !== $e->status_akhir) {
                $e->riwayat()->create(['tanggal' => now()->toDateString(), 'status_akhir' => $e->status_akhir,
                    'keterangan' => $keterangan ?? ($lama ? null : 'Form diterima'), 'user_id' => Auth::id()]);
            }

            return $e;
        });
    }

    /** Ringkasan untuk visual alur (gaya "PSN dalam daftar" RKP). */
    public function alur(Collection $daftar): array
    {
        $per = $daftar->countBy('status_akhir');
        $nama = fn (string $s) => $daftar->where('status_akhir', $s)->sortBy('nama')->take(5)->map(fn ($r) => ['id' => $r['psn_id'], 'nama' => $r['nama']])->values()->all();
        $baru = DB::table('usulan_rekomendasi as r')->join('usulan_psn as u', 'u.id', '=', 'r.usulan_id')
            ->where('r.status', 'FINAL')->where('r.rekomendasi', 'DIREKOMENDASIKAN')->whereNull('r.deleted_at')->whereNull('u.deleted_at')
            ->where('u.tahun_rkp', $this->tahun())->orderBy('u.nama')->get(['u.id', 'u.nama']);
        $dikeluarkan = $per['DIKELUARKAN'] ?? 0;

        return [
            'total' => $daftar->count(),
            'sumber' => $daftar->countBy(fn ($r) => $r['sumber'] ?: 'LAINNYA')->sortKeys()->all(),
            'diterima' => $daftar->count() - ($per['BELUM_DITERIMA'] ?? 0),
            'lengkap' => $daftar->where('lengkap', true)->count(),
            'status' => collect(config('psn_dashboard.evaluasi_status.status'))->map(fn ($l, $k) => $per[$k] ?? 0)->all(),
            'dipertahankan_nama' => $nama('DIPERTAHANKAN'),
            'dikeluarkan_nama' => $nama('DIKELUARKAN'),
            'baru' => $baru->count(),
            'baru_nama' => $baru->take(5)->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama])->all(),
            'proyeksi' => $daftar->count() - $dikeluarkan + $baru->count(),
            'rekomendasi' => collect(config('psn_dashboard.evaluasi_status.rekomendasi'))->map(fn ($l, $k) => $daftar->where('rekomendasi', $k)->count())->all(),
        ];
    }

    /**
     * Jumlah PSN per status akhir per hari (status pada suatu hari = entri riwayat terakhir PSN dengan tanggal <= hari itu;
     * PSN belum dihitung sebelum entri pertamanya).
     */
    public function harian(Collection $daftar, ?CarbonImmutable $akhir = null): array
    {
        $hari = (int) config('psn_dashboard.evaluasi_status.hari_grafik');
        $akhir ??= CarbonImmutable::today();
        $awal = $akhir->subDays($hari - 1);
        $evaluasiId = $daftar->pluck('evaluasi')->filter()->pluck('id')->all();
        $riwayat = DB::table('evaluasi_psn_riwayat')->whereIn('evaluasi_psn_id', $evaluasiId ?: [0])->where('tanggal', '<=', $akhir->toDateString())
            ->orderBy('tanggal')->orderBy('id')->get(['evaluasi_psn_id', 'tanggal', 'status_akhir'])->groupBy('evaluasi_psn_id');

        $status = array_keys(array_diff_key(config('psn_dashboard.evaluasi_status.status'), ['BELUM_DITERIMA' => 1]));
        $hasil = [];
        for ($t = $awal; $t <= $akhir; $t = $t->addDay()) {
            $tgl = $t->toDateString();
            $n = array_fill_keys($status, 0);
            foreach ($riwayat as $baris) {
                $s = $baris->filter(fn ($r) => $r->tanggal <= $tgl)->last()?->status_akhir;
                $s && isset($n[$s]) && $n[$s]++;
            }
            $hasil[] = ['tanggal' => $tgl, 'status' => $n];
        }

        return $hasil;
    }

    protected function angka(float $v): string
    {
        return number_format($v, $v == (int) $v ? 0 : 1, ',', '.');
    }
}
