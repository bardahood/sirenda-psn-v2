# Integrasi Usulan PSN dengan Aplikasi Dit. PSI

Dokumen ini menjelaskan kontrak pertukaran data antara aplikasi permohonan usulan PSN milik Dit. PSI ("PSI") dan SIRENDA PSN v2 ("SIRENDA"). Ruang lingkupnya Fase 8; lihat `docs/rancangan-aplikasi.md` §8.8.

## 1. Alur bisnis

| No | Langkah | Tempat | Implementasi SIRENDA |
|---|---|---|---|
| 1 | Usulan PSN dibuat dan diinput | PSI | — |
| 2 | Data usulan dikirim ke SIRENDA | PSI → SIRENDA | `POST /api/integrasi/psi/v1/usulan` (kontrak `psi.usulan.v1`) |
| 3 | Daftar usulan ditampilkan | SIRENDA | `/perencanaan`: kartu alur per tahap, filter Sumber = Aplikasi PSI |
| 4 | Penilaian usulan diinput | SIRENDA | `/perencanaan/{id}?tab=penilaian` (gate KU1–KU3, skor kriteria, finalisasi) |
| 5 | Hasil penilaian dibahas dan dicatat di Pleno | SIRENDA | `/perencanaan/pleno`: agenda sidang, keputusan dan catatan per usulan |
| 6 | Rekomendasi PSN dientri | SIRENDA | `/perencanaan/{id}?tab=rekomendasi`: draf, lalu tetapkan FINAL |
| 7 | Hasil penilaian, catatan pleno, dan rekomendasi dikirim ke PSI | SIRENDA → PSI | Push `POST {PSI_API_URL}/usulan/{psi_id}/hasil-penilaian` dan/atau pull `GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil` (kontrak `sirenda.hasil.v1`) |

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

## 2. Autentikasi dan keamanan

- Setiap panggilan ke API masuk SIRENDA wajib membawa header `Authorization: Bearer <PSI_TOKEN_MASUK>`. Token dibandingkan dengan `hash_equals`.
- Kode respons autentikasi:
  - `503`: `PSI_TOKEN_MASUK` belum diatur, sehingga API nonaktif.
  - `401`: token salah atau tidak ada.
  - `403`: IP pemanggil tidak ada di `PSI_IP_DIIZINKAN`, bila daftar ini diisi.
- API tanpa sesi dan tanpa CSRF. Batas laju 120 permintaan/menit.
- Isian HTML dari PSI (lingkup pekerjaan, penjelasan, bagian lain) disaring dengan daftar putih (`App\Support\HtmlAman`):
  - `script`, `style`, `iframe`, `svg`, atribut `on*`, dan URL `javascript:` dibuang.
  - Tautan diberi `rel="noopener noreferrer"`.
- Gunakan HTTPS di produksi. Token cukup panjang (≥ 40 karakter acak), mis. `php -r "echo bin2hex(random_bytes(32));"`.

Variabel lingkungan (`.env`):

| Variabel | Keterangan |
|---|---|
| `PSI_TOKEN_MASUK` | Token yang dikirim PSI ke SIRENDA. Kosong = API masuk nonaktif (503). |
| `PSI_IP_DIIZINKAN` | Daftar IP server PSI, dipisahkan koma. Kosong = tidak dibatasi. |
| `PSI_API_URL` | Alamat dasar API PSI untuk push hasil, mis. `https://psi.bappenas.go.id/api/v1`. Kosong = push nonaktif; PSI mengambil hasil sendiri (pull). |
| `PSI_API_TOKEN` | Token Bearer yang dikirim SIRENDA ke PSI saat push. |

## 3. Endpoint API masuk (dipanggil PSI)

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

### 3.1 Idempotensi dan versi

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

## 4. Kontrak `psi.usulan.v1` (PSI → SIRENDA)

Contoh lengkap: [`docs/contoh/psi-usulan.json`](contoh/psi-usulan.json).

| Bagian | Field | Wajib | Keterangan / pemetaan |
|---|---|---|---|
| — | `psi_id`, `versi`, `tahun_rkp` | ya | lihat §3.1; `tahun_rkp` 2024–2100 |
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

## 5. Kontrak `sirenda.hasil.v1` (SIRENDA → PSI)

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

### 5.1 Push: endpoint yang perlu disediakan PSI

`POST {PSI_API_URL}/usulan/{psi_id}/hasil-penilaian`

- Header: `Authorization: Bearer {PSI_API_TOKEN}`, `Accept: application/json`.
- Badan: payload `sirenda.hasil.v1`.
- Respons 2xx dianggap terkirim (`kirim_status = TERKIRIM`). Selain itu, atau bila timeout 20 detik terlampaui, status menjadi `GAGAL`. Pengguna dapat mengirim ulang; jumlah percobaan dicatat.
- PSI sebaiknya idempoten terhadap kiriman ulang, misalnya menimpa hasil per `psi_id`.

Pengiriman dipicu manual oleh PMO lewat tombol **Kirim ke PSI** di tab Rekomendasi & Kirim, setelah rekomendasi FINAL. Bila rekomendasi ditetapkan ulang, rekomendasi lama menjadi `DIGANTI` dan `kirim_status` kembali `BELUM`.

### 5.2 Pull

Bila PSI tidak menyediakan endpoint push, PSI dapat memanggil `GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil` kapan saja. Contohnya berkala, atau setelah notifikasi. Nilai `tahap` menunjukkan sejauh mana proses berjalan.

## 6. Tampilan dan hak akses di SIRENDA

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

## 7. Uji coba lokal

```bash
php artisan migrate
php artisan psi:contoh           # 4 usulan CONTOH-… lewat jalur impor yang sama dengan API
php artisan psi:contoh --hapus   # bersihkan data contoh

# API (atur PSI_TOKEN_MASUK di .env lalu php artisan config:clear)
curl -X POST http://localhost:8000/api/integrasi/psi/v1/usulan \
  -H "Authorization: Bearer $PSI_TOKEN_MASUK" -H "Content-Type: application/json" \
  --data @docs/contoh/psi-usulan.json
curl -H "Authorization: Bearer $PSI_TOKEN_MASUK" \
  http://localhost:8000/api/integrasi/psi/v1/usulan/CONTOH-USL-2026-0001/hasil
```

Tes: `php artisan test --filter=IntegrasiPsiTest`.

## 8. Keputusan terbuka (perlu konfirmasi Dit. PSI)

| No | Pertanyaan | Asumsi sementara |
|---|---|---|
| Q-17 | Format klaster di PSI: kode `ref_klaster` atau teks bebas? | Kirim `klaster_kode` bila ada; teks dicocokkan ke nama. Kegagalan menjadi peringatan. |
| Q-18 | Daftar baku "Jenis Instansi" dan "Jenis PSN" di PSI | Dipetakan lewat `config/psn_dashboard.php` → `psi` |
| Q-19 | Akses berkas (PDF usulan, surat, lampiran): tautan publik, bertoken, atau disalin ke SIRENDA? | Hanya tautan ke server PSI; berkas tidak disalin |
| Q-20 | Mekanisme penerimaan hasil di PSI: push, pull, atau keduanya? Siapa pemicu kirim? | Keduanya tersedia; push dipicu manual oleh PMO |
| Q-21 | Apakah PSI perlu menerima hasil sementara, mis. penilaian FINAL sebelum pleno? | Tidak; push hanya setelah rekomendasi FINAL. Pull selalu mengembalikan keadaan terkini. |
| Q-22 | Direktorat pengampu: ditentukan PSI atau PMO SIRENDA? | Ditetapkan PMO di SIRENDA |
