# Kamus Data SIRENDA PSN v2

> Dibangkitkan otomatis oleh `php artisan docs:kamus-data` dari skema basis data. Jangan diedit manual.

## `audit_log`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `user_id` | bigint(20) unsigned | ya | → `users.id` |  |
| `user_label` | varchar(255) | ya |  | username/email saat kejadian, termasuk riwayat impor |
| `tabel` | varchar(64) |  |  |  |
| `record_id` | bigint(20) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned | ya |  | untuk "Aktivitas Terbaru" & riwayat per proyek |
| `aksi` | varchar(20) |  |  | CREATE\|UPDATE\|DELETE\|RESTORE\|SUBMIT\|VERIFY\|RETURN\|PUBLISH |
| `nilai_lama` | longtext | ya |  |  |
| `nilai_baru` | longtext | ya |  |  |
| `ip_address` | varchar(45) | ya |  |  |
| `user_agent` | varchar(255) | ya |  |  |
| `sumber` | varchar(20) |  |  | APLIKASI\|IMPOR_LEGACY |
| `created_at` | timestamp |  |  |  |

## `indikator`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `uraian` | text |  |  |  |
| `satuan` | varchar(100) | ya |  |  |
| `baseline` | varchar(100) | ya |  |  |
| `baseline_tahun` | smallint(5) unsigned | ya |  |  |
| `target_akhir` | decimal(24,4) | ya |  |  |
| `capaian` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `indikator_target`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `indikator_id` | bigint(20) unsigned |  | → `indikator.id` |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `target` | decimal(24,4) | ya |  |  |
| `realisasi` | decimal(24,4) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `isu`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `kegiatan_id` | bigint(20) unsigned | ya | → `kegiatan.id` |  |
| `uraian` | text |  |  |  |
| `kebutuhan_dukungan` | text | ya |  |  |
| `pic_unit_kerja_id` | bigint(20) unsigned | ya | → `ref_unit_kerja.id` |  |
| `pic_nama` | varchar(255) | ya |  |  |
| `tenggat` | date | ya |  |  |
| `status` | varchar(20) |  |  | TERBUKA\|PROSES\|SELESAI |
| `tanggal_selesai` | date | ya |  |  |
| `tindak_lanjut` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `kegiatan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_ref` | varchar(40) | ya |  | psn_kegiatan:{id} atau psn_kegiatan_cp:{id} |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `parent_id` | bigint(20) unsigned | ya | → `kegiatan.id` |  |
| `jenis_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `nama` | text |  |  |  |
| `lokasi` | text | ya |  |  |
| `pelaksana` | varchar(255) | ya |  |  |
| `satuan_1` | varchar(100) | ya |  |  |
| `satuan_2` | varchar(100) | ya |  |  |
| `baseline_1` | decimal(24,4) | ya |  |  |
| `baseline_2` | decimal(24,4) | ya |  |  |
| `baseline_tahun_1` | smallint(5) unsigned | ya |  |  |
| `baseline_tahun_2` | smallint(5) unsigned | ya |  |  |
| `target_akhir_1` | decimal(24,4) | ya |  |  |
| `target_akhir_2` | decimal(24,4) | ya |  |  |
| `is_critical_path` | tinyint(1) |  |  |  |
| `sumber_dana_id` | bigint(20) unsigned | ya | → `ref_sumber_dana.id` |  |
| `status_teks` | varchar(100) | ya |  | isian bebas lama, mis. "99,7 %" / "Selesai" |
| `keterangan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `kegiatan_target`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kegiatan_id` | bigint(20) unsigned |  | → `kegiatan.id` |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `periode` | varchar(10) |  |  | TAHUNAN\|TRIWULAN\|BULANAN |
| `periode_ke` | tinyint(3) unsigned |  |  |  |
| `metode` | tinyint(3) unsigned | ya |  | psn_kegiatan_detil.metode lama |
| `target_1` | decimal(24,4) | ya |  | volume satuan_1 |
| `target_2` | decimal(24,4) | ya |  | volume satuan_2 |
| `target_persen` | decimal(7,2) | ya |  | rencana progres fisik kumulatif (%) |
| `target_fisik` | decimal(24,4) | ya |  |  |
| `pagu_rp` | decimal(24,2) | ya |  |  |
| `realisasi_1` | decimal(24,4) | ya |  |  |
| `realisasi_2` | decimal(24,4) | ya |  |  |
| `realisasi_persen` | decimal(7,2) | ya |  | realisasi progres fisik kumulatif (%) |
| `realisasi_fisik` | decimal(24,4) | ya |  |  |
| `realisasi_anggaran_rp` | decimal(24,2) | ya |  |  |
| `status` | varchar(50) | ya |  |  |
| `permasalahan` | text | ya |  |  |
| `bukti_path` | varchar(500) | ya |  |  |
| `dilaporkan_at` | timestamp | ya |  | waktu pembaruan realisasi -- dasar status "Tanpa data" |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `login_log`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `user_id` | bigint(20) unsigned | ya | → `users.id` |  |
| `username` | varchar(255) |  |  |  |
| `aktivitas` | varchar(20) |  |  | LOGIN\|LOGOUT\|GAGAL |
| `ip_address` | varchar(45) | ya |  |  |
| `created_at` | timestamp |  |  |  |

## `model_has_permissions`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `permission_id` | bigint(20) unsigned |  | → `permissions.id` |  |
| `model_type` | varchar(255) |  | PK |  |
| `model_id` | bigint(20) unsigned |  | PK |  |

## `model_has_roles`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `role_id` | bigint(20) unsigned |  | → `roles.id` |  |
| `model_type` | varchar(255) |  | PK |  |
| `model_id` | bigint(20) unsigned |  | PK |  |

## `monev`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `jenis` | varchar(20) |  |  | PENGENDALIAN\|PERENCANAAN (monev_header.jenis 1/2 lama) |
| `tanggal` | date |  |  |  |
| `tanggal_mulai` | date | ya |  |  |
| `tanggal_akhir` | date | ya |  |  |
| `pelaksana` | text | ya |  |  |
| `kepatuhan_pelaporan` | varchar(50) | ya |  |  |
| `skor` | decimal(6,2) | ya |  |  |
| `nilai_rekomendasi` | varchar(50) | ya |  |  |
| `kesimpulan` | text | ya |  |  |
| `rekomendasi_lanjutan` | text | ya |  |  |
| `keterangan` | text | ya |  |  |
| `dokumen_path` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `monev_anggaran`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `kegiatan_id` | bigint(20) unsigned | ya | → `kegiatan.id` |  |
| `uraian` | text | ya |  |  |
| `sumber` | varchar(200) | ya |  |  |
| `rencana_rp` | decimal(24,2) | ya |  |  |
| `realisasi_klaim_rp` | decimal(24,2) | ya |  |  |
| `realisasi_aktual_rp` | decimal(24,2) | ya |  |  |
| `kesesuaian` | varchar(20) | ya |  |  |
| `bukti_tersedia` | varchar(20) | ya |  |  |
| `catatan` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_capaian`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `kegiatan_id` | bigint(20) unsigned | ya | → `kegiatan.id` |  |
| `uraian` | text | ya |  |  |
| `satuan` | varchar(50) | ya |  |  |
| `target` | decimal(24,4) | ya |  |  |
| `realisasi_klaim` | decimal(24,4) | ya |  |  |
| `realisasi_aktual` | decimal(24,4) | ya |  |  |
| `kesesuaian` | varchar(20) | ya |  |  |
| `catatan` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_dokumentasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `kategori_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `judul` | text | ya |  |  |
| `deskripsi` | text | ya |  |  |
| `path` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_evaluasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `hasil_evaluasi` | text | ya |  |  |
| `isu_tantangan` | text | ya |  |  |
| `tindak_lanjut` | text | ya |  |  |
| `status_pengendalian` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_kelembagaan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `peran` | varchar(50) |  |  |  |
| `instansi_tercatat` | varchar(255) | ya |  |  |
| `instansi_aktual` | varchar(255) | ya |  |  |
| `kesesuaian` | varchar(20) | ya |  | Ya\|Sebagian\|Tidak |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_regulasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `regulasi_id` | bigint(20) unsigned | ya | → `regulasi.id` |  |
| `uraian` | text | ya |  |  |
| `justifikasi` | text | ya |  |  |
| `target_tahun` | smallint(5) unsigned | ya |  |  |
| `status_klaim` | varchar(50) | ya |  |  |
| `status_aktual` | varchar(50) | ya |  |  |
| `penanggung_jawab` | text | ya |  |  |
| `bukti_tersedia` | varchar(20) | ya |  |  |
| `kesesuaian` | varchar(20) | ya |  |  |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `monev_risiko`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `monev_id` | bigint(20) unsigned |  | → `monev.id` |  |
| `risiko_id` | bigint(20) unsigned | ya | → `risiko.id` |  |
| `uraian` | text | ya |  |  |
| `kategori` | varchar(50) | ya |  |  |
| `level_awal` | varchar(20) | ya |  |  |
| `level_harapan` | varchar(20) | ya |  |  |
| `level_aktual` | varchar(20) | ya |  |  |
| `rencana` | text | ya |  |  |
| `progres_persen` | tinyint(3) unsigned | ya |  |  |
| `status` | varchar(50) | ya |  |  |
| `hasil_evaluasi` | varchar(50) | ya |  | Sesuai/Lebih Baik \| Memburuk |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `penerima_manfaat`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `uraian` | text |  |  |  |
| `satuan` | varchar(100) | ya |  |  |
| `baseline` | decimal(24,4) | ya |  |  |
| `baseline_tahun` | smallint(5) unsigned | ya |  |  |
| `target_akhir` | decimal(24,4) | ya |  |  |
| `capaian` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `penerima_manfaat_target`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `penerima_manfaat_id` | bigint(20) unsigned |  | → `penerima_manfaat.id` |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `target` | decimal(24,4) | ya |  |  |
| `realisasi` | decimal(24,4) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `pengisian_psn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `periode_cutoff_id` | bigint(20) unsigned |  | → `periode_cutoff.id` |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `status` | varchar(15) |  |  | DRAFT\|DIAJUKAN\|DIVERIFIKASI\|DIKEMBALIKAN |
| `diajukan_at` | timestamp | ya |  |  |
| `diajukan_oleh` | bigint(20) unsigned | ya | → `users.id` |  |
| `diverifikasi_at` | timestamp | ya |  |  |
| `diverifikasi_oleh` | bigint(20) unsigned | ya | → `users.id` |  |
| `catatan_verifikator` | text | ya |  |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `pengisian_psn_riwayat`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `pengisian_psn_id` | bigint(20) unsigned |  | → `pengisian_psn.id` |  |
| `dari_status` | varchar(15) | ya |  |  |
| `ke_status` | varchar(15) |  |  |  |
| `user_id` | bigint(20) unsigned | ya | → `users.id` |  |
| `catatan` | text | ya |  |  |
| `created_at` | timestamp |  |  |  |

