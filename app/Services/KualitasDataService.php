<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PeriodeCutoff;
use App\Models\User;
use App\Support\Dashboard\FilterGlobal;
use App\Support\DashboardCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Halaman Kualitas Data: kelengkapan & ketepatan isian per cut-off, dibanding
 * cut-off sebelumnya. "Sektor" sementara = direktorat pengampu (ref_unit_kerja
 * jenis DIREKTORAT) sampai taksonomi sektor ditetapkan (Q-06).
 */
class KualitasDataService
{
    public const LABEL_FIELD = [
        'nama' => 'Nama proyek', 'klaster_id' => 'Klaster', 'status_psn_id' => 'Status PSN', 'deskripsi' => 'Deskripsi',
        'output' => 'Output', 'nilai_investasi_rp' => 'Nilai investasi', 'tahun_selesai' => 'Tahun selesai',
    ];

    public const LABEL_RELASI = [
        'psn_lokasi' => 'Lokasi', 'psn_sumber_dana' => 'Sumber dana', 'psn_unit_pengampu' => 'Unit pengampu', 'kegiatan' => 'KP/RO', 'risiko' => 'Risiko',
    ];

    protected ?Collection $direktorat = null;

    public function __construct(protected DashboardService $dashboard, protected StatusResolver $status) {}

    public function ringkasan(PeriodeCutoff $c, FilterGlobal $f): array
    {
        $user = Auth::user();
        $cakupan = $user instanceof User && $user->lihatTerbatas() ? 'unit:'.($user->unit_kerja_id ?? 0) : 'semua';

        return DashboardCache::remember('kualitas-data', $c->kode, $f->denganPeriode($c->kode)->toArray() + ['cakupan' => $cakupan], 'kualitas_data', function () use ($c, $f) {
            $kini = $this->kpi($c, $f);
            $sebelum = ($p = $c->sebelumnya()) ? $this->kpi($p, $f) : null;
            $kartu = fn (string $k, string $jenis, string $arahBaik) => ['nilai' => $kini[$k], 'sebelumnya' => $sebelum[$k] ?? null,
                'delta' => $this->status->delta($kini[$k], $sebelum[$k] ?? null, $jenis, $arahBaik)];

            return [
                'kpi' => [
                    'kelengkapan' => $kartu('kelengkapan', 'persen', 'naik'),
                    'sektor_tepat_waktu' => $kartu('sektor_tepat_waktu', 'jumlah', 'naik') + ['total_sektor' => $kini['total_sektor']],
                    'belum_diperbarui' => $kartu('belum_diperbarui', 'jumlah', 'turun') + ['total_psn' => $kini['total_psn']],
                    'menunggu_verifikasi' => $kartu('menunggu_verifikasi', 'jumlah', 'turun'),
                ],
                'per_sektor' => $this->perSektor($c, $f),
                'heatmap' => $this->heatmap($c, $f),
                'field_kosong' => $this->fieldKosong($c, $f),
            ];
        });
    }

    protected function kpi(PeriodeCutoff $c, FilterGlobal $f): array
    {
        $rows = $this->dashboard->baris($c, $f)->where('is_aktif', true);
        $ids = $rows->pluck('psn_id');
        $tanggal = CarbonImmutable::parse($c->tanggal_cutoff)->endOfDay();
        $awal = ($p = $c->sebelumnya()) ? CarbonImmutable::parse($p->tanggal_cutoff)->endOfDay()
            : $tanggal->subDays(config('psn_dashboard.status_progres.tanpa_data_hari'));

        $diperbarui = AuditLog::whereIn('psn_id', $ids)->whereBetween('created_at', [$awal, $tanggal])->distinct()->pluck('psn_id');
        $pengisian = DB::table('pengisian_psn')->where('periode_cutoff_id', $c->id)->whereIn('psn_id', $ids)->get()->keyBy('psn_id');

        // Sektor tepat waktu: semua PSN yang diampu sudah diajukan <= tanggal cut-off.
        $sektor = $this->psnPerSektor($rows);
        $tepat = $sektor->filter(fn ($psnIds) => collect($psnIds)->every(fn ($id) => ($x = $pengisian[$id] ?? null)
            && $x->diajukan_at !== null && $x->diajukan_at <= $tanggal->toDateTimeString()))->count();

        return [
            'kelengkapan' => $rows->isEmpty() ? null : round($rows->avg('kelengkapan_persen'), 1),
            'sektor_tepat_waktu' => (float) $tepat,
            'total_sektor' => $sektor->count(),
            'belum_diperbarui' => (float) $ids->diff($diperbarui)->count(),
            'total_psn' => $ids->count(),
            'menunggu_verifikasi' => (float) $pengisian->where('status', 'DIAJUKAN')->count(),
        ];
    }

    /** @return Collection<int, int[]> unit_kerja_id direktorat => psn_id[] */
    protected function psnPerSektor(Collection $rows): Collection
    {
        $dir = $this->direktorat();
        $peta = [];
        foreach ($rows as $r) {
            foreach ((array) $r->unit_kerja_id as $u) {
                if (isset($dir[$u])) {
                    $peta[$u][] = $r->psn_id;
                }
            }
        }

        return collect($peta);
    }

