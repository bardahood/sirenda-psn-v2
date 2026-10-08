<?php

namespace App\Services;

use App\Enums\LevelRisiko;
use App\Enums\StatusProgres;
use App\Models\AuditLog;
use App\Models\PeriodeCutoff;
use App\Support\DashboardCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membekukan nilai dashboard per cut-off ke tabel snapshot_* (lihat docs/kamus-indikator.md).
 * Membaca tabel inti lewat query builder (tanpa global scope) karena snapshot berlaku untuk
 * seluruh portofolio; pembatasan akses diterapkan saat snapshot dibaca.
 */
class SnapshotService
{
    protected array $cfg;

    protected CarbonImmutable $tanggal;

    protected int $bulan;

    protected int $tahun;

    public function __construct(protected StatusResolver $status)
    {
        $this->cfg = config('psn_dashboard');
    }

    /**
     * @return array<string,int|string> ringkasan
     */
    public function buat(string $kodeCutoff, ?string $tanggalCutoff = null, bool $terbit = false, bool $paksa = false): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $kodeCutoff)) {
            throw new RuntimeException("Format cut-off harus YYYY-MM, diberikan '{$kodeCutoff}'.");
        }

        $tanggal = $tanggalCutoff
            ? CarbonImmutable::parse($tanggalCutoff)
            : CarbonImmutable::createFromFormat('Y-m-d', "{$kodeCutoff}-01")->endOfMonth()->startOfDay();

        $periode = PeriodeCutoff::firstOrCreate(['kode' => $kodeCutoff], ['tanggal_cutoff' => $tanggal->toDateString(), 'status' => 'DRAFT']);

        if ($periode->isTerbit() && ! $paksa) {
            throw new RuntimeException("Cut-off {$kodeCutoff} sudah terbit. Gunakan --paksa untuk membangun ulang.");
        }

        $periode->update(['tanggal_cutoff' => $tanggal->toDateString()]);
        $this->tanggal = $tanggal;
        $this->bulan = $tanggal->month;
        $this->tahun = $tanggal->year;

        $psn = $this->psnAktifPadaCutoff();
        $kegiatan = $this->snapshotKegiatan($psn->keys());
        $risiko = $this->snapshotRisiko($psn->keys());
        $kelengkapan = $this->kelengkapan($psn);
        $baris = $this->snapshotPsn($psn, $kegiatan, $risiko, $kelengkapan);

        DB::transaction(function () use ($periode, $baris, $kegiatan, $risiko, $kelengkapan, $psn, $terbit) {
            foreach (['snapshot_psn', 'snapshot_kegiatan', 'snapshot_risiko', 'snapshot_kelengkapan'] as $t) {
                DB::table($t)->where('periode_cutoff_id', $periode->id)->delete();
            }

            $pid = ['periode_cutoff_id' => $periode->id];
            foreach (array_chunk($baris, 200) as $c) {
                DB::table('snapshot_psn')->insert(array_map(fn ($r) => $pid + $r, $c));
            }
            foreach (array_chunk($kegiatan->map(fn ($r) => $pid + collect($r)->except('pembaruan_at')->all())->values()->all(), 200) as $c) {
                DB::table('snapshot_kegiatan')->insert($c);
            }
            foreach (array_chunk($risiko->map(fn ($r) => $pid + collect($r)->except(['skor_residual', 'level_residual'])->all())->values()->all(), 200) as $c) {
                DB::table('snapshot_risiko')->insert($c);
            }
            foreach (array_chunk($kelengkapan->flatten(1)->map(fn ($r) => $pid + $r)->all(), 500) as $c) {
                DB::table('snapshot_kelengkapan')->insert($c);
            }

            // Baris pengisian (DRAFT) untuk semua PSN aktif -- dasar indikator Kualitas Data.
            DB::table('pengisian_psn')->insertOrIgnore($psn->filter(fn ($p) => $p->is_aktif)->keys()
                ->map(fn ($id) => $pid + ['psn_id' => $id, 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now()])->all());

            if ($terbit) {
                $periode->update(['status' => 'TERBIT', 'diterbitkan_at' => now(), 'diterbitkan_oleh' => Auth::id()]);
                AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username ?? 'console', 'tabel' => 'periode_cutoff',
                    'record_id' => $periode->id, 'aksi' => 'PUBLISH', 'nilai_baru' => ['kode' => $periode->kode, 'jumlah_psn' => count($baris)]]);
            }
        });

        if ($terbit) {
            DashboardCache::flush();
        }

        $status = collect($baris)->countBy('status_progres');

        return [
            'cutoff' => $periode->kode,
            'tanggal' => $tanggal->toDateString(),
            'status' => $periode->fresh()->status,
            'psn' => count($baris),
            'psn_aktif' => collect($baris)->where('is_aktif', true)->count(),
            'kegiatan' => $kegiatan->count(),
            'risiko' => $risiko->count(),
        ] + collect(StatusProgres::cases())->mapWithKeys(fn ($s) => [$s->value => $status[$s->value] ?? 0])->all();
    }

    /** PSN yang belum dihapus pada tanggal cut-off, beserta dimensi filter. */
    protected function psnAktifPadaCutoff(): Collection
    {
        $akhir = $this->tanggal->endOfDay();

        $psn = DB::table('psn as p')
            ->leftJoin('ref_status_psn as s', 's.id', '=', 'p.status_psn_id')
            ->where(fn ($q) => $q->whereNull('p.deleted_at')->orWhere('p.deleted_at', '>', $akhir))
            ->select('p.*', 's.tahap', DB::raw('COALESCE(s.is_aktif, 1) as is_aktif'))
            ->get()->keyBy('id');

        $kelompok = fn (string $tabel, string $kolom, bool $soft = false) => DB::table($tabel)
            ->when($soft, fn ($q) => $q->whereNull('deleted_at'))
            ->whereIn('psn_id', $psn->keys())->get(['psn_id', $kolom])
            ->groupBy('psn_id')->map(fn ($g) => $g->pluck($kolom)->unique()->sort()->values()->all());

        $prov = $kelompok('psn_lokasi', 'provinsi_kode', true);
        $unit = $kelompok('psn_unit_pengampu', 'unit_kerja_id');
        $dana = $kelompok('psn_sumber_dana', 'sumber_dana_id');

        return $psn->map(function ($p) use ($prov, $unit, $dana) {
            $p->is_aktif = (bool) $p->is_aktif;
            $p->provinsi = $prov[$p->id] ?? [];
            $p->unit = array_map('intval', $unit[$p->id] ?? []);
            $p->dana = array_map('intval', $dana[$p->id] ?? []);

            return $p;
        });
    }

    /**
     * Progres tiap KP/RO pada tahun cut-off s.d. bulan cut-off.
     *
     * @return Collection<int, array> keyed by kegiatan_id
     */
    protected function snapshotKegiatan(Collection $psnIds): Collection
    {
        $akhir = $this->tanggal->endOfDay();
        $kegiatan = DB::table('kegiatan')->whereIn('psn_id', $psnIds)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $akhir))
            ->get(['id', 'psn_id', 'is_critical_path'])->keyBy('id');

        $target = DB::table('kegiatan_target')->whereIn('kegiatan_id', $kegiatan->keys())->where('tahun', $this->tahun)
            ->get()->groupBy('kegiatan_id');

        return $target->map(function (Collection $baris, $kid) use ($kegiatan) {
            $g = $kegiatan[$kid];
            [$rencana, $realisasi, $pembaruan] = $this->progresKegiatan($baris);
            [$pagu, $realAnggaran] = $this->anggaranKegiatan($baris);

            $status = $this->status->statusProgres($rencana, $realisasi, $pembaruan, $this->tanggal);

            return [
                'kegiatan_id' => (int) $kid,
                'psn_id' => $g->psn_id,
                'is_critical_path' => (bool) $g->is_critical_path,
                'target_persen' => $this->bulat($rencana),
                'realisasi_persen' => $this->bulat($realisasi),
                'deviasi_pp' => $rencana !== null && $realisasi !== null ? $this->bulat($realisasi - $rencana) : null,
                'status_progres' => $status->value,
                'pagu_rp' => $pagu,
                'realisasi_anggaran_rp' => $realAnggaran,
                'is_tercapai' => $rencana !== null && $realisasi !== null && round($realisasi, 4) >= round($rencana, 4),
                'pembaruan_at' => $pembaruan,
            ];
        });
    }

    /**
     * Pilih baris periodik terakhir s.d. cut-off (bulanan, lalu triwulanan), jika tidak ada pakai tahunan.
     *
     * @return array{0: ?float, 1: ?float, 2: ?CarbonImmutable} [rencana %, realisasi %, pembaruan terakhir]
     */
    protected function progresKegiatan(Collection $baris): array
    {
        $posisi = fn ($r) => match ($r->periode) {
            'BULANAN' => (int) $r->periode_ke,
            'TRIWULAN' => (int) $r->periode_ke * 3,
            default => 12,
        };

        $periodik = $baris->whereIn('periode', ['BULANAN', 'TRIWULAN'])->filter(fn ($r) => $posisi($r) <= $this->bulan);
        $pilih = $periodik->sortBy([fn ($a, $b) => $posisi($b) <=> $posisi($a), fn ($a, $b) => $a->periode <=> $b->periode])->first()
            ?? $baris->firstWhere('periode', 'TAHUNAN');

        $pembaruan = $baris->filter(fn ($r) => $r->dilaporkan_at !== null)->max('dilaporkan_at');
        $pembaruan = $pembaruan ? CarbonImmutable::parse($pembaruan) : null;

        if (! $pilih) {
            return [null, null, $pembaruan];
        }

        // Realisasi hanya dianggap ada bila pernah dilaporkan: data lama menyimpan
        // isian kosong sebagai 0, yang bukan berarti realisasi 0%.
        $adaRealisasi = $pilih->dilaporkan_at !== null;
        $rencanaSaja = $pilih->target_persen !== null ? (float) $pilih->target_persen : null;
        if (! $adaRealisasi) {
            return [$rencanaSaja, null, $pembaruan];
        }

        // 1) Persentase fisik eksplisit.
        // Progres fisik dibatasi 0-100%: nilai di atas 100 berasal dari capaian volume
        // melampaui target atau salah isi, bukan progres fisik.
        if ($pilih->target_persen !== null && $pilih->realisasi_persen !== null) {
            return [min((float) $pilih->target_persen, 100.0), min((float) $pilih->realisasi_persen, 100.0), $pembaruan];
        }

        // 2) Capaian volume terhadap target periode.
        if ((float) $pilih->target_1 > 0 && $pilih->realisasi_1 !== null) {
            $realisasi = (float) $pilih->realisasi_1 / (float) $pilih->target_1 * 100;
            $rencana = $pilih->periode === 'TAHUNAN' && $this->cfg['snapshot']['rencana_linear_jika_kosong']
                ? $this->bulan / 12 * 100
                : 100.0;

            return [$rencana, min($realisasi, 100.0), $pembaruan];
        }

        return [$rencanaSaja, null, $pembaruan];
    }

    /** @return array{0: ?float, 1: ?float} [pagu tahun, realisasi s.d. cut-off] */
    protected function anggaranKegiatan(Collection $baris): array
    {
        if ($tahunan = $baris->firstWhere('periode', 'TAHUNAN')) {
            return [$this->angka($tahunan->pagu_rp), $this->angka($tahunan->realisasi_anggaran_rp)];
        }

        $jenis = $baris->contains('periode', 'BULANAN') ? 'BULANAN' : 'TRIWULAN';
        $batas = $jenis === 'BULANAN' ? $this->bulan : intdiv($this->bulan - 1, 3) + 1;
        $p = $baris->where('periode', $jenis);

        return [
            $p->whereNotNull('pagu_rp')->isEmpty() ? null : (float) $p->sum('pagu_rp'),
            $p->whereNotNull('realisasi_anggaran_rp')->isEmpty() ? null : (float) $p->where('periode_ke', '<=', $batas)->sum('realisasi_anggaran_rp'),
        ];
    }

    /** @return Collection<int, array> keyed by risiko_id */
    protected function snapshotRisiko(Collection $psnIds): Collection
    {
        $akhir = $this->tanggal->endOfDay();
        $risiko = DB::table('risiko')->whereIn('psn_id', $psnIds)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $akhir))->get()->keyBy('id');

        $aktual = DB::table('risiko_pemantauan')->whereIn('risiko_id', $risiko->keys())->where('tanggal', '<=', $this->tanggal->toDateString())
            ->orderBy('tanggal')->orderBy('id')->get()->keyBy('risiko_id'); // baris terakhir per risiko

        return $risiko->map(function ($r) use ($aktual) {
            $a = $aktual[$r->id] ?? null;
            [$levelH, $skorH] = $this->status->levelRisiko($r->kemungkinan_harapan, $r->dampak_harapan, $r->level_harapan);
            [$levelA, $skorA] = $a ? $this->status->levelRisiko($a->kemungkinan_aktual, $a->dampak_aktual, $a->level_aktual) : [null, null];

            return [
                'risiko_id' => $r->id,
                'psn_id' => $r->psn_id,
                'kategori_id' => $r->kategori_id,
                'kemungkinan_harapan' => $r->kemungkinan_harapan,
                'dampak_harapan' => $r->dampak_harapan,
                'level_harapan' => $levelH?->value,
                'kemungkinan_aktual' => $a?->kemungkinan_aktual,
                'dampak_aktual' => $a?->dampak_aktual,
                'level_aktual' => $levelA?->value,
                // Residual yang berlaku: aktual bila sudah dipantau, jika belum harapan.
                'skor_residual' => $levelA ? $skorA : $skorH,
                'level_residual' => $levelA ?? $levelH,
            ];
        });
    }

    /**
     * Kelengkapan per PSN per bagian (config psn_dashboard.field_wajib).
     *
     * @return Collection<int, array<int, array>> keyed by psn_id
     */
    protected function kelengkapan(Collection $psn): Collection
    {
        $fw = $this->cfg['field_wajib'];
        $ids = $psn->keys();

        // Bagian profil: gambaran_umum (kolom psn), item:{TYIT} per item narasi, relasi:{tabel}.
        $item = DB::table('psn_profil_item as i')->join('ref_kode as k', 'k.id', '=', 'i.bagian_id')
            ->whereIn('i.psn_id', $ids)->whereNull('i.deleted_at')->whereNotNull('i.isi')->where('i.isi', '<>', '')
            ->whereIn('k.kode', $fw['item_profil'])->where('k.tipe', 'TYIT')
            ->distinct()->get(['i.psn_id', 'k.kode'])->groupBy('psn_id')->map(fn ($g) => $g->pluck('kode')->flip());

        $relasi = collect($fw['relasi'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->whereIn('psn_id', $ids)
            ->when(in_array($t, ['psn_lokasi', 'kegiatan', 'risiko'], true), fn ($q) => $q->whereNull('deleted_at'))
            ->distinct()->pluck('psn_id')->flip()]);

        return $psn->map(function ($p) use ($fw, $item, $relasi) {
            $gu = collect($fw['gambaran_umum'])->filter(fn ($f) => $p->{$f} !== null && $p->{$f} !== '')->count();
            $baris = [['psn_id' => $p->id, 'bagian' => 'gambaran_umum', 'field_wajib' => count($fw['gambaran_umum']), 'field_terisi' => $gu]];

            foreach ($fw['item_profil'] as $kode) {
                $baris[] = ['psn_id' => $p->id, 'bagian' => "item:{$kode}", 'field_wajib' => 1, 'field_terisi' => (int) isset($item[$p->id][$kode])];
            }
            foreach ($fw['relasi'] as $t) {
                $baris[] = ['psn_id' => $p->id, 'bagian' => "relasi:{$t}", 'field_wajib' => 1, 'field_terisi' => (int) isset($relasi[$t][$p->id])];
            }

            return $baris;
        });
    }

    protected function snapshotPsn(Collection $psn, Collection $kegiatan, Collection $risiko, Collection $kelengkapan): array
    {
        $perPsnKegiatan = $kegiatan->groupBy('psn_id');
        $perPsnRisiko = $risiko->groupBy('psn_id');
        $bobotPagu = $this->cfg['snapshot']['bobot_progres_kegiatan'] === 'pagu';

        return $psn->map(function ($p) use ($perPsnKegiatan, $perPsnRisiko, $kelengkapan, $bobotPagu) {
            $k = $perPsnKegiatan[$p->id] ?? collect();
            $berisi = $k->filter(fn ($r) => $r['target_persen'] !== null && $r['realisasi_persen'] !== null);

            [$rencana, $realisasi] = [null, null];
            if ($berisi->isNotEmpty()) {
                $pakaiPagu = $bobotPagu && $berisi->every(fn ($r) => (float) $r['pagu_rp'] > 0);
                $bobot = fn ($r) => $pakaiPagu ? (float) $r['pagu_rp'] : 1.0;
                $total = $berisi->sum($bobot);
                $rencana = $berisi->sum(fn ($r) => $r['target_persen'] * $bobot($r)) / $total;
                $realisasi = $berisi->sum(fn ($r) => $r['realisasi_persen'] * $bobot($r)) / $total;
            }

            $pembaruan = $k->pluck('pembaruan_at')->filter()->max();
            $status = $this->status->statusProgres($rencana, $realisasi, $pembaruan, $this->tanggal);

            $r = $perPsnRisiko[$p->id] ?? collect();
            $maks = $r->filter(fn ($x) => $x['skor_residual'] !== null)->sortByDesc('skor_residual')->first();
            $levelMaks = $maks['level_residual'] ?? null;

            $kl = collect($kelengkapan[$p->id]);
            $wajib = $kl->sum('field_wajib');

            return [
                'psn_id' => $p->id,
                'klaster_id' => $p->klaster_id,
                'status_psn_id' => $p->status_psn_id,
                'tahap' => $p->tahap,
                'kategori' => $this->cfg['kategori_pkpn_dari_klaster_pkpn'] && $p->klaster_pkpn_id ? 'PKPN' : 'PSN',
                'provinsi_kode' => json_encode($p->provinsi),
                'unit_kerja_id' => json_encode($p->unit),
                'sumber_dana_id' => json_encode($p->dana),
                'is_aktif' => $p->is_aktif,
                'nilai_investasi_rp' => $this->cfg['investasi']['kecualikan_anomali_dari_k2'] && $p->investasi_anomali ? null : $p->nilai_investasi_rp,
                'progres_rencana_persen' => $this->bulat($rencana),
                'progres_realisasi_persen' => $this->bulat($realisasi),
                'deviasi_pp' => $rencana !== null && $realisasi !== null ? $this->bulat($realisasi - $rencana) : null,
                'status_progres' => $status->value,
                'pagu_rp' => $k->whereNotNull('pagu_rp')->isEmpty() ? null : $k->sum('pagu_rp'),
                'realisasi_anggaran_rp' => $k->whereNotNull('realisasi_anggaran_rp')->isEmpty() ? null : $k->sum('realisasi_anggaran_rp'),
                'jumlah_ro' => $k->count(),
                'jumlah_ro_tercapai' => $k->where('is_tercapai', true)->count(),
                'risiko_skor_maks' => $maks['skor_residual'] ?? null,
                'risiko_level_maks' => $levelMaks instanceof LevelRisiko ? $levelMaks->value : null,
                'is_kritis' => $status === StatusProgres::Terlambat || $this->status->isRisikoKritis($levelMaks),
                'kelengkapan_persen' => $wajib ? round($kl->sum('field_terisi') / $wajib * 100, 2) : null,
                'pembaruan_terakhir_at' => $pembaruan,
            ];
        })->values()->all();
    }

    protected function bulat(?float $v): ?float
    {
        return $v === null ? null : round($v, 2);
    }

    protected function angka($v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