## `penilaian`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `usulan_id` | bigint(20) unsigned |  | → `usulan_psn.id` |  |
| `monev_id` | bigint(20) unsigned | ya | → `monev.id` |  |
| `forum` | varchar(100) | ya |  | mis. Rapat Pleno II Pemutakhiran RKP 2027 |
| `tanggal` | date | ya |  |  |
| `penilai_id` | bigint(20) unsigned | ya | → `users.id` |  |
| `status` | varchar(20) |  |  | DRAFT\|FINAL |
| `gate_lulus` | tinyint(1) | ya |  |  |
| `skor_pendukung` | decimal(5,2) | ya |  |  |
| `skor_kesiapan` | decimal(5,2) | ya |  |  |
| `skor_lokasi` | decimal(5,2) | ya |  |  |
| `skor_trisula` | decimal(5,2) | ya |  |  |
| `nilai_akhir` | decimal(5,2) | ya |  |  |
| `rekomendasi` | varchar(30) | ya |  | DIREKOMENDASIKAN\|DIPERTIMBANGKAN\|TIDAK_DIREKOMENDASIKAN\|DITOLAK |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `penilaian_skor`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `penilaian_id` | bigint(20) unsigned |  | → `penilaian.id` |  |
| `kriteria_id` | bigint(20) unsigned |  | → `ref_kriteria.id` |  |
| `nilai` | tinyint(3) unsigned | ya |  | YA_TIDAK: 1/0; SKOR_0_3: 0-3; null = tidak berlaku |
| `temuan` | text | ya |  |  |
| `bukti_path` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `periode_cutoff`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | char(7) |  |  | YYYY-MM |
| `tanggal_cutoff` | date |  |  |  |
| `batas_pengisian` | date | ya |  |  |
| `status` | varchar(10) |  |  | DRAFT\|TERBIT |
| `diterbitkan_at` | timestamp | ya |  |  |
| `diterbitkan_oleh` | bigint(20) unsigned | ya | → `users.id` |  |
| `catatan` | text | ya |  |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `permissions`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `name` | varchar(255) |  |  |  |
| `guard_name` | varchar(255) |  |  |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `psn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  | psn.id pada basis data lama |
| `uuid` | char(36) |  |  |  |
| `kode_psn` | varchar(50) | ya |  | mis. DP.1-2026.1-01 |
| `kode_krisna` | varchar(50) | ya |  |  |
| `kode_rkp` | varchar(50) | ya |  |  |
| `kode_sub` | varchar(50) | ya |  |  |
| `rkp_tahun` | smallint(5) unsigned | ya |  |  |
| `kategori_rkp_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `program_id` | bigint(20) unsigned | ya | → `ref_program.id` |  |
| `klaster_id` | bigint(20) unsigned | ya | → `ref_klaster.id` |  |
| `sub_klaster_id` | bigint(20) unsigned | ya | → `ref_sub_klaster.id` |  |
| `klaster_pkpn_id` | bigint(20) unsigned | ya | → `ref_klaster_pkpn.id` |  |
| `nama` | text |  |  |  |
| `sub_proyek` | text | ya |  |  |
| `deskripsi` | longtext | ya |  |  |
| `output` | longtext | ya |  |  |
| `dampak` | longtext | ya |  |  |
| `dasar_penetapan` | longtext | ya |  |  |
| `kpu` | longtext | ya |  | Kerangka Pendanaan/KPU |
| `status_psn_id` | bigint(20) unsigned | ya | → `ref_status_psn.id` |  |
| `ket_status` | varchar(255) | ya |  |  |
| `ketersediaan_info_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `tahun_selesai` | smallint(5) unsigned | ya |  |  |
| `ket_tahun_selesai` | varchar(255) | ya |  |  |
| `rencana_investasi_rp` | decimal(24,2) | ya |  |  |
| `nilai_investasi_rp` | decimal(24,2) | ya |  |  |
| `investasi_anomali` | tinyint(1) |  |  |  |
| `sumber_input` | tinyint(3) unsigned | ya |  | psn.sumber lama (1/2) -- arti kode perlu dikonfirmasi |
| `pic_nama` | varchar(255) | ya |  | PIC Bappenas (teks bebas pada data lama) |
| `catatan` | varchar(500) | ya |  |  |
| `file_kerangka` | varchar(500) | ya |  |  |
| `file_gambar` | varchar(500) | ya |  |  |
| `file_visualisasi` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_dokumen`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `kategori_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `judul` | text |  |  |  |
| `deskripsi` | text | ya |  |  |
| `path` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_dukungan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `uraian` | text |  |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_evaluasi_status`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `tahun` | smallint(5) unsigned | ya |  |  |
| `kebutuhan_status` | text |  |  |  |
| `justifikasi` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_fasilitas`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `tahun` | smallint(5) unsigned | ya |  |  |
| `fasilitas_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `keterangan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_kelembagaan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `peran` | varchar(30) |  |  | PENGUSUL\|PENANGGUNG_JAWAB\|PELAKSANA\|PENGELOLA\|KONTRAKTOR\|SUPERVISI |
| `penanggung_jawab_id` | bigint(20) unsigned | ya | → `ref_penanggung_jawab.id` |  |
| `instansi_id` | bigint(20) unsigned | ya | → `ref_instansi.id` |  |
| `nama_teks` | text | ya |  | nama bebas bila tidak ada di referensi |
| `urutan` | smallint(5) unsigned |  |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `psn_lokasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `provinsi_kode` | varchar(13) |  | → `ref_wilayah.kode` |  |
| `kabupaten_kode` | varchar(13) | ya | → `ref_wilayah.kode` |  |
| `keterangan` | varchar(255) | ya |  |  |
| `lat` | decimal(10,7) | ya |  | titik lokasi (v2) |
| `lng` | decimal(10,7) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_profil_item`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `bagian_id` | bigint(20) unsigned |  | → `ref_kode.id` |  |
| `isi` | longtext | ya |  |  |
| `catatan` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_sdgs`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `sdgs_id` | bigint(20) unsigned |  | → `ref_kode.id` |  |
| `keterangan` | varchar(255) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `psn_stakeholder`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `jenis` | varchar(5) | ya |  | A=Stakeholder Mapping, B=Kerangka Kelembagaan (kode lama) |
| `peran_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `level` | tinyint(3) unsigned | ya |  | 1-4: Kebijakan, Fasilitator Wilayah, Operator/Investor, Partisipan |
| `peran` | text | ya |  |  |
| `instansi_utama` | text | ya |  |  |
| `instansi_pendukung` | text | ya |  |  |
| `keterangan` | text | ya |  |  |
| `catatan` | text | ya |  |  |
| `file_bukti` | varchar(500) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_sumber_dana`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `sumber_dana_id` | bigint(20) unsigned |  | → `ref_sumber_dana.id` |  |
| `nilai_rp` | decimal(24,2) | ya |  | porsi investasi bila diketahui; null = belum dirinci |
| `keterangan` | varchar(255) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `psn_target_tahunan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `uraian` | text |  |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `psn_unit_pengampu`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `unit_kerja_id` | bigint(20) unsigned |  | → `ref_unit_kerja.id` |  |
| `keterangan` | varchar(255) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `ref_instansi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(255) |  |  |  |

