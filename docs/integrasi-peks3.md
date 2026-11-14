# Prosedur API Sinkronisasi Data SIRENDA PEKS3 ke Dashboard PSN

Versi 1.0, 14 November 2026

## 1. Latar belakang dan pembagian peran

Data PSN dikelola oleh tiga aplikasi:

| Aplikasi | Pengelola | Peran dalam alur data |
|---|---|---|
| Aplikasi Perencanaan (SIF/Portal PSN) | Dit. PSI | Pengusulan PSN baru. Dashboard mengambil usulan lewat API SIF (lihat `docs/integrasi-psi.md`). |
| SIRENDA | Direktorat PEKS3 | Penginputan dan pemutakhiran data profil PSN: kegiatan/KP-RO, critical path, risiko, isu, regulasi, indikator, pendanaan, monev. **Sumber kebenaran data PSN.** |
| Dashboard PSN | Konsultan | Menampilkan data dari SIRENDA PEKS3 dan PSI, serta menyelenggarakan penilaian usulan. |

Prosedur ini mengatur agar setiap data yang diinput di SIRENDA PEKS3 otomatis diperbarui di Dashboard, tanpa ekspor/impor file SQL manual.

## 2. Gambaran alur

```
SIRENDA PEKS3                                   Dashboard PSN
──────────────                                  ──────────────────────────────────────────────
Operator menyimpan data
        │
php artisan dashboard:kirim  (cron tiap menit)
  1. cari PSN yang berubah (created_at /
     updated_at / deleted_at > titik terakhir)
  2. susun PAKET LENGKAP per PSN
  3. tanda tangani HMAC-SHA256 ───── HTTPS ──▶  POST /api/integrasi/peks3/v1/psn
                                                  4. verifikasi tanda tangan & waktu
                                                  5. ganti baris PSN tsb di BASIS DATA CERMIN
                                                  6. tandai "ada perubahan tertunda"
                                                ───────────────────────────────────────────────
                                                php artisan peks3:terapkan (scheduler tiap menit)
                                                  7. bila tidak ada kiriman baru ≥ 60 detik
                                                     (atau sudah menunggu ≥ 10 menit):
                                                     DALAM SATU TRANSAKSI
                                                       hapus data impor → impor ulang dari cermin
                                                       → bangun ulang snapshot semua cut-off
                                                  8. COMMIT, batalkan cache dashboard
```

Pokok rancangan:

- **Paket lengkap per PSN.** Setiap kiriman berisi seluruh baris milik satu PSN di semua tabel terkait, apa adanya dari basis data SIRENDA PEKS3, termasuk baris yang di-soft-delete. Dashboard mengganti seluruh baris PSN tersebut. Akibatnya penghapusan, perubahan, dan penambahan baris semuanya ikut terbawa tanpa perlu kode khusus per form.
- **Basis data cermin.** Dashboard menyimpan salinan tabel SIRENDA PEKS3 di basis data terpisah di server dashboard (koneksi `legacy`). Pemetaan ke struktur dashboard memakai importer yang sudah teruji untuk impor dump SQL selama ini, sehingga angka dashboard identik dengan hasil impor manual (uji akurasi 0 selisih).
- **Atomik.** Selama penerapan berlangsung (±15–60 detik), pengguna dashboard tetap melihat data sebelumnya; data baru tampil serentak setelah selesai. Tidak ada jeda data kosong.
- **Usulan dan penilaian tidak tersentuh.** Data milik dashboard (usulan PSI, penilaian, pleno, rekomendasi, akun dan kata sandi) tidak ikut dihapus atau ditimpa.
- **ID PSN stabil.** Tautan `/proyek/{id}` di dashboard tidak berubah antar-sinkronisasi.

Waktu tayang: perubahan tampil di dashboard sekitar **1–3 menit** setelah disimpan di SIRENDA PEKS3 (1 menit cron pengirim + 60 detik jeda + durasi penerapan). Bila operator terus menyimpan tanpa henti, penerapan tetap dilakukan paling lambat 10 menit sekali.

## 3. Keamanan

