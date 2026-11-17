<?php

namespace App\Support\Evaluasi;

/**
 * Menggolongkan teks isian K/L "Kebutuhan Status PSN" (SIRENDA PEKS3, psn_kebutuhan jenis B) menjadi
 * TIDAK / MASIH / KONFIRMASI dengan pola di config psn_dashboard.ikhtisar.kebutuhan_status (TIDAK diuji dulu).
 * Dipakai D1 menu Evaluasi dan panel "PSN Selesai menurut Kebutuhan Status" agar aturannya sama.
 */
final class KebutuhanStatus
{
    public static function kategori(?string $teks): string
    {
        $teks = trim((string) $teks);
        if ($teks === '') {
            return 'KONFIRMASI';
        }
        foreach (config('psn_dashboard.ikhtisar.kebutuhan_status') as $kategori => $pola) {
            foreach ($pola as $p) {
                if (preg_match($p, $teks)) {
                    return $kategori;
                }
            }
        }

        return 'KONFIRMASI';
    }

    /** D1 dari teks SIRENDA: MASIH / TIDAK, atau null bila kosong atau tidak tegas. */
    public static function d1(?string $teks): ?string
    {
        $k = self::kategori($teks);

        return $k === 'KONFIRMASI' ? null : $k;
    }
}
