<?php

namespace App\Services;

use App\Enums\StatusProgres;
use App\Models\PeriodeCutoff;
use App\Support\Dashboard\FilterGlobal;
use App\Support\Referensi\Kementerian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard > Pemantauan Pelaksanaan: delapan panel untuk pengambilan keputusan pimpinan
 * (docs/kamus-indikator.md, bagian Pemantauan Pelaksanaan). Angka progres, anggaran, risiko, dan
 * status dibaca dari snapshot cut-off; isu terbuka dibaca dari register isu terkini (isu tidak
 * dibekukan di snapshot). Ambang, bobot, dan pola kategori di config('psn_dashboard.pelaksanaan').
 */
class PemantauanPelaksanaanService
{
    /** Urutan status untuk pergerakan: makin besar makin baik. TANPA_DATA dinilai terpisah. */
    protected const PERINGKAT = ['TERLAMBAT' => 1, 'BERISIKO' => 2, 'ON_TRACK' => 3];

    public function __construct(protected DashboardService $dashboard, protected StatusResolver $status) {}

    public function ringkasan(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->dashboard->cacheIkhtisar('pelaksanaan.v1', $c, $f, function () use ($c, $f) {
            $aktif = $this->dashboard->baris($c, $f)->where('is_aktif', true)->values();
            $nama = DB::table('psn')->whereIn('id', $aktif->pluck('psn_id'))->pluck('nama', 'id');
            $aktif->each(fn ($r) => $r->setAttribute('nama_psn', (string) ($nama[$r->psn_id] ?? '')));
            $sebelum = ($p = $c->sebelumnya()) ? $this->dashboard->baris($p, $f)->where('is_aktif', true)->keyBy('psn_id') : null;
            $isu = $this->isuTerbuka($aktif->pluck('psn_id')->all());
            $pj = $this->penanggungJawab($aktif->pluck('psn_id')->all());

            return [
                'kpi' => $this->kpi($c, $f, $aktif, $sebelum),
                'kurva' => $this->kurva($c, $f),
                'kuadran' => $this->kuadran($aktif, $isu, $pj),
                'perhatian' => $this->perhatian($aktif, $isu, $pj),
                'matriks' => $this->matriks($aktif),
                'kepatuhan' => $this->kepatuhan($aktif),
                'hambatan' => $this->hambatan($isu, $aktif->where('is_kritis', true)->pluck('psn_id')->flip()->all()),
                'pergerakan' => $this->pergerakan($aktif, $sebelum, $p?->kode),
                'batas' => ['kepatuhan' => config('psn_dashboard.pelaksanaan.kepatuhan_top_n')],
            ];
        });
    }

    // ------------------------------------------------------------------ 1. KPI

    protected function kpi(PeriodeCutoff $c, FilterGlobal $f, Collection $aktif, ?Collection $sebelum): array
    {
        $kini = $this->nilaiKpi($aktif);
        $lalu = $sebelum ? $this->nilaiKpi($sebelum->values()) : null;
        $delta = fn (string $k, string $jenis, string $arah) => $this->status->delta($kini[$k], $lalu[$k] ?? null, $jenis, $arah);

        $tren = PeriodeCutoff::where('status', 'TERBIT')->where('tanggal_cutoff', '<=', $c->tanggal_cutoff)
            ->orderByDesc('tanggal_cutoff')->limit(12)->get()->reverse()
            ->map(fn ($p) => ['cutoff' => $p->kode] + $this->nilaiKpi($this->dashboard->baris($p, $f)->where('is_aktif', true)->values()))
            ->values()->all();

        return [
            'nilai' => $kini,
            'sebelumnya' => $lalu,
            'delta' => [
                'aktif' => $delta('aktif', 'jumlah', 'naik'),
                'realisasi' => $delta('realisasi', 'persen', 'naik'),
                'serapan' => $delta('serapan', 'persen', 'naik'),
                'kritis' => $delta('kritis', 'jumlah', 'turun'),
                'cakupan' => $delta('cakupan', 'persen', 'naik'),
            ],
            'tren' => $tren,
        ];
    }

