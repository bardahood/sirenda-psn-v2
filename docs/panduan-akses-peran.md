# Panduan Akses SIRENDA PSN per Peran Pengguna

Panduan ini menjelaskan cara masuk ke aplikasi dan alamat (URL) halaman yang dapat dibuka oleh setiap peran. Hak akses pada tabel di bawah **diukur langsung** dari aplikasi (status HTTP per peran) dan diuji otomatis di `tests/Feature/KontrakEndpointTest.php`.

## 1. Alamat aplikasi

Semua URL di panduan ini ditulis relatif terhadap alamat dasar aplikasi, yaitu nilai `APP_URL` pada berkas `.env` server.

| Lingkungan | Alamat dasar (contoh) |
|---|---|
| Produksi / intranet | `https://<domain-aplikasi>` (sesuai `APP_URL`) |
| Pengembangan lokal | `http://127.0.0.1:8000` (jalankan `php artisan serve`) |

Contoh: halaman Dashboard = `https://<domain-aplikasi>/dashboard`.

## 2. Masuk ke aplikasi

1. Buka **`/login`**.
2. Isi **nama pengguna atau surel** dan **kata sandi**, lalu klik **Masuk**.
3. Pada login pertama atau setelah kata sandi direset, aplikasi mengarahkan ke **`/ganti-password`**. Kata sandi wajib diganti sebelum halaman lain dapat dibuka.
4. Setelah berhasil, aplikasi membuka **`/dashboard`**. Alamat `/` juga diarahkan ke sana.
5. Untuk keluar, klik nama Anda di kanan atas, lalu pilih **Keluar**. Menu yang sama berisi **Ganti kata sandi** dan **Kamus indikator**.

Catatan:
- Akun dibuat oleh **Super Admin** di `/pengaturan/pengguna/baru`. Kata sandi sementara diserahkan secara aman dan wajib diganti saat login pertama.
- Peran **Operator K/L** dan **Direktorat Sektor** wajib memiliki unit kerja. Unit kerja menentukan PSN yang dapat dilihat (Operator) atau diisi dan diverifikasi (keduanya).
- Akun nonaktif tidak dapat masuk. Hubungi Super Admin.
- Percobaan login dibatasi 20 kali per menit.

## 3. Ringkasan peran

| Peran | Untuk siapa | Cakupan data |
|---|---|---|
| **Super Admin** | Pengelola aplikasi | Semua PSN, semua menu termasuk Pengaturan |
| **Pimpinan** | Pejabat pengambil keputusan | Semua PSN; hanya melihat dan mengunduh |
| **Tim Koordinasi/PMO** | Tim koordinasi PSN | Semua PSN; mengelola Perencanaan & penilaian usulan |
| **Direktorat Sektor** | Direktorat pengampu di Bappenas | Melihat semua PSN; mengisi dan **memverifikasi** PSN unitnya |
| **Operator K/L** | Petugas pengisi di K/L | **Hanya PSN unitnya** (PSN unit lain tidak terlihat); mengisi dan mengajukan |

## 4. Matriks akses URL

Arti simbol:
- ✓ = dapat dibuka
- ✓¹ = hanya untuk PSN atau usulan dalam cakupan unit kerja pengguna
- ✗ = ditolak (403 *Akses ditolak*, atau 404 bila data di luar cakupan)

