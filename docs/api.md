# Daftar Endpoint API v1

Semua endpoint berada di bawah `/api/v1`. Autentikasi memakai sesi web aplikasi (satu origin, cookie sesi + CSRF untuk metode non-GET), dengan middleware `auth`, `akun.aktif`, dan izin per modul. Tamu menerima **401**, pengguna tanpa izin **403**, dan objek di luar cakupan akses pengguna **404**.

Bentuk respons: `{ "data": …, "meta": { "cutoff": …, "filter": {…}, … } }`.

## Filter global

Berlaku untuk semua endpoint dashboard, portofolio, kualitas data, dan peta. Nilai yang tidak valid menghasilkan **422**; cut-off yang belum terbit menghasilkan **404**.

| Parameter | Contoh | Keterangan |
|---|---|---|
| `periode` | `2026-09` | Cut-off terbit (bawaan: terbaru) |
| `prov` | `31,32` | Kode provinsi Kemendagri |
| `klaster` | `3,7` | `ref_klaster.id` |
| `dit` | `12` | `ref_unit_kerja.id` (direktorat) |
| `status` | `terlambat,on_track` | `on_track`, `berisiko`, `terlambat`, `tanpa_data` |
| `kat` | `psn` | `psn` atau `pkpn` |
| `dana` | `kpbu` | `apbn`, `apbd`, `kpbu`, `lainnya` |

## Endpoint

| Metode & rute | Izin | Cache | Isi |
|---|---|---|---|
| `GET /dashboard/kpi` | `ringkasan.lihat` | per cut-off + filter + cakupan | K1–K4 + delta vs cut-off sebelumnya |
| `GET /dashboard/distribusi?dim=klaster\|direktorat\|provinsi\|dana` | `ringkasan.lihat` | sda | P1, P7, P6, distribusi direktorat |
| `GET /dashboard/progres` | `ringkasan.lihat` | sda | P2, P3, P4 + jumlah per status progres |
| `GET /dashboard/tren` | `ringkasan.lihat` | sda | P5 Januari–Desember |
| `GET /dashboard/ro-kritis?limit=10` | `ringkasan.lihat` | sda | KP/RO critical path Berisiko/Terlambat |
| `GET /dashboard/tahapan` | `ringkasan.lihat` | sda | Jumlah PSN per tahap |
| `GET /dashboard/status-data` | `ringkasan.lihat` | sda | Tanggal cut-off, kelengkapan, belum terverifikasi |
| `GET /dashboard/trisula` | `ringkasan.lihat` | sda | P8 (placeholder, metodologi belum ditetapkan) |
| `GET /dashboard/timeline-dp` | `ringkasan.lihat` | sda | Klaster Direktif Presiden menuju 2029 |
| `GET /dashboard/aktivitas` | `ringkasan.lihat` | tanpa cache | Aktivitas terbaru dari jejak audit |
| `GET /proyek` | `portofolio.lihat` | 5 menit | Portofolio (paginasi). Opsi: `q`, `urut`, `arah`, `kritis`, `tahap`, `nonaktif`, `per_halaman`, `page`, `format=csv\|xlsx` |
| `GET /proyek/{id}` | `detail.lihat` + `PsnPolicy::view` | tanpa cache | Header, profil, KP/RO, progres |
| `GET /kualitas-data` | `kualitas.lihat` | harian | KPI kualitas, per sektor, heatmap, field kosong, aktivitas |
| `GET /peta` | `ringkasan.lihat` | sda dashboard | Jumlah PSN per provinsi + koordinat |
| `GET /usulan/{id}/skor[?penilaian=id]` | `perencanaan.lihat` + `UsulanPsnPolicy::view` | tanpa cache | Hasil ScoringService: gate, komponen, nilai akhir, rekomendasi |
| `GET /risiko/ringkasan[?kategori=]` | `risiko.lihat` | sda dashboard | Matriks 5×5 harapan & aktual (`sel`, `berskala`, `tanpa_skala`, `per_level`), pipeline regulasi, ringkasan isu, opsi kategori |
| `GET /risiko/register` | `risiko.lihat` | tanpa cache | Register risiko (paginasi, skor residual menurun). Opsi: `kategori`, `jenis=harapan\|aktual` + `kemungkinan` + `dampak` (1–5), `level`, `q`, `page` |
| `GET /risiko/isu` | `risiko.lihat` | tanpa cache | Isu terbuka (lewat tenggat di atas). Opsi: `lewat_tenggat`, `termasuk_selesai`, `page` |
| `GET /filter-opsi` | login | 10 menit | Opsi filter global dari tabel referensi |
| `GET /kamus-indikator` | login | — | Teks tooltip ⓘ |

Cache dashboard dibatalkan otomatis saat snapshot cut-off diterbitkan (`DashboardCache::flush`).

## Halaman dan unduhan (non-API)

| Rute | Keterangan |
|---|---|
| `GET /laporan/ringkasan.pdf?{filter}` | PDF Ringkasan Eksekutif (angka identik dengan dashboard) |
| `GET /laporan` | Arsip laporan per cut-off terbit (`laporan.lihat`) |
| `GET /laporan/risiko.xlsx?{filter}` | Excel register risiko per cut-off (`risiko.lihat`) |
| `GET /laporan/pengisian.xlsx?periode=` | Excel rekap status pengisian & verifikasi per cut-off (`laporan.lihat`) |
| `GET /pengisian[?periode=&status=&q=&milik=]`, `GET /pengisian/{periode}/{psn}` | Daftar & formulir pengisian (`detail.input`; PSN dibatasi cakupan) |
| `POST /pengisian/{periode}/{psn}` (+ `ajukan=1`) | Simpan draf / ajukan (`PsnPolicy::update`; risiko & isu butuh `risiko.input`) |
| `POST /pengisian/{periode}/{psn}/verifikasi` (`keputusan=setuju\|kembalikan`, `catatan`) | Verifikasi (`PsnPolicy::verifikasi`); tercatat VERIFY/RETURN |
| `GET /dashboard`, `/proyek`, `/proyek/{id}?tab=`, `/perencanaan`, `/perencanaan/{id}`, `/peta`, `/kualitas-data`, `/risiko`, `/kamus-indikator` | Halaman aplikasi |
| `GET /pengaturan/{pengguna,cutoff,master}`, `POST /pengaturan/pengguna`, `PUT /pengaturan/pengguna/{id}`, `POST /pengaturan/pengguna/{id}/reset-sandi`, `POST /pengaturan/cutoff`, `PUT /pengaturan/master/{klaster,sub-klaster,unit}/{id}` | Pengaturan (izin `pengaturan.kelola`, tercatat di jejak audit) |
| `POST /perencanaan`, `POST /perencanaan/{id}/penilaian`, `PUT /perencanaan/penilaian/{id}/skor`, `POST /perencanaan/penilaian/{id}/final`, `POST /perencanaan/penilaian/{id}/buka` | Aksi penilaian usulan (diotorisasi `UsulanPsnPolicy`, tercatat di jejak audit) |

Kontrak seluruh endpoint GET diuji di `tests/Feature/KontrakEndpointTest.php`.