    /** Nilai kartu: PSN aktif, progres fisik vs rencana, serapan anggaran, PSN kritis, cakupan pelaporan. */
    protected function nilaiKpi(Collection $aktif): array
    {
        $berdata = $aktif->filter(fn ($r) => $r->progres_rencana_persen !== null && $r->progres_realisasi_persen !== null);
        [$rencana] = $this->rata($berdata, 'progres_rencana_persen');
        [$realisasi] = $this->rata($berdata, 'progres_realisasi_persen');
        $pagu = $aktif->sum(fn ($r) => (float) $r->pagu_rp);
        $real = $aktif->sum(fn ($r) => (float) $r->realisasi_anggaran_rp);
        $melapor = $aktif->where('status_progres', '<>', StatusProgres::TanpaData->value)->count();

        return [
            'aktif' => (float) $aktif->count(),
            'rencana' => $rencana,
            'realisasi' => $realisasi,
            'deviasi' => $rencana !== null && $realisasi !== null ? round($realisasi - $rencana, 1) : null,
            'status' => $rencana !== null && $realisasi !== null ? $this->status->statusDariDeviasi($realisasi - $rencana)->value : StatusProgres::TanpaData->value,
            'berdata' => $berdata->count(),
            'serapan' => $pagu > 0 ? round($real / $pagu * 100, 1) : null,
            'pagu_triliun' => round($pagu / 1e12, 2),
            'realisasi_triliun' => round($real / 1e12, 2),
            'kritis' => (float) $aktif->where('is_kritis', true)->count(),
            'melapor' => $melapor,
            'cakupan' => $aktif->isEmpty() ? null : round($melapor / $aktif->count() * 100, 1),
        ];
    }

    /** Rata-rata progres sesuai k3_metode (sama dengan K3). @return array{0: ?float, 1: int} */
    protected function rata(Collection $rows, string $kolom): array
    {
        if (config('psn_dashboard.k3_metode') === 'tertimbang') {
            $ada = $rows->filter(fn ($r) => (float) $r->nilai_investasi_rp > 0);
            $bobot = $ada->sum(fn ($r) => (float) $r->nilai_investasi_rp);

            return $bobot > 0 ? [round($ada->sum(fn ($r) => $r->{$kolom} * (float) $r->nilai_investasi_rp) / $bobot, 1), $ada->count()] : [null, 0];
        }

        return $rows->isEmpty() ? [null, 0] : [round($rows->avg($kolom), 1), $rows->count()];
    }

    // ------------------------------------------------------------------ 2. Kurva S + proyeksi

    /**
     * Rencana vs realisasi kumulatif per cut-off tahun berjalan (sama dengan P5), ditambah proyeksi
     * realisasi Desember: nilai terakhir + laju rata-rata per bulan x sisa bulan (maks. 100).
     */
    protected function kurva(PeriodeCutoff $c, FilterGlobal $f): array
    {
        $tren = $this->dashboard->tren($c, $f);
        $titik = collect($tren['bulan'])->filter(fn ($b) => $b['realisasi_persen'] !== null && $b['rencana_persen'] !== null)->values();

        $proyeksi = function (string $k) use ($titik) {
            if ($titik->isEmpty()) {
                return null;
            }
            $akhir = $titik->last();
            $laju = $titik->count() >= 2
                ? ($akhir[$k] - $titik->first()[$k]) / max(1, $akhir['bulan'] - $titik->first()['bulan'])
                : $akhir[$k] / max(1, $akhir['bulan']);

            return ['bulan_awal' => $akhir['bulan'], 'nilai_awal' => $akhir[$k], 'desember' => round(min(100, max(0, $akhir[$k] + $laju * (12 - $akhir['bulan']))), 1)];
        };
        $akhir = $titik->last();

        return $tren + [
            'proyeksi' => $proyeksi('realisasi_persen'),
            'rencana_terakhir' => $akhir['rencana_persen'] ?? null,
            'titik' => $titik->count(),
        ];
    }

    // ------------------------------------------------------------------ 3. Kuadran prioritas

    /** Titik PSN berdata progres: deviasi (x), investasi (y), isu terbuka (ukuran), status (warna). */
    protected function kuadran(Collection $aktif, Collection $isu, array $pj): array
    {
        $berdata = $aktif->filter(fn ($r) => $r->deviasi_pp !== null && (float) $r->nilai_investasi_rp > 0);
        $inv = $berdata->map(fn ($r) => (float) $r->nilai_investasi_rp / 1e12)->sort()->values();
        $median = $inv->isEmpty() ? null : round($inv->median(), 2);

        return [
            'titik' => $berdata->sort(fn ($a, $b) => [(float) $b->nilai_investasi_rp, $a->nama_psn] <=> [(float) $a->nilai_investasi_rp, $b->nama_psn])
                ->map(fn ($r) => [
                    'psn_id' => $r->psn_id, 'nama' => $r->nama_psn, 'pj' => $pj[$r->psn_id] ?? null,
                    'deviasi_pp' => (float) $r->deviasi_pp, 'investasi_triliun' => round((float) $r->nilai_investasi_rp / 1e12, 3),
                    'isu' => $isu->where('psn_id', $r->psn_id)->count(), 'status' => $r->status_progres,
                ])->values()->all(),
            'garis_x' => (float) config('psn_dashboard.status_progres.on_track_min_deviasi'),
            'garis_y' => $median,
            'tanpa_data' => $aktif->count() - $berdata->count(),
            'besar_terlambat' => $median === null ? 0 : $berdata->filter(fn ($r) => (float) $r->deviasi_pp <= (float) config('psn_dashboard.status_progres.on_track_min_deviasi')
                && (float) $r->nilai_investasi_rp / 1e12 >= $median)->count(),
        ];
    }

