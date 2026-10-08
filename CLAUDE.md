# CLAUDE.md — SIRENDA PSN v2

Konvensi proyek untuk asisten AI dan pengembang.

## Bahasa
- Label UI dan dokumen: Bahasa Indonesia formal (register pemerintahan).
- Kode, nama kelas, dan komentar boleh berbahasa Inggris. Nama tabel/kolom memakai Bahasa Indonesia (mengikuti skema yang ada).

## Basis data
- Sumber kebenaran skema: `database/migrations`. Setelah mengubah skema, jalankan `php artisan docs:kamus-data` dan perbarui `docs/skema/sirenda_psn_v2.sql`.
- Migrasi **hanya aditif** (tabel/kolom/view baru). Jangan drop/rename tabel inti tanpa persetujuan pemilik produk.
- Gunakan macro `$table->jejak()` (created_by/updated_by/deleted_by + timestamps + soft delete) untuk tabel data inti.
- Uang dalam Rupiah penuh `DECIMAL(24,2)`. Persentase capaian dihitung, tidak disimpan.
- Data periodik disimpan memanjang (`tahun`, `periode`, `periode_ke`), bukan kolom per tahun/bulan.
- Kode status disimpan sebagai VARCHAR + komentar kolom, bukan ENUM.
- Skema memakai view dan fungsi MySQL: test dijalankan di MySQL/MariaDB, bukan SQLite.

## Aturan bisnis
- Semua ambang, bobot, pemetaan kode, dan TTL cache ada di `config/psn_dashboard.php`. Jangan di-hardcode.
- Definisi indikator: `docs/kamus-indikator.md`. Angka per cut-off selalu dibaca dari `snapshot_*`.
- Gate penilaian usulan (KU1–KU3) bersifat mutlak: satu "Tidak" → DITOLAK. Logika skor hanya di `App\Services\ScoringService`; tabel `penilaian` hanya menyimpan cache hasilnya.

## Hak akses
- Peran dan izin didefinisikan di `database/seeders/PeranSeeder.php` (`modul.aksi`, kumulatif lihat < input < verifikasi < kelola).
- Pembatasan per sektor/proyek ditegakkan di level query (global scope berbasis `users.unit_kerja_id`), bukan hanya di tampilan.
- Setiap aksi input/verifikasi/kelola wajib tercatat di `audit_log`.

## Frontend
- Blade + Alpine + Tailwind; tanpa CDN (font & library dibundel Vite). Jalankan `npm run build` setelah mengubah JS/CSS.
- Token desain di `tailwind.config.js` (`primer`, `aksen`, `rounded-kartu`, `text-kpi`/`panel`/`isi`/`label`). Komponen panel: `<x-panel kode=... judul=...>` (ⓘ + unduh PNG/CSV).
- Status selalu badge berlabel (`badge-{KODE}` + `titik-{KODE}`), tidak pernah warna saja.
- Grafik: satu seri = warna aksen; dua seri memakai palet tervalidasi (#2a78d6, #eb6834); tidak ada grafik dua sumbu Y.
- Filter global hidup di query string (`Alpine.store('filter')`). Tautan antarhalaman memakai `$store.filter.tautan(path)` agar filter terbawa.

## ETL
- `app/Support/Legacy/LegacyImporter.php` hanya membaca koneksi `legacy`. Baris hasil impor menyimpan `legacy_id`/`legacy_ref`.
- Data yang tidak bisa dipetakan dicatat di laporan ETL, tidak dibuang diam-diam.
- Jangan commit dump basis data lama, `.env`, atau laporan ETL (berisi data pengguna).

## Alur kerja
- Kerjakan per fase (`docs/rancangan-aplikasi.md` §11). Di akhir fase: ringkas perubahan, daftar file, cara uji, lalu berhenti untuk review.
- Sebelum push: `./vendor/bin/pint --test` dan `php artisan test`. Setelah mengubah logika indikator: `php artisan psn:uji-akurasi` harus 0 selisih.
- Hindari memo `static` di service (basi antar-permintaan/tes); gunakan properti instance.
- Distribusi/peringkat wajib berurutan deterministik (jumlah menurun, nama menaik) agar URL mereproduksi tampilan.
