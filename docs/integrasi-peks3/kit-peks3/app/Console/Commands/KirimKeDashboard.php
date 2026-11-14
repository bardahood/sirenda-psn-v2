<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Kit pengirim SIRENDA PEKS3 -> Dashboard PSN (Prosedur API, docs/integrasi-peks3.md).
 * Salin berkas ini ke app/Console/Commands/ aplikasi SIRENDA PEKS3. Kompatibel PHP 7.4+ / Laravel 7+.
 *
 *   php artisan dashboard:kirim                      PSN yang berubah sejak kiriman terakhir (cron tiap menit)
 *   php artisan dashboard:kirim --psn=471 --psn=12   PSN tertentu (mis. dipanggil setelah simpan)
 *   php artisan dashboard:kirim --semua --rekonsiliasi   seluruh PSN + hapus PSN yang hilang (cron malam)
 *   php artisan dashboard:kirim --referensi          master, users (tanpa kata sandi), psn_2027, peta (cron mingguan)
 *   php artisan dashboard:kirim --cek                uji koneksi & tanda tangan (GET /status)
 *
 * config/services.php: 'dashboard_peks3' => ['url' => env('DASHBOARD_PEKS3_URL'), 'client_id' => env('DASHBOARD_PEKS3_CLIENT_ID', 'sirenda-peks3'),
 *                                         'rahasia' => env('DASHBOARD_PEKS3_RAHASIA')],
 * .env: DASHBOARD_PEKS3_URL=https://dashboard.sirenda-psn.id/api/integrasi/peks3/v1
 *       DASHBOARD_PEKS3_CLIENT_ID=sirenda-peks3
 *       DASHBOARD_PEKS3_RAHASIA=<kunci HMAC yang sama dengan PEKS3_RAHASIA di dashboard>
 */
class KirimKeDashboard extends Command
{
    protected $signature = 'dashboard:kirim
        {--psn=* : psn.id tertentu}
        {--semua : Kirim seluruh PSN}
        {--sejak= : Kirim PSN yang berubah sejak waktu ini (Y-m-d H:i:s); bawaan: titik simpan terakhir}
        {--referensi : Kirim tabel referensi}
        {--rekonsiliasi : Kirim daftar seluruh psn.id agar PSN yang dihapus ikut terhapus di dashboard}
        {--cek : Hanya uji koneksi}';

    protected $description = 'Kirim data PSN ke Dashboard PSN melalui API integrasi/peks3/v1';

    /** Tabel per PSN => kolom kunci PSN (harus sama dengan config dashboard psn_dashboard.peks3.tabel_psn). */
    const TABEL_PSN = [
        'psn' => 'id', 'psn_log' => 'id',
        'lokasi_psn' => 'psn_id', 'unit_pengampu' => 'psn_id', 'psn_penanggung_jawab' => 'psn_id', 'psn_pendanaan' => 'psn_id',
        'psn_sdgs' => 'psn_id', 'psn_item' => 'psn_id', 'target_psn' => 'psn_id', 'dukungan_psn' => 'psn_id',
        'psn_fasilitas' => 'psn_id', 'psn_kebutuhan' => 'psn_id', 'psn_dokumentasi' => 'psn_id', 'psn_stakeholder' => 'psn_id',
        'psn_indikator' => 'psn_id', 'psn_penerima' => 'psn_id', 'psn_trisula' => 'psn_id', 'psn_trisula_triwulan' => 'psn_id',
        'psn_kegiatan' => 'psn_id', 'psn_kegiatan_detil' => 'psn_id', 'psn_kegiatan_cp' => 'psn_id', 'psn_kegiatan_detil_cp' => 'psn_id',
        'psn_risiko' => 'psn_id', 'psn_pj_perlakuan' => 'psn_id', 'psn_regulasi' => 'psn_id', 'monev_header' => 'psn_id',
        'psn_risiko_log' => 'psn_id', 'psn_regulasi_log' => 'psn_id', 'psn_kegiatan_log' => 'psn_id', 'psn_indikator_log' => 'psn_id',
        'psn_penerima_log' => 'psn_id', 'psn_trisula_log' => 'psn_id', 'psn_item_log' => 'psn_id', 'psn_kebutuhan_log' => 'psn_id',
        'psn_stakeholder_log' => 'psn_id', 'psn_dokumentasi_log' => 'psn_id', 'psn_fasilitas_log' => 'psn_id', 'psn_sdgs_log' => 'psn_id',
        'psn_pj_perlakuan_log' => 'psn_id',
    ];

    const TABEL_MONEV = ['monev_detil', 'monev_kelembagaan', 'monev_capaian', 'monev_anggaran', 'monev_risiko', 'monev_regulasi', 'monev_dokumentasi', 'monev_evaluasi'];

    const TABEL_REFERENSI = ['master' => ['tipe_tabel', 'kode_tabel'], 'users' => ['username'], 'psn_2027' => ['id'], 'peta' => ['code', 'hc-key']];