    // ------------------------------------------------------------------ 4. Daftar perhatian pimpinan

    protected function perhatian(Collection $aktif, Collection $isu, array $pj): array
    {
        $cfg = config('psn_dashboard.pelaksanaan.prioritas');
        $b = $cfg['bobot'];
        $investasi = $aktif->map(fn ($r) => (float) $r->nilai_investasi_rp)->sort()->values();
        $n = max(1, $investasi->count());
        $persentil = fn (float $v) => $investasi->filter(fn ($x) => $x <= $v)->count() / $n * 100;
        $isuPer = $isu->groupBy('psn_id');

        $daftar = $aktif->map(function ($r) use ($b, $cfg, $persentil, $isuPer, $pj) {
            $alasan = [];
            $skor = 0.0;
            $tambah = function (string $kode, string $label, float $nilai) use (&$alasan, &$skor) {
                $alasan[] = ['kode' => $kode, 'label' => $label];
                $skor += $nilai;
            };
            match ($r->status_progres) {
                'TERLAMBAT' => $tambah('TERLAMBAT', 'Terlambat', $b['TERLAMBAT']),
                'BERISIKO' => $tambah('BERISIKO', 'Berisiko', $b['BERISIKO']),
                'TANPA_DATA' => $tambah('TANPA_DATA', 'Tanpa laporan', $b['TANPA_DATA']),
                default => null,
            };
            match ($r->risiko_level_maks) {
                'Sangat Tinggi' => $tambah('RISIKO', 'Risiko Sangat Tinggi', $b['risiko_sangat_tinggi']),
                'Tinggi' => $tambah('RISIKO', 'Risiko Tinggi', $b['risiko_tinggi']),
                default => null,
            };
            $isuPsn = $isuPer[$r->psn_id] ?? collect();
            if (($kritis = $isuPsn->where('jalur_kritis', true)->count()) > 0) {
                $tambah('ISU_KRITIS', "{$kritis} isu jalur kritis", $b['isu_jalur_kritis']);
            }
            if ($isuPsn->count() >= $cfg['isu_banyak_min']) {
                $tambah('ISU_BANYAK', $isuPsn->count().' isu terbuka', $b['isu_banyak']);
            }
            $pInv = (float) $r->nilai_investasi_rp > 0 ? $persentil((float) $r->nilai_investasi_rp) : 0;
            if ($pInv >= $cfg['investasi_besar_persentil']) {
                $alasan[] = ['kode' => 'INVESTASI', 'label' => 'Investasi besar'];
            }
            $skor += $b['investasi'] * $pInv / 100;

            return [
                'psn_id' => $r->psn_id, 'nama' => $r->nama_psn, 'pj' => $pj[$r->psn_id] ?? null, 'skor' => round($skor, 1),
                'status' => $r->status_progres, 'investasi_triliun' => round((float) $r->nilai_investasi_rp / 1e12, 2),
                'rencana_persen' => $r->progres_rencana_persen !== null ? (float) $r->progres_rencana_persen : null,
                'realisasi_persen' => $r->progres_realisasi_persen !== null ? (float) $r->progres_realisasi_persen : null,
                'isu' => $isuPsn->count(), 'alasan' => $alasan,
            ];
        })->filter(fn ($x) => collect($x['alasan'])->whereNotIn('kode', ['INVESTASI'])->isNotEmpty());

        return $daftar->sort(fn ($a, $b) => [$b['skor'], $a['nama']] <=> [$a['skor'], $b['nama']])
            ->take($cfg['jumlah'])->values()->all();
    }

    // ------------------------------------------------------------------ 5. Matriks klaster x status