| Aspek | Ketentuan |
|---|---|
| Transport | HTTPS wajib. |
| Identitas | Header `X-Client-Id: sirenda-peks3`. |
| Kunci bersama | `PEKS3_RAHASIA` (dashboard) = `DASHBOARD_PEKS3_RAHASIA` (PEKS3). Minimal 32 karakter acak; buat dengan `php -r "echo bin2hex(random_bytes(32));"`. Disampaikan lewat kanal tertutup, tidak lewat email/chat grup, dan tidak pernah di-commit ke git. |
| Tanda tangan | `X-Signature: sha256=` + hex(HMAC-SHA256(kunci, `X-Timestamp` + `"."` + body mentah)). Body GET = string kosong. |
| Kedaluwarsa | `X-Timestamp` (detik epoch) harus berselisih ≤ 300 detik dari jam server dashboard. Jam kedua server wajib tersinkron (NTP). |
| Anti-replay | Tanda tangan yang sama hanya diterima sekali (409 bila diulang). |
| Pembatasan IP | Opsional: `PEKS3_IP_DIIZINKAN` berisi IP publik server SIRENDA PEKS3. |
| Data sensitif | Kolom `users.password`, `token`, `remember_token` tidak dikirim oleh kit dan dibuang oleh dashboard bila terkirim. |
| Basis data cermin | Dashboard hanya menulis ke basis data yang sudah ditandai dengan `php artisan peks3:siapkan-cermin` (tabel penanda `peks3_cermin`), sehingga salah konfigurasi tidak dapat menulis ke basis data SIRENDA PEKS3 yang asli. |

Contoh menyusun tanda tangan (PHP):

```php
$body = json_encode($data, JSON_UNESCAPED_UNICODE);
$t    = (string) time();
$sig  = 'sha256=' . hash_hmac('sha256', $t . '.' . $body, $rahasia);
// header: X-Client-Id, X-Timestamp: $t, X-Signature: $sig, Content-Type: application/json
```

## 4. Spesifikasi endpoint

Alamat dasar: `https://dashboard.sirenda-psn.id/api/integrasi/peks3/v1`. Batas: 240 permintaan per menit.

### 4.1 `POST /psn`: kirim paket PSN

Body: satu paket, atau `{"paket": [ ... ]}` berisi maksimal 50 paket.

```json
{
  "paket": [
    {
      "psn_id": 471,
      "versi": "1794021234123456",
      "tabel": {
        "psn":           [ { "id": 471, "kode_psn": "M.09-2026.0-83", "proyek_psn": "Tanggul Pantai", "kode_krisna": "", "...": "..." } ],
        "psn_kegiatan":  [ { "id": 1201, "psn_id": 471, "kegiatan": "...", "realisasi_target_1_2026": "55", "...": "..." } ],
        "psn_kebutuhan": [ ],
        "monev_header":  [ { "id": 3, "psn_id": 471, "...": "..." } ],
        "monev_detil":   [ { "id": 10, "monev_id": 3, "...": "..." } ]
      }
    },
    { "psn_id": 12, "versi": "1794021234123999", "dihapus": true }
  ]
}
```

| Field | Wajib | Keterangan |
|---|---|---|
| `psn_id` | ya | `psn.id` di SIRENDA PEKS3. |
| `versi` | ya | Urutan kiriman: mikrodetik/milidetik/detik epoch atau tanggal ISO 8601. Paket dengan versi lebih lama dari yang tersimpan diabaikan (`VERSI_LAMA`). |
| `tabel` | ya, kecuali `dihapus` | Objek nama tabel → daftar baris (objek kolom → nilai, nama kolom persis seperti basis data SIRENDA PEKS3). `tabel.psn` wajib berisi tepat satu baris. |
| `dihapus` | tidak | `true` bila PSN dihapus fisik di SIRENDA PEKS3: seluruh barisnya dihapus dari cermin. |

Aturan isi paket:

1. **Tabel yang dikirim diganti seluruhnya** untuk PSN tersebut. Daftar kosong `[]` berarti PSN itu tidak lagi memiliki baris di tabel tersebut. Tabel yang tidak disebut tidak diubah. Kit selalu mengirim semua tabel.
2. Setiap baris tabel per PSN wajib memiliki `psn_id` (untuk `psn` dan `psn_log`: `id`) sama dengan `psn_id` paket; tabel anak monev wajib merujuk `monev_header` yang ada di paket yang sama. Bila tidak, paket ditolak tanpa ada data yang ditulis.
3. Kirim baris apa adanya, termasuk baris yang di-soft-delete (`deleted_at` terisi) dan string kosong. Dashboard yang menentukan baris mana yang ditampilkan.
4. Kolom yang tidak dikenal (kolom baru di PEKS3) diabaikan dengan peringatan; tabel yang tidak dikenal juga diabaikan. Kabari konsultan bila ada kolom/tabel baru yang perlu tampil.
5. Isi paket yang sama persis dengan kiriman sebelumnya tidak memicu penerapan (`TETAP`).

