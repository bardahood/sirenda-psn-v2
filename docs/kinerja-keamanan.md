# Kinerja dan Keamanan

## Profil kinerja endpoint

`php artisan psn:profil-kinerja --ulang=20` mengukur waktu respons dan jumlah query lewat kernel HTTP (tanpa jaringan), baik tanpa cache (cache dikosongkan tiap permintaan) maupun dengan cache.

Data riil: 380 PSN, 760 baris snapshot. Lingkungan: MariaDB 10.11, PHP 8.3, kontainer pengembangan.

| Endpoint | Tanpa cache P95 (ms) | Query | Dengan cache P95 (ms) | Query |
|---|---|---|---|---|
| `/api/v1/dashboard/kpi` | 51 | 12 | 6 | 6 |
| `/api/v1/dashboard/distribusi` (4 dimensi) | 23–31 | 10 | 5–6 | 6 |
| `/api/v1/dashboard/progres` | 32 | 9 | 10 | 6 |
| `/api/v1/dashboard/tren` | 52 | 12 | 8 | 6 |
| `/api/v1/dashboard/ro-kritis` | 36 | 10 | 7 | 6 |
| `/api/v1/proyek` | 15–19 | 9 | 5–6 | 5 |
| `/api/v1/proyek/{id}` | 40 | 31 | 35 | 31 |
| `/api/v1/kualitas-data` | 370 | 30 | 15 | 7 |
| `/api/v1/peta` | 29 | 10 | 7 | 6 |
| `/api/v1/usulan/{id}/skor` | 10 | 7 | 9 | 7 |

**Target < 500 ms per endpoint panel dengan cache: tercapai** (P95 terburuk 35 ms). Tanpa cache pun semuanya di bawah 500 ms.

**Penjaga N+1**: `tests/Feature/KinerjaQueryTest.php` memastikan jumlah query tidak bertambah saat jumlah data bertambah:
- endpoint agregat diuji pada 3 PSN vs 28 PSN;
- detail proyek (4 tab + API) diuji pada 2 KP/RO vs 30 KP/RO.

## Waktu muat halaman (browser)

`node tests/Browser/ukur-halaman.mjs <url> <username> <password> 10` mengukur waktu dari navigasi sampai semua panel tergambar dengan Chromium 1440×900. Hasil pada data riil, memakai server pengembangan `php artisan serve` yang memproses permintaan satu per satu:

| Halaman | P50 | P95 |
|---|---|---|
| `/dashboard` (13 panel, 12 permintaan API paralel) | 618 ms | 1.170 ms |
| `/proyek` | 206 ms | 316 ms |
| `/kualitas-data` | 433 ms | 648 ms |

**Target halaman ringkasan < 3 detik (P95): tercapai.** Dengan PHP-FPM di produksi, permintaan paralel diproses bersamaan sehingga waktunya akan lebih rendah.

## Keamanan

| Kebutuhan | Implementasi |
|---|---|
| HTTPS | `URL::forceScheme('https')` di produksi; header HSTS untuk permintaan HTTPS; `SESSION_SECURE_COOKIE=true` di produksi |
| Session timeout 30 menit | `SESSION_LIFETIME=30` (diuji) |
| Rate limit login | 5 percobaan/menit per akun+IP, ditambah throttle 20/menit per IP pada rute login; percobaan gagal dicatat di `login_log` |
| Validasi input | Form Request/validator pada semua parameter filter dan formulir (422 untuk nilai tidak valid) |
| Pembatasan data | Global scope di level query (Operator K/L), Policy per halaman/objek, 404 untuk objek di luar cakupan |
| Jejak audit | Pengguna, waktu, nilai lama, dan nilai baru untuk setiap perubahan model inti, serta aksi verifikasi, terbit, dan pengembalian |
| Header | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store` untuk halaman berlogin |
| Akun | Akun hasil impor wajib ganti kata sandi (min. 12 karakter, huruf besar/kecil, angka); akun nonaktif langsung dikeluarkan |

**Belum dipasang: Content-Security-Policy.** Build standar Alpine.js memerlukan `unsafe-eval`. Untuk CSP ketat, ganti dengan build CSP Alpine (`@alpinejs/csp`) dan pindahkan ekspresi inline ke komponen terdaftar. Ini dicatat sebagai pekerjaan lanjutan.
