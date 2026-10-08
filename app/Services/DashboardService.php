<?php

namespace App\Services;

use App\Enums\StatusProgres;
use App\Models\AuditLog;
use App\Models\PeriodeCutoff;
use App\Models\SnapshotKegiatan;
use App\Models\SnapshotPsn;
use App\Models\User;
use App\Support\Dashboard\FilterGlobal;
use App\Support\DashboardCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Angka Ringkasan Eksekutif (K1-K4, P1-P8, komponen lain) dari snapshot cut-off terbit.
 * Baris snapshot dibaca lewat model ber-scope cakupan akses, lalu difilter dan
 * diagregasi di PHP (<= ±400 baris per cut-off) agar perilaku kolom JSON multi-nilai
 * seragam di MySQL & MariaDB. Hasil di-cache per (panel, cut-off, filter, cakupan).
 */
class DashboardService
{
    public const URUTAN_TAHAP = ['PERENCANAAN' => 'Perencanaan', 'TRANSAKSI' => 'Transaksi', 'KONSTRUKSI' => 'Konstruksi', 'OPERASI' => 'Operasi/Selesai'];

    public const LABEL_SKEMA = ['APBN' => 'APBN', 'APBD' => 'APBD', 'KPBU' => 'KPBU', 'LAINNYA' => 'Lainnya'];

    public function __construct(protected StatusResolver $status) {}

    /** Cut-off yang diminta (harus terbit) atau terbit terbaru; null bila belum ada. */
    public function cutoff(FilterGlobal $f): ?PeriodeCutoff
    {
        if ($f->periode === null) {
            return PeriodeCutoff::terbaru();
        }

        return PeriodeCutoff::where('kode', $f->periode)->where('status', 'TERBIT')->first()
            ?? throw new NotFoundHttpException("Cut-off {$f->periode} belum terbit.");
    }

    // ------------------------------------------------------------------ K1-K4

    public function kpi(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('kpi', $c, $f, function () use ($c, $f) {
            $kini = $this->ringkasKpi($this->baris($c, $f));
            $sebelum = ($p = $c->sebelumnya()) ? $this->ringkasKpi($this->baris($p, $f)) : null;

            $kartu = fn (string $kode, string $jenis, string $arahBaik) => [
                'kode' => $kode,
                'nilai' => $kini[$kode],
                'sebelumnya' => $sebelum[$kode] ?? null,
                'delta' => $this->status->delta($kini[$kode], $sebelum[$kode] ?? null, $jenis, $arahBaik),
            ];

            return [
                $kartu('K1', 'jumlah', 'naik'),
                $kartu('K2', 'jumlah', 'naik') + ['satuan' => 'Rp triliun'],
                $kartu('K3', 'persen', 'naik') + ['satuan' => '%', 'cakupan_psn' => $kini['K3_n'], 'metode' => config('psn_dashboard.k3_metode')],
                $kartu('K4', 'jumlah', 'turun'),
            ];
        });
    }

    /** Nilai K1-K4 per cut-off terbit (maks. 12 terakhir s.d. cut-off ini) untuk sparkline kartu KPI. */
    public function kpiTren(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('kpi-tren', $c, $f, function () use ($c, $f) {
            return PeriodeCutoff::where('status', 'TERBIT')->where('tanggal_cutoff', '<=', $c->tanggal_cutoff)
                ->orderByDesc('tanggal_cutoff')->limit(12)->get()->reverse()
                ->map(fn ($p) => ['cutoff' => $p->kode] + collect($this->ringkasKpi($this->baris($p, $f)))->only(['K1', 'K2', 'K3', 'K4'])->all())
                ->values()->all();
        });
    }

    protected function ringkasKpi(Collection $rows): array
    {
        $aktif = $rows->where('is_aktif', true);
        [$k3, $n] = $this->rataProgres($aktif, 'progres_realisasi_persen');

        return [
            'K1' => (float) $aktif->count(),
            'K2' => round($aktif->sum(fn ($r) => (float) $r->nilai_investasi_rp) / 1e12, 1),
            'K3' => $k3,
            'K3_n' => $n,
            'K4' => (float) $aktif->where('is_kritis', true)->count(),
        ];
    }