## `ref_klaster`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(150) |  |  |  |
| `urutan` | smallint(5) unsigned |  |  |  |
| `is_aktif` | tinyint(1) |  |  |  |

## `ref_klaster_pkpn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(150) |  |  |  |

## `ref_kode`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `tipe` | varchar(10) |  |  |  |
| `kode` | varchar(20) |  |  |  |
| `nama` | varchar(500) |  |  |  |
| `urutan` | smallint(5) unsigned |  |  |  |

## `ref_kriteria`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  | pertanyaan.id lama |
| `kelompok` | varchar(20) |  |  | UTAMA\|PENDUKUNG\|KESIAPAN\|LOKASI\|TRISULA |
| `kode` | varchar(10) |  |  |  |
| `induk_id` | bigint(20) unsigned | ya | → `ref_kriteria.id` |  |
| `uraian` | text |  |  |  |
| `rubrik` | text | ya |  |  |
| `tipe_nilai` | varchar(15) |  |  | YA_TIDAK\|SKOR_0_3 |
| `kondisional` | varchar(50) | ya |  | mis. PENGUSUL_KL, PENGUSUL_PEMDA, PENGUSUL_BU, INFRASTRUKTUR |
| `urutan` | smallint(5) unsigned |  |  |  |
| `is_aktif` | tinyint(1) |  |  |  |

