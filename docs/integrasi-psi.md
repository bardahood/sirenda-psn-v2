# Integrasi Usulan PSN dengan Aplikasi Dit. PSI (SIF)

Dokumen ini menjelaskan integrasi antara Portal **SIF** (Strategic Infrastructure Financing, Portal PSN dan KPBU milik Dit. Pembiayaan Strategis dan Inovatif) dan SIRENDA PSN v2. Dalam integrasi ini SIRENDA berperan sebagai **PMO**.

Acuan dari Dit. PSI:
- *Dokumen Integrasi API Usulan PSN untuk PMO dan Pengodean PSN*, v1.3, 13 Oktober 2026;
- *Dokumentasi Tata Cara Penggunaan API Client Portal PSN dan KPBU* (autentikasi berbasis RSA key), 2026.

**SIF menyediakan seluruh API.** SIRENDA mengambil usulan dan mengirim balik hasil penilaian, sehingga SIRENDA tidak perlu membuka endpoint untuk SIF. API masuk SIRENDA yang dibuat sebelumnya (Lampiran A) tetap tersedia, tetapi tidak dipakai SIF.

## 1. Alur bisnis

| No | Langkah | Tempat | Implementasi SIRENDA |
|---|---|---|---|
| 1 | Usulan PSN dibuat dan diajukan (status `submitted`) | SIF | — |
| 2 | Usulan diambil SIRENDA | SIF → SIRENDA | `GET /` (daftar) dan `GET /{kode_usulan}` (detail). Dipicu oleh tombol **Ambil usulan dari PSI** di `/perencanaan`, perintah `php artisan psi:sinkron`, atau penjadwal tiap jam (`SIF_SINKRON_OTOMATIS=true`) |
| 3 | Daftar usulan ditampilkan | SIRENDA | `/perencanaan`: kartu alur per tahap, filter Sumber = Aplikasi PSI |
| 4 | Penilaian usulan diinput | SIRENDA | `/perencanaan/{id}?tab=penilaian` (gate KU1–KU3, skor kriteria, finalisasi) |
| 5 | Hasil penilaian dibahas dan dicatat di pleno | SIRENDA | `/perencanaan/pleno` |
| 6 | Rekomendasi PSN dientri | SIRENDA | `/perencanaan/{id}?tab=rekomendasi`: draf, lalu tetapkan FINAL |
| 7 | Hasil penilaian, catatan pleno, dan rekomendasi dikirim ke SIF | SIRENDA → SIF | `POST /{kode_usulan}/penilaian` berisi `skor_pmo` dan `catatan_pmo` (tombol **Kirim ke PSI**) |

Status akhir usulan (disetujui, ditolak, atau perlu revisi) tetap diputuskan staf Bappenas di SIF. Skor dan catatan PMO bersifat internal Bappenas dan tidak ditampilkan kepada pengusul.


Tahap alur diturunkan dari data dan tidak disimpan (`App\Services\AlurUsulan`):

| Kode | Label | Syarat |
|---|---|---|
| `DITERIMA` | Diterima | Belum ada penilaian |
| `PENILAIAN` | Sedang dinilai | Ada penilaian berstatus DRAFT |
| `DINILAI` | Penilaian final | Ada penilaian FINAL, belum ada keputusan pleno |
| `PLENO` | Dibahas pleno | Ada keputusan pleno, belum ada rekomendasi FINAL |
| `REKOMENDASI` | Rekomendasi ditetapkan | Ada rekomendasi FINAL, belum terkirim ke PSI |
| `TERKIRIM` | Terkirim ke PSI | `usulan_psi.kirim_status = TERKIRIM` |
| `DITARIK` | Ditarik | PSI menarik usulan (`status_psi = DITARIK`) |

## 2. Koneksi, autentikasi, dan enkripsi

Base URL: `https://<host-sif>/api/v1/external/usulan-psn`. Batasnya 60 request per menit, dan hanya usulan berstatus `submitted` atau `under_review` yang dapat diakses (selain itu 404/422).

Setiap request dari SIRENDA (`App\Services\SifClient`) membawa:

| Header | Isi |
|---|---|
| `X-API-Key` | API key dari SIF (salah atau kosong → 401) |
| `X-Client-Id` | Client ID dari SIF, mis. `konsultan-psn-snkv93` |
| `X-Session-Key` | base64 kunci sesi AES-256 (32 byte acak, baru setiap request) yang dibungkus **RSA-OAEP, SHA-256, MGF1-SHA-256** dengan kunci publik RSA client (tanpa header ini → 403 *Encryption required*) |
| `Content-Type` | `application/vnd.sif.encrypted+json` untuk body POST; body JSON polos ditolak (415) |

- **Body POST** dikirim sebagai amplop `{"encrypted": true, "algorithm": "AES-256-GCM", "format": "iv+ciphertext+tag", "data": "<base64>"}`.
- **Field `data` respons** dienkripsi dengan kunci sesi yang sama, dalam format `base64(iv[12] + ciphertext + tag[16])`. Field `status` dan `message` tetap teks biasa.
- **Unduhan berkas** (PDF dan lampiran) berupa biner tanpa amplop. Berkas dilindungi `X-API-Key`, sehingga diunduh oleh server SIRENDA lalu diteruskan ke pengguna yang berhak (`GET /perencanaan/{usulan}/dokumen/{dokumen}`). Pengguna tidak memerlukan akun SIF.
- PHP hanya menyediakan OAEP dengan SHA-1, sehingga padding OAEP-SHA256 disusun sesuai RFC 8017 di `App\Support\Integrasi\SifKripto`. Implementasi ini diverifikasi silang dengan `openssl pkeyutl -pkeyopt rsa_oaep_md:sha256 -pkeyopt rsa_mgf1_md:sha256` (kedua arah, 20/20). AES-256-GCM diverifikasi silang dengan pustaka `cryptography` Python.
- **Kunci privat tidak diperlukan SIRENDA.** Kunci sesi dibungkus dengan kunci publik, dan respons dibuka dengan kunci sesi milik SIRENDA sendiri. Jangan simpan kunci privat di server SIRENDA maupun di repositori.

Variabel lingkungan (`.env`):

| Variabel | Keterangan |
|---|---|
| `SIF_BASE_URL` | `https://<host-sif>/api/v1/external/usulan-psn` |
| `SIF_API_KEY` | API key dari SIF (belum diterima) |
| `SIF_CLIENT_ID` | Client ID, mis. `konsultan-psn-snkv93` |
| `SIF_KUNCI_PUBLIK` | Path kunci publik RSA (PEM). Bawaan: `storage/app/private/kunci/sif-public.pem` (folder ini tidak ikut git) |
| `SIF_TAHUN_RKP` | Opsional. Tahun RKP usulan; kosong = tahun `submitted_at` + 1 |
| `SIF_SINKRON_OTOMATIS` | `true` agar usulan diambil otomatis setiap jam. Perlu cron `php artisan schedule:run` setiap menit |

## 3. Pengambilan usulan (SIF → SIRENDA)

`App\Services\SifSinkronService` membaca semua halaman daftar (`per_page` 100), mengambil detail tiap usulan, memetakannya ke struktur usulan SIRENDA, lalu menyimpannya lewat jalur impor yang sama dengan API masuk (`PsiImportService`).

- **Idempoten per `kode_usulan`.** Sidik jari SHA-256 dari isi detail disimpan. Bila isinya tidak berubah, usulan dilewati (status "tetap").
- **Versi.** Versi naik setiap kali isi berubah. Perubahan `status` SIF (`submitted`/`under_review`) tidak dihitung. Bila penilaian sudah FINAL, usulan ditandai **Versi baru dari PSI** (perlu tinjau ulang).
- **Galat per usulan tidak menghentikan sinkron.** Setiap galat dicatat di tab Riwayat (aksi *Ambil usulan*) dan di ringkasan sinkron.
- Penilaian, pleno, dan rekomendasi yang sudah dibuat di SIRENDA tidak disentuh. Penetapan PMO (direktorat pengampu, klaster, jenis pengusul) dipertahankan, kecuali nilai dari SIF dapat dipetakan.

### 3.1 Pemetaan data SIF → SIRENDA