    /**
     * Rata-rata progres sesuai config k3_metode: tertimbang investasi (PSN tanpa
     * investasi valid tidak ikut) atau sederhana.
     *
     * @return array{0: ?float, 1: int} [nilai, jumlah PSN yang dihitung]
     */
    protected function rataProgres(Collection $rows, string $kolom): array
    {
        $ada = $rows->filter(fn ($r) => $r->{$kolom} !== null);

        if (config('psn_dashboard.k3_metode') === 'tertimbang') {
            $ada = $ada->filter(fn ($r) => (float) $r->nilai_investasi_rp > 0);
            $bobot = $ada->sum(fn ($r) => (float) $r->nilai_investasi_rp);

            return $bobot > 0
                ? [round($ada->sum(fn ($r) => $r->{$kolom} * (float) $r->nilai_investasi_rp) / $bobot, 1), $ada->count()]
                : [null, 0];
        }

        return $ada->isEmpty() ? [null, 0] : [round($ada->avg($kolom), 1), $ada->count()];
    }

    // ------------------------------------------------------------------ P1, P6, P7, direktorat

    public function distribusi(PeriodeCutoff $c, FilterGlobal $f, string $dim): array
    {
        return $this->cache("distribusi.{$dim}", $c, $f, function () use ($c, $f, $dim) {
            $aktif = $this->baris($c, $f)->where('is_aktif', true);

            return match ($dim) {
                'klaster' => $this->perKlaster($aktif),
                'direktorat' => $this->perDirektorat($aktif),
                'provinsi' => $this->perProvinsi($aktif),
                'dana' => $this->perDana($aktif),
                'pulau' => $this->perPulau($aktif),
                'komposisi_dana' => $this->komposisiDana($aktif),
            };
        });
    }

    protected function perKlaster(Collection $rows): array
    {
        $nama = DB::table('ref_klaster')->pluck('nama', 'id');
        $semua = self::urutDeterministik($rows->countBy(fn ($r) => $r->klaster_id ?? 0)
            ->map(fn ($n, $id) => ['id' => $id ?: null, 'label' => $nama[$id] ?? 'Tanpa klaster', 'jumlah' => $n]));
        $topN = config('psn_dashboard.p1_top_n');

        $hasil = $semua->take($topN);
        if ($semua->count() > $topN) {
            $hasil->push(['id' => null, 'label' => 'Lainnya', 'jumlah' => $semua->slice($topN)->sum('jumlah'), 'gabungan' => $semua->count() - $topN]);
        }

        return $hasil->values()->all();
    }

    protected function perDirektorat(Collection $rows): array
    {
        $dir = DB::table('ref_unit_kerja')->where('jenis', 'DIREKTORAT')->pluck('nama', 'id');

        return self::urutDeterministik($rows->flatMap(fn ($r) => array_intersect((array) $r->unit_kerja_id, $dir->keys()->all()))
            ->countBy()->map(fn ($n, $id) => ['id' => $id, 'label' => $dir[$id], 'jumlah' => $n]))->all();
    }

    protected function perProvinsi(Collection $rows): array
    {
        $prov = DB::table('ref_wilayah')->where('level', '<=', 1)->get()->keyBy('kode');

        return self::urutDeterministik($rows->flatMap(fn ($r) => (array) $r->provinsi_kode)->countBy()
            ->map(fn ($n, $kode) => ['kode' => (string) $kode, 'label' => $prov[$kode]->nama ?? $kode, 'hc_key' => $prov[$kode]->hc_key ?? null, 'jumlah' => $n]))
            ->all();
    }

    /** PSN per kelompok pulau (PSN multi-provinsi dalam satu pulau dihitung sekali), urutan geografis. */
    protected function perPulau(Collection $rows): array
    {
        $prov = DB::table('ref_wilayah')->where('level', 1)->get(['kode', 'lat', 'lng'])->keyBy('kode');

        return collect(config('psn_dashboard.pulau'))->map(function ($kode, $nama) use ($rows, $prov) {
            $p = $prov->only($kode);

            return [
                'kode' => $nama, 'label' => $nama,
                'jumlah' => $rows->filter(fn ($r) => array_intersect((array) $r->provinsi_kode, $kode))->count(),
                'lat' => $p->isEmpty() ? null : round($p->avg('lat'), 3), 'lng' => $p->isEmpty() ? null : round($p->avg('lng'), 3),
            ];
        })->values()->all();
    }

