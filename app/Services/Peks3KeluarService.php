<?php

namespace App\Services;

use App\Models\Isu;
use App\Models\Kegiatan;
use App\Models\KegiatanTarget;
use App\Models\Peks3Keluar;
use App\Models\Risiko;
use App\Models\RisikoPemantauan;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sinkron arah balik Dashboard -> SIRENDA PEKS3 (docs/integrasi-peks3.md §9).
 *
 *  1. catat*(): setiap isian menu Pengisian dicatat sebagai operasi di peks3_keluar, berisi
 *     - kirim: perubahan dalam nama tabel/kolom PEKS3, beserta "dasar" (nilai kolom tersebut di cermin
 *       saat diubah) untuk deteksi konflik di penerima;
 *     - lokal: cara memulihkan isian di tabel dashboard setelah impor ulang dari cermin.
 *  2. kirim(): operasi ANTRE dikirim bertanda tangan HMAC ke penerima di PEKS3 (kit TerimaDariDashboard).
 *  3. pulihkan(): dipanggil Peks3TerapkanService setelah impor ulang. Operasi yang hasilnya sudah tampak
 *     di cermin ditandai SELESAI; selain itu isian dashboard dipulihkan agar tidak hilang. Kolom yang
 *     tidak ada di PEKS3 (catatan permasalahan, bukti, riwayat pemantauan per cut-off) selalu dipulihkan.
 */
class Peks3KeluarService
{
    /** Status yang isian PEKS3-nya masih perlu dipulihkan di dashboard (belum tampak di cermin). */
    public const BELUM_TERCERMIN = ['ANTRE', 'DITERIMA', 'KONFLIK', 'DITOLAK', 'GAGAL'];

    protected ?Connection $cermin = null;

    protected bool $cerminDicek = false;

    // ------------------------------------------------------------------ 1. pencatatan

    /** Realisasi/rencana bulanan KP/RO -> psn_kegiatan_detil(_cp) kolom *_{bulan}. */
    public function catatRo(Kegiatan $k, KegiatanTarget $t, ?User $u): void
    {
        if (! preg_match('/^(psn_kegiatan|psn_kegiatan_cp):(\d+)$/', (string) $k->legacy_ref, $m) || ! ($psn = $this->psnLama($k->psn_id))) {
            return;
        }
        [$tabel, $kolomInduk] = $m[1] === 'psn_kegiatan' ? ['psn_kegiatan_detil', 'kegiatan_id'] : ['psn_kegiatan_detil_cp', 'cp_id'];
        $b = (int) $t->periode_ke;
        $tahun = $this->tahunPeks3($tabel, $kolomInduk, (int) $m[2], (int) $t->tahun);
        $nilai = ["target_persen_{$b}" => $this->num($t->target_persen), "realisasi_persen_{$b}" => $this->num($t->realisasi_persen),
            "realisasi_anggaran_{$b}" => $this->num($t->realisasi_anggaran_rp)];
        $kunci = "ro:{$k->legacy_ref}:{$t->tahun}:{$b}";
        $baris = $this->baris($tabel, [$kolomInduk => (int) $m[2], 'tahun' => $tahun]);

        $this->simpanOperasi('RO_BULAN', $k->psn_id, $kunci, [
            'aksi' => 'isi_bulan', 'tabel' => $tabel, 'psn_id' => $psn, 'induk' => ['kolom' => $kolomInduk, 'id' => (int) $m[2], 'tahun' => $tahun],
            'dasar' => $this->dasar($kunci, $nilai, $baris), 'nilai' => $nilai,
        ], [
            'kegiatan_ref' => $k->legacy_ref, 'tahun' => (int) $t->tahun, 'bulan' => $b,
            'nilai' => ['target_persen' => $t->target_persen, 'realisasi_persen' => $t->realisasi_persen, 'realisasi_anggaran_rp' => $t->realisasi_anggaran_rp,
                'dilaporkan_at' => $t->dilaporkan_at?->toDateTimeString()],
            'tetap' => ['permasalahan' => $t->permasalahan, 'bukti_path' => $t->bukti_path],
        ], $u);
    }

