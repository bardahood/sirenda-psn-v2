<?php

namespace App\Services;

use App\Enums\LevelRisiko;
use App\Models\Isu;
use App\Models\PeriodeCutoff;
use App\Models\Regulasi;
use App\Models\SnapshotRisiko;
use App\Models\User;
use App\Support\Dashboard\FilterGlobal;
use App\Support\DashboardCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Halaman Risiko, Isu & Regulasi. Heatmap & register membaca snapshot_risiko cut-off
 * (nilai beku); isu & regulasi membaca data terkini untuk PSN yang lolos filter global.
 * Risiko lama yang hanya berlabel level (tanpa skala 1-5) tidak ditempatkan di heatmap
 * (tidak dikarang) tetapi dihitung terpisah dan tetap tampil di register.
 */
class RisikoService
{
    public const TAHAP_REGULASI = ['IDENTIFIKASI' => 'Identifikasi', 'PENYUSUNAN' => 'Penyusunan', 'HARMONISASI' => 'Harmonisasi', 'DITETAPKAN' => 'Ditetapkan'];

    public function __construct(protected DashboardService $dashboard, protected StatusResolver $status) {}

    /** @return int[] psn_id aktif yang lolos filter global pada cut-off */
    public function psnIds(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->dashboard->baris($c, $f)->where('is_aktif', true)->pluck('psn_id')->all();
    }

    public function ringkasan(PeriodeCutoff $c, FilterGlobal $f, ?int $kategori = null): array
    {
        $user = Auth::user();
        $cakupan = $user instanceof User && $user->lihatTerbatas() ? 'unit:'.($user->unit_kerja_id ?? 0) : 'semua';

        return DashboardCache::remember('risiko', $c->kode, $f->denganPeriode($c->kode)->toArray() + ['kategori' => $kategori, 'cakupan' => $cakupan], 'dashboard', function () use ($c, $f, $kategori) {
            $ids = $this->psnIds($c, $f);
            $rows = SnapshotRisiko::query()->where('periode_cutoff_id', $c->id)->whereIn('psn_id', $ids)
                ->when($kategori, fn ($q) => $q->where('kategori_id', $kategori))->get();

            $heatmap = function (string $jenis) use ($rows) {
                $sel = [];
                foreach ($rows as $r) {
                    $k = $r->{"kemungkinan_{$jenis}"};
                    $d = $r->{"dampak_{$jenis}"};
                    if ($k && $d) {
                        $sel["{$k}-{$d}"] = ($sel["{$k}-{$d}"] ?? 0) + 1;
                    }
                }

                return [
                    'sel' => collect($sel)->map(fn ($n, $kd) => ['kemungkinan' => (int) explode('-', $kd)[0], 'dampak' => (int) explode('-', $kd)[1], 'jumlah' => $n,
                        'level' => $this->status->levelDariSkor((int) explode('-', $kd)[0] * (int) explode('-', $kd)[1])->value])->values()->all(),
                    'berskala' => array_sum($sel),
                    'tanpa_skala' => $rows->filter(fn ($r) => ! ($r->{"kemungkinan_{$jenis}"} && $r->{"dampak_{$jenis}"}) && $r->{"level_{$jenis}"})->count(),
                    'per_level' => collect(LevelRisiko::cases())->map(fn ($l) => ['level' => $l->value, 'jumlah' => $rows->where("level_{$jenis}", $l->value)->count()])->all(),
                ];
            };

            $regulasi = Regulasi::query()->whereIn('psn_id', $ids)->groupBy('tahap')->selectRaw('tahap, COUNT(*) AS n')->pluck('n', 'tahap');
            $isu = Isu::query()->whereIn('psn_id', $ids)->where('status', '<>', 'SELESAI');

            return [
                'total_risiko' => $rows->count(),
                'harapan' => $heatmap('harapan'),
                'aktual' => $heatmap('aktual'),
                'regulasi' => collect(self::TAHAP_REGULASI)->map(fn ($l, $k) => ['tahap' => $k, 'label' => $l, 'jumlah' => (int) ($regulasi[$k] ?? 0)])->values()->all(),
                'isu' => ['terbuka' => (clone $isu)->count(), 'lewat_tenggat' => (clone $isu)->whereNotNull('tenggat')->where('tenggat', '<', now()->toDateString())->count(),
                    'tanpa_pic' => (clone $isu)->whereNull('pic_nama')->whereNull('pic_unit_kerja_id')->count()],
                'kategori' => DB::table('ref_kode')->where('tipe', 'RISK')->orderBy('urutan')->get(['id as nilai', 'nama as label']),
            ];
        });
    }