| SIF (model data §3–4) | SIRENDA | Catatan |
|---|---|---|
| `kode_usulan` | `usulan_psi.psi_id`; juga id surat | `USL-YYYYMMDD-NNNN` |
| `submitted_at` | `diajukan_at`; tahun RKP = tahun + 1 | SIF tidak mengirim tahun RKP (Q-28) |
| `surat.{nomor, sifat, tempat, tanggal, ditujukan_kepada}` | Tab Surat Pengusulan | |
| `data_umum.instansi_pengusul` / `proposer.instansi` | Pengusul | |
| `proposer.jenis_instansi` | Jenis pengusul | `kementerian_lembaga` → K/L; `pemerintah_daerah` → Pemda; `bumn`, `swasta` → BUMN/Swasta |
| `proposer.{nama, email}`, `penandatangan.jabatan` | Narahubung | |
| `data_umum.nama_usulan_psn` (atau `proyek.nama`) | Nama usulan | |
| `data_umum.klaster` | Klaster | Nama klaster SIF identik dengan `ref_klaster`; "Lainnya" memakai `klaster_lainnya` dan menjadi peringatan pemetaan |
| `data_umum.{sektor, jenis_psn}` | Sektor, jenis PSN | `jenis_psn` "Infrastruktur" mengaktifkan KK3–KK4 |
| `rincian_tambahan.capex` | Nilai investasi | Teks seperti "Rp 12.500.000.000.000" atau "12,5 triliun" diurai; bila gagal dibiarkan kosong (teks asli tetap tampil) |
| `rincian_usulan.profil_proyek.lokasi`, `proyek.lokasi` | Lokasi provinsi | Nama provinsi yang disebut di teks dicocokkan ke `ref_wilayah` |
| `profil_proyek.{tujuan, sasaran, rangkuman_lingkup}` | Tab Rincian Usulan | |
| `kriteria_utama`, `kriteria_pendukung`, `kriteria_kesiapan`, `rincian_tambahan`, `proyek_list` (jamak), `catatan_review_bappenas` | Tab Rincian Usulan, berupa tabel | Bahan bagi penilai untuk mengisi KU/KP/KK |
| `kesiapan.jalur_kritis[]{id, periode, kegiatan, kritis, pendahulu, strategi}` | Diagram dan tabel jalur kritis; strategi di penjelasan | Pendahulu yang tidak dikenal menjadi peringatan |
| `kesiapan.struktur_kelembagaan`, `manajemen_risiko`, `dokumen_lampiran` | Tab Kesiapan Proyek, berupa tabel | |
| `files.dokumen_usulan` | PDF & Lampiran: Dokumen PDF Usulan | |
| `files.lampiran[]` + `attachments[]` | PDF & Lampiran | `surat_persetujuan_menteri`, `surat_rekomendasi` → Surat Pengusulan; lainnya → Lampiran Pendukung |

Contoh detail lengkap ada di `docs/contoh/sif-usulan-detail.json`.

## 4. Pengiriman hasil (SIRENDA → SIF)

`POST /{kode_usulan}/penilaian` (`PsiHasilService::payloadSif`), dikirim setelah rekomendasi resmi FINAL melalui tombol **Kirim ke PSI**:

| Field SIF | Isi dari SIRENDA |
|---|---|
| `skor_pmo` | Nilai akhir penilaian FINAL (0–100) |
| `catatan_pmo` | Ringkasan rekomendasi resmi (label, nomor dokumen, tanggal, catatan); keputusan pleno beserta catatan dan tindak lanjut; nilai akhir dan status gate; skor komponen; catatan penilaian; temuan per kriteria. Maksimal 10.000 karakter |
| `field_notes`, `attachment_reviews` | Belum dikirim, karena kunci `field_key` SIF belum dipetakan ke kriteria SIRENDA (Q-29) |

- Mengirim ulang akan menimpa skor dan catatan sebelumnya di SIF.
- Status kiriman (Belum, Terkirim, Gagal) dan jumlah percobaan tampil di tab Rekomendasi & Kirim. Setiap percobaan tercatat di tab Riwayat dan di jejak audit (`PUBLISH`).
- Bila usulan di SIF sudah tidak berstatus `submitted`/`under_review`, SIF menolak dengan 422 dan status kiriman menjadi Gagal.

## 5. Tampilan dan hak akses di SIRENDA