    protected function matriks(Collection $aktif): array
    {
        $nama = DB::table('ref_klaster')->pluck('nama', 'id');
        $status = array_map(fn ($s) => $s->value, StatusProgres::cases());
        $baris = DashboardService::urutDeterministik($aktif->groupBy(fn ($r) => $r->klaster_id ?? 0)->map(fn ($g, $id) => [
            'id' => $id ?: null, 'label' => $nama[$id] ?? 'Tanpa klaster', 'jumlah' => $g->count(),
            'sel' => collect($status)->mapWithKeys(fn ($s) => [$s => $g->where('status_progres', $s)->count()])->all(),
        ]));

        $top = config('psn_dashboard.pelaksanaan.matriks_top_n');
        $hasil = $baris->take($top)->values();
        if ($baris->count() > $top) {
            $sisa = $baris->slice($top);
            $hasil->push(['id' => null, 'label' => 'Lainnya ('.$sisa->count().' klaster)', 'jumlah' => $sisa->sum('jumlah'),
                'sel' => collect($status)->mapWithKeys(fn ($s) => [$s => $sisa->sum(fn ($x) => $x['sel'][$s])])->all()]);
        }

        return ['status' => $status, 'baris' => $hasil->all(),
            'total' => collect($status)->mapWithKeys(fn ($s) => [$s => $aktif->where('status_progres', $s)->count()])->all()];
    }

    // ------------------------------------------------------------------ 6. Kepatuhan pelaporan per K/L

    protected function kepatuhan(Collection $aktif): array
    {
        $hasil = [];
        foreach (Kementerian::daftar() as $slug => $kl) {
            $psn = $aktif->filter(fn ($r) => array_intersect($kl['unit'], array_map('intval', (array) $r->unit_kerja_id)));
            if ($psn->isEmpty()) {
                continue;
            }
            $melapor = $psn->where('status_progres', '<>', StatusProgres::TanpaData->value)->count();
            $hasil[] = ['slug' => $slug, 'label' => $kl['label'], 'jumlah' => $psn->count(), 'melapor' => $melapor,
                'persen' => round($melapor / $psn->count() * 100, 1)];
        }
        // Terendah dulu; seri: PSN terbanyak dulu, lalu nama.
        usort($hasil, fn ($a, $b) => [$a['persen'], $b['jumlah'], $a['label']] <=> [$b['persen'], $a['jumlah'], $b['label']]);
        $melapor = $aktif->where('status_progres', '<>', StatusProgres::TanpaData->value)->count();

        return ['kl' => $hasil, 'jumlah_kl' => count($hasil), 'nol' => count(array_filter($hasil, fn ($x) => $x['melapor'] === 0)),
            'total' => $aktif->count(), 'melapor' => $melapor];
    }

    // ------------------------------------------------------------------ 7. Hambatan

    /** Isu terbuka per kategori, dipisah: pada PSN kritis (K4) dan PSN lain. */
    protected function hambatan(Collection $isu, array $kritis): array
    {
        $kat = config('psn_dashboard.pelaksanaan.kategori_isu');
        $lain = config('psn_dashboard.pelaksanaan.kategori_isu_lainnya');
        $hasil = collect($kat)->map(fn ($k, $kode) => ['kode' => $kode, 'label' => $k['label'], 'psn_kritis' => 0, 'psn_lain' => 0, 'psn' => []])
            ->put('LAINNYA', ['kode' => 'LAINNYA', 'label' => $lain, 'psn_kritis' => 0, 'psn_lain' => 0, 'psn' => []]);

        foreach ($isu as $i) {
            $k = $i->kategori;
            $h = $hasil[$k];
            $h[isset($kritis[$i->psn_id]) ? 'psn_kritis' : 'psn_lain']++;
            $h['psn'][$i->psn_id] = true;
            $hasil[$k] = $h;
        }

        $baris = $hasil->map(fn ($h) => ['jumlah' => $h['psn_kritis'] + $h['psn_lain'], 'psn' => count($h['psn'])] + $h)->values();
        // Urut jumlah menurun, "Lainnya" selalu terakhir.
        $baris = $baris->sort(fn ($a, $b) => [$a['kode'] === 'LAINNYA', -$a['jumlah'], $a['label']] <=> [$b['kode'] === 'LAINNYA', -$b['jumlah'], $b['label']])->values();

        return ['kategori' => $baris->all(), 'total' => $isu->count(), 'pada_psn_kritis' => $isu->filter(fn ($i) => isset($kritis[$i->psn_id]))->count(),
            'jalur_kritis' => $isu->where('jalur_kritis', true)->count(),
            'psn' => $isu->pluck('psn_id')->unique()->count()];
    }

