# Evaluasi Status PSN (Keberlanjutan PSN pada RKP Berikutnya)

Modul ini memindahkan kertas kerja **"Input Form Pengendalian PSN 2026 v4"** ke dalam dashboard. Tujuannya menentukan apakah setiap PSN **dipertahankan** atau **dikeluarkan** dari daftar PSN pada RKP berikutnya (bawaan RKP 2027, `EVALUASI_TAHUN_RKP`).

Menu: **Evaluasi** (`/evaluasi-status`), dengan tiga halaman: **Pemantauan**, **Kertas Kerja (Format Ringkas)** (`/evaluasi-status/kertas-kerja`), dan **Grafik Status Harian** (`/evaluasi-status/grafik`).

## 1. Dari kertas kerja ke aplikasi

| Kertas kerja | Di aplikasi |
|---|---|
| Bagian A–C (identitas, tahapan, progres, pembiayaan, isu) diketik ulang per PSN | **Dibaca otomatis dari profil PSN** (data SIRENDA PEKS3, snapshot cut-off terbit terakhir). Form Pengendalian yang dicetak dari profil dipakai PJ untuk melengkapi dan mengoreksi. |
| A6 tahun penetapan, A12 jenis PSN, C1 "Tidak ada isu", D1–D3, PIC | Diisi pada halaman evaluasi per PSN (peran Operator K/L ke atas). |
| Kolom "Cek otomatis" (sheet Aturan Wajib Isi) | **Cek kelengkapan otomatis** per aturan (lihat §3), ditampilkan sebagai daftar ✓/✗. |
| Hasil Pengecekan → Tindak Lanjut | Sama; tindak lanjut otomatis dari hasil pengecekan dan masih dapat diubah. |
| Catatan Hasil Analisis → Indikasi Status | Sama; Indikasi Status hanya dapat diisi setelah catatan ada (ditolak bila kosong). |
| Status Akhir (rumus) | Sama: Indikasi Status, bila kosong Tindak Lanjut, bila kosong Proses Pengecekan. Ditambah status **Form belum diterima** untuk PSN yang formnya belum kembali. |
| Sheet Riwayat Status (diisi manual) | **Otomatis**: setiap perubahan status akhir tercatat dengan tanggal, pengguna, dan keterangan. Setiap perubahan isian tercatat di jejak audit. |
| Sheet Grafik Status Harian (14 hari) | Grafik batang bertumpuk "Pergerakan Status Akhir per Hari", dengan aturan yang sama. |
| Penandaan merah "Lengkap" padahal cek otomatis belum lengkap | Peringatan merah di formulir dan di pesan simpan. |

## 2. Fitur analisis yang ditambahkan

1. **Indikator per PSN**, dihitung dari data profil:
   - deviasi progres fisik (realisasi − rencana s.d. cut-off, dalam poin);
   - serapan anggaran (realisasi ÷ pagu s.d. cut-off);
   - pergeseran jadwal (proyeksi selesai D3 − target A7);
   - usia sebagai PSN (tahun RKP − A6);
   - jumlah isu terbuka, isu jalur kritis, dan regulasi yang belum ditetapkan.
2. **Rekomendasi sistem** sebagai alat bantu analis (bukan keputusan):

   | Rekomendasi | Aturan |
   |---|---|
   | Indikasi dikeluarkan | D1 = tidak lagi membutuhkan status PSN, **atau** tahapan kumulatif selesai dan D1 tidak menyatakan masih membutuhkan |
   | Data belum cukup | Masih ada isian wajib yang kosong (§3) |
   | Perlu pendalaman | Ada sinyal: deviasi fisik ≤ −10 poin; serapan < 50%; proyeksi selesai mundur ≥ 2 tahun; masih Penyiapan/Transaksi ≥ 3 tahun sejak ditetapkan; ada isu yang menghambat jalur kritis; atau D1 masih membutuhkan tetapi tanpa fasilitas D2 |
   | Indikasi dipertahankan | D1 masih membutuhkan, isian lengkap, tanpa sinyal di atas |

   Ambang dapat diubah di `config/psn_dashboard.php` → `evaluasi_status.ambang`. Setiap rekomendasi disertai daftar sinyal penyebabnya.
3. **Alur keberlanjutan** (visual bertangga, seperti bahan paparan RKP): PSN aktif (RKP/Permenko) → Form diterima → Dalam pengecekan (per status) → Indikasi (+dipertahankan / −dikeluarkan beserta nama PSN) → **Proyeksi PSN dalam daftar RKP berikutnya** = aktif − dikeluarkan + usulan baru yang direkomendasikan (FINAL, dari menu Perencanaan). Semua angka dapat diklik untuk menyaring daftar.
4. **Daftar evaluasi** dengan filter status akhir, rekomendasi sistem, kelengkapan, sumber, klaster, dan pencarian, serta **unduhan Excel** dengan kolom mengikuti format ringkas kertas kerja ditambah hasil analisis.