    const KOLOM_RAHASIA = ['password', 'token', 'remember_token'];

    const PAKET_PER_PERMINTAAN = 20;

    /** Tumpang-tindih jendela waktu agar perubahan di batas detik tidak terlewat (dashboard mengabaikan isi yang sama). */
    const TUMPANG_DETIK = 120;

    protected $kolom = [];

    public function handle()
    {
        foreach (['url', 'client_id', 'rahasia'] as $k) {
            if (! $this->cfg($k)) {
                $this->error('DASHBOARD_PEKS3_'.strtoupper($k).' belum diisi di .env');

                return 1;
            }
        }

        try {
            if ($this->option('cek')) {
                $r = $this->kirim('GET', '/status', '');
                $this->info('Terhubung. '.json_encode($r['data'] ?? $r));

                return 0;
            }
            if ($this->option('referensi')) {
                $this->kirimReferensi();
            }

            // Titik simpan = stempel waktu terbesar di data PEKS3 sendiri (bukan jam server), agar tidak
            // bergantung pada zona waktu PHP vs MySQL. Diambil SEBELUM membaca perubahan.
            $tanda = $this->waktuTerbaru();
            if ($this->option('semua')) {
                $id = DB::table('psn')->orderBy('id')->pluck('id')->all();
            } elseif ($this->option('psn')) {
                $id = array_map('intval', $this->option('psn'));
            } else {
                $sejak = $this->option('sejak') ?: $this->titik();
                $id = $sejak ? $this->psnBerubah($this->kurangi($sejak, self::TUMPANG_DETIK)) : DB::table('psn')->orderBy('id')->pluck('id')->all();
            }
            $this->kirimPsn($id);

            if ($this->option('rekonsiliasi')) {
                $r = $this->kirim('POST', '/rekonsiliasi', json_encode(['psn_id' => DB::table('psn')->pluck('id')->map(function ($v) {
                    return (int) $v;
                })->all()]));
                $this->info('Rekonsiliasi: '.count($r['data']['dihapus'] ?? []).' PSN dihapus di dashboard.');
            }
            if (! $this->option('psn') && ! $this->option('sejak') && $tanda) {
                $this->simpanTitik($tanda);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    protected function kirimPsn(array $id)
    {
        $id = array_values(array_unique(array_filter($id)));
        if (! $id) {
            $this->line('Tidak ada PSN yang berubah.');

            return;
        }
        $hitung = [];
        foreach (array_chunk($id, self::PAKET_PER_PERMINTAAN) as $kelompok) {
            $paket = array_map(function ($i) {
                return $this->paket((int) $i);
            }, $kelompok);
            $r = $this->kirim('POST', '/psn', json_encode(['paket' => $paket], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            foreach ($r['data'] ?? [] as $h) {
                $hitung[$h['hasil']] = ($hitung[$h['hasil']] ?? 0) + 1;
                if ($h['hasil'] === 'DITOLAK') {
                    $this->warn("PSN {$h['psn_id']} ditolak: {$h['pesan']}");
                }
            }
        }
        $this->info(count($id).' PSN dikirim: '.json_encode($hitung));
    }

    /** Paket lengkap satu PSN: seluruh baris PSN tersebut di setiap tabel, apa adanya (termasuk yang soft delete). */
    protected function paket(int $id): array
    {
        if (! DB::table('psn')->where('id', $id)->exists()) {
            return ['psn_id' => $id, 'versi' => $this->versi(), 'dihapus' => true];
        }
        $tabel = [];
        foreach (self::TABEL_PSN as $t => $kunci) {
            if (Schema::hasTable($t)) {
                $tabel[$t] = $this->baris(DB::table($t)->where($kunci, $id)->get());
            }
        }
        $monev = array_column($tabel['monev_header'] ?? [], 'id');
        foreach (self::TABEL_MONEV as $t) {
            if (Schema::hasTable($t)) {
                $tabel[$t] = $monev ? $this->baris(DB::table($t)->whereIn('monev_id', $monev)->get()) : [];
            }
        }

        return ['psn_id' => $id, 'versi' => $this->versi(), 'tabel' => $tabel];
    }

    /** psn.id yang barisnya (di tabel mana pun) dibuat/diubah/dihapus sejak $sejak. */
    protected function psnBerubah(string $sejak): array
    {
        $id = [];
        foreach (self::TABEL_PSN as $t => $kunci) {
            if (! Schema::hasTable($t) || ! ($w = $this->kolomWaktu($t))) {
                continue;
            }
            $q = DB::table($t)->where(function ($q) use ($w, $sejak) {
                foreach ($w as $k) {
                    $q->orWhere($k, '>=', $sejak);
                }
            });
            $id = array_merge($id, $q->distinct()->pluck($kunci)->all());
        }
        foreach (self::TABEL_MONEV as $t) {
            if (Schema::hasTable($t) && ($w = $this->kolomWaktu($t))) {
                $id = array_merge($id, DB::table($t)->join('monev_header', 'monev_header.id', '=', "{$t}.monev_id")
                    ->where(function ($q) use ($w, $sejak, $t) {
                        foreach ($w as $k) {
                            $q->orWhere("{$t}.{$k}", '>=', $sejak);
                        }
                    })->distinct()->pluck('monev_header.psn_id')->all());
            }
        }

        return array_values(array_unique(array_map('intval', $id)));
    }

    /** Stempel waktu terbesar (created_at/updated_at/deleted_at) di seluruh tabel PSN. */
    protected function waktuTerbaru()
    {
        $maks = null;
        foreach (array_merge(array_keys(self::TABEL_PSN), self::TABEL_MONEV) as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            foreach ($this->kolomWaktu($t) as $k) {
                $v = DB::table($t)->max($k);
                if ($v && (! $maks || strcmp((string) $v, $maks) > 0)) {
                    $maks = (string) $v;
                }
            }
        }

        return $maks;
    }

    protected function kurangi(string $waktu, int $detik): string
    {
        $t = strtotime($waktu);

        return $t ? date('Y-m-d H:i:s', $t - $detik) : $waktu;
    }

    protected function kirimReferensi()
    {
        foreach (self::TABEL_REFERENSI as $t => $kunci) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            $q = DB::table($t);
            foreach ($kunci as $k) {
                $q->orderBy($k);
            }
            $n = 0;
            $q->chunk(2000, function ($rows) use ($t, &$n) {
                $baris = array_map(function ($r) {
                    return array_diff_key($r, array_flip(self::KOLOM_RAHASIA));
                }, $this->baris($rows));
                $this->kirim('POST', '/referensi', json_encode(['tabel' => $t, 'baris' => $baris], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                $n += count($baris);
            });
            $this->info("Referensi {$t}: {$n} baris dikirim.");
        }
    }

    /** Kirim bertanda tangan HMAC-SHA256 dengan 3x percobaan. 409 (replay) dianggap sudah diterima. */
    protected function kirim(string $metode, string $path, string $body): array
    {
        $galat = '';
        for ($coba = 1; $coba <= 3; $coba++) {
            $t = (string) time();
            $ch = curl_init(rtrim($this->cfg('url'), '/').$path);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $metode,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json', 'Accept: application/json',
                    'X-Client-Id: '.$this->cfg('client_id'),
                    'X-Timestamp: '.$t,
                    'X-Signature: sha256='.hash_hmac('sha256', $t.'.'.$body, $this->cfg('rahasia')),
                ],
            ]);
            if ($metode !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $isi = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $galat = $isi === false ? curl_error($ch) : "HTTP {$status}: ".substr((string) $isi, 0, 500);
            curl_close($ch);

            if ($status >= 200 && $status < 300) {
                return json_decode((string) $isi, true) ?: [];
            }
            if ($status === 409) {
                return [];
            }
            if (in_array($status, [401, 403, 422], true)) {
                break; // kesalahan konfigurasi/data: tidak diulang
            }
            sleep($coba * 5);
        }
        throw new RuntimeException('Gagal mengirim ke dashboard: '.$galat);
    }

    protected function baris($rows): array
    {
        return array_map(function ($r) {
            return (array) $r;
        }, $rows instanceof Collection ? $rows->all() : (array) $rows);
    }

    protected function kolomWaktu(string $t): array
    {
        if (! isset($this->kolom[$t])) {
            $this->kolom[$t] = array_values(array_intersect(['created_at', 'updated_at', 'deleted_at'], Schema::getColumnListing($t)));
        }

        return $this->kolom[$t];
    }

    protected function versi(): string
    {
        // Mikrodetik epoch: urutan kiriman. Dashboard mengabaikan paket yang lebih lama dari yang tersimpan.
        $m = explode(' ', microtime());

        return $m[1].str_pad((string) min(999999, (int) floor($m[0] * 1e6)), 6, '0', STR_PAD_LEFT);
    }

    /** Baca config/services.php (aman saat config:cache), lalu .env sebagai cadangan. */
    protected function cfg(string $k)
    {
        return config('services.dashboard_peks3.'.$k) ?: env('DASHBOARD_PEKS3_'.strtoupper($k), $k === 'client_id' ? 'sirenda-peks3' : null);
    }

    protected function titik()
    {
        $f = storage_path('app/dashboard-sinkron.json');

        return is_file($f) ? (json_decode((string) file_get_contents($f), true)['sejak'] ?? null) : null;
    }

    protected function simpanTitik(string $sejak)
    {
        file_put_contents(storage_path('app/dashboard-sinkron.json'), json_encode(['sejak' => $sejak, 'dikirim_at' => date('c')]));
    }
}