## `ref_penanggung_jawab`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(255) |  |  |  |
| `instansi_id` | bigint(20) unsigned | ya | → `ref_instansi.id` |  |

## `ref_program`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(255) |  |  |  |

## `ref_status_psn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(150) |  |  |  |
| `tahap` | varchar(20) | ya |  | PERENCANAAN\|TRANSAKSI\|KONSTRUKSI\|OPERASI -- pemetaan Tahapan Status dashboard |
| `is_aktif` | tinyint(1) |  |  | false = keluar dari PSN, tidak dihitung pada K1 |
| `urutan` | smallint(5) unsigned |  |  |  |

## `ref_sub_klaster`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(150) |  |  |  |
| `klaster_id` | bigint(20) unsigned | ya | → `ref_klaster.id` |  |

## `ref_sumber_dana`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(150) |  |  |  |
| `skema` | varchar(20) | ya |  | APBN\|APBD\|KPBU\|LAINNYA -- pengelompokan P6, lihat config psn_dashboard |

## `ref_unit_kerja`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `kode` | varchar(10) |  |  |  |
| `nama` | varchar(255) |  |  |  |
| `jenis` | varchar(20) |  |  | DIREKTORAT\|KL\|PEMDA\|BU\|LAINNYA (heuristik nama, perlu kurasi) |
| `induk_id` | bigint(20) unsigned | ya | → `ref_unit_kerja.id` |  |
| `is_aktif` | tinyint(1) |  |  |  |