- `/perencanaan` menampilkan kartu alur per tahap (klik untuk memfilter), filter sumber (Aplikasi PSI / Input SIRENDA), dan kolom Sumber, Rekomendasi (resmi vs hitungan sistem), serta Tahap alur.
- Detail usulan berisi stepper alur dan tab:
  - Surat Pengusulan, Data Umum (dengan Penetapan di SIRENDA), Rincian Usulan, Kesiapan Proyek (diagram jalur kritis), PDF & Lampiran;
  - Penilaian, Pleno, Rekomendasi & Kirim, Riwayat (log integrasi dan jejak audit).
  - Usulan input SIRENDA hanya punya tab Data Umum, Penilaian, Pleno, Rekomendasi, dan Riwayat.
- `/perencanaan/pleno` menampilkan daftar sidang. Detail sidang memuat agenda, keputusan, catatan, tindak lanjut, dan tandai selesai. Catatan wajib diisi untuk keputusan selain "Disetujui". Keputusan terkunci setelah sidang selesai.

| Aksi | Izin |
|---|---|
| Lihat daftar/detail usulan, daftar & detail sidang pleno | `perencanaan.lihat`; Operator/Direktorat hanya usulan unitnya |
| Penilaian (isi skor) | `UsulanPsnPolicy::nilai` (Direktorat pengampu, PMO) |
| Penetapan direktorat/klaster/jenis pengusul, sidang pleno, rekomendasi, kirim ke PSI | `perencanaan.kelola` (PMO, Super Admin) |

Usulan PSI yang **belum punya direktorat pengampu** hanya terlihat oleh peran berlingkup nasional. PMO menetapkannya di tab Data Umum.

| Ambil usulan dari PSI (tombol/perintah) | `perencanaan.kelola` (PMO, Super Admin) atau penjadwal |
| Membuka berkas usulan dari SIF | Siapa pun yang berhak melihat usulan tersebut |

## 6. Uji coba

```bash
php artisan migrate
# Simpan kunci publik dari Dit. PSI (bukan kunci privat):
#   storage/app/private/kunci/sif-public.pem
# Isi SIF_BASE_URL, SIF_API_KEY, SIF_CLIENT_ID di .env, lalu:
php artisan config:clear
php artisan psi:sinkron                 # ambil semua usulan submitted/under_review
php artisan psi:sinkron --belum-dinilai # hanya yang belum punya skor PMO

php artisan psi:contoh                  # data contoh tanpa koneksi SIF (4 usulan CONTOH-…)
php artisan psi:contoh --hapus
```

Tes otomatis memakai server SIF tiruan yang benar-benar mendekripsi dan mengenkripsi (`tests/Support/SifTiruan.php`): `php artisan test --filter='SifIntegrasiTest|IntegrasiPsiTest'`.

## 7. Keputusan terbuka (perlu konfirmasi Dit. PSI)


| No | Pertanyaan | Asumsi sementara |
|---|---|---|
| Q-18 | Daftar baku "Jenis Instansi" dan "Jenis PSN" di PSI | Dipetakan lewat `config/psn_dashboard.php` → `psi` |
| Q-21 | Apakah PSI perlu menerima hasil sementara, mis. penilaian FINAL sebelum pleno? | Tidak; push hanya setelah rekomendasi FINAL. Pull selalu mengembalikan keadaan terkini. |
| Q-22 | Direktorat pengampu: ditentukan PSI atau PMO SIRENDA? | Ditetapkan PMO di SIRENDA |

| Q-28 | SIF tidak mengirim tahun RKP usulan | Tahun `submitted_at` + 1 (mis. diajukan 2026 → RKP 2027); dapat ditimpa `SIF_TAHUN_RKP` |
| Q-29 | Daftar `field_key` untuk `field_notes` dan apakah PMO perlu mengisi `attachment_reviews` | Belum dikirim; temuan per kriteria masuk `catatan_pmo` |
| Q-30 | Kunci untuk membungkus kunci sesi: dokumen Portal menyebut kunci publik **server**, dokumen Integrasi menyebut kunci publik **client** | Memakai kunci publik client (`konsultan-psn-public.pem`) sesuai dokumen Integrasi v1.3; cukup ganti berkas `SIF_KUNCI_PUBLIK` bila ternyata kunci server |
| Q-31 | `X-API-Key` dan alamat host SIF (uji coba & produksi) | Belum diterima; integrasi siap begitu keduanya diisi di `.env` |
| Q-32 | Kunci privat client ikut dikirim melalui kanal biasa, padahal dokumen Portal §7 melarangnya | Kunci privat tidak dipakai SIRENDA; sebaiknya Dit. PSI membuat pasangan kunci baru untuk produksi |

