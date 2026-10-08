-- Skema SIRENDA PSN v2 (DDL saja, tanpa data). Dibangkitkan dari `php artisan migrate` lalu mysqldump --no-data.
-- Sumber kebenaran skema tetap database/migrations; berkas ini untuk telaah DBA.

/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_label` varchar(255) DEFAULT NULL COMMENT 'username/email saat kejadian, termasuk riwayat impor',
  `tabel` varchar(64) NOT NULL,
  `record_id` bigint(20) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned DEFAULT NULL COMMENT 'untuk "Aktivitas Terbaru" & riwayat per proyek',
  `aksi` varchar(20) NOT NULL COMMENT 'CREATE|UPDATE|DELETE|RESTORE|SUBMIT|VERIFY|RETURN|PUBLISH',
  `nilai_lama` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nilai_lama`)),
  `nilai_baru` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nilai_baru`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `sumber` varchar(20) NOT NULL DEFAULT 'APLIKASI' COMMENT 'APLIKASI|IMPOR_LEGACY',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'null = riwayat sistem lama tanpa stempel waktu',
  PRIMARY KEY (`id`),
  KEY `audit_log_user_id_foreign` (`user_id`),
  KEY `audit_log_tabel_record_id_index` (`tabel`,`record_id`),
  KEY `audit_log_psn_id_created_at_index` (`psn_id`,`created_at`),
  KEY `audit_log_created_at_index` (`created_at`),
  CONSTRAINT `audit_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `indikator` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `uraian` text NOT NULL,
  `satuan` varchar(100) DEFAULT NULL,
  `baseline` varchar(100) DEFAULT NULL,
  `baseline_tahun` smallint(5) unsigned DEFAULT NULL,
  `target_akhir` decimal(24,4) DEFAULT NULL,
  `capaian` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indikator_legacy_id_unique` (`legacy_id`),
  KEY `indikator_psn_id_foreign` (`psn_id`),
  KEY `indikator_created_by_foreign` (`created_by`),
  KEY `indikator_updated_by_foreign` (`updated_by`),
  KEY `indikator_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `indikator_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `indikator_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `indikator_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indikator_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `indikator_target` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `indikator_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `target` decimal(24,4) DEFAULT NULL,
  `realisasi` decimal(24,4) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `indikator_target_indikator_id_tahun_unique` (`indikator_id`,`tahun`),
  KEY `indikator_target_created_by_foreign` (`created_by`),
  KEY `indikator_target_updated_by_foreign` (`updated_by`),
  CONSTRAINT `indikator_target_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `indikator_target_indikator_id_foreign` FOREIGN KEY (`indikator_id`) REFERENCES `indikator` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indikator_target_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `isu` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `kegiatan_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text NOT NULL,
  `kebutuhan_dukungan` text DEFAULT NULL,
  `pic_unit_kerja_id` bigint(20) unsigned DEFAULT NULL,
  `pic_nama` varchar(255) DEFAULT NULL,
  `tenggat` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'TERBUKA' COMMENT 'TERBUKA|PROSES|SELESAI',
  `tanggal_selesai` date DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isu_legacy_id_unique` (`legacy_id`),
  KEY `isu_psn_id_foreign` (`psn_id`),
  KEY `isu_kegiatan_id_foreign` (`kegiatan_id`),
  KEY `isu_pic_unit_kerja_id_foreign` (`pic_unit_kerja_id`),
  KEY `isu_created_by_foreign` (`created_by`),
  KEY `isu_updated_by_foreign` (`updated_by`),
  KEY `isu_deleted_by_foreign` (`deleted_by`),
  KEY `isu_status_tenggat_index` (`status`,`tenggat`),
  CONSTRAINT `isu_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `isu_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `isu_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `isu_pic_unit_kerja_id_foreign` FOREIGN KEY (`pic_unit_kerja_id`) REFERENCES `ref_unit_kerja` (`id`) ON DELETE SET NULL,
  CONSTRAINT `isu_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `isu_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kegiatan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_ref` varchar(40) DEFAULT NULL COMMENT 'psn_kegiatan:{id} atau psn_kegiatan_cp:{id}',
  `psn_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `jenis_id` bigint(20) unsigned DEFAULT NULL,
  `nama` text NOT NULL,
  `lokasi` text DEFAULT NULL,
  `pelaksana` varchar(255) DEFAULT NULL,
  `satuan_1` varchar(100) DEFAULT NULL,
  `satuan_2` varchar(100) DEFAULT NULL,
  `baseline_1` decimal(24,4) DEFAULT NULL,
  `baseline_2` decimal(24,4) DEFAULT NULL,
  `baseline_tahun_1` smallint(5) unsigned DEFAULT NULL,
  `baseline_tahun_2` smallint(5) unsigned DEFAULT NULL,
  `target_akhir_1` decimal(24,4) DEFAULT NULL,
  `target_akhir_2` decimal(24,4) DEFAULT NULL,
  `is_critical_path` tinyint(1) NOT NULL DEFAULT 0,
  `sumber_dana_id` bigint(20) unsigned DEFAULT NULL,
  `status_teks` varchar(100) DEFAULT NULL COMMENT 'isian bebas lama, mis. "99,7 %" / "Selesai"',
  `keterangan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kegiatan_legacy_ref_unique` (`legacy_ref`),
  KEY `kegiatan_parent_id_foreign` (`parent_id`),
  KEY `kegiatan_jenis_id_foreign` (`jenis_id`),
  KEY `kegiatan_sumber_dana_id_foreign` (`sumber_dana_id`),
  KEY `kegiatan_created_by_foreign` (`created_by`),
  KEY `kegiatan_updated_by_foreign` (`updated_by`),
  KEY `kegiatan_deleted_by_foreign` (`deleted_by`),
  KEY `kegiatan_psn_id_is_critical_path_index` (`psn_id`,`is_critical_path`),
  CONSTRAINT `kegiatan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kegiatan_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kegiatan_jenis_id_foreign` FOREIGN KEY (`jenis_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kegiatan_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kegiatan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kegiatan_sumber_dana_id_foreign` FOREIGN KEY (`sumber_dana_id`) REFERENCES `ref_sumber_dana` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kegiatan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kegiatan_target` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kegiatan_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `periode` varchar(10) NOT NULL COMMENT 'TAHUNAN|TRIWULAN|BULANAN',
  `periode_ke` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `metode` tinyint(3) unsigned DEFAULT NULL COMMENT 'psn_kegiatan_detil.metode lama',
  `target_1` decimal(24,4) DEFAULT NULL COMMENT 'volume satuan_1',
  `target_2` decimal(24,4) DEFAULT NULL COMMENT 'volume satuan_2',
  `target_persen` decimal(7,2) DEFAULT NULL COMMENT 'rencana progres fisik kumulatif (%)',
  `target_fisik` decimal(24,4) DEFAULT NULL,
  `pagu_rp` decimal(24,2) DEFAULT NULL,
  `realisasi_1` decimal(24,4) DEFAULT NULL,
  `realisasi_2` decimal(24,4) DEFAULT NULL,
  `realisasi_persen` decimal(7,2) DEFAULT NULL COMMENT 'realisasi progres fisik kumulatif (%)',
  `realisasi_fisik` decimal(24,4) DEFAULT NULL,
  `realisasi_anggaran_rp` decimal(24,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `permasalahan` text DEFAULT NULL,
  `bukti_path` varchar(500) DEFAULT NULL,
  `dilaporkan_at` timestamp NULL DEFAULT NULL COMMENT 'waktu pembaruan realisasi -- dasar status "Tanpa data"',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kegiatan_target_periode_unique` (`kegiatan_id`,`tahun`,`periode`,`periode_ke`),
  KEY `kegiatan_target_created_by_foreign` (`created_by`),
  KEY `kegiatan_target_updated_by_foreign` (`updated_by`),
  KEY `kegiatan_target_tahun_periode_periode_ke_index` (`tahun`,`periode`,`periode_ke`),
  CONSTRAINT `kegiatan_target_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kegiatan_target_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kegiatan_target_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `aktivitas` varchar(20) NOT NULL COMMENT 'LOGIN|LOGOUT|GAGAL',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `login_log_user_id_foreign` (`user_id`),
  KEY `login_log_username_created_at_index` (`username`,`created_at`),
  CONSTRAINT `login_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `jenis` varchar(20) NOT NULL COMMENT 'PENGENDALIAN|PERENCANAAN (monev_header.jenis 1/2 lama)',
  `tanggal` date NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_akhir` date DEFAULT NULL,
  `pelaksana` text DEFAULT NULL,
  `kepatuhan_pelaporan` varchar(50) DEFAULT NULL,
  `skor` decimal(6,2) DEFAULT NULL,
  `nilai_rekomendasi` varchar(50) DEFAULT NULL,
  `kesimpulan` text DEFAULT NULL,
  `rekomendasi_lanjutan` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dokumen_path` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monev_legacy_id_unique` (`legacy_id`),
  KEY `monev_psn_id_foreign` (`psn_id`),
  KEY `monev_created_by_foreign` (`created_by`),
  KEY `monev_updated_by_foreign` (`updated_by`),
  KEY `monev_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `monev_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_anggaran` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `kegiatan_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text DEFAULT NULL,
  `sumber` varchar(200) DEFAULT NULL,
  `rencana_rp` decimal(24,2) DEFAULT NULL,
  `realisasi_klaim_rp` decimal(24,2) DEFAULT NULL,
  `realisasi_aktual_rp` decimal(24,2) DEFAULT NULL,
  `kesesuaian` varchar(20) DEFAULT NULL,
  `bukti_tersedia` varchar(20) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_anggaran_monev_id_foreign` (`monev_id`),
  KEY `monev_anggaran_kegiatan_id_foreign` (`kegiatan_id`),
  KEY `monev_anggaran_created_by_foreign` (`created_by`),
  KEY `monev_anggaran_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_anggaran_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_anggaran_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_anggaran_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_anggaran_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_capaian` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `kegiatan_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text DEFAULT NULL,
  `satuan` varchar(50) DEFAULT NULL,
  `target` decimal(24,4) DEFAULT NULL,
  `realisasi_klaim` decimal(24,4) DEFAULT NULL,
  `realisasi_aktual` decimal(24,4) DEFAULT NULL,
  `kesesuaian` varchar(20) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_capaian_monev_id_foreign` (`monev_id`),
  KEY `monev_capaian_kegiatan_id_foreign` (`kegiatan_id`),
  KEY `monev_capaian_created_by_foreign` (`created_by`),
  KEY `monev_capaian_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_capaian_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_capaian_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_capaian_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_capaian_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_dokumentasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `kategori_id` bigint(20) unsigned DEFAULT NULL,
  `judul` text DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `path` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_dokumentasi_monev_id_foreign` (`monev_id`),
  KEY `monev_dokumentasi_kategori_id_foreign` (`kategori_id`),
  KEY `monev_dokumentasi_created_by_foreign` (`created_by`),
  KEY `monev_dokumentasi_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_dokumentasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_dokumentasi_kategori_id_foreign` FOREIGN KEY (`kategori_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_dokumentasi_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_dokumentasi_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_evaluasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `hasil_evaluasi` text DEFAULT NULL,
  `isu_tantangan` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status_pengendalian` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_evaluasi_monev_id_foreign` (`monev_id`),
  KEY `monev_evaluasi_created_by_foreign` (`created_by`),
  KEY `monev_evaluasi_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_evaluasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_evaluasi_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_evaluasi_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_kelembagaan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `peran` varchar(50) NOT NULL,
  `instansi_tercatat` varchar(255) DEFAULT NULL,
  `instansi_aktual` varchar(255) DEFAULT NULL,
  `kesesuaian` varchar(20) DEFAULT NULL COMMENT 'Ya|Sebagian|Tidak',
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_kelembagaan_monev_id_foreign` (`monev_id`),
  KEY `monev_kelembagaan_created_by_foreign` (`created_by`),
  KEY `monev_kelembagaan_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_kelembagaan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_kelembagaan_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_kelembagaan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_regulasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `regulasi_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text DEFAULT NULL,
  `justifikasi` text DEFAULT NULL,
  `target_tahun` smallint(5) unsigned DEFAULT NULL,
  `status_klaim` varchar(50) DEFAULT NULL,
  `status_aktual` varchar(50) DEFAULT NULL,
  `penanggung_jawab` text DEFAULT NULL,
  `bukti_tersedia` varchar(20) DEFAULT NULL,
  `kesesuaian` varchar(20) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_regulasi_monev_id_foreign` (`monev_id`),
  KEY `monev_regulasi_regulasi_id_foreign` (`regulasi_id`),
  KEY `monev_regulasi_created_by_foreign` (`created_by`),
  KEY `monev_regulasi_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_regulasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_regulasi_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_regulasi_regulasi_id_foreign` FOREIGN KEY (`regulasi_id`) REFERENCES `regulasi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_regulasi_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monev_risiko` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monev_id` bigint(20) unsigned NOT NULL,
  `risiko_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `level_awal` varchar(20) DEFAULT NULL,
  `level_harapan` varchar(20) DEFAULT NULL,
  `level_aktual` varchar(20) DEFAULT NULL,
  `rencana` text DEFAULT NULL,
  `progres_persen` tinyint(3) unsigned DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `hasil_evaluasi` varchar(50) DEFAULT NULL COMMENT 'Sesuai/Lebih Baik | Memburuk',
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `monev_risiko_monev_id_foreign` (`monev_id`),
  KEY `monev_risiko_risiko_id_foreign` (`risiko_id`),
  KEY `monev_risiko_created_by_foreign` (`created_by`),
  KEY `monev_risiko_updated_by_foreign` (`updated_by`),
  CONSTRAINT `monev_risiko_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_risiko_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monev_risiko_risiko_id_foreign` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monev_risiko_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penerima_manfaat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `uraian` text NOT NULL,
  `satuan` varchar(100) DEFAULT NULL,
  `baseline` decimal(24,4) DEFAULT NULL,
  `baseline_tahun` smallint(5) unsigned DEFAULT NULL,
  `target_akhir` decimal(24,4) DEFAULT NULL,
  `capaian` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `penerima_manfaat_legacy_id_unique` (`legacy_id`),
  KEY `penerima_manfaat_psn_id_foreign` (`psn_id`),
  KEY `penerima_manfaat_created_by_foreign` (`created_by`),
  KEY `penerima_manfaat_updated_by_foreign` (`updated_by`),
  KEY `penerima_manfaat_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `penerima_manfaat_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penerima_manfaat_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penerima_manfaat_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penerima_manfaat_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penerima_manfaat_target` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `penerima_manfaat_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `target` decimal(24,4) DEFAULT NULL,
  `realisasi` decimal(24,4) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `penerima_manfaat_target_penerima_manfaat_id_tahun_unique` (`penerima_manfaat_id`,`tahun`),
  KEY `penerima_manfaat_target_created_by_foreign` (`created_by`),
  KEY `penerima_manfaat_target_updated_by_foreign` (`updated_by`),
  CONSTRAINT `penerima_manfaat_target_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penerima_manfaat_target_penerima_manfaat_id_foreign` FOREIGN KEY (`penerima_manfaat_id`) REFERENCES `penerima_manfaat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penerima_manfaat_target_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengisian_psn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periode_cutoff_id` bigint(20) unsigned NOT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|DIAJUKAN|DIVERIFIKASI|DIKEMBALIKAN',
  `diajukan_at` timestamp NULL DEFAULT NULL,
  `diajukan_oleh` bigint(20) unsigned DEFAULT NULL,
  `diverifikasi_at` timestamp NULL DEFAULT NULL,
  `diverifikasi_oleh` bigint(20) unsigned DEFAULT NULL,
  `catatan_verifikator` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengisian_psn_periode_cutoff_id_psn_id_unique` (`periode_cutoff_id`,`psn_id`),
  KEY `pengisian_psn_psn_id_foreign` (`psn_id`),
  KEY `pengisian_psn_diajukan_oleh_foreign` (`diajukan_oleh`),
  KEY `pengisian_psn_diverifikasi_oleh_foreign` (`diverifikasi_oleh`),
  KEY `pengisian_psn_periode_cutoff_id_status_index` (`periode_cutoff_id`,`status`),
  CONSTRAINT `pengisian_psn_diajukan_oleh_foreign` FOREIGN KEY (`diajukan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengisian_psn_diverifikasi_oleh_foreign` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengisian_psn_periode_cutoff_id_foreign` FOREIGN KEY (`periode_cutoff_id`) REFERENCES `periode_cutoff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengisian_psn_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengisian_psn_riwayat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pengisian_psn_id` bigint(20) unsigned NOT NULL,
  `dari_status` varchar(15) DEFAULT NULL,
  `ke_status` varchar(15) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pengisian_psn_riwayat_pengisian_psn_id_foreign` (`pengisian_psn_id`),
  KEY `pengisian_psn_riwayat_user_id_foreign` (`user_id`),
  CONSTRAINT `pengisian_psn_riwayat_pengisian_psn_id_foreign` FOREIGN KEY (`pengisian_psn_id`) REFERENCES `pengisian_psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengisian_psn_riwayat_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penilaian` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usulan_id` bigint(20) unsigned NOT NULL,
  `monev_id` bigint(20) unsigned DEFAULT NULL,
  `forum` varchar(100) DEFAULT NULL COMMENT 'mis. Rapat Pleno II Pemutakhiran RKP 2027',
  `tanggal` date DEFAULT NULL,
  `penilai_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|FINAL',
  `gate_lulus` tinyint(1) DEFAULT NULL,
  `skor_pendukung` decimal(5,2) DEFAULT NULL,
  `skor_kesiapan` decimal(5,2) DEFAULT NULL,
  `skor_lokasi` decimal(5,2) DEFAULT NULL,
  `skor_trisula` decimal(5,2) DEFAULT NULL,
  `nilai_akhir` decimal(5,2) DEFAULT NULL,
  `rekomendasi` varchar(30) DEFAULT NULL COMMENT 'DIREKOMENDASIKAN|DIPERTIMBANGKAN|TIDAK_DIREKOMENDASIKAN|DITOLAK',
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `penilaian_usulan_id_foreign` (`usulan_id`),
  KEY `penilaian_monev_id_foreign` (`monev_id`),
  KEY `penilaian_penilai_id_foreign` (`penilai_id`),
  KEY `penilaian_created_by_foreign` (`created_by`),
  KEY `penilaian_updated_by_foreign` (`updated_by`),
  KEY `penilaian_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `penilaian_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_monev_id_foreign` FOREIGN KEY (`monev_id`) REFERENCES `monev` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_penilai_id_foreign` FOREIGN KEY (`penilai_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_usulan_id_foreign` FOREIGN KEY (`usulan_id`) REFERENCES `usulan_psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penilaian_skor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `penilaian_id` bigint(20) unsigned NOT NULL,
  `kriteria_id` bigint(20) unsigned NOT NULL,
  `nilai` tinyint(3) unsigned DEFAULT NULL COMMENT 'YA_TIDAK: 1/0; SKOR_0_3: 0-3; null = tidak berlaku',
  `temuan` text DEFAULT NULL,
  `bukti_path` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `penilaian_skor_penilaian_id_kriteria_id_unique` (`penilaian_id`,`kriteria_id`),
  KEY `penilaian_skor_kriteria_id_foreign` (`kriteria_id`),
  KEY `penilaian_skor_created_by_foreign` (`created_by`),
  KEY `penilaian_skor_updated_by_foreign` (`updated_by`),
  CONSTRAINT `penilaian_skor_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_skor_kriteria_id_foreign` FOREIGN KEY (`kriteria_id`) REFERENCES `ref_kriteria` (`id`),
  CONSTRAINT `penilaian_skor_penilaian_id_foreign` FOREIGN KEY (`penilaian_id`) REFERENCES `penilaian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_skor_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `periode_cutoff` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` char(7) NOT NULL COMMENT 'YYYY-MM',
  `tanggal_cutoff` date NOT NULL,
  `batas_pengisian` date DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|TERBIT',
  `diterbitkan_at` timestamp NULL DEFAULT NULL,
  `diterbitkan_oleh` bigint(20) unsigned DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `periode_cutoff_kode_unique` (`kode`),
  KEY `periode_cutoff_diterbitkan_oleh_foreign` (`diterbitkan_oleh`),
  CONSTRAINT `periode_cutoff_diterbitkan_oleh_foreign` FOREIGN KEY (`diterbitkan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL COMMENT 'psn.id pada basis data lama',
  `uuid` char(36) NOT NULL,
  `kode_psn` varchar(50) DEFAULT NULL COMMENT 'mis. DP.1-2026.1-01',
  `kode_krisna` varchar(50) DEFAULT NULL,
  `kode_rkp` varchar(50) DEFAULT NULL,
  `kode_sub` varchar(50) DEFAULT NULL,
  `rkp_tahun` smallint(5) unsigned DEFAULT NULL,
  `kategori_rkp_id` bigint(20) unsigned DEFAULT NULL,
  `program_id` bigint(20) unsigned DEFAULT NULL,
  `klaster_id` bigint(20) unsigned DEFAULT NULL,
  `sub_klaster_id` bigint(20) unsigned DEFAULT NULL,
  `klaster_pkpn_id` bigint(20) unsigned DEFAULT NULL,
  `nama` text NOT NULL,
  `sub_proyek` text DEFAULT NULL,
  `deskripsi` longtext DEFAULT NULL,
  `output` longtext DEFAULT NULL,
  `dampak` longtext DEFAULT NULL,
  `dasar_penetapan` longtext DEFAULT NULL,
  `kpu` longtext DEFAULT NULL COMMENT 'Kerangka Pendanaan/KPU',
  `status_psn_id` bigint(20) unsigned DEFAULT NULL,
  `ket_status` varchar(255) DEFAULT NULL,
  `ketersediaan_info_id` bigint(20) unsigned DEFAULT NULL,
  `tahun_selesai` smallint(5) unsigned DEFAULT NULL,
  `ket_tahun_selesai` varchar(255) DEFAULT NULL,
  `rencana_investasi_rp` decimal(24,2) DEFAULT NULL,
  `nilai_investasi_rp` decimal(24,2) DEFAULT NULL,
  `investasi_anomali` tinyint(1) NOT NULL DEFAULT 0,
  `sumber_input` tinyint(3) unsigned DEFAULT NULL COMMENT 'psn.sumber lama (1/2) -- arti kode perlu dikonfirmasi',
  `pic_nama` varchar(255) DEFAULT NULL COMMENT 'PIC Bappenas (teks bebas pada data lama)',
  `catatan` varchar(500) DEFAULT NULL,
  `file_kerangka` varchar(500) DEFAULT NULL,
  `file_gambar` varchar(500) DEFAULT NULL,
  `file_visualisasi` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_uuid_unique` (`uuid`),
  UNIQUE KEY `psn_legacy_id_unique` (`legacy_id`),
  UNIQUE KEY `psn_kode_psn_unique` (`kode_psn`),
  KEY `psn_kategori_rkp_id_foreign` (`kategori_rkp_id`),
  KEY `psn_program_id_foreign` (`program_id`),
  KEY `psn_sub_klaster_id_foreign` (`sub_klaster_id`),
  KEY `psn_klaster_pkpn_id_foreign` (`klaster_pkpn_id`),
  KEY `psn_status_psn_id_foreign` (`status_psn_id`),
  KEY `psn_ketersediaan_info_id_foreign` (`ketersediaan_info_id`),
  KEY `psn_created_by_foreign` (`created_by`),
  KEY `psn_updated_by_foreign` (`updated_by`),
  KEY `psn_deleted_by_foreign` (`deleted_by`),
  KEY `psn_klaster_id_status_psn_id_index` (`klaster_id`,`status_psn_id`),
  KEY `psn_kode_krisna_index` (`kode_krisna`),
  CONSTRAINT `psn_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_kategori_rkp_id_foreign` FOREIGN KEY (`kategori_rkp_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_ketersediaan_info_id_foreign` FOREIGN KEY (`ketersediaan_info_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_klaster_id_foreign` FOREIGN KEY (`klaster_id`) REFERENCES `ref_klaster` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_klaster_pkpn_id_foreign` FOREIGN KEY (`klaster_pkpn_id`) REFERENCES `ref_klaster_pkpn` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `ref_program` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_status_psn_id_foreign` FOREIGN KEY (`status_psn_id`) REFERENCES `ref_status_psn` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_sub_klaster_id_foreign` FOREIGN KEY (`sub_klaster_id`) REFERENCES `ref_sub_klaster` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_dokumen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `kategori_id` bigint(20) unsigned DEFAULT NULL,
  `judul` text NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `path` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_dokumen_psn_id_foreign` (`psn_id`),
  KEY `psn_dokumen_kategori_id_foreign` (`kategori_id`),
  KEY `psn_dokumen_created_by_foreign` (`created_by`),
  KEY `psn_dokumen_updated_by_foreign` (`updated_by`),
  KEY `psn_dokumen_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `psn_dokumen_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_dokumen_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_dokumen_kategori_id_foreign` FOREIGN KEY (`kategori_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_dokumen_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_dokumen_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_dukungan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `uraian` text NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_dukungan_psn_id_foreign` (`psn_id`),
  KEY `psn_dukungan_created_by_foreign` (`created_by`),
  KEY `psn_dukungan_updated_by_foreign` (`updated_by`),
  KEY `psn_dukungan_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `psn_dukungan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_dukungan_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_dukungan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_dukungan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_evaluasi_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned DEFAULT NULL,
  `kebutuhan_status` text NOT NULL,
  `justifikasi` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_evaluasi_status_psn_id_foreign` (`psn_id`),
  KEY `psn_evaluasi_status_created_by_foreign` (`created_by`),
  KEY `psn_evaluasi_status_updated_by_foreign` (`updated_by`),
  KEY `psn_evaluasi_status_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `psn_evaluasi_status_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_evaluasi_status_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_evaluasi_status_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_evaluasi_status_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_fasilitas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned DEFAULT NULL,
  `fasilitas_id` bigint(20) unsigned DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_fasilitas_psn_id_foreign` (`psn_id`),
  KEY `psn_fasilitas_fasilitas_id_foreign` (`fasilitas_id`),
  KEY `psn_fasilitas_created_by_foreign` (`created_by`),
  KEY `psn_fasilitas_updated_by_foreign` (`updated_by`),
  KEY `psn_fasilitas_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `psn_fasilitas_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_fasilitas_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_fasilitas_fasilitas_id_foreign` FOREIGN KEY (`fasilitas_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_fasilitas_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_fasilitas_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_kelembagaan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `peran` varchar(30) NOT NULL COMMENT 'PENGUSUL|PENANGGUNG_JAWAB|PELAKSANA|PENGELOLA|KONTRAKTOR|SUPERVISI',
  `penanggung_jawab_id` bigint(20) unsigned DEFAULT NULL,
  `instansi_id` bigint(20) unsigned DEFAULT NULL,
  `nama_teks` text DEFAULT NULL COMMENT 'nama bebas bila tidak ada di referensi',
  `urutan` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_kelembagaan_penanggung_jawab_id_foreign` (`penanggung_jawab_id`),
  KEY `psn_kelembagaan_instansi_id_foreign` (`instansi_id`),
  KEY `psn_kelembagaan_created_by_foreign` (`created_by`),
  KEY `psn_kelembagaan_updated_by_foreign` (`updated_by`),
  KEY `psn_kelembagaan_psn_id_peran_index` (`psn_id`,`peran`),
  CONSTRAINT `psn_kelembagaan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_kelembagaan_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `ref_instansi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_kelembagaan_penanggung_jawab_id_foreign` FOREIGN KEY (`penanggung_jawab_id`) REFERENCES `ref_penanggung_jawab` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_kelembagaan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_kelembagaan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_lokasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `provinsi_kode` varchar(13) NOT NULL,
  `kabupaten_kode` varchar(13) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL COMMENT 'titik lokasi (v2)',
  `lng` decimal(10,7) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_lokasi_psn_id_foreign` (`psn_id`),
  KEY `psn_lokasi_created_by_foreign` (`created_by`),
  KEY `psn_lokasi_updated_by_foreign` (`updated_by`),
  KEY `psn_lokasi_deleted_by_foreign` (`deleted_by`),
  KEY `psn_lokasi_kabupaten_kode_foreign` (`kabupaten_kode`),
  KEY `psn_lokasi_provinsi_kode_psn_id_index` (`provinsi_kode`,`psn_id`),
  CONSTRAINT `psn_lokasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_lokasi_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_lokasi_kabupaten_kode_foreign` FOREIGN KEY (`kabupaten_kode`) REFERENCES `ref_wilayah` (`kode`),
  CONSTRAINT `psn_lokasi_provinsi_kode_foreign` FOREIGN KEY (`provinsi_kode`) REFERENCES `ref_wilayah` (`kode`),
  CONSTRAINT `psn_lokasi_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_lokasi_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_profil_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `bagian_id` bigint(20) unsigned NOT NULL,
  `isi` longtext DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_profil_item_legacy_id_unique` (`legacy_id`),
  KEY `psn_profil_item_bagian_id_foreign` (`bagian_id`),
  KEY `psn_profil_item_created_by_foreign` (`created_by`),
  KEY `psn_profil_item_updated_by_foreign` (`updated_by`),
  KEY `psn_profil_item_deleted_by_foreign` (`deleted_by`),
  KEY `psn_profil_item_psn_id_bagian_id_index` (`psn_id`,`bagian_id`),
  CONSTRAINT `psn_profil_item_bagian_id_foreign` FOREIGN KEY (`bagian_id`) REFERENCES `ref_kode` (`id`),
  CONSTRAINT `psn_profil_item_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_profil_item_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_profil_item_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_profil_item_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_sdgs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `sdgs_id` bigint(20) unsigned NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_sdgs_psn_id_sdgs_id_unique` (`psn_id`,`sdgs_id`),
  KEY `psn_sdgs_sdgs_id_foreign` (`sdgs_id`),
  KEY `psn_sdgs_created_by_foreign` (`created_by`),
  KEY `psn_sdgs_updated_by_foreign` (`updated_by`),
  CONSTRAINT `psn_sdgs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_sdgs_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_sdgs_sdgs_id_foreign` FOREIGN KEY (`sdgs_id`) REFERENCES `ref_kode` (`id`),
  CONSTRAINT `psn_sdgs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_stakeholder` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `jenis` varchar(5) DEFAULT NULL COMMENT 'A=Stakeholder Mapping, B=Kerangka Kelembagaan (kode lama)',
  `peran_id` bigint(20) unsigned DEFAULT NULL,
  `level` tinyint(3) unsigned DEFAULT NULL COMMENT '1-4: Kebijakan, Fasilitator Wilayah, Operator/Investor, Partisipan',
  `peran` text DEFAULT NULL,
  `instansi_utama` text DEFAULT NULL,
  `instansi_pendukung` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `file_bukti` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_stakeholder_legacy_id_unique` (`legacy_id`),
  KEY `psn_stakeholder_psn_id_foreign` (`psn_id`),
  KEY `psn_stakeholder_peran_id_foreign` (`peran_id`),
  KEY `psn_stakeholder_created_by_foreign` (`created_by`),
  KEY `psn_stakeholder_updated_by_foreign` (`updated_by`),
  KEY `psn_stakeholder_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `psn_stakeholder_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_stakeholder_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_stakeholder_peran_id_foreign` FOREIGN KEY (`peran_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_stakeholder_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_stakeholder_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_sumber_dana` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `sumber_dana_id` bigint(20) unsigned NOT NULL,
  `nilai_rp` decimal(24,2) DEFAULT NULL COMMENT 'porsi investasi bila diketahui; null = belum dirinci',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_sumber_dana_psn_id_sumber_dana_id_unique` (`psn_id`,`sumber_dana_id`),
  KEY `psn_sumber_dana_sumber_dana_id_foreign` (`sumber_dana_id`),
  KEY `psn_sumber_dana_created_by_foreign` (`created_by`),
  KEY `psn_sumber_dana_updated_by_foreign` (`updated_by`),
  CONSTRAINT `psn_sumber_dana_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_sumber_dana_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_sumber_dana_sumber_dana_id_foreign` FOREIGN KEY (`sumber_dana_id`) REFERENCES `ref_sumber_dana` (`id`),
  CONSTRAINT `psn_sumber_dana_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_target_tahunan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `uraian` text NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `psn_target_tahunan_created_by_foreign` (`created_by`),
  KEY `psn_target_tahunan_updated_by_foreign` (`updated_by`),
  KEY `psn_target_tahunan_deleted_by_foreign` (`deleted_by`),
  KEY `psn_target_tahunan_psn_id_tahun_index` (`psn_id`,`tahun`),
  CONSTRAINT `psn_target_tahunan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_target_tahunan_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_target_tahunan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_target_tahunan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psn_unit_pengampu` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `psn_id` bigint(20) unsigned NOT NULL,
  `unit_kerja_id` bigint(20) unsigned NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `psn_unit_pengampu_psn_id_unit_kerja_id_unique` (`psn_id`,`unit_kerja_id`),
  KEY `psn_unit_pengampu_created_by_foreign` (`created_by`),
  KEY `psn_unit_pengampu_updated_by_foreign` (`updated_by`),
  KEY `psn_unit_pengampu_unit_kerja_id_index` (`unit_kerja_id`),
  CONSTRAINT `psn_unit_pengampu_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `psn_unit_pengampu_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_unit_pengampu_unit_kerja_id_foreign` FOREIGN KEY (`unit_kerja_id`) REFERENCES `ref_unit_kerja` (`id`) ON DELETE CASCADE,
  CONSTRAINT `psn_unit_pengampu_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_instansi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_instansi_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_klaster` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `urutan` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_klaster_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_klaster_pkpn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_klaster_pkpn_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_kode` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipe` varchar(10) NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(500) NOT NULL,
  `urutan` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_kode_tipe_kode_unique` (`tipe`,`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_kriteria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL COMMENT 'pertanyaan.id lama',
  `kelompok` varchar(20) NOT NULL COMMENT 'UTAMA|PENDUKUNG|KESIAPAN|LOKASI|TRISULA',
  `kode` varchar(10) NOT NULL,
  `induk_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text NOT NULL,
  `rubrik` text DEFAULT NULL,
  `tipe_nilai` varchar(15) NOT NULL COMMENT 'YA_TIDAK|SKOR_0_3',
  `kondisional` varchar(50) DEFAULT NULL COMMENT 'mis. PENGUSUL_KL, PENGUSUL_PEMDA, PENGUSUL_BU, INFRASTRUKTUR',
  `urutan` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_kriteria_kode_unique` (`kode`),
  UNIQUE KEY `ref_kriteria_legacy_id_unique` (`legacy_id`),
  KEY `ref_kriteria_induk_id_foreign` (`induk_id`),
  CONSTRAINT `ref_kriteria_induk_id_foreign` FOREIGN KEY (`induk_id`) REFERENCES `ref_kriteria` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_penanggung_jawab` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `instansi_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_penanggung_jawab_kode_unique` (`kode`),
  KEY `ref_penanggung_jawab_instansi_id_foreign` (`instansi_id`),
  CONSTRAINT `ref_penanggung_jawab_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `ref_instansi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_program` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_program_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_status_psn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tahap` varchar(20) DEFAULT NULL COMMENT 'PERENCANAAN|TRANSAKSI|KONSTRUKSI|OPERASI -- pemetaan Tahapan Status dashboard',
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'false = keluar dari PSN, tidak dihitung pada K1',
  `urutan` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_status_psn_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_sub_klaster` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `klaster_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_sub_klaster_kode_unique` (`kode`),
  KEY `ref_sub_klaster_klaster_id_foreign` (`klaster_id`),
  CONSTRAINT `ref_sub_klaster_klaster_id_foreign` FOREIGN KEY (`klaster_id`) REFERENCES `ref_klaster` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_sumber_dana` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `skema` varchar(20) DEFAULT NULL COMMENT 'APBN|APBD|KPBU|LAINNYA -- pengelompokan P6, lihat config psn_dashboard',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_sumber_dana_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_unit_kerja` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `jenis` varchar(20) NOT NULL DEFAULT 'LAINNYA' COMMENT 'DIREKTORAT|KL|PEMDA|BU|LAINNYA (heuristik nama, perlu kurasi)',
  `induk_id` bigint(20) unsigned DEFAULT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_unit_kerja_kode_unique` (`kode`),
  KEY `ref_unit_kerja_induk_id_foreign` (`induk_id`),
  KEY `ref_unit_kerja_jenis_index` (`jenis`),
  CONSTRAINT `ref_unit_kerja_induk_id_foreign` FOREIGN KEY (`induk_id`) REFERENCES `ref_unit_kerja` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_wilayah` (
  `kode` varchar(13) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `level` tinyint(3) unsigned NOT NULL COMMENT '0=nasional, 1=provinsi, 2=kab/kota, 3=kecamatan, 4=kel/desa',
  `induk_kode` varchar(13) DEFAULT NULL,
  `hc_key` varchar(20) DEFAULT NULL COMMENT 'kunci peta Highcharts dari tabel lama `peta` (provinsi)',
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`kode`),
  KEY `ref_wilayah_level_kode_index` (`level`,`kode`),
  KEY `ref_wilayah_induk_kode_index` (`induk_kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regulasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `nama` text NOT NULL,
  `justifikasi` text DEFAULT NULL,
  `penanggung_jawab` text DEFAULT NULL,
  `tahap` varchar(20) NOT NULL DEFAULT 'IDENTIFIKASI' COMMENT 'IDENTIFIKASI|PENYUSUNAN|HARMONISASI|DITETAPKAN',
  `nomor_penetapan` varchar(255) DEFAULT NULL,
  `tanggal_penetapan` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `regulasi_legacy_id_unique` (`legacy_id`),
  KEY `regulasi_created_by_foreign` (`created_by`),
  KEY `regulasi_updated_by_foreign` (`updated_by`),
  KEY `regulasi_deleted_by_foreign` (`deleted_by`),
  KEY `regulasi_psn_id_tahap_index` (`psn_id`,`tahap`),
  CONSTRAINT `regulasi_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regulasi_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `regulasi_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `regulasi_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regulasi_target_tahun` (
  `regulasi_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`regulasi_id`,`tahun`),
  CONSTRAINT `regulasi_target_tahun_regulasi_id_foreign` FOREIGN KEY (`regulasi_id`) REFERENCES `regulasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `risiko` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `kegiatan_id` bigint(20) unsigned DEFAULT NULL,
  `uraian` text NOT NULL,
  `kategori_id` bigint(20) unsigned DEFAULT NULL,
  `level_awal` varchar(20) DEFAULT NULL COMMENT 'Rendah|Sedang|Tinggi|Sangat Tinggi',
  `kemungkinan_awal` tinyint(3) unsigned DEFAULT NULL,
  `dampak_awal` tinyint(3) unsigned DEFAULT NULL,
  `level_harapan` varchar(20) DEFAULT NULL COMMENT 'risiko residual harapan',
  `kemungkinan_harapan` tinyint(3) unsigned DEFAULT NULL,
  `dampak_harapan` tinyint(3) unsigned DEFAULT NULL,
  `rencana_perlakuan` text DEFAULT NULL,
  `penanggung_jawab` text DEFAULT NULL,
  `is_titik_kritis` tinyint(1) NOT NULL DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `risiko_legacy_id_unique` (`legacy_id`),
  KEY `risiko_kegiatan_id_foreign` (`kegiatan_id`),
  KEY `risiko_kategori_id_foreign` (`kategori_id`),
  KEY `risiko_created_by_foreign` (`created_by`),
  KEY `risiko_updated_by_foreign` (`updated_by`),
  KEY `risiko_deleted_by_foreign` (`deleted_by`),
  KEY `risiko_psn_id_level_harapan_index` (`psn_id`,`level_harapan`),
  CONSTRAINT `risiko_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_kategori_id_foreign` FOREIGN KEY (`kategori_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `risiko_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `risiko_pemantauan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `risiko_id` bigint(20) unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `triwulan` tinyint(3) unsigned DEFAULT NULL,
  `level_aktual` varchar(20) DEFAULT NULL,
  `kemungkinan_aktual` tinyint(3) unsigned DEFAULT NULL,
  `dampak_aktual` tinyint(3) unsigned DEFAULT NULL,
  `progres_persen` tinyint(3) unsigned DEFAULT NULL,
  `status_perlakuan` varchar(50) DEFAULT NULL COMMENT 'BELUM|BERJALAN|SELESAI',
  `sumber` varchar(20) NOT NULL DEFAULT 'PELAPORAN' COMMENT 'PELAPORAN|MONEV',
  `monev_risiko_id` bigint(20) unsigned DEFAULT NULL COMMENT 'diisi bila sumber = MONEV',
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risiko_pemantauan_created_by_foreign` (`created_by`),
  KEY `risiko_pemantauan_updated_by_foreign` (`updated_by`),
  KEY `risiko_pemantauan_risiko_id_tanggal_index` (`risiko_id`,`tanggal`),
  CONSTRAINT `risiko_pemantauan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_pemantauan_risiko_id_foreign` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE,
  CONSTRAINT `risiko_pemantauan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `risiko_perlakuan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `risiko_id` bigint(20) unsigned NOT NULL,
  `uraian` varchar(255) DEFAULT NULL,
  `instansi_id` bigint(20) unsigned DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risiko_perlakuan_risiko_id_foreign` (`risiko_id`),
  KEY `risiko_perlakuan_instansi_id_foreign` (`instansi_id`),
  KEY `risiko_perlakuan_created_by_foreign` (`created_by`),
  KEY `risiko_perlakuan_updated_by_foreign` (`updated_by`),
  CONSTRAINT `risiko_perlakuan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_perlakuan_instansi_id_foreign` FOREIGN KEY (`instansi_id`) REFERENCES `ref_instansi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risiko_perlakuan_risiko_id_foreign` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE,
  CONSTRAINT `risiko_perlakuan_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `risiko_tahun_perlakuan` (
  `risiko_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`risiko_id`,`tahun`),
  CONSTRAINT `risiko_tahun_perlakuan_risiko_id_foreign` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snapshot_kegiatan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periode_cutoff_id` bigint(20) unsigned NOT NULL,
  `kegiatan_id` bigint(20) unsigned NOT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `is_critical_path` tinyint(1) NOT NULL DEFAULT 0,
  `target_persen` decimal(7,2) DEFAULT NULL,
  `realisasi_persen` decimal(7,2) DEFAULT NULL,
  `deviasi_pp` decimal(7,2) DEFAULT NULL,
  `status_progres` varchar(15) NOT NULL,
  `pagu_rp` decimal(24,2) DEFAULT NULL,
  `realisasi_anggaran_rp` decimal(24,2) DEFAULT NULL,
  `is_tercapai` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `snapshot_kegiatan_periode_cutoff_id_kegiatan_id_unique` (`periode_cutoff_id`,`kegiatan_id`),
  KEY `snapshot_kegiatan_kegiatan_id_foreign` (`kegiatan_id`),
  KEY `snapshot_kegiatan_psn_id_foreign` (`psn_id`),
  KEY `snapshot_kegiatan_cp_deviasi_index` (`periode_cutoff_id`,`is_critical_path`,`deviasi_pp`),
  CONSTRAINT `snapshot_kegiatan_kegiatan_id_foreign` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_kegiatan_periode_cutoff_id_foreign` FOREIGN KEY (`periode_cutoff_id`) REFERENCES `periode_cutoff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_kegiatan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snapshot_kelengkapan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periode_cutoff_id` bigint(20) unsigned NOT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `bagian` varchar(30) NOT NULL,
  `field_wajib` smallint(5) unsigned NOT NULL,
  `field_terisi` smallint(5) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `snapshot_kelengkapan_periode_cutoff_id_psn_id_bagian_unique` (`periode_cutoff_id`,`psn_id`,`bagian`),
  KEY `snapshot_kelengkapan_psn_id_foreign` (`psn_id`),
  CONSTRAINT `snapshot_kelengkapan_periode_cutoff_id_foreign` FOREIGN KEY (`periode_cutoff_id`) REFERENCES `periode_cutoff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_kelengkapan_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snapshot_psn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periode_cutoff_id` bigint(20) unsigned NOT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `klaster_id` bigint(20) unsigned DEFAULT NULL,
  `status_psn_id` bigint(20) unsigned DEFAULT NULL,
  `tahap` varchar(20) DEFAULT NULL,
  `kategori` varchar(10) DEFAULT NULL COMMENT 'PSN|PKPN',
  `provinsi_kode` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provinsi_kode`)),
  `unit_kerja_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unit_kerja_id`)),
  `sumber_dana_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sumber_dana_id`)),
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `nilai_investasi_rp` decimal(24,2) DEFAULT NULL,
  `progres_rencana_persen` decimal(7,2) DEFAULT NULL,
  `progres_realisasi_persen` decimal(7,2) DEFAULT NULL,
  `deviasi_pp` decimal(7,2) DEFAULT NULL,
  `status_progres` varchar(15) NOT NULL COMMENT 'ON_TRACK|BERISIKO|TERLAMBAT|TANPA_DATA',
  `pagu_rp` decimal(24,2) DEFAULT NULL,
  `realisasi_anggaran_rp` decimal(24,2) DEFAULT NULL,
  `jumlah_ro` int(10) unsigned NOT NULL DEFAULT 0,
  `jumlah_ro_tercapai` int(10) unsigned NOT NULL DEFAULT 0,
  `risiko_skor_maks` tinyint(3) unsigned DEFAULT NULL,
  `risiko_level_maks` varchar(20) DEFAULT NULL,
  `is_kritis` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'K4: Terlambat ATAU risiko residual >= Tinggi',
  `kelengkapan_persen` decimal(5,2) DEFAULT NULL,
  `pembaruan_terakhir_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `snapshot_psn_periode_cutoff_id_psn_id_unique` (`periode_cutoff_id`,`psn_id`),
  KEY `snapshot_psn_psn_id_foreign` (`psn_id`),
  KEY `snapshot_psn_periode_cutoff_id_klaster_id_index` (`periode_cutoff_id`,`klaster_id`),
  KEY `snapshot_psn_periode_cutoff_id_status_progres_index` (`periode_cutoff_id`,`status_progres`),
  CONSTRAINT `snapshot_psn_periode_cutoff_id_foreign` FOREIGN KEY (`periode_cutoff_id`) REFERENCES `periode_cutoff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_psn_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `snapshot_risiko` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periode_cutoff_id` bigint(20) unsigned NOT NULL,
  `risiko_id` bigint(20) unsigned NOT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `kategori_id` bigint(20) unsigned DEFAULT NULL,
  `kemungkinan_harapan` tinyint(3) unsigned DEFAULT NULL,
  `dampak_harapan` tinyint(3) unsigned DEFAULT NULL,
  `level_harapan` varchar(20) DEFAULT NULL,
  `kemungkinan_aktual` tinyint(3) unsigned DEFAULT NULL,
  `dampak_aktual` tinyint(3) unsigned DEFAULT NULL,
  `level_aktual` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `snapshot_risiko_periode_cutoff_id_risiko_id_unique` (`periode_cutoff_id`,`risiko_id`),
  KEY `snapshot_risiko_risiko_id_foreign` (`risiko_id`),
  KEY `snapshot_risiko_psn_id_foreign` (`psn_id`),
  CONSTRAINT `snapshot_risiko_periode_cutoff_id_foreign` FOREIGN KEY (`periode_cutoff_id`) REFERENCES `periode_cutoff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_risiko_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `snapshot_risiko_risiko_id_foreign` FOREIGN KEY (`risiko_id`) REFERENCES `risiko` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trisula` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `psn_id` bigint(20) unsigned NOT NULL,
  `kategori_id` bigint(20) unsigned DEFAULT NULL,
  `indikator` text DEFAULT NULL,
  `kontribusi` text DEFAULT NULL,
  `satuan` varchar(100) DEFAULT NULL,
  `baseline` decimal(24,4) DEFAULT NULL,
  `baseline_tahun` smallint(5) unsigned DEFAULT NULL,
  `target_akhir` decimal(24,4) DEFAULT NULL,
  `capaian` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trisula_legacy_id_unique` (`legacy_id`),
  KEY `trisula_psn_id_foreign` (`psn_id`),
  KEY `trisula_kategori_id_foreign` (`kategori_id`),
  KEY `trisula_created_by_foreign` (`created_by`),
  KEY `trisula_updated_by_foreign` (`updated_by`),
  KEY `trisula_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `trisula_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trisula_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trisula_kategori_id_foreign` FOREIGN KEY (`kategori_id`) REFERENCES `ref_kode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trisula_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trisula_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trisula_target` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trisula_id` bigint(20) unsigned NOT NULL,
  `tahun` smallint(5) unsigned NOT NULL,
  `periode` varchar(10) NOT NULL DEFAULT 'TAHUNAN' COMMENT 'TAHUNAN|TRIWULAN',
  `periode_ke` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `target` decimal(24,4) DEFAULT NULL,
  `realisasi` decimal(24,4) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trisula_target_trisula_id_tahun_periode_periode_ke_unique` (`trisula_id`,`tahun`,`periode`,`periode_ke`),
  KEY `trisula_target_created_by_foreign` (`created_by`),
  KEY `trisula_target_updated_by_foreign` (`updated_by`),
  CONSTRAINT `trisula_target_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trisula_target_trisula_id_foreign` FOREIGN KEY (`trisula_id`) REFERENCES `trisula` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trisula_target_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `unit_kerja_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `wajib_ganti_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `jumlah_login` int(10) unsigned NOT NULL DEFAULT 0,
  `legacy_grup` varchar(50) DEFAULT NULL COMMENT 'users.grup pada basis data lama',
  `legacy_akses` varchar(20) DEFAULT NULL COMMENT 'users.akses (kode UNIT) pada basis data lama',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_unit_kerja_id_index` (`unit_kerja_id`),
  CONSTRAINT `users_unit_kerja_id_foreign` FOREIGN KEY (`unit_kerja_id`) REFERENCES `ref_unit_kerja` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usulan_lokasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usulan_id` bigint(20) unsigned NOT NULL,
  `provinsi_kode` varchar(13) NOT NULL,
  `kabupaten_kode` varchar(13) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usulan_lokasi_usulan_id_foreign` (`usulan_id`),
  KEY `usulan_lokasi_provinsi_kode_foreign` (`provinsi_kode`),
  KEY `usulan_lokasi_kabupaten_kode_foreign` (`kabupaten_kode`),
  CONSTRAINT `usulan_lokasi_kabupaten_kode_foreign` FOREIGN KEY (`kabupaten_kode`) REFERENCES `ref_wilayah` (`kode`),
  CONSTRAINT `usulan_lokasi_provinsi_kode_foreign` FOREIGN KEY (`provinsi_kode`) REFERENCES `ref_wilayah` (`kode`),
  CONSTRAINT `usulan_lokasi_usulan_id_foreign` FOREIGN KEY (`usulan_id`) REFERENCES `usulan_psn` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usulan_psn` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int(10) unsigned DEFAULT NULL,
  `tahun_rkp` smallint(5) unsigned NOT NULL,
  `nama` text NOT NULL,
  `klaster_id` bigint(20) unsigned DEFAULT NULL,
  `pengusul_instansi_id` bigint(20) unsigned DEFAULT NULL,
  `pengusul_teks` varchar(255) DEFAULT NULL,
  `jenis_pengusul` varchar(20) DEFAULT NULL COMMENT 'KL|PEMDA|BUMN_SWASTA',
  `is_infrastruktur` tinyint(1) NOT NULL DEFAULT 0,
  `nilai_investasi_rp` decimal(24,2) DEFAULT NULL,
  `psn_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DIAJUKAN' COMMENT 'DIAJUKAN|DINILAI|DITETAPKAN|DITOLAK',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usulan_psn_legacy_id_unique` (`legacy_id`),
  KEY `usulan_psn_klaster_id_foreign` (`klaster_id`),
  KEY `usulan_psn_pengusul_instansi_id_foreign` (`pengusul_instansi_id`),
  KEY `usulan_psn_psn_id_foreign` (`psn_id`),
  KEY `usulan_psn_created_by_foreign` (`created_by`),
  KEY `usulan_psn_updated_by_foreign` (`updated_by`),
  KEY `usulan_psn_deleted_by_foreign` (`deleted_by`),
  CONSTRAINT `usulan_psn_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usulan_psn_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usulan_psn_klaster_id_foreign` FOREIGN KEY (`klaster_id`) REFERENCES `ref_klaster` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usulan_psn_pengusul_instansi_id_foreign` FOREIGN KEY (`pengusul_instansi_id`) REFERENCES `ref_instansi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usulan_psn_psn_id_foreign` FOREIGN KEY (`psn_id`) REFERENCES `psn` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usulan_psn_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_kegiatan_progres` AS SELECT
 1 AS `kegiatan_id`,
  1 AS `psn_id`,
  1 AS `nama`,
  1 AS `is_critical_path`,
  1 AS `tahun`,
  1 AS `periode`,
  1 AS `periode_ke`,
  1 AS `target_persen`,
  1 AS `realisasi_persen`,
  1 AS `deviasi_pp`,
  1 AS `pagu_rp`,
  1 AS `realisasi_anggaran_rp`,
  1 AS `dilaporkan_at` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_psn_ringkas` AS SELECT
 1 AS `psn_id`,
  1 AS `kode_psn`,
  1 AS `nama`,
  1 AS `klaster_id`,
  1 AS `klaster_kode`,
  1 AS `klaster_nama`,
  1 AS `klaster_pkpn_id`,
  1 AS `kategori`,
  1 AS `status_psn_id`,
  1 AS `status_psn_nama`,
  1 AS `tahap`,
  1 AS `is_aktif`,
  1 AS `nilai_investasi_rp`,
  1 AS `investasi_anomali`,
  1 AS `tahun_selesai`,
  1 AS `provinsi_kode_list`,
  1 AS `unit_kerja_id_list`,
  1 AS `sumber_dana_id_list`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_kegiatan_progres`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_kegiatan_progres` AS select `g`.`id` AS `kegiatan_id`,`g`.`psn_id` AS `psn_id`,`g`.`nama` AS `nama`,`g`.`is_critical_path` AS `is_critical_path`,`t`.`tahun` AS `tahun`,`t`.`periode` AS `periode`,`t`.`periode_ke` AS `periode_ke`,`t`.`target_persen` AS `target_persen`,`t`.`realisasi_persen` AS `realisasi_persen`,`t`.`realisasi_persen` - `t`.`target_persen` AS `deviasi_pp`,`t`.`pagu_rp` AS `pagu_rp`,`t`.`realisasi_anggaran_rp` AS `realisasi_anggaran_rp`,coalesce(`t`.`dilaporkan_at`,`t`.`updated_at`) AS `dilaporkan_at` from (`kegiatan` `g` join `kegiatan_target` `t` on(`t`.`kegiatan_id` = `g`.`id`)) where `g`.`deleted_at` is null and `t`.`id` = (select `t2`.`id` from `kegiatan_target` `t2` where `t2`.`kegiatan_id` = `g`.`id` and `t2`.`tahun` = `t`.`tahun` order by `t2`.`realisasi_persen` is null,field(`t2`.`periode`,'BULANAN','TRIWULAN','TAHUNAN'),`t2`.`periode_ke` desc limit 1) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_psn_ringkas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_psn_ringkas` AS select `p`.`id` AS `psn_id`,`p`.`kode_psn` AS `kode_psn`,`p`.`nama` AS `nama`,`p`.`klaster_id` AS `klaster_id`,`k`.`kode` AS `klaster_kode`,`k`.`nama` AS `klaster_nama`,`p`.`klaster_pkpn_id` AS `klaster_pkpn_id`,case when `p`.`klaster_pkpn_id` is not null then 'PKPN' else 'PSN' end AS `kategori`,`p`.`status_psn_id` AS `status_psn_id`,`s`.`nama` AS `status_psn_nama`,`s`.`tahap` AS `tahap`,coalesce(`s`.`is_aktif`,1) AS `is_aktif`,`p`.`nilai_investasi_rp` AS `nilai_investasi_rp`,`p`.`investasi_anomali` AS `investasi_anomali`,`p`.`tahun_selesai` AS `tahun_selesai`,(select group_concat(distinct `l`.`provinsi_kode` order by `l`.`provinsi_kode` ASC separator ',') from `psn_lokasi` `l` where `l`.`psn_id` = `p`.`id` and `l`.`deleted_at` is null) AS `provinsi_kode_list`,(select group_concat(distinct `u`.`unit_kerja_id` order by `u`.`unit_kerja_id` ASC separator ',') from `psn_unit_pengampu` `u` where `u`.`psn_id` = `p`.`id`) AS `unit_kerja_id_list`,(select group_concat(distinct `d`.`sumber_dana_id` order by `d`.`sumber_dana_id` ASC separator ',') from `psn_sumber_dana` `d` where `d`.`psn_id` = `p`.`id`) AS `sumber_dana_id_list`,`p`.`updated_at` AS `updated_at` from ((`psn` `p` left join `ref_klaster` `k` on(`k`.`id` = `p`.`klaster_id`)) left join `ref_status_psn` `s` on(`s`.`id` = `p`.`status_psn_id`)) where `p`.`deleted_at` is null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