## `ref_wilayah`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `kode` | varchar(13) |  | PK |  |
| `nama` | varchar(150) |  |  |  |
| `level` | tinyint(3) unsigned |  |  | 0=nasional, 1=provinsi, 2=kab/kota, 3=kecamatan, 4=kel/desa |
| `induk_kode` | varchar(13) | ya |  |  |
| `hc_key` | varchar(20) | ya |  | kunci peta Highcharts dari tabel lama `peta` (provinsi) |
| `lat` | decimal(10,7) | ya |  |  |
| `lng` | decimal(10,7) | ya |  |  |

## `regulasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `nama` | text |  |  |  |
| `justifikasi` | text | ya |  |  |
| `penanggung_jawab` | text | ya |  |  |
| `tahap` | varchar(20) |  |  | IDENTIFIKASI\|PENYUSUNAN\|HARMONISASI\|DITETAPKAN |
| `nomor_penetapan` | varchar(255) | ya |  |  |
| `tanggal_penetapan` | date | ya |  |  |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `regulasi_target_tahun`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `regulasi_id` | bigint(20) unsigned |  | → `regulasi.id` |  |
| `tahun` | smallint(5) unsigned |  | PK |  |

## `risiko`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `kegiatan_id` | bigint(20) unsigned | ya | → `kegiatan.id` |  |
| `uraian` | text |  |  |  |
| `kategori_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `level_awal` | varchar(20) | ya |  | Rendah\|Sedang\|Tinggi\|Sangat Tinggi |
| `kemungkinan_awal` | tinyint(3) unsigned | ya |  |  |
| `dampak_awal` | tinyint(3) unsigned | ya |  |  |
| `level_harapan` | varchar(20) | ya |  | risiko residual harapan |
| `kemungkinan_harapan` | tinyint(3) unsigned | ya |  |  |
| `dampak_harapan` | tinyint(3) unsigned | ya |  |  |
| `rencana_perlakuan` | text | ya |  |  |
| `penanggung_jawab` | text | ya |  |  |
| `is_titik_kritis` | tinyint(1) |  |  |  |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `risiko_pemantauan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `risiko_id` | bigint(20) unsigned |  | → `risiko.id` |  |
| `tanggal` | date |  |  |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `triwulan` | tinyint(3) unsigned | ya |  |  |
| `level_aktual` | varchar(20) | ya |  |  |
| `kemungkinan_aktual` | tinyint(3) unsigned | ya |  |  |
| `dampak_aktual` | tinyint(3) unsigned | ya |  |  |
| `progres_persen` | tinyint(3) unsigned | ya |  |  |
| `status_perlakuan` | varchar(50) | ya |  | BELUM\|BERJALAN\|SELESAI |
| `sumber` | varchar(20) |  |  | PELAPORAN\|MONEV |
| `monev_risiko_id` | bigint(20) unsigned | ya |  | diisi bila sumber = MONEV |
| `catatan` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `risiko_perlakuan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `risiko_id` | bigint(20) unsigned |  | → `risiko.id` |  |
| `uraian` | varchar(255) | ya |  |  |
| `instansi_id` | bigint(20) unsigned | ya | → `ref_instansi.id` |  |
| `keterangan` | varchar(255) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `risiko_tahun_perlakuan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `risiko_id` | bigint(20) unsigned |  | → `risiko.id` |  |
| `tahun` | smallint(5) unsigned |  | PK |  |