    /** Pemantauan risiko per cut-off: riwayat tetap di dashboard; yang terbaru -> psn_risiko.risiko_residual & status. */
    public function catatPemantauan(Risiko $r, RisikoPemantauan $m, ?User $u): void
    {
        [$ref, $legacy, $bergantung] = $this->rujukan('RISIKO_BARU', $r->legacy_id, $r->id);
        $tanggal = $m->tanggal->toDateString();
        $terbaru = ! DB::table('risiko_pemantauan')->where('risiko_id', $r->id)->where('sumber', 'PELAPORAN')->where('tanggal', '>', $tanggal)->exists();
        $nilai = ['risiko_residual' => $m->level_aktual, 'status' => $m->status_perlakuan];
        $kunci = "risiko_pantau:{$ref}:{$tanggal}";
        $kirim = $terbaru && ($psn = $this->psnLama($r->psn_id)) ? [
            'aksi' => 'ubah', 'tabel' => 'psn_risiko', 'psn_id' => $psn, 'baris_id' => $legacy,
            'dasar' => $this->dasar("risiko_pantau:{$ref}", $nilai, $legacy ? $this->baris('psn_risiko', ['id' => $legacy]) : null), 'nilai' => $nilai,
        ] : [];

        $this->simpanOperasi('RISIKO_PANTAU', $r->psn_id, $kunci, $kirim, [
            'risiko' => $ref, 'tanggal' => $tanggal,
            'tetap' => collect($m->only(['tahun', 'triwulan', 'level_aktual', 'kemungkinan_aktual', 'dampak_aktual', 'progres_persen', 'status_perlakuan', 'catatan']))->all(),
        ], $u, $bergantung, null, $kirim ? 'ANTRE' : 'SELESAI');
    }

    public function catatRisikoBaru(Risiko $r, ?User $u): void
    {
        $kategori = $r->kategori_id ? DB::table('ref_kode')->where('id', $r->kategori_id)->value('kode') : null;
        $this->simpanOperasi('RISIKO_BARU', $r->psn_id, "risiko_baru:{$r->id}", ($psn = $this->psnLama($r->psn_id)) ? [
            'aksi' => 'tambah', 'tabel' => 'psn_risiko', 'psn_id' => $psn,
            'nilai' => ['risiko' => $r->uraian, 'kategori' => $kategori, 'risiko_harapan' => $r->level_harapan, 'rencana' => $r->rencana_perlakuan,
                'penanggung_jawab' => $r->penanggung_jawab],
        ] : [], [
            'nilai' => collect($r->only(['uraian', 'kemungkinan_harapan', 'dampak_harapan', 'level_harapan', 'rencana_perlakuan', 'penanggung_jawab']))->all() + ['kategori' => $kategori],
        ], $u, null, $r->id);
    }

    /** Status/PIC/tenggat/tindak lanjut isu -> psn_kebutuhan (kolom tambahan PEKS3, §9.5). */
    public function catatIsu(Isu $i, ?User $u): void
    {
        [$ref, $legacy, $bergantung] = $this->rujukan('ISU_BARU', $i->legacy_id, $i->id);
        $nilai = $this->nilaiIsu($i);
        $kunci = "isu:{$ref}";
        $this->simpanOperasi('ISU_UBAH', $i->psn_id, $kunci, ($psn = $this->psnLama($i->psn_id)) ? [
            'aksi' => 'ubah', 'tabel' => 'psn_kebutuhan', 'psn_id' => $psn, 'baris_id' => $legacy,
            'dasar' => $this->dasar($kunci, $nilai, $legacy ? $this->baris('psn_kebutuhan', ['id' => $legacy]) : null), 'nilai' => $nilai,
        ] : [], ['isu' => $ref, 'nilai' => $this->lokalIsu($i)], $u, $bergantung);
    }

    public function catatIsuBaru(Isu $i, ?User $u): void
    {
        $this->simpanOperasi('ISU_BARU', $i->psn_id, "isu_baru:{$i->id}", ($psn = $this->psnLama($i->psn_id)) ? [
            'aksi' => 'tambah', 'tabel' => 'psn_kebutuhan', 'psn_id' => $psn,
            'nilai' => ['jenis' => 'A', 'point' => $i->uraian, 'deskripsi' => $i->kebutuhan_dukungan] + $this->nilaiIsu($i),
        ] : [], ['nilai' => ['uraian' => $i->uraian, 'kebutuhan_dukungan' => $i->kebutuhan_dukungan] + $this->lokalIsu($i)], $u, null, $i->id);
    }

