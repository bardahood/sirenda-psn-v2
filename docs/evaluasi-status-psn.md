# Evaluasi Status PSN (Keberlanjutan PSN pada RKP Berikutnya)

Modul ini memindahkan kertas kerja **"Input Form Pengendalian PSN 2026 v4"** ke dalam dashboard. Tujuannya menentukan apakah setiap PSN **dipertahankan** atau **dikeluarkan** dari daftar PSN pada RKP berikutnya (bawaan RKP 2027, `EVALUASI_TAHUN_RKP`).

Menu: **Evaluasi** (`/evaluasi-status`).

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