## Lampiran A. API masuk SIRENDA (opsional, tidak dipakai SIF)

Sebelum spesifikasi SIF diterima, SIRENDA menyediakan API masuk agar aplikasi PSI dapat **mengirim** usulan dan **mengambil** hasil. Setelah dokumen Integrasi v1.3 menetapkan bahwa SIF menyediakan seluruh API, API ini tidak dipakai. API ini tetap tersedia (nonaktif bila `PSI_KUNCI_PUBLIK` dan `PSI_TOKEN_MASUK` kosong) dan dipakai `psi:contoh` sebagai jalur impor bersama.


### A.1 Autentikasi dan keamanan

Setiap panggilan ke API masuk SIRENDA wajib membawa header `Authorization: Bearer <token>`. Ada dua jenis token yang diterima.

**a. JWT RS256 (utama).** PSI membuat JWT baru untuk **setiap permintaan** dan menandatanganinya dengan kunci privat klien. SIRENDA memverifikasinya dengan kunci publik pasangannya (`PSI_KUNCI_PUBLIK`).

| Bagian | Ketentuan |
|---|---|
| Header | `{"alg":"RS256","typ":"JWT"}`. Algoritme lain (`none`, `HS256`, dst.) ditolak. |
| `iss` atau `sub` | Client ID yang terdaftar (`PSI_CLIENT_ID`), mis. `konsultan-psn-snkv93` |
| `iat`, `exp` | Wajib, dalam detik Unix. `exp − iat` ≤ 300 detik. Toleransi selisih jam server 60 detik. |
| `jti` | Wajib, unik per permintaan (mis. UUID). Token dengan `jti` yang sama ditolak (anti-replay). |
| `aud` | Opsional. Bila dikirim dan `PSI_JWT_AUDIENS` diisi, nilainya harus sama. |

Contoh klaim: `{"iss":"konsultan-psn-snkv93","iat":1792656000,"exp":1792656120,"jti":"6f1c…"}`.

**b. Token statis `PSI_TOKEN_MASUK` (alternatif/transisi).** Dibandingkan dengan `hash_equals`. Kosongkan bila hanya JWT yang dipakai.

- SIRENDA **hanya memerlukan kunci publik**. Kunci privat disimpan di server PSI dan tidak boleh berada di server SIRENDA maupun di repo (`*.pem` sudah diabaikan git).
- Kode respons autentikasi:
  - `503`: kunci publik tidak terbaca dan `PSI_TOKEN_MASUK` kosong, sehingga API nonaktif.
  - `401`: token salah, tidak ada, kedaluwarsa, client ID tidak dikenal, atau `jti` dipakai ulang. Alasannya disebutkan di `message`.
  - `403`: IP pemanggil tidak ada di `PSI_IP_DIIZINKAN`, bila daftar ini diisi.
- API tanpa sesi dan tanpa CSRF. Batas laju 120 permintaan/menit.
- Isian HTML dari PSI (lingkup pekerjaan, penjelasan, bagian lain) disaring dengan daftar putih (`App\Support\HtmlAman`):
  - `script`, `style`, `iframe`, `svg`, atribut `on*`, dan URL `javascript:` dibuang.
  - Tautan diberi `rel="noopener noreferrer"`.
- Gunakan HTTPS di produksi. Token cukup panjang (≥ 40 karakter acak), mis. `php -r "echo bin2hex(random_bytes(32));"`.

Variabel lingkungan (`.env`):

| Variabel | Keterangan |
|---|---|
| `PSI_CLIENT_ID` | Client ID PSI yang diizinkan, mis. `konsultan-psn-snkv93` |
| `PSI_KUNCI_PUBLIK` | Path berkas kunci publik PEM, relatif terhadap root proyek atau absolut. Rekomendasi: `storage/app/private/kunci/psi-public.pem` |
| `PSI_JWT_AUDIENS` | Opsional, nilai `aud` yang diharapkan |
| `PSI_TOKEN_MASUK` | Opsional, token statis alternatif. Bila kunci publik dan token sama-sama kosong, API masuk nonaktif (503). |
| `PSI_IP_DIIZINKAN` | Daftar IP server PSI, dipisahkan koma. Kosong = tidak dibatasi. |

### A.2 Endpoint API masuk (dipanggil PSI)