    protected function nilaiIsu(Isu $i): array
    {
        return ['status' => $i->status, 'pic_nama' => $i->pic_nama, 'tenggat' => $i->tenggat?->toDateString(),
            'tindak_lanjut' => $i->tindak_lanjut, 'tanggal_selesai' => $i->tanggal_selesai?->toDateString()];
    }

    protected function lokalIsu(Isu $i): array
    {
        return $this->nilaiIsu($i);
    }

    /**
     * Rujukan baris dashboard yang mungkin belum ada di PEKS3: [kunci rujukan, id PEKS3 atau null, id operasi tambah].
     * Baris hasil impor -> "id:{legacy_id}". Baris yang dibuat di dashboard -> "op:{id operasi tambah}".
     */
    protected function rujukan(string $jenisTambah, ?int $legacyId, int $v2Id): array
    {
        if ($legacyId) {
            return ["id:{$legacyId}", $legacyId, null];
        }
        $op = Peks3Keluar::where('jenis', $jenisTambah)->where('v2_id', $v2Id)->latest('id')->first();

        return $op ? ["op:{$op->id}", $op->peks3_id, $op->peks3_id ? null : $op->id] : ["v2:{$v2Id}", null, null];
    }

    /**
     * Simpan operasi. Isian berurutan pada sasaran yang sama digabung selama operasi sebelumnya belum terkirim
     * (dasar tetap dari isian pertama, nilai dan pemulihan dari isian terakhir).
     */
    protected function simpanOperasi(string $jenis, int $psnId, string $kunci, array $kirim, array $lokal, ?User $u, ?int $bergantung = null, ?int $v2Id = null, ?string $status = null): void
    {
        $status ??= $kirim ? 'ANTRE' : 'SELESAI';
        $ada = Peks3Keluar::where('kunci', $kunci)->where('status', 'ANTRE')->whereNull('dikirim_at')->latest('id')->first();
        if ($ada && $status === 'ANTRE') {
            $k = $ada->kirim;
            $k['nilai'] = $kirim['nilai'];
            $ada->update(['kirim' => $k, 'lokal' => $lokal, 'user_id' => $u?->id ?? $ada->user_id]);

            return;
        }
        Peks3Keluar::create(['jenis' => $jenis, 'psn_id' => $psnId, 'kunci' => $kunci, 'kirim' => $kirim, 'lokal' => $lokal, 'status' => $status,
            'bergantung_id' => $bergantung, 'v2_id' => $v2Id, 'user_id' => $u?->id, 'selesai_at' => $status === 'SELESAI' ? now() : null]);
    }

    /**
     * Nilai kolom PEKS3 sebelum diubah: dari cermin, ditimpa nilai operasi sebelumnya pada sasaran sama yang sudah
     * diterima PEKS3 tetapi belum kembali ke cermin (agar tidak dianggap konflik dengan isian dashboard sendiri).
     */
    protected function dasar(string $kunci, array $nilai, ?array $baris): array
    {
        $dasar = [];
        foreach (array_keys($nilai) as $kol) {
            $dasar[$kol] = $baris[$kol] ?? null;
        }
        $terakhir = Peks3Keluar::where('kunci', 'like', $kunci.'%')->where('status', 'DITERIMA')->latest('id')->first();
        foreach (($terakhir?->kirim['nilai'] ?? []) as $kol => $v) {
            array_key_exists($kol, $dasar) && $dasar[$kol] = $v;
        }

        return $dasar;
    }

    /** Tahun baris PEKS3: kebalikan koreksi tahun impor (config legacy.koreksi_tahun_kegiatan_detil). */
    protected function tahunPeks3(string $tabel, string $kolom, int $induk, int $tahun): int
    {
        $calon = array_merge([$tahun], array_keys(array_filter(config('psn_dashboard.legacy.koreksi_tahun_kegiatan_detil', []), fn ($v) => (int) $v === $tahun)));
        foreach ($calon as $th) {
            if ($this->baris($tabel, [$kolom => $induk, 'tahun' => $th])) {
                return (int) $th;
            }
        }

        return $tahun;
    }

    protected function psnLama(int $psnId): ?int
    {
        $id = DB::table('psn')->where('id', $psnId)->value('legacy_id');

        return $id ? (int) $id : null;
    }

    // ------------------------------------------------------------------ 2. pengiriman