    /**
     * Komposisi investasi per kombinasi skema yang saling lepas (setiap PSN tepat di satu kelompok),
     * sehingga bagian-bagiannya berjumlah = total investasi (K2). Urutan kelompok tetap.
     */
    protected function komposisiDana(Collection $rows): array
    {
        $skema = $this->skemaPerDanaId();
        $kelompok = collect(['APBN' => 'APBN', 'KPBU' => 'KPBU', 'CAMPURAN' => 'Campuran (≥ 2 skema)', 'LAINNYA' => 'Lainnya', 'APBD' => 'APBD', 'TANPA' => 'Belum ada data dana'])
            ->map(fn ($l, $k) => ['kode' => strtolower($k), 'label' => $l, 'investasi_triliun' => 0.0, 'jumlah' => 0]);

        foreach ($rows as $r) {
            $s = array_values(array_unique(array_map(fn ($id) => $skema[$id] ?? 'LAINNYA', (array) $r->sumber_dana_id)));
            $k = match (count($s)) {
                0 => 'TANPA', 1 => $s[0], default => 'CAMPURAN'
            };
            $k = $kelompok->has($k) ? $k : 'LAINNYA';
            $kelompok[$k] = ['investasi_triliun' => $kelompok[$k]['investasi_triliun'] + (float) $r->nilai_investasi_rp / 1e12, 'jumlah' => $kelompok[$k]['jumlah'] + 1] + $kelompok[$k];
        }
        $total = $kelompok->sum('investasi_triliun');

        return $kelompok->map(fn ($h) => ['investasi_triliun' => round($h['investasi_triliun'], 1),
            'persen' => $total > 0 ? round($h['investasi_triliun'] / $total * 100, 1) : null] + $h)->values()->all();
    }

    /**
     * Urutan jumlah menurun dengan pemecah seri nama menaik, agar hasil (termasuk
     * batas "8 teratas + Lainnya") selalu sama untuk filter yang sama.
     */
    public static function urutDeterministik(Collection $baris): Collection
    {
        return $baris->sort(fn ($a, $b) => [$b['jumlah'], $a['label']] <=> [$a['jumlah'], $b['label']])->values();
    }

    protected function perDana(Collection $rows): array
    {
        $skema = $this->skemaPerDanaId();
        $hasil = collect(self::LABEL_SKEMA)->map(fn ($label, $kode) => ['kode' => strtolower($kode), 'label' => $label, 'investasi_triliun' => 0.0, 'jumlah' => 0]);

        foreach ($rows as $r) {
            foreach (array_unique(array_map(fn ($id) => $skema[$id] ?? 'LAINNYA', (array) $r->sumber_dana_id)) as $s) {
                $hasil[$s] = ['investasi_triliun' => $hasil[$s]['investasi_triliun'] + (float) $r->nilai_investasi_rp / 1e12, 'jumlah' => $hasil[$s]['jumlah'] + 1] + $hasil[$s];
            }
        }

        return $hasil->map(fn ($h) => ['investasi_triliun' => round($h['investasi_triliun'], 1)] + $h)->values()->all();
    }

    // ------------------------------------------------------------------ P2-P4