## `roles`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `name` | varchar(255) |  |  |  |
| `guard_name` | varchar(255) |  |  |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `role_has_permissions`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `permission_id` | bigint(20) unsigned |  | → `permissions.id` |  |
| `role_id` | bigint(20) unsigned |  | → `roles.id` |  |

## `snapshot_kegiatan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `periode_cutoff_id` | bigint(20) unsigned |  | → `periode_cutoff.id` |  |
| `kegiatan_id` | bigint(20) unsigned |  | → `kegiatan.id` |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `is_critical_path` | tinyint(1) |  |  |  |
| `target_persen` | decimal(7,2) | ya |  |  |
| `realisasi_persen` | decimal(7,2) | ya |  |  |
| `deviasi_pp` | decimal(7,2) | ya |  |  |
| `status_progres` | varchar(15) |  |  |  |
| `pagu_rp` | decimal(24,2) | ya |  |  |
| `realisasi_anggaran_rp` | decimal(24,2) | ya |  |  |
| `is_tercapai` | tinyint(1) |  |  |  |
| `created_at` | timestamp |  |  |  |

## `snapshot_kelengkapan`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `periode_cutoff_id` | bigint(20) unsigned |  | → `periode_cutoff.id` |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `bagian` | varchar(30) |  |  |  |
| `field_wajib` | smallint(5) unsigned |  |  |  |
| `field_terisi` | smallint(5) unsigned |  |  |  |
| `created_at` | timestamp |  |  |  |