Tabel per PSN (kunci `psn_id`, kecuali `psn` dan `psn_log` memakai `id`):

`psn`, `psn_log`, `lokasi_psn`, `unit_pengampu`, `psn_penanggung_jawab`, `psn_pendanaan`, `psn_sdgs`, `psn_item`, `target_psn`, `dukungan_psn`, `psn_fasilitas`, `psn_kebutuhan`, `psn_dokumentasi`, `psn_stakeholder`, `psn_indikator`, `psn_penerima`, `psn_trisula`, `psn_trisula_triwulan`, `psn_kegiatan`, `psn_kegiatan_detil`, `psn_kegiatan_cp`, `psn_kegiatan_detil_cp`, `psn_risiko`, `psn_pj_perlakuan`, `psn_regulasi`, `monev_header`, serta tabel riwayat `psn_risiko_log`, `psn_regulasi_log`, `psn_kegiatan_log`, `psn_indikator_log`, `psn_penerima_log`, `psn_trisula_log`, `psn_item_log`, `psn_kebutuhan_log`, `psn_stakeholder_log`, `psn_dokumentasi_log`, `psn_fasilitas_log`, `psn_sdgs_log`, `psn_pj_perlakuan_log`.

Tabel anak monev (kunci `monev_id`): `monev_detil`, `monev_kelembagaan`, `monev_capaian`, `monev_anggaran`, `monev_risiko`, `monev_regulasi`, `monev_dokumentasi`, `monev_evaluasi`.

Respons `202`:

```json
{ "status": "success", "message": "2 paket diproses, 0 ditolak. Perubahan tampil di dashboard dalam beberapa menit.",
  "data": [ { "psn_id": 471, "hasil": "DITERIMA", "baris": { "psn": 1, "psn_kegiatan": 2 } },
            { "psn_id": 12, "hasil": "DITERIMA" } ] }
```

`hasil` per paket: `DITERIMA`, `TETAP` (isi sama), `VERSI_LAMA` (diabaikan), `DITOLAK` (dengan `pesan`). Bila sebagian paket ditolak, `status` = `sebagian` (HTTP 202); bila semua ditolak, HTTP 422.

### 4.2 `POST /referensi`: tabel referensi

```json
{ "tabel": "master", "baris": [ { "tipe_tabel": "KLRH", "kode_tabel": "3201010001", "...": "..." } ] }
```

Tabel: `master` (kunci `tipe_tabel`,`kode_tabel`), `users` (kunci `username`, tanpa kata sandi), `psn_2027` (`id`), `peta` (`code`,`hc-key`). Upsert per kunci, tanpa penghapusan; maksimal 5.000 baris per permintaan. Dikirim saat ada perubahan referensi atau terjadwal mingguan.

### 4.3 `POST /rekonsiliasi`: PSN yang dihapus fisik

```json
{ "psn_id": [1, 2, 3, "... seluruh psn.id yang masih ada di SIRENDA PEKS3"] }
```

PSN yang ada di cermin tetapi tidak ada di daftar akan dihapus. Pengaman: ditolak (422) bila akan menghapus lebih dari 20% PSN, karena kemungkinan besar daftarnya terpotong.

### 4.4 `GET /status`

Status antrean: `tertunda`, `tertunda_sejak`, `perubahan_terakhir_at`, `diterapkan_at`, `durasi_detik`, `galat_terakhir`, `psn_di_cermin`, `psn_di_dashboard`. Dipakai `dashboard:kirim --cek` untuk menguji koneksi.

### 4.5 Kode status HTTP

| Kode | Arti | Tindakan pengirim |
|---|---|---|
| 202 | Diterima (lihat `hasil` per paket) | Selesai. |
| 401 | Client ID/tanda tangan salah, atau jam server berselisih > 5 menit | Periksa kunci dan NTP; jangan diulang otomatis. |
| 403 | IP tidak diizinkan | Minta konsultan menambah IP. |
| 409 | Permintaan yang sama sudah diterima | Anggap berhasil. |
| 422 | Isi paket tidak valid | Baca `message`/`pesan`; perbaiki data. |
| 429 | Melebihi batas permintaan | Ulangi setelah jeda. |
| 503 | Sinkron belum aktif / basis data cermin belum disiapkan | Hubungi konsultan. |
| 5xx lain | Gangguan server dashboard | Ulangi dengan jeda (kit: 3 kali). |