    public function progres(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('progres.v2', $c, $f, function () use ($c, $f) {
            $aktif = $this->baris($c, $f)->where('is_aktif', true);
            $denganData = $aktif->filter(fn ($r) => $r->progres_rencana_persen !== null && $r->progres_realisasi_persen !== null);
            [$rencana] = $this->rataProgres($denganData, 'progres_rencana_persen');
            [$realisasi, $n] = $this->rataProgres($denganData, 'progres_realisasi_persen');

            $pagu = $aktif->sum(fn ($r) => (float) $r->pagu_rp);
            $realAnggaran = $aktif->sum(fn ($r) => (float) $r->realisasi_anggaran_rp);
            $ro = $aktif->sum('jumlah_ro');

            return [
                'P2' => [
                    'rencana_persen' => $rencana,
                    'realisasi_persen' => $realisasi,
                    'deviasi_pp' => $rencana !== null && $realisasi !== null ? round($realisasi - $rencana, 1) : null,
                    'status' => $rencana !== null && $realisasi !== null ? $this->status->statusDariDeviasi($realisasi - $rencana)->value : StatusProgres::TanpaData->value,
                    'cakupan_psn' => $n,
                    'total_psn' => $aktif->count(),
                ],
                'P3' => [
                    'pagu_triliun' => round($pagu / 1e12, 3),
                    'realisasi_triliun' => round($realAnggaran / 1e12, 3),
                    'persen' => $pagu > 0 ? round($realAnggaran / $pagu * 100, 1) : null,
                    'tahun' => $c->tanggal_cutoff->year,
                ],
                'P4' => [
                    'tercapai' => (int) $aktif->sum('jumlah_ro_tercapai'),
                    'total' => (int) $ro,
                    'persen' => $ro > 0 ? round($aktif->sum('jumlah_ro_tercapai') / $ro * 100, 1) : null,
                ],
                'DP' => $this->progresDp($aktif),
                'status_progres' => collect(StatusProgres::cases())->map(fn ($s) => [
                    'kode' => $s->value, 'label' => $s->label(), 'jumlah' => $aktif->where('status_progres', $s->value)->count(),
                ])->all(),
            ];
        });
    }

    /** PSN klaster Direktif Presiden: total dan yang On Track pada cut-off. */
    protected function progresDp(Collection $aktif): array
    {
        $dp = $aktif->where('klaster_id', $this->idKlasterDp());

        return ['total' => $dp->count(), 'on_track' => $dp->where('status_progres', StatusProgres::OnTrack->value)->count(),
            'berdata' => $dp->where('status_progres', '<>', StatusProgres::TanpaData->value)->count()];
    }

    protected function idKlasterDp(): ?int
    {
        return DB::table('ref_klaster')->where('kode', 'DP')->value('id');
    }

    // ------------------------------------------------------------------ P5

    public function tren(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('tren', $c, $f, function () use ($c, $f) {
            $tahun = $c->tanggal_cutoff->year;
            $cutoffs = PeriodeCutoff::where('status', 'TERBIT')->whereYear('tanggal_cutoff', $tahun)
                ->where('tanggal_cutoff', '<=', $c->tanggal_cutoff)->orderBy('tanggal_cutoff')->get();

            $bulan = array_fill(1, 12, ['rencana_persen' => null, 'realisasi_persen' => null, 'cutoff' => null]);
            foreach ($cutoffs as $p) {
                $rows = $this->baris($p, $f)->where('is_aktif', true)
                    ->filter(fn ($r) => $r->progres_rencana_persen !== null && $r->progres_realisasi_persen !== null);
                [$rencana] = $this->rataProgres($rows, 'progres_rencana_persen');
                [$realisasi] = $this->rataProgres($rows, 'progres_realisasi_persen');
                $bulan[$p->tanggal_cutoff->month] = ['rencana_persen' => $rencana, 'realisasi_persen' => $realisasi, 'cutoff' => $p->kode];
            }

            return ['tahun' => $tahun, 'bulan' => collect($bulan)->map(fn ($v, $m) => ['bulan' => $m] + $v)->values()->all()];
        });
    }

    // ------------------------------------------------------------------ komponen lain

    public function roKritis(PeriodeCutoff $c, FilterGlobal $f, int $limit = 10): array
    {
        return $this->cache("ro-kritis.v2.{$limit}", $c, $f, function () use ($c, $f, $limit) {
            $psnIds = $this->baris($c, $f)->where('is_aktif', true)->pluck('psn_id');

            $baris = SnapshotKegiatan::query()
                ->join('kegiatan as k', 'k.id', '=', 'snapshot_kegiatan.kegiatan_id')
                ->join('psn as p', 'p.id', '=', 'snapshot_kegiatan.psn_id')
                ->where('snapshot_kegiatan.periode_cutoff_id', $c->id)
                ->where('snapshot_kegiatan.is_critical_path', true)
                ->whereIn('snapshot_kegiatan.status_progres', [StatusProgres::Berisiko->value, StatusProgres::Terlambat->value])
                ->whereIn('snapshot_kegiatan.psn_id', $psnIds)
                ->orderBy('snapshot_kegiatan.deviasi_pp')->limit($limit)
                ->get(['snapshot_kegiatan.*', 'k.nama as kegiatan_nama', 'p.nama as psn_nama']);
            $dir = $this->direktoratPsn($baris->pluck('psn_id')->unique()->all());

            return $baris->map(fn ($r) => [
                'direktorat' => $dir[$r->psn_id] ?? null,
                'kegiatan_id' => $r->kegiatan_id, 'kegiatan' => $r->kegiatan_nama, 'psn_id' => $r->psn_id, 'psn' => $r->psn_nama,
                'rencana_persen' => (float) $r->target_persen, 'realisasi_persen' => (float) $r->realisasi_persen,
                'deviasi_pp' => (float) $r->deviasi_pp, 'status' => $r->status_progres,
            ])->all();
        });
    }

