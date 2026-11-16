<?php

namespace App\Support;

/**
 * Menaikkan batas memori & waktu PHP hanya untuk proses berat (ekspor/unggah Excel, penerapan & pengisian
 * cermin PEKS3), agar tidak bergantung pada memory_limit hosting (cPanel bawaan 128M). Batas tidak pernah
 * diturunkan: bila hosting sudah lebih besar atau tanpa batas (-1), nilai hosting dipakai.
 */
class ProsesBerat
{
    public static function siapkan(): void
    {
        $cfg = config('psn_dashboard.proses_berat');
        $kini = self::bita((string) ini_get('memory_limit'));
        $mau = self::bita((string) $cfg['memori']);
        if ($kini !== -1 && ($mau === -1 || $mau > $kini)) {
            @ini_set('memory_limit', (string) $cfg['memori']);
        }
        $waktu = (int) ini_get('max_execution_time');
        if ($waktu !== 0 && $waktu < $cfg['waktu_detik'] && function_exists('set_time_limit')) {
            @set_time_limit($cfg['waktu_detik']);
        }
    }

    /** "128M" -> 134217728; "-1" -> -1. */
    public static function bita(string $nilai): int
    {
        $nilai = trim($nilai);
        if ($nilai === '' || $nilai === '-1') {
            return -1;
        }
        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024 ** 3,
            'm' => $angka * 1024 ** 2,
            'k' => $angka * 1024,
            default => $angka,
        };
    }
}