## 5. Langkah di sisi SIRENDA PEKS3 (tim pengembang PEKS3)

Kit pengirim: `docs/integrasi-peks3/kit-peks3/app/Console/Commands/KirimKeDashboard.php` (PHP 7.4+, Laravel 7+, memakai cURL; tidak perlu mengubah form input).

1. Salin berkas kit ke `app/Console/Commands/KirimKeDashboard.php` aplikasi SIRENDA PEKS3. Pada Laravel 7/8, pastikan `app/Console/Kernel.php` memuat `$this->load(__DIR__.'/Commands');` (bawaan).
2. Tambahkan ke `config/services.php`:
   ```php
   'dashboard_peks3' => [
       'url' => env('DASHBOARD_PEKS3_URL'),
       'client_id' => env('DASHBOARD_PEKS3_CLIENT_ID', 'sirenda-peks3'),
       'rahasia' => env('DASHBOARD_PEKS3_RAHASIA'),
   ],
   ```
3. Tambahkan ke `.env`:
   ```
   DASHBOARD_PEKS3_URL=https://dashboard.sirenda-psn.id/api/integrasi/peks3/v1
   DASHBOARD_PEKS3_CLIENT_ID=sirenda-peks3
   DASHBOARD_PEKS3_RAHASIA=<kunci dari konsultan>
   ```
   lalu `php artisan config:cache`.
4. Uji koneksi: `php artisan dashboard:kirim --cek` → `Terhubung. {...}`.
5. Kiriman awal (sekali): `php artisan dashboard:kirim --referensi`, lalu `php artisan dashboard:kirim --semua --rekonsiliasi`. Uji internal: 381 PSN ±75 detik.
6. Pasang cron (cPanel → Cron Jobs):
   ```
   * * * * *   cd /home/<akun>/<folder-sirenda> && php artisan dashboard:kirim >> /dev/null 2>&1
   15 1 * * *  cd /home/<akun>/<folder-sirenda> && php artisan dashboard:kirim --semua --rekonsiliasi >> /dev/null 2>&1
   30 1 * * 0  cd /home/<akun>/<folder-sirenda> && php artisan dashboard:kirim --referensi >> /dev/null 2>&1
   ```
   Kiriman malam `--semua` menangkap penghapusan fisik baris anak yang tidak meninggalkan stempel waktu; PSN yang tidak berubah hanya dijawab `TETAP` dan tidak memicu penerapan.
7. Opsional, agar lebih cepat: panggil setelah simpan di controller, `Artisan::call('dashboard:kirim', ['--psn' => [$psnId]]);`.

Cara kit mendeteksi perubahan: kit mencatat stempel waktu terbesar (`created_at`/`updated_at`/`deleted_at`) yang ada di data SIRENDA PEKS3 sendiri ke `storage/app/dashboard-sinkron.json`. Kiriman berikutnya mengambil PSN yang memiliki baris dengan stempel waktu ≥ titik tersebut dikurangi 2 menit. Karena patokannya data, bukan jam server, perbedaan zona waktu PHP dan MySQL tidak membuat perubahan terlewat. Syaratnya, aplikasi PEKS3 mengisi `updated_at` setiap kali menyimpan (perilaku bawaan Laravel).

## 6. Langkah di sisi Dashboard (konsultan)

1. cPanel → **MySQL Databases**: buat basis data cermin, misalnya `<akun>_peks3`, beri hak penuh ke pengguna basis data dashboard.
2. Isi awal cermin dari dump SIRENDA PEKS3 terbaru: `php tools/konversi-dump-mysql.php dump.sql dump-siap.sql --database=<akun>_peks3` (membuang DEFINER dan mengarahkan `USE` ke basis data cermin), lalu impor `dump-siap.sql` ke basis data cermin lewat phpMyAdmin.
3. `.env` dashboard:
   ```
   LEGACY_DB_HOST=localhost
   LEGACY_DB_DATABASE=<akun>_peks3
   LEGACY_DB_USERNAME=<pengguna db dashboard>
   LEGACY_DB_PASSWORD=<kata sandinya>
   PEKS3_AKTIF=true
   PEKS3_RAHASIA=<64 karakter hex>
   PEKS3_IP_DIIZINKAN=<IP publik server SIRENDA PEKS3>
   ```