    public function tahapan(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('tahapan', $c, $f, function () use ($c, $f) {
            $hitung = $this->baris($c, $f)->where('is_aktif', true)->countBy(fn ($r) => $r->tahap ?? 'TANPA');

            return collect(self::URUTAN_TAHAP + ['TANPA' => 'Tanpa status'])
                ->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label, 'jumlah' => $hitung[$kode] ?? 0])
                ->values()->all();
        });
    }

    public function statusData(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('status-data', $c, $f, function () use ($c, $f) {
            $aktif = $this->baris($c, $f)->where('is_aktif', true);
            $belum = DB::table('pengisian_psn')->where('periode_cutoff_id', $c->id)
                ->whereIn('psn_id', $aktif->pluck('psn_id'))->where('status', '<>', 'DIVERIFIKASI')->count();

            return [
                'tanggal_cutoff' => $c->tanggal_cutoff->toDateString(),
                'diterbitkan_at' => $c->diterbitkan_at?->toIso8601String(),
                'kelengkapan_persen' => $aktif->isEmpty() ? null : round($aktif->avg('kelengkapan_persen'), 1),
                'belum_terverifikasi' => $belum,
                'total_psn' => $aktif->count(),
            ];
        });
    }

    /** P8: metodologi belum ditetapkan -- placeholder jumlah indikator per kategori. */
    public function trisula(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('trisula', $c, $f, function () use ($c, $f) {
            $psnIds = $this->baris($c, $f)->where('is_aktif', true)->pluck('psn_id');
            $per = DB::table('trisula as t')->leftJoin('ref_kode as k', 'k.id', '=', 't.kategori_id')
                ->whereIn('t.psn_id', $psnIds)->whereNull('t.deleted_at')
                ->groupBy('k.nama')->selectRaw("COALESCE(k.nama, 'Tanpa kategori') AS label, COUNT(*) AS indikator, COUNT(DISTINCT t.psn_id) AS psn")->get();

            return ['metodologi_ditetapkan' => false, 'kategori' => $per->map(fn ($r) => (array) $r)->all()];
        });
    }

    public function timelineDirektifPresiden(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('timeline-dp', $c, $f, function () use ($c, $f) {
            $dp = DB::table('ref_klaster')->where('kode', 'DP')->value('id');
            $ids = $this->baris($c, $f)->where('is_aktif', true)->where('klaster_id', $dp)->pluck('psn_id');
            $tahun = DB::table('psn')->whereIn('id', $ids)->pluck('tahun_selesai')
                ->countBy(fn ($t) => $t === null ? 'TANPA' : ($t > 2029 ? 'SETELAH_2029' : (string) $t));

            $awal = (int) min($c->tanggal_cutoff->year, (int) ($tahun->keys()->filter(fn ($k) => is_numeric($k))->min() ?? 2029));

            return collect(range($awal, 2029))->map(fn ($t) => ['label' => (string) $t, 'jumlah' => $tahun[(string) $t] ?? 0])
                ->push(['label' => '> 2029', 'jumlah' => $tahun['SETELAH_2029'] ?? 0])
                ->push(['label' => 'Belum ada', 'jumlah' => $tahun['TANPA'] ?? 0])
                ->all();
        });
    }

    /** Daftar proyek klaster Direktif Presiden (tahun selesai terdekat dulu) untuk timeline menuju 2029. */
    public function dpProyek(PeriodeCutoff $c, FilterGlobal $f): array
    {
        return $this->cache('dp-proyek', $c, $f, function () use ($c, $f) {
            $rows = $this->baris($c, $f)->where('is_aktif', true)->where('klaster_id', $this->idKlasterDp())->keyBy('psn_id');
            $psn = DB::table('psn')->whereIn('id', $rows->keys())->get(['id', 'nama', 'tahun_selesai']);
            $dir = $this->direktoratPsn($rows->keys()->all());

            $daftar = $psn->sort(fn ($a, $b) => [$a->tahun_selesai === null, $a->tahun_selesai, $a->nama] <=> [$b->tahun_selesai === null, $b->tahun_selesai, $b->nama])
                ->map(fn ($p) => [
                    'psn_id' => $p->id, 'nama' => $p->nama, 'direktorat' => $dir[$p->id] ?? null, 'tahun_selesai' => $p->tahun_selesai,
                    'status' => $rows[$p->id]->status_progres, 'realisasi_persen' => $rows[$p->id]->progres_realisasi_persen !== null ? (float) $rows[$p->id]->progres_realisasi_persen : null,
                ])->values();

            return ['tahun_awal' => $c->tanggal_cutoff->year, 'tahun_akhir' => 2029, 'total' => $daftar->count(),
                'proyek' => $daftar->take(config('psn_dashboard.dashboard_top.dp_proyek'))->all()];
        });
    }

    /** @return array<int,string> psn_id => nama direktorat pengampu pertama (abjad) */
    protected function direktoratPsn(array $psnIds): array
    {
        return DB::table('psn_unit_pengampu as pu')->join('ref_unit_kerja as u', 'u.id', '=', 'pu.unit_kerja_id')
            ->where('u.jenis', 'DIREKTORAT')->whereIn('pu.psn_id', $psnIds)->orderBy('u.nama')->get(['pu.psn_id', 'u.nama'])
            ->groupBy('psn_id')->map(fn ($g) => $g->first()->nama)->all();
    }

    /** Aktivitas terbaru dari jejak audit, dibatasi cakupan akses (tanpa cache). */
    public function aktivitas(int $limit = 10): array
    {
        $user = Auth::user();

        return AuditLog::query()
            ->leftJoin('psn as p', 'p.id', '=', 'audit_log.psn_id')
            ->when($user instanceof User && $user->lihatTerbatas(), fn ($q) => $q->whereIn('audit_log.psn_id', fn ($s) => $s->select('psn_id')
                ->from('psn_unit_pengampu')->where('unit_kerja_id', $user->unit_kerja_id ?? 0)))
            ->orderByDesc('audit_log.created_at')->orderByDesc('audit_log.id')->limit($limit)
            ->get(['audit_log.*', 'p.nama as psn_nama'])
            ->map(fn ($r) => [
                'waktu' => $r->created_at?->toIso8601String(), 'pengguna' => $r->user_label ?? 'sistem', 'aksi' => $r->aksi,
                'tabel' => $r->tabel, 'psn_id' => $r->psn_id, 'psn' => $r->psn_nama, 'sumber' => $r->sumber,
            ])->all();
    }

    // ------------------------------------------------------------------ bantu

    /** Baris snapshot_psn cut-off, lewat model (scope cakupan aktif), lolos filter. */
    public function baris(PeriodeCutoff $c, FilterGlobal $f): Collection
    {
        $skema = $this->skemaPerDanaId();

        return SnapshotPsn::query()->where('periode_cutoff_id', $c->id)->get()
            ->filter(fn ($r) => $f->cocok($r, $skema))->values();
    }

    /** @return array<int,string> ref_sumber_dana.id => skema (dari config skema_dana) */
    public function skemaPerDanaId(): array
    {
        $map = config('psn_dashboard.skema_dana');

        return DB::table('ref_sumber_dana')->pluck('kode', 'id')->map(fn ($kode) => $map[$kode] ?? 'LAINNYA')->all();
    }

    protected function cache(string $nama, PeriodeCutoff $c, FilterGlobal $f, \Closure $hitung): mixed
    {
        $user = Auth::user();
        $cakupan = $user instanceof User && $user->lihatTerbatas() ? 'unit:'.($user->unit_kerja_id ?? 0) : 'semua';

        return DashboardCache::remember($nama, $c->kode, $f->denganPeriode($c->kode)->toArray() + ['cakupan' => $cakupan], 'dashboard', $hitung);
    }
}
