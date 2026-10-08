<?php

namespace App\Services;

use App\Models\PeriodeCutoff;
use App\Models\SnapshotPsn;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Tabel Portofolio PSN. Dibaca dari snapshot cut-off yang sama dengan dashboard
 * sehingga drill-down dari kartu/grafik menghasilkan jumlah PSN yang identik.
 * Pembatasan cakupan akses diterapkan oleh scope model SnapshotPsn.
 */
class PortofolioService
{
    /** Kolom urut yang diizinkan => ekspresi SQL. */
    public const URUTAN = [
        'nama' => 'p.nama',
        'kode' => 'p.kode_psn',
        'klaster' => 'k.nama',
        'investasi' => 'snapshot_psn.nilai_investasi_rp',
        'progres' => 'snapshot_psn.progres_realisasi_persen',
        'deviasi' => 'snapshot_psn.deviasi_pp',
        'status' => 'snapshot_psn.status_progres',
        'kelengkapan' => 'snapshot_psn.kelengkapan_persen',
        'risiko' => 'snapshot_psn.risiko_skor_maks',
    ];

    public function __construct(protected DashboardService $dashboard) {}

    /**
     * @param  array{q?: ?string, kritis?: bool, nonaktif?: bool, tahap?: ?string, urut?: ?string, arah?: ?string}  $opsi
     */
    public function query(PeriodeCutoff $c, FilterGlobal $f, array $opsi = []): Builder
    {
        $q = SnapshotPsn::query()
            ->join('psn as p', 'p.id', '=', 'snapshot_psn.psn_id')
            ->leftJoin('ref_klaster as k', 'k.id', '=', 'snapshot_psn.klaster_id')
            ->leftJoin('ref_status_psn as s', 's.id', '=', 'snapshot_psn.status_psn_id')
            ->where('snapshot_psn.periode_cutoff_id', $c->id)
            ->select([
                'snapshot_psn.*', 'p.nama', 'p.kode_psn', 'p.tahun_selesai', 'p.investasi_anomali',
                'k.nama as klaster_nama', 's.nama as status_psn_nama',
            ]);

        $f->terapkanSql($q, $this->dashboard->skemaPerDanaId());

        if (empty($opsi['nonaktif'])) {
            $q->where('snapshot_psn.is_aktif', true);
        }
        if (! empty($opsi['kritis'])) {
            $q->where('snapshot_psn.is_kritis', true);
        }
        if (! empty($opsi['tahap'])) {
            $opsi['tahap'] === 'TANPA' ? $q->whereNull('snapshot_psn.tahap') : $q->where('snapshot_psn.tahap', $opsi['tahap']);
        }
        if ($cari = trim((string) ($opsi['q'] ?? ''))) {
            $q->where(fn ($w) => $w->where('p.nama', 'like', "%{$cari}%")->orWhere('p.kode_psn', 'like', "%{$cari}%"));
        }

        $kolom = self::URUTAN[$opsi['urut'] ?? 'nama'] ?? 'p.nama';
        $arah = ($opsi['arah'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        // Nilai kosong selalu di akhir, apa pun arah urutannya.
        $q->orderByRaw("{$kolom} IS NULL")->orderBy(DB::raw($kolom), $arah)->orderBy('p.id');

        return $q;
    }

    public function halaman(PeriodeCutoff $c, FilterGlobal $f, array $opsi, int $perHalaman = 25): LengthAwarePaginator
    {
        $hasil = $this->query($c, $f, $opsi)->paginate(min(max($perHalaman, 10), 100));
        $hasil->setCollection($hasil->getCollection()->map(fn ($r) => $this->baris($r)));

        return $hasil;
    }

    public function baris(object $r): array
    {
        static $prov = null;
        $prov ??= DB::table('ref_wilayah')->where('level', '<=', 1)->pluck('nama', 'kode');

        return [
            'id' => $r->psn_id,
            'kode' => $r->kode_psn,
            'nama' => $r->nama,
            'klaster' => $r->klaster_nama,
            'tahap' => $r->tahap,
            'status_psn' => $r->status_psn_nama,
            'kategori' => $r->kategori,
            'provinsi' => collect($r->provinsi_kode ?? [])->map(fn ($k) => $prov[$k] ?? $k)->values()->all(),
            'investasi_triliun' => $r->nilai_investasi_rp !== null ? round($r->nilai_investasi_rp / 1e12, 2) : null,
            'investasi_anomali' => (bool) $r->investasi_anomali,
            'rencana_persen' => $r->progres_rencana_persen !== null ? (float) $r->progres_rencana_persen : null,
            'realisasi_persen' => $r->progres_realisasi_persen !== null ? (float) $r->progres_realisasi_persen : null,
            'deviasi_pp' => $r->deviasi_pp !== null ? (float) $r->deviasi_pp : null,
            'status_progres' => $r->status_progres,
            'is_kritis' => (bool) $r->is_kritis,
            'risiko_level' => $r->risiko_level_maks,
            'kelengkapan_persen' => $r->kelengkapan_persen !== null ? (float) $r->kelengkapan_persen : null,
            'pembaruan_terakhir' => $r->pembaruan_terakhir_at?->toDateString(),
            'tahun_selesai' => $r->tahun_selesai,
        ];
    }
}