    /** @return array{status:string, dikirim?:int, diterima?:int, konflik?:int, ditolak?:int, pesan?:string} */
    public function kirim(): array
    {
        $cfg = config('psn_dashboard.peks3.balik');
        if (! $cfg['aktif'] || ! $cfg['url'] || ! config('psn_dashboard.peks3.rahasia')) {
            return ['status' => 'NONAKTIF', 'pesan' => 'Kiriman balik belum aktif (PEKS3_BALIK_AKTIF, PEKS3_BALIK_URL, PEKS3_RAHASIA).'];
        }

        $siap = [];
        foreach (Peks3Keluar::where('status', 'ANTRE')->where(fn ($q) => $q->whereNull('berikutnya_at')->orWhere('berikutnya_at', '<=', now()))
            ->orderBy('id')->limit($cfg['maks_operasi'] * 2)->get() as $op) {
            if ($op->bergantung_id) {
                $dep = Peks3Keluar::find($op->bergantung_id);
                if (! $dep || in_array($dep->status, ['DITOLAK', 'DIABAIKAN'], true)) {
                    $op->update(['status' => 'DITOLAK', 'pesan' => 'Data induknya (dibuat di dashboard) tidak diterima PEKS3.']);

                    continue;
                }
                if (! $dep->peks3_id) {
                    continue; // tunggu induk diterima PEKS3
                }
                $k = $op->kirim;
                $k['baris_id'] = $dep->peks3_id;
                $op->kirim = $k;
            }
            $siap[] = $op;
            if (count($siap) >= $cfg['maks_operasi']) {
                break;
            }
        }
        if (! $siap) {
            return ['status' => 'KOSONG'];
        }

        $nama = User::whereIn('id', collect($siap)->pluck('user_id')->filter())->pluck('username', 'id');
        $body = json_encode(['perubahan' => array_map(fn ($op) => $op->kirim + ['id' => $op->id, 'pengguna' => $nama[$op->user_id] ?? 'dashboard'], $siap)], JSON_UNESCAPED_UNICODE);
        $t = (string) time();
        $kini = now();

        try {
            $res = Http::timeout($cfg['timeout_detik'])->withHeaders([
                'X-Client-Id' => $cfg['client_id'], 'X-Timestamp' => $t, 'Accept' => 'application/json',
                'X-Signature' => 'sha256='.hash_hmac('sha256', "{$t}.{$body}", config('psn_dashboard.peks3.rahasia')),
            ])->withBody($body, 'application/json')->post(rtrim($cfg['url'], '/').'/perubahan');
            $gagal = $res->successful() ? null : "HTTP {$res->status()}: ".mb_substr((string) ($res->json('message') ?? $res->body()), 0, 500);
        } catch (Throwable $e) {
            $res = null;
            $gagal = 'Tidak terhubung ke PEKS3: '.mb_substr($e->getMessage(), 0, 500);
        }

        if ($gagal) {
            foreach ($siap as $op) {
                $n = $op->percobaan + 1;
                $op->update(['percobaan' => $n, 'pesan' => $gagal, 'dikirim_at' => $kini, 'kirim' => $op->kirim,
                    'status' => $n >= $cfg['maks_percobaan'] ? 'GAGAL' : 'ANTRE', 'berikutnya_at' => $kini->copy()->addMinutes(min(60, 2 ** min($n, 6)))]);
            }

            return ['status' => 'GAGAL', 'dikirim' => count($siap), 'pesan' => $gagal];
        }

        $hasil = collect($res->json('data') ?? [])->keyBy('id');
        $hitung = ['DITERIMA' => 0, 'KONFLIK' => 0, 'DITOLAK' => 0];
        foreach ($siap as $op) {
            $h = $hasil[$op->id] ?? ['hasil' => 'DITOLAK', 'pesan' => 'Tidak ada jawaban untuk operasi ini.'];
            $st = in_array($h['hasil'] ?? null, ['DITERIMA', 'KONFLIK', 'DITOLAK'], true) ? $h['hasil'] : 'DITOLAK';
            $hitung[$st]++;
            $op->update(['status' => $st, 'kirim' => $op->kirim, 'dikirim_at' => $kini, 'percobaan' => $op->percobaan + 1, 'berikutnya_at' => null,
                'peks3_id' => $h['baris_id'] ?? $op->kirim['baris_id'] ?? $op->peks3_id, 'diterima_at' => $st === 'DITERIMA' ? $kini : null,
                'pesan' => $st === 'DITERIMA' ? ($h['pesan'] ?? null) : ($h['pesan'] ?? $st)]);
        }

        return ['status' => 'BERHASIL', 'dikirim' => count($siap), 'diterima' => $hitung['DITERIMA'], 'konflik' => $hitung['KONFLIK'], 'ditolak' => $hitung['DITOLAK']];
    }

