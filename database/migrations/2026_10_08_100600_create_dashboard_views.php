<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View baca untuk lapisan Service dashboard. View hanya menggabungkan data
 * terkini; angka per cut-off dibaca dari tabel snapshot_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per PSN dengan dimensi filter global. Kategori PKPN
        // diturunkan dari klaster_pkpn_id (asumsi -- lihat docs/rancangan-aplikasi.md Q-07).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_psn_ringkas AS
            SELECT
                p.id AS psn_id,
                p.kode_psn,
                p.nama,
                p.klaster_id,
                k.kode AS klaster_kode,
                k.nama AS klaster_nama,
                p.klaster_pkpn_id,
                CASE WHEN p.klaster_pkpn_id IS NOT NULL THEN 'PKPN' ELSE 'PSN' END AS kategori,
                p.status_psn_id,
                s.nama AS status_psn_nama,
                s.tahap,
                COALESCE(s.is_aktif, 1) AS is_aktif,
                p.nilai_investasi_rp,
                p.investasi_anomali,
                p.tahun_selesai,
                (SELECT GROUP_CONCAT(DISTINCT l.provinsi_kode ORDER BY l.provinsi_kode)
                   FROM psn_lokasi l WHERE l.psn_id = p.id AND l.deleted_at IS NULL) AS provinsi_kode_list,
                (SELECT GROUP_CONCAT(DISTINCT u.unit_kerja_id ORDER BY u.unit_kerja_id)
                   FROM psn_unit_pengampu u WHERE u.psn_id = p.id) AS unit_kerja_id_list,
                (SELECT GROUP_CONCAT(DISTINCT d.sumber_dana_id ORDER BY d.sumber_dana_id)
                   FROM psn_sumber_dana d WHERE d.psn_id = p.id) AS sumber_dana_id_list,
                p.updated_at
            FROM psn p
            LEFT JOIN ref_klaster k ON k.id = p.klaster_id
            LEFT JOIN ref_status_psn s ON s.id = p.status_psn_id
            WHERE p.deleted_at IS NULL
        SQL);

        // Realisasi terbaru per KP/RO: baris periode terakhir yang memiliki
        // realisasi pada tiap tahun. Deviasi dalam poin persentase.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_kegiatan_progres AS
            SELECT
                g.id AS kegiatan_id,
                g.psn_id,
                g.nama,
                g.is_critical_path,
                t.tahun,
                t.periode,
                t.periode_ke,
                t.target_persen,
                t.realisasi_persen,
                t.realisasi_persen - t.target_persen AS deviasi_pp,
                t.pagu_rp,
                t.realisasi_anggaran_rp,
                COALESCE(t.dilaporkan_at, t.updated_at) AS dilaporkan_at
            FROM kegiatan g
            JOIN kegiatan_target t ON t.kegiatan_id = g.id
            WHERE g.deleted_at IS NULL
              AND t.id = (
                  SELECT t2.id FROM kegiatan_target t2
                  WHERE t2.kegiatan_id = g.id AND t2.tahun = t.tahun
                  ORDER BY (t2.realisasi_persen IS NULL), FIELD(t2.periode, 'BULANAN', 'TRIWULAN', 'TAHUNAN'), t2.periode_ke DESC
                  LIMIT 1
              )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_kegiatan_progres');
        DB::statement('DROP VIEW IF EXISTS v_psn_ringkas');
    }
};