4. ```
   php artisan migrate --force
   php artisan peks3:siapkan-cermin     # tandai cermin + ubah tabel MyISAM ke InnoDB
   php artisan config:cache && php artisan route:cache
   ```
5. Cron dashboard (jika belum): `* * * * * php /path/artisan schedule:run`. Penjadwal menjalankan `peks3:terapkan` setiap menit bila `PEKS3_AKTIF=true`.
6. Uji dari server dashboard: `php artisan peks3:kirim-uji --status` dan `php artisan peks3:kirim-uji <psn_id>`, lalu `php artisan peks3:terapkan --paksa`.
7. Serahkan kepada tim PEKS3: dokumen ini, berkas kit, dan `PEKS3_RAHASIA` lewat kanal tertutup.

Pengaturan opsional: `PEKS3_JEDA_DETIK` (bawaan 60) dan `PEKS3_TUNGGU_MAKS_DETIK` (bawaan 600).

## 7. Pemantauan dan penanganan masalah

| Gejala | Pemeriksaan |
|---|---|
| Data baru tidak tampil | Di PEKS3: `php artisan dashboard:kirim --cek`. Di dashboard: tabel `integrasi_peks3_status` (kolom `tertunda_sejak`, `galat`) dan `integrasi_peks3_log`. Cron kedua sisi berjalan? |
| `401 X-Timestamp ...` | Jam server berselisih; aktifkan NTP. |
| `401 X-Signature tidak valid` | Kunci kedua sisi berbeda, atau body diubah proxy; pastikan yang ditandatangani adalah body persis yang dikirim. |
| Paket `DITOLAK` | Pesan menyebut tabel dan baris yang salah, misalnya `psn_kegiatan[3].psn_id harus bernilai 471`. |
| Penerapan `GAGAL` | `integrasi_peks3_status.galat` dan `storage/logs/laravel.log`. Data dashboard tetap versi sebelumnya (transaksi dibatalkan); antrean tetap tertunda dan dicoba lagi menit berikutnya. |
| Ingin memaksa sinkron penuh | PEKS3: `dashboard:kirim --semua --rekonsiliasi`; dashboard: `peks3:terapkan --paksa`. |

## 8. Catatan dan batasan

- SIRENDA PEKS3 adalah sumber kebenaran data PSN. Setiap penerapan membangun ulang data PSN di dashboard dari cermin, sehingga perubahan data PSN yang dibuat langsung di dashboard (di luar penilaian usulan) akan tertimpa.
- Snapshot setiap cut-off dibangun ulang dari data terkini, seperti saat impor dump manual. Nilai bulanan (target/realisasi per periode) tetap sesuai periodenya; atribut tanpa riwayat, misalnya status PSN, mengikuti nilai terbaru.
- Riwayat login pengguna PEKS3 (`user_logs`) tidak disinkronkan.
- Penambahan tabel atau kolom baru di PEKS3 yang perlu tampil di dashboard memerlukan penyesuaian importer dashboard dan daftar tabel di kit/konfigurasi.

## Lampiran: berkas terkait di repositori dashboard

| Berkas | Isi |
|---|---|
| `routes/api.php` | Rute `integrasi/peks3/v1/*` |
| `app/Http/Middleware/TandaTanganPeks3.php` | Verifikasi HMAC, waktu, replay, IP |
| `app/Http/Controllers/Api/Integrasi/Peks3Controller.php` | Endpoint |
| `app/Services/Peks3CerminService.php` | Penulisan ke basis data cermin |
| `app/Services/Peks3TerapkanService.php` | Penerapan atomik ke dashboard |
| `app/Console/Commands/Peks3*.php` | `peks3:siapkan-cermin`, `peks3:terapkan`, `peks3:kirim-uji` |
| `config/psn_dashboard.php` → `peks3` | Daftar tabel, jeda, batas |
| `docs/integrasi-peks3/kit-peks3/` | Kit pengirim untuk SIRENDA PEKS3 |
| `tests/Feature/Peks3SinkronTest.php` | Tes otomatis |
