# Kriteria Selesai v1: Status dan Bukti

| Kriteria | Status | Bukti |
|---|---|---|
| Angka dashboard identik dengan query acuan | ✅ | `php artisan psn:uji-akurasi`: 110/110 cocok pada data riil; `AkurasiIndikatorTest` (data uji kasus tepi + data riil). Lihat `docs/uji-akurasi.md` |
| Semua filter berjalan | ✅ | `DashboardApiTest`, `PortofolioDetailKualitasTest` (jumlah portofolio = K1 untuk 10 kombinasi filter), `AkurasiIndikatorTest` |
| Drill-down berjalan | ✅ | Kartu → `/proyek?{filter}` (K4 → `kritis=1`) → `/proyek/{id}` → tab KP/RO/Progres (bukti); breadcrumb & Kembali mempertahankan filter (`test_detail_tab_profil_kpro_progres_dokumen`) |
| Ekspor berjalan | ✅ | PNG/CSV per panel; CSV & Excel portofolio; PDF Ringkasan Eksekutif (`EksporPetaAksesTest`) |
| Tautan URL mereproduksi tampilan | ✅ | Seluruh filter dan opsi tabel ada di query string; urutan seri deterministik (`test_urutan_seri_deterministik_untuk_url_yang_sama`) |
| Target kinerja | ✅ | Endpoint P95 ≤ 35 ms dengan cache (≤ 370 ms tanpa cache); halaman ringkasan P95 1,17 detik. Lihat `docs/kinerja-keamanan.md` |
| Uji hak akses lulus | ✅ | `KontrakEndpointTest::test_matriks_hak_akses_per_peran` (5 peran × 15 halaman/endpoint), `CakupanAksesTest`, `PerencanaanTest::test_hak_akses_sesuai_matriks`, `EksporPetaAksesTest` |
| Dokumentasi | ✅ | `docs/kamus-indikator.md`, `README.md` (snapshot, impor, uji), `docs/api.md`, `docs/rancangan-aplikasi.md`, `docs/kamus-data.md` |

## Batasan yang diketahui (di luar v1 atau menunggu keputusan)

- **Data progres fisik di sistem lama hampir kosong.** 368 dari 373 PSN berstatus "Tanpa data" sampai pelaporan berjalan di v2.
- **Keputusan terbuka Q-02 s.d. Q-14** (`docs/rancangan-aplikasi.md` §10), terutama:
  - ambang rekomendasi (Q-11);
  - sub-kriteria Lokasi dan Trisula (Q-10);
  - GeoJSON provinsi (Q-12);
  - definisi sektor (Q-06).
- **Halaman `/risiko` (heatmap 5×5) dan `/laporan` terjadwal** termasuk rilis v2.
- **CSP belum dipasang** (lihat `docs/kinerja-keamanan.md`).
- **Formulir input profil/KP/RO di v2 belum dibangun.** Selama masa transisi, data masuk lewat impor dari sistem lama (`legacy:import`).