## `snapshot_psn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `periode_cutoff_id` | bigint(20) unsigned |  | → `periode_cutoff.id` |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `klaster_id` | bigint(20) unsigned | ya |  |  |
| `status_psn_id` | bigint(20) unsigned | ya |  |  |
| `tahap` | varchar(20) | ya |  |  |
| `kategori` | varchar(10) | ya |  | PSN\|PKPN |
| `provinsi_kode` | longtext | ya |  |  |
| `unit_kerja_id` | longtext | ya |  |  |
| `sumber_dana_id` | longtext | ya |  |  |
| `is_aktif` | tinyint(1) |  |  |  |
| `nilai_investasi_rp` | decimal(24,2) | ya |  |  |
| `progres_rencana_persen` | decimal(7,2) | ya |  |  |
| `progres_realisasi_persen` | decimal(7,2) | ya |  |  |
| `deviasi_pp` | decimal(7,2) | ya |  |  |
| `status_progres` | varchar(15) |  |  | ON_TRACK\|BERISIKO\|TERLAMBAT\|TANPA_DATA |
| `pagu_rp` | decimal(24,2) | ya |  |  |
| `realisasi_anggaran_rp` | decimal(24,2) | ya |  |  |
| `jumlah_ro` | int(10) unsigned |  |  |  |
| `jumlah_ro_tercapai` | int(10) unsigned |  |  |  |
| `risiko_skor_maks` | tinyint(3) unsigned | ya |  |  |
| `risiko_level_maks` | varchar(20) | ya |  |  |
| `is_kritis` | tinyint(1) |  |  | K4: Terlambat ATAU risiko residual >= Tinggi |
| `kelengkapan_persen` | decimal(5,2) | ya |  |  |
| `pembaruan_terakhir_at` | timestamp | ya |  |  |
| `created_at` | timestamp |  |  |  |

## `snapshot_risiko`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `periode_cutoff_id` | bigint(20) unsigned |  | → `periode_cutoff.id` |  |
| `risiko_id` | bigint(20) unsigned |  | → `risiko.id` |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `kategori_id` | bigint(20) unsigned | ya |  |  |
| `kemungkinan_harapan` | tinyint(3) unsigned | ya |  |  |
| `dampak_harapan` | tinyint(3) unsigned | ya |  |  |
| `level_harapan` | varchar(20) | ya |  |  |
| `kemungkinan_aktual` | tinyint(3) unsigned | ya |  |  |
| `dampak_aktual` | tinyint(3) unsigned | ya |  |  |
| `level_aktual` | varchar(20) | ya |  |  |
| `created_at` | timestamp |  |  |  |

## `trisula`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `psn_id` | bigint(20) unsigned |  | → `psn.id` |  |
| `kategori_id` | bigint(20) unsigned | ya | → `ref_kode.id` |  |
| `indikator` | text | ya |  |  |
| `kontribusi` | text | ya |  |  |
| `satuan` | varchar(100) | ya |  |  |
| `baseline` | decimal(24,4) | ya |  |  |
| `baseline_tahun` | smallint(5) unsigned | ya |  |  |
| `target_akhir` | decimal(24,4) | ya |  |  |
| `capaian` | text | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `trisula_target`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `trisula_id` | bigint(20) unsigned |  | → `trisula.id` |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `periode` | varchar(10) |  |  | TAHUNAN\|TRIWULAN |
| `periode_ke` | tinyint(3) unsigned |  |  |  |
| `target` | decimal(24,4) | ya |  |  |
| `realisasi` | decimal(24,4) | ya |  |  |
| `status` | varchar(255) | ya |  |  |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `users`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `username` | varchar(100) |  |  |  |
| `name` | varchar(255) |  |  |  |
| `email` | varchar(255) |  |  |  |
| `unit_kerja_id` | bigint(20) unsigned | ya | → `ref_unit_kerja.id` |  |
| `is_active` | tinyint(1) |  |  |  |
| `wajib_ganti_password` | tinyint(1) |  |  |  |
| `last_login_at` | timestamp | ya |  |  |
| `jumlah_login` | int(10) unsigned |  |  |  |
| `legacy_grup` | varchar(50) | ya |  | users.grup pada basis data lama |
| `legacy_akses` | varchar(20) | ya |  | users.akses (kode UNIT) pada basis data lama |
| `email_verified_at` | timestamp | ya |  |  |
| `password` | varchar(255) |  |  |  |
| `remember_token` | varchar(100) | ya |  |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |

## `usulan_lokasi`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `usulan_id` | bigint(20) unsigned |  | → `usulan_psn.id` |  |
| `provinsi_kode` | varchar(13) |  | → `ref_wilayah.kode` |  |
| `kabupaten_kode` | varchar(13) | ya | → `ref_wilayah.kode` |  |

## `usulan_psn`

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `id` | bigint(20) unsigned |  | PK |  |
| `legacy_id` | int(10) unsigned | ya |  |  |
| `tahun_rkp` | smallint(5) unsigned |  |  |  |
| `nama` | text |  |  |  |
| `klaster_id` | bigint(20) unsigned | ya | → `ref_klaster.id` |  |
| `pengusul_instansi_id` | bigint(20) unsigned | ya | → `ref_instansi.id` |  |
| `pengusul_teks` | varchar(255) | ya |  |  |
| `jenis_pengusul` | varchar(20) | ya |  | KL\|PEMDA\|BUMN_SWASTA |
| `is_infrastruktur` | tinyint(1) |  |  |  |
| `nilai_investasi_rp` | decimal(24,2) | ya |  |  |
| `psn_id` | bigint(20) unsigned | ya | → `psn.id` |  |
| `status` | varchar(20) |  |  | DIAJUKAN\|DINILAI\|DITETAPKAN\|DITOLAK |
| `created_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `updated_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `created_at` | timestamp | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
| `deleted_by` | bigint(20) unsigned | ya | → `users.id` |  |
| `deleted_at` | timestamp | ya |  |  |

## `v_kegiatan_progres` (view)

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `kegiatan_id` | bigint(20) unsigned |  |  |  |
| `psn_id` | bigint(20) unsigned |  |  |  |
| `nama` | text |  |  |  |
| `is_critical_path` | tinyint(1) |  |  |  |
| `tahun` | smallint(5) unsigned |  |  |  |
| `periode` | varchar(10) |  |  | TAHUNAN\|TRIWULAN\|BULANAN |
| `periode_ke` | tinyint(3) unsigned |  |  |  |
| `target_persen` | decimal(7,2) | ya |  | rencana progres fisik kumulatif (%) |
| `realisasi_persen` | decimal(7,2) | ya |  | realisasi progres fisik kumulatif (%) |
| `deviasi_pp` | decimal(8,2) | ya |  |  |
| `pagu_rp` | decimal(24,2) | ya |  |  |
| `realisasi_anggaran_rp` | decimal(24,2) | ya |  |  |
| `dilaporkan_at` | timestamp /* mariadb-5.3 */ | ya |  |  |

## `v_psn_ringkas` (view)

| Kolom | Tipe | Null | Relasi | Keterangan |
|---|---|---|---|---|
| `psn_id` | bigint(20) unsigned |  |  |  |
| `kode_psn` | varchar(50) | ya |  | mis. DP.1-2026.1-01 |
| `nama` | text |  |  |  |
| `klaster_id` | bigint(20) unsigned | ya |  |  |
| `klaster_kode` | varchar(10) | ya |  |  |
| `klaster_nama` | varchar(150) | ya |  |  |
| `klaster_pkpn_id` | bigint(20) unsigned | ya |  |  |
| `kategori` | varchar(4) |  |  |  |
| `status_psn_id` | bigint(20) unsigned | ya |  |  |
| `status_psn_nama` | varchar(150) | ya |  |  |
| `tahap` | varchar(20) | ya |  | PERENCANAAN\|TRANSAKSI\|KONSTRUKSI\|OPERASI -- pemetaan Tahapan Status dashboard |
| `is_aktif` | int(4) |  |  |  |
| `nilai_investasi_rp` | decimal(24,2) | ya |  |  |
| `investasi_anomali` | tinyint(1) |  |  |  |
| `tahun_selesai` | smallint(5) unsigned | ya |  |  |
| `provinsi_kode_list` | mediumtext | ya |  |  |
| `unit_kerja_id_list` | mediumtext | ya |  |  |
| `sumber_dana_id_list` | mediumtext | ya |  |  |
| `updated_at` | timestamp | ya |  |  |
