<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Isu;
use App\Models\Kegiatan;
use App\Models\KegiatanTarget;
use App\Models\PengisianPsn;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Models\Risiko;
use App\Models\RisikoPemantauan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur pemutakhiran per PSN per cut-off: Operator/Direktorat mengisi realisasi bulanan KP/RO,
 * pemantauan risiko aktual, dan tindak lanjut isu -> Ajukan -> Direktorat memverifikasi atau
 * mengembalikan. Status: (BELUM) -> DRAFT -> DIAJUKAN -> DIVERIFIKASI | DIKEMBALIKAN -> DIAJUKAN ...
 * Setiap perubahan data tercatat lewat AuditObserver; transisi status dicatat eksplisit
 * (SUBMIT/VERIFY/RETURN) beserta pengisian_psn_riwayat.
 */
class PengisianService
{
    public const STATUS = ['BELUM' => 'Belum diisi', 'DRAFT' => 'Draf', 'DIAJUKAN' => 'Diajukan', 'DIVERIFIKASI' => 'Diverifikasi', 'DIKEMBALIKAN' => 'Dikembalikan'];

    public const DAPAT_DIUBAH = ['BELUM', 'DRAFT', 'DIKEMBALIKAN'];

    public function __construct(protected StatusResolver $status) {}