    /** Kirim ulang operasi KONFLIK/DITOLAK/GAGAL. $timpa: abaikan pemeriksaan konflik (isian dashboard menimpa PEKS3). */
    public function kirimUlang(Peks3Keluar $op, bool $timpa): void
    {
        $k = $op->kirim;
        if ($timpa) {
            unset($k['dasar']);
        }
        $op->update(['kirim' => $k, 'status' => 'ANTRE', 'percobaan' => 0, 'berikutnya_at' => null, 'dikirim_at' => null, 'pesan' => null]);
    }

    /** Ikuti data PEKS3: isian dashboard tidak dikirim dan tidak dipulihkan lagi (kecuali catatan khusus dashboard). */
    public function abaikan(Peks3Keluar $op): void
    {
        $op->update(['status' => 'DIABAIKAN', 'selesai_at' => now()]);
    }

    // ------------------------------------------------------------------ 3. pemulihan setelah impor ulang

    /** @return array{selesai:int, dipulihkan:int} */
    public function pulihkan(): array
    {
        $hasil = ['selesai' => 0, 'dipulihkan' => 0];
        $baru = []; // id operasi tambah -> id baris dashboard hasil pemulihan/impor

        foreach (Peks3Keluar::where('status', 'DITERIMA')->orderBy('id')->get() as $op) {
            if ($this->tercermin($op)) {
                $op->update(['status' => 'SELESAI', 'selesai_at' => now()]);
                $hasil['selesai']++;
            }
        }

        foreach (Peks3Keluar::where('status', '<>', 'DIABAIKAN')->orderBy('id')->get() as $op) {
            $sementara = in_array($op->status, self::BELUM_TERCERMIN, true);
            $l = $op->lokal;
            $ok = match ($op->jenis) {
                'RO_BULAN' => $this->pulihkanRo($l, $sementara),
                'RISIKO_PANTAU' => $this->pulihkanPemantauan($l, $baru),
                'RISIKO_BARU', 'ISU_BARU' => $this->pulihkanTambah($op, $sementara, $baru),
                'ISU_UBAH' => $sementara && $this->pulihkanIsu($l, $baru),
                default => false,
            };
            $ok && $hasil['dipulihkan']++;
        }

        return $hasil;
    }

    protected function pulihkanRo(array $l, bool $sementara): bool
    {
        $kid = DB::table('kegiatan')->where('legacy_ref', $l['kegiatan_ref'])->whereNull('deleted_at')->value('id');
        $isi = array_filter($l['tetap'], fn ($v) => $v !== null) + ($sementara ? $l['nilai'] : []);
        if (! $kid || ! $isi) {
            return false;
        }
        $kunci = ['kegiatan_id' => $kid, 'tahun' => $l['tahun'], 'periode' => 'BULANAN', 'periode_ke' => $l['bulan']];
        DB::table('kegiatan_target')->where($kunci)->exists()
            ? DB::table('kegiatan_target')->where($kunci)->update($isi + ['updated_at' => now()])
            : DB::table('kegiatan_target')->insert($kunci + $isi + ['created_at' => now(), 'updated_at' => now()]);

        return true;
    }

    protected function pulihkanPemantauan(array $l, array $baru): bool
    {
        if (! $rid = $this->idDashboard('risiko', $l['risiko'], $baru)) {
            return false;
        }
        $kunci = ['risiko_id' => $rid, 'tanggal' => $l['tanggal'], 'sumber' => 'PELAPORAN'];
        DB::table('risiko_pemantauan')->updateOrInsert($kunci, $l['tetap'] + ['updated_at' => now(), 'created_at' => now()]);

        return true;
    }