Prefiks: `/api/integrasi/psi/v1`. Semua respons berbentuk JSON `{ "data": ... }`; galat validasi `422 { "message", "errors" }`.

| Metode & rute | Keterangan | Respons |
|---|---|---|
| `GET /referensi` | Kode referensi untuk pemetaan di PSI: klaster (kode, nama), provinsi (kode BPS), jenis instansi yang dikenali, jenis dokumen, keputusan pleno | 200 |
| `POST /usulan` | Kirim usulan baru atau perbaruan (badan: `psi.usulan.v1`) | 201 `DITERIMA`, 200 `DIPERBARUI` / `DIABAIKAN_VERSI_LAMA`, 422 |
| `POST /usulan/{psi_id}/tarik` | Pengusul menarik usulan. Badan opsional `{ "alasan": "..." }` | 200 `DITARIK`, 404 |
| `GET /usulan/{psi_id}/hasil` | Ambil hasil penilaian, pleno, dan rekomendasi (`sirenda.hasil.v1`) | 200, 404 |

Contoh respons `POST /usulan`:

```json
{ "data": {
  "psi_id": "USL-2026-0001", "sirenda_id": 57, "status": "DITERIMA",
  "peringatan": ["Klaster \"Jalan Tol\" tidak dikenali; tetapkan klaster di SIRENDA."],
  "url_hasil": "https://sirenda.example/api/integrasi/psi/v1/usulan/USL-2026-0001/hasil"
} }
```

#### A.2.1 Idempotensi dan versi

- Kunci usulan adalah `psi_id`, unik dan stabil, dengan karakter `A–Z a–z 0–9 . _ -`, maksimal 64.
- `versi` (bilangan bulat ≥ 1) wajib dinaikkan PSI setiap kali isian berubah.
  - Versi ≤ versi tersimpan: diabaikan dengan status `DIABAIKAN_VERSI_LAMA`, tanpa perubahan data. Aman untuk dikirim ulang.
  - Versi lebih baru: data umum, surat, rincian, dan kesiapan diperbarui. Jalur kritis dan daftar dokumen **diganti seluruhnya**.
  - Penilaian, pleno, dan rekomendasi yang sudah dibuat di SIRENDA tidak disentuh.
- Bila versi baru datang setelah penilaian FINAL, usulan ditandai `perlu_tinjau_ulang`. Penilai melihat badge "Versi baru dari PSI" dan memutuskan apakah penilaian perlu dibuka kembali.
- Satu impor = satu transaksi dan satu entri `audit_log` (`sumber = INTEGRASI_PSI`). Setiap panggilan juga dicatat di `integrasi_psi_log` (arah, aksi, status HTTP, pesan, payload).
- Direktorat pengampu hanya ditetapkan di SIRENDA dan tidak pernah diubah oleh impor.
- Klaster dan jenis pengusul mengikuti nilai PSI yang **dapat dipetakan**. Bila nilai versi baru tidak dapat dipetakan, penetapan PMO yang ada dipertahankan.
- Status infrastruktur hanya diperbarui bila `jenis_psn` dikirim.

### A.3 Kontrak `psi.usulan.v1` (PSI → SIRENDA)

Contoh lengkap: [`docs/contoh/psi-usulan.json`](contoh/psi-usulan.json).