## 3. Aturan wajib isi (cek otomatis)

| Butir | Wajib bila | Terpenuhi bila (sumber data) |
|---|---|---|
| A6 | selalu | tahun penetapan diisi di evaluasi |
| A7 | selalu | `psn.tahun_selesai` terisi |
| B1 | selalu | status PSN memetakan ke tahapan |
| B2 progres | tahapan Konstruksi / Beroperasi sebagian / Kumulatif selesai | rencana dan realisasi progres fisik ada di snapshot |
| B2 pembiayaan | idem | pagu dan realisasi anggaran ada di snapshot |
| B3 | tahapan Penyiapan / Transaksi | minimal 1 KP/RO (milestone) tercatat |
| B4 | tahapan Beroperasi sebagian / Kumulatif selesai | minimal 1 indikator atau penerima manfaat tercatat |
| C1, C2 | tahapan Penyiapan s.d. Beroperasi sebagian, kecuali dicentang "Tidak ada isu" | ada isu terbuka, dan minimal satu berisi kebutuhan dukungan |
| D1 | selalu | dipilih |
| D2 | D1 = masih membutuhkan | minimal 1 fasilitas |
| D3 | D1 = masih membutuhkan dan tahapan belum selesai | tahapan target, target, dan proyeksi tahun selesai terisi |

Kekurangan pada A–C diperbaiki di SIRENDA PEKS3 (sumber data), lalu otomatis terbawa ke dashboard (lihat `docs/integrasi-peks3.md`).

## 4. Hak akses

| Peran | Lihat | Isian PJ (tanggal form, A6, A12, D1–D3, PIC) | Hasil pengecekan, tindak lanjut, catatan | Indikasi status |
|---|---|---|---|---|
| Super Admin, Tim Koordinasi/PMO | ✓ | ✓ | ✓ | ✓ |
| Direktorat Sektor (PSN unitnya) | ✓ | ✓ | ✓ | – |
| Operator K/L (PSN unitnya) | ✓ | ✓ | – | – |
| Pimpinan | ✓ | – | – | – |

Izin `evaluasi.*` dibuat dan diberikan ke peran oleh migrasi `2026_11_15_100000`, tanpa mengubah izin lain.

## 5. Data

- `evaluasi_psn`: satu baris per PSN per tahun RKP (unik `tahun_rkp`, `psn_id`). `status_akhir` disimpan sebagai turunan untuk penyaringan.
- `evaluasi_psn_riwayat`: perubahan status akhir (tanggal, status, keterangan, pengguna).
- Evaluasi tidak ikut terhapus saat sinkron PEKS3 maupun `legacy:import --fresh`, karena ID PSN dipertahankan antar-impor.

## 6. Kertas kerja (sheet "Format Ringkas") dan normalisasi data

Halaman **Kertas Kerja** menampilkan 92 kolom yang sama persis dengan sheet "Format Ringkas" (kolom A sampai CN), 1 baris = 1 PSN. Unduhan **Unduh kertas kerja (Excel)** memakai tata letak yang sama: header bertingkat, kolom wajib berwarna, sel profil biru muda, isian wajib kosong kuning, dan kolom otomatis abu. Isian dilakukan per PSN di halaman evaluasi, dan setiap perubahan Status Akhir langsung tercermin di **Grafik Status Harian**.

Grafik Status Harian meniru sheet aslinya: tanggal mulai dapat dipilih (14 hari), tabel jumlah PSN per status per hari, baris "posisi saat ini", tanggal riwayat terakhir, dan cek "riwayat hari ini = posisi kertas kerja".

### 6.1 Asal setiap kelompok kolom