| Menu / fungsi | URL | Super Admin | Pimpinan | PMO | Direktorat | Operator K/L |
|---|---|:-:|:-:|:-:|:-:|:-:|
| Dashboard | `/dashboard` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Portofolio PSN | `/proyek` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Detail proyek | `/proyek/{id}` (`?tab=profil\|kpro\|progres\|dokumen`) | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Perencanaan (daftar & detail usulan) | `/perencanaan`, `/perencanaan/{id}` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Usulan baru | `/perencanaan/baru` | ✓ | ✗ | ✓ | ✓ | ✓ |
| Pengisian & Verifikasi | `/pengisian` | ✓ | ✗ | ✗ | ✓ | ✓¹ |
| Formulir pengisian | `/pengisian/{YYYY-MM}/{id_psn}` | ✓ | ✗ | ✗ | ✓ (ubah ✓¹) | ✓¹ |
| Risiko, Isu & Regulasi | `/risiko` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Peta Sebaran | `/peta` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Kualitas Data | `/kualitas-data` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Laporan (arsip per cut-off) | `/laporan` | ✓ | ✓ | ✓ | ✓ | ✗ |
| PDF Ringkasan Eksekutif | `/laporan/ringkasan.pdf?periode=YYYY-MM` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Excel Register Risiko | `/laporan/risiko.xlsx?periode=YYYY-MM` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Excel Rekap Pengisian | `/laporan/pengisian.xlsx?periode=YYYY-MM` | ✓ | ✓ | ✓ | ✓ | ✗ |
| Excel Portofolio | `/api/v1/proyek?format=xlsx` | ✓ | ✓ | ✓ | ✓ | ✓¹ |
| Kamus Indikator | `/kamus-indikator` | ✓ | ✓ | ✓ | ✓ | ✓ |
| Pengaturan: Pengguna & Peran | `/pengaturan/pengguna` | ✓ | ✗ | ✗ | ✗ | ✗ |
| Pengaturan: Cut-off & Snapshot | `/pengaturan/cutoff` | ✓ | ✗ | ✗ | ✗ | ✗ |
| Pengaturan: Master Data | `/pengaturan/master` | ✓ | ✗ | ✗ | ✗ | ✗ |
| Ganti kata sandi | `/ganti-password` | ✓ | ✓ | ✓ | ✓ | ✓ |

Menu di sidebar hanya menampilkan halaman yang boleh dibuka oleh peran pengguna.

## 5. Panduan per peran

### 5.1 Super Admin
1. **Kelola pengguna:** buka `/pengaturan/pengguna`, lalu klik **+ Pengguna baru** (`/pengaturan/pengguna/baru`).
   - Pilih peran, dan isi unit kerja untuk Operator atau Direktorat.
   - Salin kata sandi sementara yang tampil setelah disimpan.
   - Reset kata sandi dilakukan dari halaman ubah pengguna.
2. **Terbitkan data bulanan:** buka `/pengaturan/cutoff`, isi periode `YYYY-MM`, centang **Terbitkan**, lalu klik **Bangun snapshot**. Dashboard langsung memakai cut-off baru.
3. **Master data:** buka `/pengaturan/master` untuk mengubah nama dan status klaster, sektor, serta unit kerja. Perubahan ini menjadi opsi filter global.
4. Semua halaman lain dapat dibuka, termasuk mengisi dan memverifikasi pengisian PSN mana pun.

### 5.2 Pimpinan
1. Mulai dari `/dashboard`. Atur **Periode Data, Provinsi, Sektor, Status Proyek, Dana & Kategori** di bagian atas halaman.
   - Klik batang, titik peta, atau irisan grafik untuk menambah filter.
   - Klik kartu KPI untuk membuka daftar PSN terkait.
2. Unduh **PDF Ringkasan** dari tombol di dashboard. Hasilnya mengikuti filter yang sedang aktif.
3. Arsip bulanan tersedia di `/laporan`: PDF, Excel portofolio, register risiko, dan rekap pengisian per cut-off.
4. Pimpinan hanya melihat; formulir ubah tidak ditampilkan.

### 5.3 Tim Koordinasi/PMO
1. Semua akses lihat seperti Pimpinan.
2. **Perencanaan:**
   - Buat usulan di `/perencanaan/baru`.
   - Nilai usulan di `/perencanaan/{id}`: isi skor sub-kriteria, lalu **Finalisasi**. Hanya PMO dan Super Admin yang dapat membuka kembali penilaian final.
3. Laporan arsip di `/laporan`.

### 5.4 Direktorat Sektor
1. Melihat semua PSN di Dashboard, Portofolio, Risiko, dan Peta.
2. **Verifikasi pengisian bulanan:** buka `/pengisian`.
   - Secara default daftar menampilkan "Hanya PSN unit saya"; hapus centang untuk melihat semua PSN.
   - PSN berstatus **Diajukan** tampil di atas dengan tautan **Tinjau**.
   - Di halaman `/pengisian/{YYYY-MM}/{id}`, isi catatan lalu pilih **Verifikasi** atau **Kembalikan untuk perbaikan**. Catatan wajib diisi bila mengembalikan.