    /** Periode pengisian: kode yang diminta, periode DRAFT terbaru, atau bulan berjalan. Bulan mendatang ditolak. */
    public function periode(?string $kode): PeriodeCutoff
    {
        $kode ??= PeriodeCutoff::where('status', 'DRAFT')->where('kode', '<=', now()->format('Y-m'))->orderByDesc('tanggal_cutoff')->value('kode')
            ?? now()->format('Y-m');
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $kode) && $kode <= now()->format('Y-m'), 404);

        return PeriodeCutoff::firstOrCreate(['kode' => $kode], [
            'tanggal_cutoff' => CarbonImmutable::createFromFormat('!Y-m', $kode)->endOfMonth()->toDateString(), 'status' => 'DRAFT',
        ]);
    }

    public function batasPengisian(PeriodeCutoff $p): CarbonImmutable
    {
        return CarbonImmutable::parse($p->batas_pengisian ?? $p->tanggal_cutoff->copy()->addDays(config('psn_dashboard.pengisian.batas_hari_setelah_cutoff')));
    }

    /** @return string[] kode periode yang dapat dipilih (terbaru dulu) */
    public function daftarPeriode(): array
    {
        $kini = now()->format('Y-m');

        return PeriodeCutoff::where('kode', '<=', $kini)->orderByDesc('kode')->pluck('kode')->prepend($kini)->unique()->values()->all();
    }

    /**
     * Daftar PSN aktif dalam cakupan pengguna beserta status pengisian periode.
     * Opsi: q, status (kode STATUS), milik (hanya PSN unit pengguna).
     */
    public function daftar(PeriodeCutoff $p, User $u, array $o): array
    {
        $dasar = Psn::query()->from('psn')
            ->leftJoin('ref_status_psn as s', 's.id', '=', 'psn.status_psn_id')
            ->leftJoin('pengisian_psn as g', fn ($j) => $j->on('g.psn_id', '=', 'psn.id')->where('g.periode_cutoff_id', $p->id))
            ->whereRaw('COALESCE(s.is_aktif, 1) = 1')
            ->when($o['milik'] ?? false, fn ($q) => $q->whereExists(fn ($w) => $w->from('psn_unit_pengampu as pu')
                ->whereColumn('pu.psn_id', 'psn.id')->where('pu.unit_kerja_id', $u->unit_kerja_id ?? 0)))
            ->when(trim((string) ($o['q'] ?? '')), fn ($q, $v) => $q->where(fn ($w) => $w->where('psn.nama', 'like', "%{$v}%")->orWhere('psn.kode_psn', 'like', "%{$v}%")));

        $rekap = (clone $dasar)->selectRaw("COALESCE(g.status, 'BELUM') AS st, COUNT(*) AS n")->groupBy('st')->pluck('n', 'st');

        $hal = (clone $dasar)
            ->when($o['status'] ?? null, fn ($q, $v) => $v === 'BELUM' ? $q->whereNull('g.id') : $q->where('g.status', $v))
            ->select(['psn.id', 'psn.nama', 'psn.kode_psn', 'g.status', 'g.diajukan_at', 'g.diverifikasi_at', 'g.updated_at as diubah_at'])
            // Yang perlu tindakan di atas: dikembalikan, diajukan, draf, belum, terverifikasi.
            ->orderByRaw("FIELD(COALESCE(g.status, 'BELUM'), 'DIKEMBALIKAN', 'DIAJUKAN', 'DRAFT', 'BELUM', 'DIVERIFIKASI')")->orderBy('psn.nama')->orderBy('psn.id')
            ->paginate(25)->withQueryString();

        $ids = $hal->getCollection()->pluck('id');
        $unit = DB::table('psn_unit_pengampu as pu')->join('ref_unit_kerja as u', 'u.id', '=', 'pu.unit_kerja_id')->whereIn('pu.psn_id', $ids)
            ->orderBy('u.nama')->get(['pu.psn_id', 'u.nama'])->groupBy('psn_id');
        $ro = DB::table('kegiatan as k')->join('kegiatan_target as t', 't.kegiatan_id', '=', 'k.id')->whereNull('k.deleted_at')
            ->whereIn('k.psn_id', $ids)->where('t.tahun', $p->tanggal_cutoff->year)
            ->groupBy('k.psn_id')->selectRaw('k.psn_id, COUNT(DISTINCT k.id) AS n')->pluck('n', 'psn_id');

        $hal->setCollection($hal->getCollection()->map(fn ($r) => [
            'id' => $r->id, 'nama' => $r->nama, 'kode' => $r->kode_psn, 'status' => $r->status ?? 'BELUM',
            'unit' => ($unit[$r->id] ?? collect())->pluck('nama')->all(), 'jumlah_ro' => (int) ($ro[$r->id] ?? 0),
            'diajukan_at' => $r->diajukan_at, 'diverifikasi_at' => $r->diverifikasi_at, 'diubah_at' => $r->diubah_at,
        ]));

        return ['halaman' => $hal, 'rekap' => collect(self::STATUS)->map(fn ($l, $k) => (int) ($rekap[$k] ?? 0))->all()];
    }

    public function pengisian(PeriodeCutoff $p, Psn $psn): ?PengisianPsn
    {
        return PengisianPsn::withoutGlobalScopes()->where('periode_cutoff_id', $p->id)->where('psn_id', $psn->id)->first();
    }

    public function statusPengisian(PeriodeCutoff $p, Psn $psn): string
    {
        return $this->pengisian($p, $psn)?->status ?? 'BELUM';
    }

    /** Data untuk formulir pengisian satu PSN pada satu periode. */
    public function formulir(PeriodeCutoff $p, Psn $psn): array
    {
        $tahun = $p->tanggal_cutoff->year;
        $bulan = $p->tanggal_cutoff->month;

        $kegiatan = $this->kegiatanBertarget($psn, $tahun)->load(['target' => fn ($q) => $q->where('tahun', $tahun)]);
        $ro = $kegiatan->map(function (Kegiatan $k) use ($bulan) {
            $t = $k->target;
            $ini = $t->first(fn ($x) => $x->periode === 'BULANAN' && (int) $x->periode_ke === $bulan);
            $tahunan = $t->firstWhere('periode', 'TAHUNAN');
            $lalu = $t->filter(fn ($x) => $x->periode === 'BULANAN' && (int) $x->periode_ke < $bulan && $x->dilaporkan_at)->sortByDesc('periode_ke')->first();

            return [
                'id' => $k->id, 'nama' => $k->nama, 'is_critical_path' => (bool) $k->is_critical_path,
                'satuan' => $k->satuan_1, 'target_volume' => $tahunan?->target_1, 'pagu_rp' => $tahunan?->pagu_rp,
                'rencana_persen' => $ini?->target_persen ?? $lalu?->target_persen,
                'realisasi_persen' => $ini?->realisasi_persen,
                'realisasi_anggaran_rp' => $ini?->realisasi_anggaran_rp,
                'anggaran_sd_bulan_lalu' => (float) $t->filter(fn ($x) => $x->periode === 'BULANAN' && (int) $x->periode_ke < $bulan)->sum('realisasi_anggaran_rp'),
                'permasalahan' => $ini?->permasalahan, 'bukti' => $ini?->bukti_path, 'dilaporkan_at' => $ini?->dilaporkan_at,
                'realisasi_lalu' => $lalu?->realisasi_persen, 'bulan_lalu' => $lalu ? (int) $lalu->periode_ke : null,
            ];
        })->values()->all();

        $risiko = Risiko::where('psn_id', $psn->id)->with(['kategori', 'pemantauan' => fn ($q) => $q->orderBy('tanggal')->orderBy('id')])->orderBy('id')->get()
            ->map(function (Risiko $r) use ($p) {
                $ini = $r->pemantauan->first(fn ($x) => $x->tanggal->isSameDay($p->tanggal_cutoff) && $x->sumber === 'PELAPORAN');
                $lalu = $r->pemantauan->filter(fn ($x) => $x->tanggal->lt($p->tanggal_cutoff))->last();
                [$level] = $this->status->levelRisiko($r->kemungkinan_harapan, $r->dampak_harapan, $r->level_harapan);

                return [
                    'id' => $r->id, 'uraian' => $r->uraian, 'kategori' => $r->kategori?->nama, 'level_harapan' => $level?->value,
                    'kemungkinan_harapan' => $r->kemungkinan_harapan, 'dampak_harapan' => $r->dampak_harapan, 'rencana_perlakuan' => $r->rencana_perlakuan,
                    'kemungkinan_aktual' => $ini?->kemungkinan_aktual, 'dampak_aktual' => $ini?->dampak_aktual, 'level_aktual' => $ini?->level_aktual,
                    'status_perlakuan' => $ini?->status_perlakuan, 'catatan' => $ini?->catatan,
                    'lalu' => $lalu ? ['tanggal' => $lalu->tanggal->toDateString(), 'level' => $lalu->level_aktual, 'kemungkinan' => $lalu->kemungkinan_aktual, 'dampak' => $lalu->dampak_aktual] : null,
                ];
            })->all();

        $awalBulan = $p->tanggal_cutoff->copy()->startOfMonth();
        $isu = Isu::where('psn_id', $psn->id)
            ->where(fn ($q) => $q->where('status', '<>', 'SELESAI')->orWhere('tanggal_selesai', '>=', $awalBulan))
            ->orderByRaw("status = 'SELESAI'")->orderByRaw('tenggat IS NULL')->orderBy('tenggat')->orderBy('id')->get()
            ->map(fn (Isu $i) => [
                'id' => $i->id, 'uraian' => $i->uraian, 'kebutuhan_dukungan' => $i->kebutuhan_dukungan, 'status' => $i->status,
                'pic_nama' => $i->pic_nama, 'tenggat' => $i->tenggat?->toDateString(), 'tindak_lanjut' => $i->tindak_lanjut,
                'lewat_tenggat' => $i->tenggat !== null && $i->tenggat->lt(now()->startOfDay()) && $i->status !== 'SELESAI',
            ])->all();

        $g = $this->pengisian($p, $psn);

        return [
            'pengisian' => $g, 'status' => $g?->status ?? 'BELUM', 'tahun' => $tahun, 'bulan' => $bulan,
            'ro' => $ro, 'risiko' => $risiko, 'isu' => $isu,
            'riwayat' => $g ? DB::table('pengisian_psn_riwayat as r')->leftJoin('users as u', 'u.id', '=', 'r.user_id')
                ->where('r.pengisian_psn_id', $g->id)->orderByDesc('r.id')->get(['r.*', 'u.name as pengguna']) : collect(),
            'kategori_risiko' => DB::table('ref_kode')->where('tipe', 'RISK')->orderBy('urutan')->pluck('nama', 'id'),
        ];
    }

    /** KP/RO aktif yang punya target pada tahun periode. */
    public function kegiatanBertarget(Psn $psn, int $tahun): Collection
    {
        return Kegiatan::where('psn_id', $psn->id)->whereHas('target', fn ($q) => $q->where('tahun', $tahun))
            ->orderByDesc('is_critical_path')->orderBy('id')->get();
    }

    /**
     * Simpan isian (draf). $d: ro[id]{rencana_persen, realisasi_persen, realisasi_anggaran_rp, permasalahan},
     * bukti[id] (UploadedFile), risiko[id]{kemungkinan_aktual, dampak_aktual, status_perlakuan, catatan},
     * risiko_baru{...}, isu[id]{status, pic_nama, tenggat, tindak_lanjut}, isu_baru{...}.
     *
     * @return array{ro: int, risiko: int, isu: int} jumlah baris yang berubah
     */
    public function simpan(PeriodeCutoff $p, Psn $psn, array $d, User $u, bool $bolehRisiko): array
    {
        $this->pastikanDapatDiubah($p, $psn);
        $tahun = $p->tanggal_cutoff->year;
        $bulan = $p->tanggal_cutoff->month;
        $hitung = ['ro' => 0, 'risiko' => 0, 'isu' => 0];

        DB::transaction(function () use ($p, $psn, $d, $u, $bolehRisiko, $tahun, $bulan, &$hitung) {
            $kegiatanIds = $this->kegiatanBertarget($psn, $tahun)->pluck('id')->all();
            foreach ($d['ro'] ?? [] as $kid => $x) {
                if (! in_array((int) $kid, $kegiatanIds, true)) {
                    continue;
                }
                $t = KegiatanTarget::firstOrNew(['kegiatan_id' => $kid, 'tahun' => $tahun, 'periode' => 'BULANAN', 'periode_ke' => $bulan]);
                $t->fill([
                    'target_persen' => $this->angka($x['rencana_persen'] ?? null),
                    'realisasi_persen' => $this->angka($x['realisasi_persen'] ?? null),
                    'realisasi_anggaran_rp' => $this->angka($x['realisasi_anggaran_rp'] ?? null),
                    'permasalahan' => $this->teks($x['permasalahan'] ?? null),
                ]);
                if (($f = $d['bukti'][$kid] ?? null) instanceof UploadedFile) {
                    $t->bukti_path = $f->store("bukti/{$psn->id}", 'public');
                }
                // Waktu pelaporan hanya bergeser bila realisasi benar-benar berubah (dasar status "Tanpa data").
                if ($t->isDirty(['realisasi_persen', 'realisasi_anggaran_rp']) && ($t->realisasi_persen !== null || $t->realisasi_anggaran_rp !== null)) {
                    $t->dilaporkan_at = now();
                }
                if (! $t->exists && $t->realisasi_persen === null && $t->target_persen === null && $t->realisasi_anggaran_rp === null && $t->permasalahan === null && ! $t->bukti_path) {
                    continue; // baris kosong tidak dibuat
                }
                if ($t->isDirty()) {
                    $t->save();
                    $hitung['ro']++;
                }
            }

            if ($bolehRisiko) {
                $hitung['risiko'] += $this->simpanRisiko($p, $psn, $d);
                $hitung['isu'] += $this->simpanIsu($psn, $d);
            }

            $g = $this->pengisian($p, $psn);
            if (! $g) {
                $g = new PengisianPsn(['periode_cutoff_id' => $p->id, 'psn_id' => $psn->id, 'status' => 'DRAFT']);
                $g->saveQuietly();
                $this->transisi($g, null, 'DRAFT', $u);
            } else {
                $g->touch();
            }
        });

        return $hitung;
    }

    protected function simpanRisiko(PeriodeCutoff $p, Psn $psn, array $d): int
    {
        $n = 0;
        $milik = Risiko::where('psn_id', $psn->id)->pluck('id')->all();
        foreach ($d['risiko'] ?? [] as $rid => $x) {
            if (! in_array((int) $rid, $milik, true)) {
                continue;
            }
            $k = $this->bulat($x['kemungkinan_aktual'] ?? null);
            $dm = $this->bulat($x['dampak_aktual'] ?? null);
            $st = $x['status_perlakuan'] ?? null ?: null;
            $cat = $this->teks($x['catatan'] ?? null);
            $m = RisikoPemantauan::firstOrNew(['risiko_id' => $rid, 'tanggal' => $p->tanggal_cutoff->toDateString(), 'sumber' => 'PELAPORAN']);
            if (! $m->exists && $k === null && $dm === null && $st === null && $cat === null) {
                continue;
            }
            [$level] = $this->status->levelRisiko($k, $dm);
            $m->fill(['tahun' => $p->tanggal_cutoff->year, 'triwulan' => intdiv($p->tanggal_cutoff->month - 1, 3) + 1,
                'kemungkinan_aktual' => $k, 'dampak_aktual' => $dm, 'level_aktual' => $level?->value, 'status_perlakuan' => $st, 'catatan' => $cat]);
            if ($m->isDirty()) {
                $m->save();
                $n++;
            }
        }

        $b = $d['risiko_baru'] ?? [];
        if ($uraian = $this->teks($b['uraian'] ?? null)) {
            $k = $this->bulat($b['kemungkinan_harapan'] ?? null);
            $dm = $this->bulat($b['dampak_harapan'] ?? null);
            [$level] = $this->status->levelRisiko($k, $dm);
            Risiko::create(['psn_id' => $psn->id, 'uraian' => $uraian, 'kategori_id' => $b['kategori_id'] ?? null ?: null,
                'kemungkinan_harapan' => $k, 'dampak_harapan' => $dm, 'level_harapan' => $level?->value,
                'rencana_perlakuan' => $this->teks($b['rencana_perlakuan'] ?? null), 'penanggung_jawab' => $this->teks($b['penanggung_jawab'] ?? null)]);
            $n++;
        }

        return $n;
    }

    protected function simpanIsu(Psn $psn, array $d): int
    {
        $n = 0;
        $isu = Isu::where('psn_id', $psn->id)->get()->keyBy('id');
        foreach ($d['isu'] ?? [] as $id => $x) {
            if (! $i = $isu[(int) $id] ?? null) {
                continue;
            }
            $i->fill(['status' => $x['status'] ?? $i->status, 'pic_nama' => $this->teks($x['pic_nama'] ?? null),
                'tenggat' => $x['tenggat'] ?? null ?: null, 'tindak_lanjut' => $this->teks($x['tindak_lanjut'] ?? null)]);
            if ($i->isDirty('status')) {
                $i->tanggal_selesai = $i->status === 'SELESAI' ? now()->toDateString() : null;
            }
            if ($i->isDirty()) {
                $i->save();
                $n++;
            }
        }

        $b = $d['isu_baru'] ?? [];
        if ($uraian = $this->teks($b['uraian'] ?? null)) {
            Isu::create(['psn_id' => $psn->id, 'uraian' => $uraian, 'kebutuhan_dukungan' => $this->teks($b['kebutuhan_dukungan'] ?? null),
                'pic_nama' => $this->teks($b['pic_nama'] ?? null), 'tenggat' => $b['tenggat'] ?? null ?: null, 'status' => 'TERBUKA']);
            $n++;
        }

        return $n;
    }

    public function ajukan(PeriodeCutoff $p, Psn $psn, User $u): void
    {
        $this->pastikanDapatDiubah($p, $psn);
        $g = $this->pengisian($p, $psn) ?? throw ValidationException::withMessages(['status' => 'Simpan isian terlebih dahulu sebelum mengajukan.']);

        if (config('psn_dashboard.pengisian.wajib_realisasi_semua_ro')) {
            $tahun = $p->tanggal_cutoff->year;
            $kosong = $this->kegiatanBertarget($psn, $tahun)->filter(fn (Kegiatan $k) => ! KegiatanTarget::where('kegiatan_id', $k->id)
                ->where(['tahun' => $tahun, 'periode' => 'BULANAN', 'periode_ke' => $p->tanggal_cutoff->month])->whereNotNull('realisasi_persen')->exists());
            if ($kosong->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => "Realisasi progres belum diisi untuk {$kosong->count()} KP/RO: ".$kosong->pluck('nama')->take(5)->implode('; ')]);
            }
        }

        $dari = $g->status;
        $g->forceFill(['status' => 'DIAJUKAN', 'diajukan_at' => now(), 'diajukan_oleh' => $u->id])->saveQuietly();
        $this->transisi($g, $dari, 'DIAJUKAN', $u, null, 'SUBMIT');
    }

    public function verifikasi(PeriodeCutoff $p, Psn $psn, User $u, bool $setuju, ?string $catatan): void
    {
        abort_if($p->isTerbit(), 409, 'Periode sudah diterbitkan; data terkunci.');
        $g = $this->pengisian($p, $psn);
        if (! $g || $g->status !== 'DIAJUKAN') {
            throw ValidationException::withMessages(['status' => 'Hanya isian berstatus Diajukan yang dapat diverifikasi atau dikembalikan.']);
        }
        if (! $setuju && ! trim((string) $catatan)) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi saat mengembalikan isian.']);
        }

        $ke = $setuju ? 'DIVERIFIKASI' : 'DIKEMBALIKAN';
        $g->forceFill(['status' => $ke, 'diverifikasi_at' => now(), 'diverifikasi_oleh' => $u->id, 'catatan_verifikator' => $catatan ?: null])->saveQuietly();
        $this->transisi($g, 'DIAJUKAN', $ke, $u, $catatan, $setuju ? 'VERIFY' : 'RETURN');
    }

    public function dapatDiubah(PeriodeCutoff $p, string $status): bool
    {
        return ! $p->isTerbit() && in_array($status, self::DAPAT_DIUBAH, true);
    }

    protected function pastikanDapatDiubah(PeriodeCutoff $p, Psn $psn): void
    {
        abort_if($p->isTerbit(), 409, 'Periode sudah diterbitkan; data terkunci.');
        $st = $this->statusPengisian($p, $psn);
        if (! in_array($st, self::DAPAT_DIUBAH, true)) {
            throw ValidationException::withMessages(['status' => 'Isian berstatus '.self::STATUS[$st].' tidak dapat diubah.']);
        }
    }

    protected function transisi(PengisianPsn $g, ?string $dari, string $ke, User $u, ?string $catatan = null, ?string $aksi = null): void
    {
        DB::table('pengisian_psn_riwayat')->insert(['pengisian_psn_id' => $g->id, 'dari_status' => $dari, 'ke_status' => $ke,
            'user_id' => $u->id, 'catatan' => $catatan, 'created_at' => now()]);
        AuditLog::create(['user_id' => $u->id, 'user_label' => $u->username, 'tabel' => 'pengisian_psn', 'record_id' => $g->id, 'psn_id' => $g->psn_id,
            'aksi' => $aksi ?? 'CREATE', 'nilai_lama' => $dari ? ['status' => $dari] : null, 'nilai_baru' => array_filter(['status' => $ke, 'catatan' => $catatan])]);
    }

    protected function angka(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Format Indonesia (1.234,5) maupun titik desimal (1234.5).
        $v = trim((string) $v);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return is_numeric($v) ? number_format((float) $v, 2, '.', '') : null;
    }

    protected function bulat(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    protected function teks(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
