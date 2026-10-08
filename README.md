# SIRENDA PSN v2

Basis data terpadu dan aplikasi Dashboard Monitoring & Evaluasi Proyek Strategis Nasional (PSN) — Kementerian PPN/Bappenas.

Skema dirancang ulang dari basis data SIRENDA PSN lama (`sirendapsn_bappenas`), dinormalisasi, dan diisi lewat ETL yang dapat diulang. Rancangan lengkap ada di **[docs/rancangan-aplikasi.md](docs/rancangan-aplikasi.md)**.

| Dokumen | Isi |
|---|---|
| [docs/rancangan-aplikasi.md](docs/rancangan-aplikasi.md) | Arsitektur, modul, model data, pemetaan lama→baru, aturan bisnis, RBAC, API, hasil impor, keputusan terbuka |
| [docs/kamus-indikator.md](docs/kamus-indikator.md) | Definisi K1–K4, P1–P8, status otomatis, penilaian usulan |
| [docs/kamus-data.md](docs/kamus-data.md) | Kamus kolom (dibangkitkan dari skema) |
| [docs/skema/sirenda_psn_v2.sql](docs/skema/sirenda_psn_v2.sql) | DDL lengkap untuk telaah DBA |

## Stack

Laravel 11 · PHP 8.2+ · MySQL 8 (diuji juga di MariaDB 10.11) · spatie/laravel-permission 6.

## Instalasi

```bash
composer install
cp .env.example .env && php artisan key:generate   # isi DB_* dan LEGACY_DB_*
php artisan migrate --seed                        # skema + referensi + peran + akun admin (kata sandi dicetak sekali)
```

## Menjalankan aplikasi

```bash
npm install && npm run build
php artisan serve        # buka http://127.0.0.1:8000, login dengan akun admin dari seeder
```

Halaman yang sudah tersedia: `/login`, `/dashboard` (Ringkasan Eksekutif), `/proyek` (Portofolio PSN), `/proyek/{id}` (Detail Proyek), `/kualitas-data`, dan `/ganti-password`. Halaman lain menyusul per fase (lihat `docs/rancangan-aplikasi.md` §11).

## Impor data dari basis data lama

1. Pulihkan dump lama ke server MySQL (contoh: `mysql --force sirendapsn_bappenas < sirendapsn_bappenas_YYYYMMDD.sql`).
   `--force` diperlukan karena dump berisi beberapa view dan tabel stub yang rusak; objek tersebut tidak dipakai ETL.
2. Atur `LEGACY_DB_*` di `.env`.
3. Jalankan:

```bash
php artisan legacy:import --fresh              # impor penuh (mengosongkan data v2 terlebih dahulu)
php artisan legacy:import --only=risiko,monev  # langkah tertentu saja
```

Ringkasan jumlah baris dan peringatan (kode tidak dikenal, investasi anomali, baris yatim) dicetak di konsol. Laporan lengkap disimpan di `storage/app/private/etl/laporan-*.json`.

**Data pribadi:** hash kata sandi lama tidak dibawa. Semua akun hasil impor mendapat kata sandi acak dan wajib reset. Dump lama dan laporan ETL **jangan** di-commit ke repo.

## Snapshot per cut-off

Semua angka dashboard per cut-off dan pembanding "vs cut-off sebelumnya" dibaca dari tabel `snapshot_*`.

```bash
php artisan psn:snapshot 2026-09              # bangun/bangun ulang snapshot DRAFT (tanggal = akhir bulan)
php artisan psn:snapshot 2026-09 --terbit     # terbitkan: dipakai dashboard, membatalkan cache, tercatat di audit_log
php artisan psn:snapshot 2026-09 --paksa      # bangun ulang snapshot yang sudah terbit
php artisan psn:snapshot 2026-09 --tanggal=2026-09-25   # tanggal cut-off khusus
```

Penjadwal (`routes/console.php`) membangun snapshot DRAFT bulan lalu setiap tanggal 1 pukul 02.00. Jalankan `php artisan schedule:work`, atau pasang cron `* * * * * php artisan schedule:run`. Penerbitan otomatis dapat diaktifkan lewat `psn_dashboard.snapshot.terbit_otomatis`.

## Pengujian

```bash
php artisan test                    # skema, referensi, RBAC (butuh DB MySQL `sirenda_psn_v2_test`)
LEGACY_TEST=1 php artisan test      # + uji akurasi ETL terhadap basis data lama
```

## Perintah lain

- `php artisan docs:kamus-data`: membangkitkan ulang `docs/kamus-data.md` setelah skema berubah.