| Kolom Format Ringkas | Sumber | Tabel/kolom |
|---|---|---|
| A1–A5, A7–A11, B1 | Profil SIRENDA | `psn`, `psn_lokasi`, `psn_kelembagaan`, `psn_sumber_dana`, `snapshot_psn` |
| A6, A12 | Isian evaluasi | `evaluasi_psn.a6_tahun_penetapan`, `jenis_psn` |
| B2.a/B2.b (7 komponen × satuan, target akhir, target kumulatif, realisasi, keterangan) | Isian; bila kosong memakai profil (progres fisik, pembiayaan, regulasi) | **`evaluasi_psn_capaian`** (baru) |
| B3.a/B3.b milestone | Isian | **`evaluasi_psn_milestone`** (baru) |
| B4 manfaat | Isian | `evaluasi_psn.b4_manfaat` (baru) |
| C1 tipologi | Isian | `evaluasi_psn.c1_tipologi` (JSON kode), `c1_lainnya` (baru) |
| C2 isu (uraian, tipologi, jalur kritis, upaya, dukungan) | Isian; bila belum ada memakai isu terbuka di profil | **`evaluasi_psn_isu`** (baru) |
| D1–D3, PIC | Isian | `evaluasi_psn` |
| Lampiran | Isian | **`evaluasi_psn_lampiran`** (baru) |
| Informasi tambahan | Isian | `evaluasi_psn.info_tambahan` (baru) |
| Cek otomatis, Status Akhir | Dihitung | `EvaluasiStatusService::analisis()`, `evaluasi_psn.status_akhir` |
| Hasil pengecekan, tindak lanjut, catatan, indikasi | Isian | `evaluasi_psn` |

### 6.2 Mengapa dinormalisasi

Sheet Format Ringkas menyimpan kelompok berulang dalam satu baris: B2 berisi 7 komponen × 5 kolom (35 kolom yang sebagian besar kosong karena Fisik dan Non Fisik saling meniadakan), sementara B3, C2, dan lampiran ditulis sebagai teks bernomor "1. … 2. …" dalam satu sel. Bila disalin apa adanya ke tabel lebar, data tersebut tidak dapat dihitung, divalidasi, atau dipertahankan saat urutan berubah. Karena itu:

1. **Kelompok berulang dipecah menjadi tabel anak memanjang** (`evaluasi_psn_capaian`, `_milestone`, `_isu`, `_lampiran`), satu baris per komponen/milestone/isu/lampiran, sejalan dengan aturan proyek untuk menyimpan data periodik secara memanjang. Keuntungannya: validasi per kolom (angka, tanggal, kode), deviasi dan agregasi dapat dihitung (misalnya jumlah isu jalur kritis per tipologi), dan tidak ada kolom kosong yang tidak relevan.
2. **Pilihan majemuk berukuran kecil dan tetap** (C1 tipologi, D2 fasilitas) disimpan sebagai larik kode JSON di `evaluasi_psn`, karena selalu dibaca dan ditulis utuh. Bila nanti diperlukan rekap lintas PSN yang berat (misalnya "fasilitas paling banyak dibutuhkan"), keduanya dapat dipindahkan ke tabel pivot tanpa mengubah tampilan.
3. **Data yang sudah ada di profil tidak digandakan.** Nilai B2 dan isu C2 dibaca dari profil selama isian kosong; isian hanya menyimpan angka yang berbeda menurut Form Pengendalian. Isu C2 menyimpan `isu_id` asal tanpa foreign key karena tabel `isu` dibangun ulang saat sinkron PEKS3.
4. Tabel anak memakai `jejak(false)` (pencatat pembuat/pengubah tanpa soft delete) dan tercatat di `audit_log` dengan `psn_id` induknya; baris dicocokkan menurut posisi saat disimpan sehingga ID stabil.

### 6.3 Isian langsung di kertas kerja

Sel yang boleh diisi pengguna dapat diklik langsung di halaman Kertas Kerja (atau dipilih dengan Tab lalu Enter). Editor kecil muncul sesuai jenis kolom: angka, teks, tanggal, pilihan, atau kotak centang (C1, D2). Tekan Enter atau klik **Simpan**: isian dikirim ke `PATCH /evaluasi-status/{psn}/sel`, dan baris yang sudah dihitung ulang (kelengkapan, rekomendasi, status akhir) langsung menggantikan baris lama. Riwayat status dan Grafik Status Harian ikut diperbarui.

| Kolom | Dapat diisi langsung oleh |
|---|---|
| A6, A12, B2 (semua komponen sesuai jenis PSN), B4, C1, D1–D3, PIC, informasi tambahan | Operator K/L, Direktorat Sektor, PMO, Super Admin (dalam cakupan unitnya) |
| Hasil pengecekan, tindak lanjut, catatan analisis | Direktorat Sektor, PMO, Super Admin |
| Indikasi status (wajib ada catatan analisis) | PMO, Super Admin |
| B3 milestone, C2 isu, lampiran | Klik sel membuka bagian formulir PSN yang sesuai (berisi beberapa baris) |
| A1–A5, A7–A11, B1, kolom otomatis | Tidak dapat diubah (sumbernya SIRENDA PEKS3 atau hasil hitung) |

Bila form PSN belum ditandai diterima, isian sel pertama sekaligus menandainya diterima hari ini (riwayat: "Form diterima (isian kertas kerja)"). Sel B2 berlatar biru (data profil) dapat ditimpa dengan angka Form Pengendalian; mengosongkannya mengembalikan nilai profil.