    protected function pulihkanTambah(Peks3Keluar $op, bool $sementara, array &$baru): bool
    {
        $tabel = $op->jenis === 'RISIKO_BARU' ? 'risiko' : 'isu';
        // Sudah kembali dari PEKS3: tautkan ke baris hasil impor.
        if ($op->peks3_id && ($id = DB::table($tabel)->where('legacy_id', $op->peks3_id)->value('id'))) {
            $baru[$op->id] = (int) $id;
            $op->v2_id !== (int) $id && $op->update(['v2_id' => $id]);

            return false;
        }
        if (! $sementara || ! DB::table('psn')->where('id', $op->psn_id)->exists()) {
            return false;
        }
        $n = $op->lokal['nilai'];
        $isi = $tabel === 'risiko'
            ? ['uraian' => $n['uraian'], 'kategori_id' => $n['kategori'] ? DB::table('ref_kode')->where('tipe', 'RISK')->where('kode', $n['kategori'])->value('id') : null,
                'kemungkinan_harapan' => $n['kemungkinan_harapan'], 'dampak_harapan' => $n['dampak_harapan'], 'level_harapan' => $n['level_harapan'],
                'rencana_perlakuan' => $n['rencana_perlakuan'], 'penanggung_jawab' => $n['penanggung_jawab']]
            : $n;
        $id = DB::table($tabel)->insertGetId(['psn_id' => $op->psn_id] + $isi + ['created_by' => $op->user_id, 'created_at' => $op->created_at, 'updated_at' => now()]);
        $baru[$op->id] = $id;
        $op->update(['v2_id' => $id]);

        return true;
    }

    protected function pulihkanIsu(array $l, array $baru): bool
    {
        if (! $id = $this->idDashboard('isu', $l['isu'], $baru)) {
            return false;
        }
        DB::table('isu')->where('id', $id)->update($l['nilai'] + ['updated_at' => now()]);

        return true;
    }

    /** Rujukan "id:{legacy}" / "op:{operasi}" / "v2:{id}" -> id baris dashboard saat ini. */
    protected function idDashboard(string $tabel, string $ref, array $baru): ?int
    {
        [$jenis, $n] = explode(':', $ref, 2);

        return match ($jenis) {
            'id' => ($v = DB::table($tabel)->where('legacy_id', (int) $n)->value('id')) ? (int) $v : null,
            'op' => $baru[(int) $n] ?? null,
            default => DB::table($tabel)->where('id', (int) $n)->exists() ? (int) $n : null,
        };
    }

    /** Apakah isian yang sudah diterima PEKS3 sudah tampak di cermin (kembali lewat sinkron PEKS3 -> dashboard). */
    public function tercermin(Peks3Keluar $op): bool
    {
        $k = $op->kirim;
        if (! $k) {
            return true;
        }
        $baris = match ($k['aksi']) {
            'isi_bulan' => $this->baris($k['tabel'], [$k['induk']['kolom'] => $k['induk']['id'], 'tahun' => $k['induk']['tahun']]),
            default => $op->peks3_id ? $this->baris($k['tabel'], ['id' => $op->peks3_id]) : null,
        };
        if (! $baris) {
            return false;
        }
        if ($k['aksi'] === 'tambah') {
            return true;
        }
        foreach ($k['nilai'] as $kol => $v) {
            if (! array_key_exists($kol, $baris) || ! self::sama($baris[$kol], $v)) {
                return false;
            }
        }

        return true;
    }

    /** Pembanding nilai PEKS3 (kolom float/teks) dengan isian dashboard. */
    public static function sama($a, $b): bool
    {
        $kosong = fn ($v) => $v === null || (is_string($v) && trim($v) === '');
        if ($kosong($a) || $kosong($b)) {
            return $kosong($a) && $kosong($b);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) <= max(1e-6, abs((float) $b) * 1e-5);
        }
        $tgl = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) ? substr((string) $v, 0, 10) : trim((string) $v);

        return $tgl($a) === $tgl($b);
    }

    // ------------------------------------------------------------------ cermin

    protected function baris(string $tabel, array $where): ?array
    {
        if (! $c = $this->koneksiCermin()) {
            return null;
        }
        try {
            $r = $c->table($tabel)->where($where)->whereNull('deleted_at')->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }

        return $r ? (array) $r : null;
    }

    protected function koneksiCermin(): ?Connection
    {
        if (! $this->cerminDicek) {
            $this->cerminDicek = true;
            try {
                $c = DB::connection('legacy');
                $this->cermin = $c->getSchemaBuilder()->hasTable('peks3_cermin') ? $c : null;
            } catch (Throwable) {
                $this->cermin = null;
            }
        }

        return $this->cermin;
    }

    protected function num($v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }
}