    /**
     * Isu belum selesai (status selain SELESAI) milik PSN terpilih, dari register isu terkini,
     * dengan penanda jalur kritis (KP/RO critical path) dan kategori hambatan.
     */
    protected function isuTerbuka(array $psnIds): Collection
    {
        $kat = config('psn_dashboard.pelaksanaan.kategori_isu');

        return DB::table('isu as i')->leftJoin('kegiatan as k', 'k.id', '=', 'i.kegiatan_id')
            ->whereIn('i.psn_id', $psnIds)->whereNull('i.deleted_at')->where('i.status', '<>', 'SELESAI')
            ->orderBy('i.id')->get(['i.id', 'i.psn_id', 'i.uraian', 'i.kebutuhan_dukungan', 'k.is_critical_path'])
            ->map(function ($i) use ($kat) {
                $teks = $i->uraian.' '.$i->kebutuhan_dukungan;
                $kode = 'LAINNYA';
                foreach ($kat as $k => $c) {
                    if (preg_match($c['pola'], $teks)) {
                        $kode = $k;
                        break;
                    }
                }

                return (object) ['psn_id' => (int) $i->psn_id, 'jalur_kritis' => (bool) $i->is_critical_path, 'kategori' => $kode];
            });
    }

    // ------------------------------------------------------------------ 8. Pergerakan status

    protected function pergerakan(Collection $aktif, ?Collection $sebelum, ?string $kodeSebelum): array
    {
        $kelompok = ['MEMBAIK' => 'Membaik', 'MEMBURUK' => 'Memburuk', 'TETAP' => 'Tetap', 'MULAI' => 'Mulai melapor', 'BERHENTI' => 'Berhenti melapor', 'BARU' => 'Baru tercatat'];
        $hitung = array_fill_keys(array_keys($kelompok), 0);
        $sorot = [];
        if ($sebelum === null) {
            return ['sebelumnya' => null, 'kelompok' => [], 'sorot' => []];
        }

        foreach ($aktif as $r) {
            $lama = $sebelum[$r->psn_id]->status_progres ?? null;
            $baru = $r->status_progres;
            $k = match (true) {
                $lama === null => 'BARU',
                $lama === 'TANPA_DATA' && $baru === 'TANPA_DATA' => 'TETAP',
                $lama === 'TANPA_DATA' => 'MULAI',
                $baru === 'TANPA_DATA' => 'BERHENTI',
                self::PERINGKAT[$baru] > self::PERINGKAT[$lama] => 'MEMBAIK',
                self::PERINGKAT[$baru] < self::PERINGKAT[$lama] => 'MEMBURUK',
                default => 'TETAP',
            };
            $hitung[$k]++;
            if (in_array($k, ['MEMBURUK', 'BERHENTI'], true)) {
                $sorot[] = ['psn_id' => $r->psn_id, 'nama' => $r->nama_psn, 'kelompok' => $k, 'dari' => $lama, 'ke' => $baru,
                    'investasi_triliun' => round((float) $r->nilai_investasi_rp / 1e12, 2)];
            }
        }
        usort($sorot, fn ($a, $b) => [$a['kelompok'] !== 'MEMBURUK', -$a['investasi_triliun'], $a['nama']] <=> [$b['kelompok'] !== 'MEMBURUK', -$b['investasi_triliun'], $b['nama']]);

        return [
            'sebelumnya' => $kodeSebelum,
            'kelompok' => collect($kelompok)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l, 'jumlah' => $hitung[$k]])->values()->all(),
            'sorot' => array_slice($sorot, 0, config('psn_dashboard.pelaksanaan.pergerakan_top_n')),
            'jumlah_sorot' => count($sorot),
        ];
    }

    // ------------------------------------------------------------------ bantu

    /** @return array<int,string> psn_id => penanggung jawab pertama (urutan) */
    protected function penanggungJawab(array $psnIds): array
    {
        return DB::table('psn_kelembagaan as k')->join('ref_penanggung_jawab as p', 'p.id', '=', 'k.penanggung_jawab_id')
            ->whereIn('k.psn_id', $psnIds)->where('k.peran', 'PENANGGUNG_JAWAB')->orderBy('k.urutan')->orderBy('k.id')
            ->get(['k.psn_id', 'p.nama'])->groupBy('psn_id')->map(fn ($g) => $g->first()->nama)->all();
    }
}
