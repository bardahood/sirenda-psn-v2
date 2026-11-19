<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kit penerima Dashboard PSN -> SIRENDA PEKS3 (arah balik, docs/integrasi-peks3.md §9).
 * Salin ke app/Http/Controllers/ aplikasi SIRENDA PEKS3. Kompatibel PHP 7.4+ / Laravel 7+.
 *
 * Route (routes/api.php):
 *   Route::post('/dashboard/v1/perubahan', [\App\Http\Controllers\TerimaDariDashboardController::class, 'perubahan'])
 *       ->middleware('throttle:120,1');
 *   Route::get('/dashboard/v1/status', [\App\Http\Controllers\TerimaDariDashboardController::class, 'status']);
 *   (alamat lengkap = https://<domain-sirenda>/api/dashboard/v1, diisikan ke PEKS3_BALIK_URL di dashboard)
 *
 * config/services.php 'dashboard_peks3' (sama dengan kit pengirim) ditambah:
 *   'balik_client_id' => env('DASHBOARD_PEKS3_BALIK_CLIENT_ID', 'dashboard-psn'),
 *   'balik_aktif' => env('DASHBOARD_PEKS3_BALIK_AKTIF', false),
 *   'balik_koneksi' => env('DASHBOARD_PEKS3_BALIK_KONEKSI'),   // kosong = koneksi bawaan aplikasi
 *
 * Kunci HMAC sama dengan kit pengirim (DASHBOARD_PEKS3_RAHASIA). Setiap perubahan ditulis dengan updated_by
 * "dashboard:<username>" dan updated_at = sekarang, sehingga kit pengirim (dashboard:kirim) mengirimnya kembali
 * ke dashboard sebagai konfirmasi.
 */
class TerimaDariDashboardController extends Controller
{
    /** Tabel dan kolom yang boleh diubah dashboard. Kolom lain di dalam "nilai" ditolak. */
    const IZIN = [
        'psn_kegiatan_detil' => ['induk' => 'kegiatan_id', 'kolom' => '/^(target_persen|realisasi_persen|realisasi_anggaran)_([1-9]|1[0-2])$/'],
        'psn_kegiatan_detil_cp' => ['induk' => 'cp_id', 'kolom' => '/^(target_persen|realisasi_persen|realisasi_anggaran)_([1-9]|1[0-2])$/'],
        'psn_risiko' => ['kolom' => '/^(risiko|kategori|risiko_harapan|rencana|penanggung_jawab|risiko_residual|status)$/'],
        'psn_kebutuhan' => ['kolom' => '/^(jenis|point|deskripsi|status|pic_nama|tenggat|tindak_lanjut|tanggal_selesai)$/'],
    ];

    const MAKS_OPERASI = 50;

    const TOLERANSI_DETIK = 300;

    public function status(Request $r): JsonResponse
    {
        if ($galat = $this->autentikasi($r)) {
            return $galat;
        }

        return response()->json(['status' => 'success', 'data' => ['waktu' => date('c'), 'tabel' => array_keys(self::IZIN),
            'kolom_isu' => $this->db()->getSchemaBuilder()->hasColumn('psn_kebutuhan', 'status')]]);
    }

    public function perubahan(Request $r): JsonResponse
    {
        if ($galat = $this->autentikasi($r)) {
            return $galat;
        }
        $body = json_decode($r->getContent(), true);
        $ops = is_array($body) ? ($body['perubahan'] ?? null) : null;
        if (! is_array($ops) || $ops === [] || count($ops) > self::MAKS_OPERASI) {
            return response()->json(['status' => 'error', 'message' => 'Body harus {"perubahan": [...]} berisi 1 sampai '.self::MAKS_OPERASI.' operasi.'], 422);
        }

        $hasil = [];
        foreach ($ops as $op) {
            try {
                $hasil[] = $this->db()->transaction(function () use ($op) {
                    return $this->terapkan(is_array($op) ? $op : []);
                });
            } catch (Throwable $e) {
                $hasil[] = ['id' => $op['id'] ?? null, 'hasil' => 'DITOLAK', 'pesan' => 'Gagal disimpan: '.mb_substr($e->getMessage(), 0, 300)];
            }
        }

        return response()->json(['status' => 'success', 'data' => $hasil]);
    }

    /** Satu operasi: isi_bulan | ubah | tambah. */
    protected function terapkan(array $op): array
    {
        $id = $op['id'] ?? null;
        $tolak = function ($pesan) use ($id) {
            return ['id' => $id, 'hasil' => 'DITOLAK', 'pesan' => $pesan];
        };
        $tabel = (string) ($op['tabel'] ?? '');
        $nilai = $op['nilai'] ?? null;
        $psnId = (int) ($op['psn_id'] ?? 0);
        if (! isset(self::IZIN[$tabel]) || ! is_array($nilai) || $nilai === [] || $psnId < 1) {
            return $tolak('Tabel, psn_id, atau nilai tidak valid.');
        }
        foreach (array_keys($nilai) as $kol) {
            if (! preg_match(self::IZIN[$tabel]['kolom'], (string) $kol)) {
                return $tolak("Kolom {$kol} tidak boleh diubah dari dashboard.");
            }
        }
        $db = $this->db();
        if (! $db->table('psn')->where('id', $psnId)->exists()) {
            return $tolak("PSN {$psnId} tidak ditemukan.");
        }

        // Kolom yang belum ada di basis data PEKS3 (mis. status isu sebelum migrasi §9.5) dilewati dan dilaporkan.
        $ada = array_flip($db->getSchemaBuilder()->getColumnListing($tabel));
        $dilewati = array_values(array_diff(array_keys($nilai), array_keys($ada)));
        $nilai = array_intersect_key($nilai, $ada);
        if ($nilai === []) {
            return $tolak('Kolom '.implode(', ', $dilewati).' belum ada di tabel '.$tabel.' SIRENDA PEKS3.');
        }
        $jejak = ['updated_by' => mb_substr('dashboard:'.($op['pengguna'] ?? ''), 0, 50), 'updated_at' => date('Y-m-d H:i:s')];
        $pesan = $dilewati ? 'Kolom belum ada di PEKS3, dilewati: '.implode(', ', $dilewati) : null;

        $aksi = $op['aksi'] ?? '';
        if ($aksi === 'tambah') {
            $baris = $nilai + ['psn_id' => $psnId, 'uuid' => (string) Str::uuid(), 'created_by' => $jejak['updated_by'], 'created_at' => $jejak['updated_at']] + $jejak;
            $baru = $db->table($tabel)->insertGetId($baris);

            return ['id' => $id, 'hasil' => 'DITERIMA', 'baris_id' => $baru, 'pesan' => $pesan];
        }

        if ($aksi === 'isi_bulan') {
            $induk = $op['induk'] ?? [];
            $kolomInduk = self::IZIN[$tabel]['induk'] ?? null;
            if (! $kolomInduk || ($induk['kolom'] ?? null) !== $kolomInduk || empty($induk['id']) || empty($induk['tahun'])) {
                return $tolak('Data induk KP/RO tidak valid.');
            }
            $tabelInduk = $tabel === 'psn_kegiatan_detil' ? 'psn_kegiatan' : 'psn_kegiatan_cp';
            if (! $db->table($tabelInduk)->where('id', (int) $induk['id'])->where('psn_id', $psnId)->whereNull('deleted_at')->exists()) {
                return $tolak("KP/RO {$tabelInduk}#{$induk['id']} tidak ditemukan pada PSN {$psnId}.");
            }
            $baris = $db->table($tabel)->where($kolomInduk, (int) $induk['id'])->where('tahun', (int) $induk['tahun'])->whereNull('deleted_at')
                ->orderByDesc('id')->lockForUpdate()->first();
            if (! $baris) {
                $baru = $db->table($tabel)->insertGetId($nilai + [$kolomInduk => (int) $induk['id'], 'psn_id' => $psnId, 'tahun' => (int) $induk['tahun'],
                    'uuid' => (string) Str::uuid(), 'created_by' => $jejak['updated_by'], 'created_at' => $jejak['updated_at']] + $jejak);

                return ['id' => $id, 'hasil' => 'DITERIMA', 'baris_id' => $baru, 'pesan' => $pesan];
            }
        } elseif ($aksi === 'ubah') {
            $barisId = (int) ($op['baris_id'] ?? 0);
            $baris = $db->table($tabel)->where('id', $barisId)->where('psn_id', $psnId)->lockForUpdate()->first();
            if (! $baris || $baris->deleted_at !== null) {
                return $tolak("Baris {$tabel}#{$barisId} tidak ditemukan atau sudah dihapus di PEKS3.");
            }
        } else {
            return $tolak('Aksi tidak dikenal.');
        }

        // Deteksi konflik per kolom: nilai saat ini harus sama dengan "dasar" (nilai yang dilihat dashboard sebelum diubah).
        if (isset($op['dasar']) && is_array($op['dasar'])) {
            $beda = [];
            foreach ($op['dasar'] as $kol => $v) {
                if (array_key_exists($kol, $nilai) && property_exists($baris, $kol) && ! $this->sama($baris->{$kol}, $v)
                    && ! $this->sama($baris->{$kol}, $nilai[$kol])) {
                    $beda[] = "{$kol}: PEKS3 \"".mb_substr((string) $baris->{$kol}, 0, 60).'"';
                }
            }
            if ($beda) {
                return ['id' => $id, 'hasil' => 'KONFLIK', 'baris_id' => $baris->id,
                    'pesan' => 'Sudah diubah di SIRENDA PEKS3 setelah diisi di dashboard. '.implode('; ', $beda)];
            }
        }
        $db->table($tabel)->where('id', $baris->id)->update($nilai + $jejak);

        return ['id' => $id, 'hasil' => 'DITERIMA', 'baris_id' => $baris->id, 'pesan' => $pesan];
    }

    protected function sama($a, $b): bool
    {
        $kosong = function ($v) {
            return $v === null || (is_string($v) && trim($v) === '');
        };
        if ($kosong($a) || $kosong($b)) {
            return $kosong($a) && $kosong($b);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) <= max(1e-6, abs((float) $b) * 1e-5);
        }
        $t = function ($v) {
            return preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) ? substr((string) $v, 0, 10) : trim((string) $v);
        };

        return $t($a) === $t($b);
    }

    /** Verifikasi X-Client-Id, X-Timestamp (maks. 5 menit), X-Signature HMAC-SHA256, dan anti-replay. */
    protected function autentikasi(Request $r): ?JsonResponse
    {
        $cfg = config('services.dashboard_peks3', []);
        if (! ($cfg['balik_aktif'] ?? false) || strlen((string) ($cfg['rahasia'] ?? '')) < 32) {
            return response()->json(['status' => 'error', 'message' => 'Penerimaan dari dashboard belum diaktifkan.'], 503);
        }
        $t = (string) $r->header('X-Timestamp');
        $sig = (string) $r->header('X-Signature');
        $harus = 'sha256='.hash_hmac('sha256', $t.'.'.$r->getContent(), (string) $cfg['rahasia']);
        if ($r->header('X-Client-Id') !== ($cfg['balik_client_id'] ?? 'dashboard-psn') || ! ctype_digit($t) || ! hash_equals($harus, $sig)) {
            return response()->json(['status' => 'error', 'message' => 'Client ID atau tanda tangan tidak valid.'], 401);
        }
        if (abs(time() - (int) $t) > self::TOLERANSI_DETIK) {
            return response()->json(['status' => 'error', 'message' => 'Stempel waktu kedaluwarsa; periksa jam server (NTP).'], 401);
        }
        if (! Cache::add('dashboard-balik:'.hash('sha256', $sig), 1, self::TOLERANSI_DETIK * 2)) {
            return response()->json(['status' => 'error', 'message' => 'Permintaan yang sama sudah diterima.'], 409);
        }

        return null;
    }

    protected function db()
    {
        $koneksi = config('services.dashboard_peks3.balik_koneksi');

        return $koneksi ? DB::connection($koneksi) : DB::connection();
    }
}
