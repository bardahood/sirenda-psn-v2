<?php

namespace App\Support;

/**
 * Label UI berbahasa Indonesia untuk kode teknis (nama tabel, aksi audit).
 * Padanannya di frontend: resources/js/label.js -- perbarui bersamaan.
 */
final class Label
{
    public const TABEL = [
        'psn' => 'profil PSN', 'psn_lokasi' => 'lokasi PSN', 'psn_stakeholder' => 'stakeholder', 'psn_profil_item' => 'isian project profile',
        'psn_dokumen' => 'dokumen', 'psn_fasilitas' => 'fasilitas', 'psn_sdgs' => 'SDGs', 'psn_sumber_dana' => 'sumber dana',
        'kegiatan' => 'KP/RO', 'kegiatan_target' => 'target/realisasi KP/RO', 'risiko' => 'risiko', 'risiko_pemantauan' => 'pemantauan risiko',
        'risiko_perlakuan' => 'perlakuan risiko', 'regulasi' => 'regulasi', 'isu' => 'isu', 'indikator' => 'indikator',
        'penerima_manfaat' => 'penerima manfaat', 'trisula' => 'indikator Trisula', 'periode_cutoff' => 'snapshot cut-off', 'pengisian_psn' => 'pengisian data',
    ];

    public const AKSI = [
        'CREATE' => 'menambah', 'UPDATE' => 'mengubah', 'DELETE' => 'menghapus', 'RESTORE' => 'memulihkan',
        'PUBLISH' => 'menerbitkan', 'SUBMIT' => 'mengajukan', 'VERIFY' => 'memverifikasi', 'RETURN' => 'mengembalikan',
    ];

    public static function tabel(string $t): string
    {
        return self::TABEL[$t] ?? str_replace('_', ' ', $t);
    }

    public static function aksi(string $a): string
    {
        return self::AKSI[$a] ?? strtolower($a);
    }

    public static function pengguna(?string $p): string
    {
        return in_array($p, [null, '', 'console', 'sistem'], true) ? 'Sistem' : $p;
    }
}