| Bagian | Field | Wajib | Keterangan / pemetaan |
|---|---|---|---|
| — | `psi_id`, `versi`, `tahun_rkp` | ya | lihat §A.2.1; `tahun_rkp` 2024–2100 |
| — | `status` | | `DIAJUKAN` / `DIREVISI` |
| — | `diajukan_at` | | ISO 8601 |
| `surat` | `psi_surat_id` (wajib bila `surat` ada), `nomor`, `sifat`, `lampiran`, `tempat`, `tanggal`, `ditujukan_kepada`, `perihal` | | Kop Surat. Satu surat dapat memuat beberapa usulan dengan `psi_surat_id` yang sama; tab Surat menampilkan semuanya. |
| `pengusul` | `instansi` | ya | Teks nama instansi pengusul |
| | `jenis_instansi` | | Dipetakan ke `jenis_pengusul` (KL / PEMDA / BUMN_SWASTA) lewat `config('psn_dashboard.psi.jenis_instansi')` |
| | `kontak.{nama,jabatan,email,telepon}` | | Narahubung |
| `data_umum` | `nama` | ya | Nama usulan PSN |
| | `klaster_kode` / `klaster` | | Dicocokkan ke `ref_klaster` lewat kode, lalu nama. Gagal = peringatan; klaster ditetapkan manual. |
| | `sektor`, `jenis_psn` | | Disimpan sebagai teks. `jenis_psn` yang ada di `jenis_psn_infrastruktur` mengaktifkan sub-kriteria KK3–KK4. |
| | `nilai_investasi_rp` | | Rupiah penuh |
| | `lokasi[].provinsi_kode`, `kabupaten_kode` | | Kode wilayah BPS (`GET /referensi`). Kode tidak dikenal = peringatan. |
| `rincian` | `tujuan`, `sasaran` | | Teks |
| | `lingkup_pekerjaan_html` | | Rich text (disaring) |
| | `bagian_lain[]{judul, isi_html}` | | Bagian Profil Proyek lain |
| `kesiapan` | `jalur_kritis[]{kode, kegiatan, target_selesai, sebelumnya[], jalur_kritis}` | | `kode` unik dalam satu usulan; `sebelumnya` merujuk kode lain; `jalur_kritis=true` digambar kuning |
| | `penjelasan_html`, `diagram_url`, `bagian_lain[]` | | Penjelasan tambahan |
| `dokumen[]` | `jenis` (`PDF_USULAN` / `SURAT_PENGUSULAN` / `LAMPIRAN`), `url` | ya | Tautan berkas di server PSI |
| | `judul`, `nama_berkas`, `ukuran`, `sha256` | | |

Nilai yang tidak dapat dipetakan **tidak menolak** usulan. Nilai itu dikembalikan di `peringatan`, disimpan di `usulan_psi.peringatan_impor`, dan ditampilkan di tab Data Umum agar PMO menetapkannya.

### A.4 Kontrak `sirenda.hasil.v1` (SIRENDA → PSI)

Bentuk payload sama untuk push dan pull:

```json
{
  "skema": "sirenda.hasil.v1",
  "psi_id": "USL-2026-0001", "sirenda_id": 57, "versi_usulan": 1,
  "tahap": "REKOMENDASI",
  "penilaian": {
    "id": 12, "forum": "Penilaian Usulan RKP 2027", "tanggal": "2026-10-20",
    "gate_lulus": true, "gate_gagal": [],
    "skor_komponen": { "kesiapan": 80.0, "manfaat": 75.0 },
    "nilai_akhir": 79.25, "rekomendasi_sistem": "DIREKOMENDASIKAN", "catatan": null,
    "kriteria": [ { "kode": "KU1", "kelompok": "Gate", "uraian": "...", "berlaku": true, "nilai": 1, "temuan": null } ]
  },
  "pleno": [ { "nama": "Rapat Pleno II", "tanggal": "2026-10-24", "keputusan": "DISETUJUI_CATATAN",
               "keputusan_label": "Disetujui dengan catatan", "catatan": "...", "tindak_lanjut": "..." } ],
  "rekomendasi": { "nilai": "DIREKOMENDASIKAN", "label": "Direkomendasikan", "catatan": "...",
                   "nomor_dokumen": "BA-01/PLENO/2026", "tanggal": "2026-10-22",
                   "ditetapkan_oleh": "Nama Pejabat", "ditetapkan_at": "2026-10-22T12:19:00+07:00" },
  "dibuat_at": "2026-10-22T12:30:00+07:00"
}
```

Penjelasan:

- `penilaian` hanya berisi penilaian **FINAL** terbaru; bernilai `null` bila belum ada.
- `kriteria[].berlaku = false` menandai sub-kriteria yang tidak berlaku untuk jenis pengusul/proyek ini dan tidak ikut dihitung.
- `pleno` hanya memuat agenda yang sudah diputus.
- `rekomendasi` hanya berisi rekomendasi berstatus FINAL.
- Nilai `rekomendasi.nilai`: `DIREKOMENDASIKAN`, `DIPERTIMBANGKAN`, `TIDAK_DIREKOMENDASIKAN`, `DITOLAK`.

#### A.4.1 Pull

Bila PSI tidak menyediakan endpoint push, PSI dapat memanggil `GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil` kapan saja. Contohnya berkala, atau setelah notifikasi. Nilai `tahap` menunjukkan sejauh mana proses berjalan.