    /**
     * Register risiko, diurutkan dari skor residual tertinggi. Opsi: kategori, jenis (harapan|aktual)
     * + kemungkinan + dampak (klik sel heatmap), level, q.
     */
    public function registerQuery(PeriodeCutoff $c, FilterGlobal $f, array $o): Builder
    {
        $skorLabel = collect(config('psn_dashboard.risiko.skor_dari_label'))->map(fn ($v, $l) => "WHEN '{$l}' THEN {$v}")->implode(' ');
        $jenis = ($o['jenis'] ?? 'harapan') === 'aktual' ? 'aktual' : 'harapan';

        return SnapshotRisiko::query()
            ->join('risiko as r', 'r.id', '=', 'snapshot_risiko.risiko_id')
            ->join('psn as p', 'p.id', '=', 'snapshot_risiko.psn_id')
            ->leftJoin('ref_kode as k', 'k.id', '=', 'snapshot_risiko.kategori_id')
            ->where('snapshot_risiko.periode_cutoff_id', $c->id)
            ->whereIn('snapshot_risiko.psn_id', $this->psnIds($c, $f))
            ->when($o['kategori'] ?? null, fn (Builder $q, $v) => $q->where('snapshot_risiko.kategori_id', $v))
            ->when(($o['kemungkinan'] ?? null) && ($o['dampak'] ?? null), fn (Builder $q) => $q
                ->where("snapshot_risiko.kemungkinan_{$jenis}", $o['kemungkinan'])->where("snapshot_risiko.dampak_{$jenis}", $o['dampak']))
            ->when($o['level'] ?? null, fn (Builder $q, $v) => $q->where("snapshot_risiko.level_{$jenis}", $v))
            ->when($o['q'] ?? null, fn (Builder $q, $v) => $q->where(fn ($w) => $w->where('r.uraian', 'like', "%{$v}%")->orWhere('p.nama', 'like', "%{$v}%")))
            ->select(['snapshot_risiko.*', 'r.uraian', 'r.penanggung_jawab', 'r.rencana_perlakuan', 'p.nama as psn_nama', 'k.nama as kategori_nama'])
            ->selectRaw("COALESCE(snapshot_risiko.kemungkinan_aktual * snapshot_risiko.dampak_aktual, CASE snapshot_risiko.level_aktual {$skorLabel} END,
                snapshot_risiko.kemungkinan_harapan * snapshot_risiko.dampak_harapan, CASE snapshot_risiko.level_harapan {$skorLabel} END) AS skor_residual")
            ->orderByRaw('skor_residual IS NULL')->orderByDesc('skor_residual')->orderBy('p.nama');
    }

    public function register(PeriodeCutoff $c, FilterGlobal $f, array $o, int $perHalaman = 20): LengthAwarePaginator
    {
        $hasil = $this->registerQuery($c, $f, $o)->paginate($perHalaman);
        $hasil->setCollection($hasil->getCollection()->map(fn ($r) => $this->barisRegister($r)));

        return $hasil;
    }

    public function barisRegister(object $r): array
    {
        return [
            'risiko_id' => $r->risiko_id, 'psn_id' => $r->psn_id, 'psn' => $r->psn_nama, 'uraian' => $r->uraian, 'kategori' => $r->kategori_nama,
            'harapan' => ['level' => $r->level_harapan, 'kemungkinan' => $r->kemungkinan_harapan, 'dampak' => $r->dampak_harapan],
            'aktual' => ['level' => $r->level_aktual, 'kemungkinan' => $r->kemungkinan_aktual, 'dampak' => $r->dampak_aktual],
            'skor_residual' => $r->skor_residual !== null ? (int) $r->skor_residual : null,
            'pic' => $r->penanggung_jawab, 'mitigasi' => $r->rencana_perlakuan,
        ];
    }

    /** Isu & debottlenecking terbuka: lewat tenggat di atas, lalu tenggat terdekat. */
    public function isu(PeriodeCutoff $c, FilterGlobal $f, array $o, int $perHalaman = 15): LengthAwarePaginator
    {
        $hariIni = now()->toDateString();
        $hasil = Isu::query()->join('psn as p', 'p.id', '=', 'isu.psn_id')->leftJoin('ref_unit_kerja as u', 'u.id', '=', 'isu.pic_unit_kerja_id')
            ->whereIn('isu.psn_id', $this->psnIds($c, $f))
            ->when(! ($o['termasuk_selesai'] ?? false), fn ($q) => $q->where('isu.status', '<>', 'SELESAI'))
            ->when($o['lewat_tenggat'] ?? false, fn ($q) => $q->whereNotNull('isu.tenggat')->where('isu.tenggat', '<', $hariIni))
            ->select(['isu.*', 'p.nama as psn_nama', 'u.nama as pic_unit'])
            ->orderByRaw('(isu.tenggat IS NOT NULL AND isu.tenggat < ?) DESC', [$hariIni])->orderByRaw('isu.tenggat IS NULL')->orderBy('isu.tenggat')->orderBy('isu.id')
            ->paginate($perHalaman);

        $hasil->setCollection($hasil->getCollection()->map(fn ($i) => [
            'id' => $i->id, 'psn_id' => $i->psn_id, 'psn' => $i->psn_nama, 'uraian' => $i->uraian, 'kebutuhan_dukungan' => $i->kebutuhan_dukungan,
            'pic' => $i->pic_nama ?? $i->pic_unit, 'tenggat' => $i->tenggat?->toDateString(), 'status' => $i->status,
            'lewat_tenggat' => $i->tenggat !== null && $i->tenggat->toDateString() < $hariIni && $i->status !== 'SELESAI',
        ]));

        return $hasil;
    }
}