    protected function perSektor(PeriodeCutoff $c, FilterGlobal $f): array
    {
        $rows = $this->dashboard->baris($c, $f)->where('is_aktif', true)->keyBy('psn_id');
        $dir = $this->direktorat();

        return $this->psnPerSektor($rows)->map(fn ($ids, $u) => [
            'id' => $u,
            'label' => $dir[$u],
            'jumlah_psn' => count($ids),
            'kelengkapan_persen' => round(collect($ids)->avg(fn ($id) => (float) $rows[$id]->kelengkapan_persen), 1),
        ])->sortBy('kelengkapan_persen')->values()->all();
    }

    /** Sektor x bagian profil: rata-rata % terisi. */
    protected function heatmap(PeriodeCutoff $c, FilterGlobal $f): array
    {
        $rows = $this->dashboard->baris($c, $f)->where('is_aktif', true);
        $sektor = $this->psnPerSektor($rows);
        $dir = $this->direktorat();
        $bagian = $this->labelBagian();

        $isi = DB::table('snapshot_kelengkapan')->where('periode_cutoff_id', $c->id)->whereIn('psn_id', $rows->pluck('psn_id'))
            ->get(['psn_id', 'bagian', 'field_wajib', 'field_terisi'])->groupBy('psn_id');

        $sel = [];
        foreach ($sektor as $u => $ids) {
            foreach (array_keys($bagian) as $b) {
                $baris = collect($ids)->flatMap(fn ($id) => ($isi[$id] ?? collect())->where('bagian', $b));
                $wajib = $baris->sum('field_wajib');
                $sel[] = ['sektor_id' => $u, 'bagian' => $b, 'persen' => $wajib ? round($baris->sum('field_terisi') / $wajib * 100, 1) : null];
            }
        }

        return [
            'sektor' => $sektor->keys()->map(fn ($u) => ['id' => $u, 'label' => $dir[$u]])->sortBy('label')->values()->all(),
            'bagian' => collect($bagian)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l])->values()->all(),
            'sel' => $sel,
        ];
    }

    /** 50 PSN dengan kelengkapan terendah beserta bagian/field yang kosong. */
    protected function fieldKosong(PeriodeCutoff $c, FilterGlobal $f, int $limit = 50): array
    {
        $rows = $this->dashboard->baris($c, $f)->where('is_aktif', true)->sortBy([['kelengkapan_persen', 'asc'], ['psn_id', 'asc']])->take($limit);
        $ids = $rows->pluck('psn_id');
        $psn = DB::table('psn')->whereIn('id', $ids)->get(['id', 'nama', 'kode_psn', ...array_keys(self::LABEL_FIELD)])->keyBy('id');
        $kosong = DB::table('snapshot_kelengkapan')->where('periode_cutoff_id', $c->id)->whereIn('psn_id', $ids)
            ->whereColumn('field_terisi', '<', 'field_wajib')->get()->groupBy('psn_id');
        $bagian = $this->labelBagian();

        return $rows->map(function ($r) use ($psn, $kosong, $bagian) {
            $p = $psn[$r->psn_id];
            $daftar = ($kosong[$r->psn_id] ?? collect())->map(function ($k) use ($p, $bagian) {
                if ($k->bagian !== 'gambaran_umum') {
                    return $bagian[$k->bagian] ?? $k->bagian;
                }
                // Gambaran umum: rinci field yang kosong (kondisi data terkini).
                $f = collect(config('psn_dashboard.field_wajib.gambaran_umum'))->filter(fn ($x) => $p->{$x} === null || $p->{$x} === '')
                    ->map(fn ($x) => self::LABEL_FIELD[$x] ?? $x)->implode(', ');

                return 'Gambaran Umum'.($f ? " ({$f})" : '');
            })->values()->all();

            return ['id' => $r->psn_id, 'kode' => $p->kode_psn, 'nama' => $p->nama, 'kelengkapan_persen' => (float) $r->kelengkapan_persen, 'kosong' => $daftar];
        })->values()->all();
    }

    public function aktivitas(int $limit = 15): array
    {
        return $this->dashboard->aktivitas($limit);
    }

    /** @return array<string,string> kode bagian => label */
    public function labelBagian(): array
    {
        $tyit = DB::table('ref_kode')->where('tipe', 'TYIT')->pluck('nama', 'kode');
        $fw = config('psn_dashboard.field_wajib');

        return ['gambaran_umum' => 'Gambaran Umum']
            + collect($fw['item_profil'])->mapWithKeys(fn ($k) => ["item:{$k}" => $tyit[$k] ?? $k])->all()
            + collect($fw['relasi'])->mapWithKeys(fn ($t) => ["relasi:{$t}" => self::LABEL_RELASI[$t] ?? $t])->all();
    }

    /** @return Collection<int,string> */
    protected function direktorat(): Collection
    {
        // Memo per instance (bukan static) agar tidak basi antar-permintaan/proses panjang.
        return $this->direktorat ??= DB::table('ref_unit_kerja')->where('jenis', 'DIREKTORAT')->pluck('nama', 'id');
    }
}
