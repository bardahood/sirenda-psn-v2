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
| `kl` | `kementerian-perhubungan` | Kementerian/lembaga induk unit pengampu (slug dari `GET /filter-opsi` → `data.kementerian`). Mencakup semua unitnya (Ditjen, BBWS, dll.); pengelompokan diatur di `config/psn_dashboard.php` → `kementerian` |
| `thn` | `2029,2030` | Tahun target selesai PSN pada cut-off (`snapshot_psn.tahun_selesai`); opsi di `data.tahun` |
| `psn` | `45,55` | `psn.id` — hanya PSN tertentu (maks. 100). Opsinya di `GET /filter-opsi` → `data.psn`, mengikuti cakupan akses pengguna |

## Endpoint

| Metode & rute | Izin | Cache | Isi |
|---|---|---|---|
| `GET /dashboard/kpi` | `ringkasan.lihat` | per cut-off + filter + cakupan | K1–K4 + delta vs cut-off sebelumnya |
| `GET /dashboard/distribusi?dim=klaster\|direktorat\|provinsi\|dana` | `ringkasan.lihat` | sda | P1, P7, P6, distribusi direktorat |
| `GET /dashboard/progres` | `ringkasan.lihat` | sda | P2, P3, P4, `DP` (PSN Direktif Presiden: total, on track) + jumlah per status progres |
| `GET /dashboard/tren` | `ringkasan.lihat` | sda | P5 Januari–Desember |
| `GET /dashboard/ro-kritis?limit=10` | `ringkasan.lihat` | sda | KP/RO critical path Berisiko/Terlambat |
| `GET /dashboard/tahapan` | `ringkasan.lihat` | sda | Jumlah PSN per tahap |
| `GET /dashboard/status-data` | `ringkasan.lihat` | sda | Tanggal cut-off, kelengkapan, belum terverifikasi |
| `GET /dashboard/trisula` | `ringkasan.lihat` | sda | P8 (placeholder, metodologi belum ditetapkan) |
| `GET /dashboard/timeline-dp` | `ringkasan.lihat` | sda | Klaster Direktif Presiden menuju 2029 |
| `GET /dashboard/kpi-tren` | `ringkasan.lihat` | sda | K1–K4 per cut-off terbit (maks. 12) untuk sparkline kartu |
| `GET /dashboard/distribusi?dim=pulau\|komposisi_dana` | `ringkasan.lihat` | sda | PSN per kelompok pulau (dihitung sekali per pulau); komposisi investasi per kombinasi skema yang saling lepas (Σ = K2) |
| `GET /dashboard/dp-proyek` | `ringkasan.lihat` | sda | Proyek klaster Direktif Presiden (tahun selesai, status, realisasi) untuk timeline |
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

## API masuk untuk aplikasi Dit. PSI (`/api/integrasi/psi/v1`, opsional)

Integrasi utama dengan SIF berjalan ke arah sebaliknya: SIRENDA memanggil API SIF (`psi:sinkron`, `POST /perencanaan/sinkron-psi`; lihat `docs/integrasi-psi.md`). API masuk di bawah ini tetap tersedia tetapi tidak dipakai SIF.

Mesin-ke-mesin; **tanpa sesi**. Autentikasi memakai `Authorization: Bearer <JWT RS256>`: ditandatangani kunci privat klien PSI, `iss`/`sub` = `PSI_CLIENT_ID`, `jti` sekali pakai. Token statis `PSI_TOKEN_MASUK` diterima sebagai alternatif. Tersedia opsi allowlist IP `PSI_IP_DIIZINKAN`, dengan batas 120 permintaan/menit. Kontrak lengkap ada di `docs/integrasi-psi.md`.

| Metode & rute | Isi |
|---|---|
| `GET /referensi` | Kode klaster, provinsi, jenis instansi, jenis dokumen, keputusan pleno |
| `POST /usulan` | Terima/perbarui usulan (`psi.usulan.v1`): 201 `DITERIMA`, 200 `DIPERBARUI`/`DIABAIKAN_VERSI_LAMA`, 422 |
| `POST /usulan/{psi_id}/tarik` | Pengusul menarik usulan |
| `GET /usulan/{psi_id}/hasil` | Hasil penilaian FINAL, keputusan pleno, rekomendasi resmi (`sirenda.hasil.v1`) |

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

| `GET /perencanaan?sumber=PSI\|MANUAL&tahap=`, `GET /perencanaan/{id}?tab=surat\|umum\|rincian\|kesiapan\|dokumen\|penilaian\|pleno\|rekomendasi\|riwayat` | Daftar usulan per tahap alur dan detail per tab |
| `POST /perencanaan/sinkron-psi` | Ambil usulan dari API SIF (`perencanaan.kelola`) |
| `GET /perencanaan/{id}/dokumen/{dokumen}` | Unduh berkas usulan dari SIF melalui server SIRENDA (`UsulanPsnPolicy::view`) |
| `PUT /perencanaan/{id}/pemetaan`, `POST /perencanaan/{id}/rekomendasi` (`tetapkan=1` → FINAL), `POST /perencanaan/{id}/kirim-psi` | Penetapan direktorat/klaster, rekomendasi resmi, kirim hasil ke PSI (`perencanaan.kelola`) |
| `GET /perencanaan/pleno`, `GET /perencanaan/pleno/{id}`; `POST /perencanaan/pleno`, `PUT /perencanaan/pleno/{id}`, `POST …/{id}/agenda`, `DELETE …/{id}/agenda/{agenda}`, `PUT …/{id}/keputusan`, `POST …/{id}/selesai` | Sidang pleno (lihat: `perencanaan.lihat`; ubah: `perencanaan.kelola`) |

Kontrak seluruh endpoint GET diuji di `tests/Feature/KontrakEndpointTest.php`.