3. Direktorat juga dapat mengisi data PSN unitnya, seperti Operator, serta memverifikasi usulan di Perencanaan.

### 5.5 Operator K/L
1. Hanya PSN yang diampu unit kerjanya yang tampil. Membuka PSN unit lain menghasilkan *404 Tidak ditemukan*.
2. **Pengisian bulanan** di `/pengisian` (periode default: bulan berjalan). Klik **Isi** pada PSN, lalu lengkapi:
   - **A.** Rencana dan realisasi progres fisik kumulatif (%), realisasi anggaran bulan ini (Rp), permasalahan, dan bukti dukung (PDF/JPG/PNG, maks. 10 MB).
   - **B.** Pemantauan risiko: kemungkinan × dampak aktual (1–5), status perlakuan, dan catatan. Risiko baru dapat ditambahkan.
   - **C.** Isu: PIC, tenggat, status, dan tindak lanjut. Isu baru dapat ditambahkan.
3. Klik **Simpan draf** untuk menyimpan sementara; bisa diulang berkali-kali.
4. Klik **Simpan & ajukan verifikasi** bila sudah lengkap.
   - Pengajuan ditolak bila masih ada KP/RO tanpa realisasi.
   - Setelah diajukan, isian terkunci sampai diverifikasi atau dikembalikan.
5. Bila **Dikembalikan**, catatan verifikator tampil di atas formulir. Perbaiki, lalu ajukan ulang.
6. Usulan PSN baru dapat dibuat di `/perencanaan/baru`; usulan otomatis tercatat atas unit Anda.
7. Halaman `/laporan` tidak tersedia untuk Operator. PDF Ringkasan dapat diunduh dari dashboard, terbatas pada PSN unitnya.

## 6. Tautan dengan filter (dapat dibagikan)

Filter disimpan di alamat halaman. Menyalin URL berarti menyalin tampilan yang sama, sesuai hak akses penerima. Contoh:

| Kebutuhan | URL |
|---|---|
| Dashboard Jawa Barat & DKI, cut-off September 2026 | `/dashboard?periode=2026-09&prov=32,31` |
| PSN berstatus Terlambat | `/proyek?status=terlambat` |
| PSN dengan risiko kritis | `/proyek?kritis=1` |
| PSN tahap Konstruksi | `/proyek?tahap=KONSTRUKSI` |
| Pengisian yang menunggu verifikasi, Oktober 2026 | `/pengisian?periode=2026-10&status=DIAJUKAN` |
| Excel register risiko cut-off Agustus 2026 | `/laporan/risiko.xlsx?periode=2026-08` |

Parameter filter global:
- `periode` (YYYY-MM)
- `prov` (kode provinsi)
- `klaster` (ID)
- `dit` (ID direktorat)
- `status` (`on_track`, `berisiko`, `terlambat`, `tanpa_data`)
- `kat` (`psn`, `pkpn`)
- `dana` (`apbn`, `apbd`, `kpbu`, `lainnya`)

Beberapa nilai dipisahkan koma.

## 7. Pesan yang mungkin muncul

| Pesan / kode | Arti | Tindakan |
|---|---|---|
| Diarahkan ke `/login` | Sesi berakhir atau belum masuk | Masuk kembali |
| Diarahkan ke `/ganti-password` | Kata sandi sementara harus diganti | Ganti kata sandi |
| **403** Akses ditolak | Peran tidak memiliki izin untuk halaman ini | Minta Super Admin menyesuaikan peran |
| **404** Tidak ditemukan | Data tidak ada atau di luar cakupan unit kerja | Periksa unit kerja akun Anda |
| **409** Periode terkunci | Cut-off sudah diterbitkan; isian tidak dapat diubah | Hubungi Super Admin bila perlu koreksi |
| "Belum ada cut-off yang diterbitkan" | Belum ada snapshot terbit | Super Admin menerbitkan di `/pengaturan/cutoff` |
