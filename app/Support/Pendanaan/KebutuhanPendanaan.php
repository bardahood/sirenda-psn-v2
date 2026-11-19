<?php

namespace App\Support\Pendanaan;

use Illuminate\Support\Facades\DB;

/**
 * A9 "Total kebutuhan pendanaan / nilai investasi / anggaran program" (Form Pengendalian & Evaluasi).
 * Jumlah Indikasi Pendanaan per sumber dana di SIRENDA (psn_sumber_dana.nilai_rp) bila sudah dirinci;
 * bila belum, Nilai Investasi profil PSN. Kartu Total Investasi (K2) tetap memakai Nilai Investasi.
 */
final class KebutuhanPendanaan
{
    /** Selisih di bawah ini dianggap sama (pembulatan isian). */
    public const TOLERANSI_RP = 1_000_000;

    /** @return array<int, float> psn_id => jumlah indikasi pendanaan (> 0 saja) */
    public static function indikasi(array $psnId): array
    {
        return DB::table('psn_sumber_dana')->whereIn('psn_id', $psnId ?: [0])->whereNotNull('nilai_rp')
            ->groupBy('psn_id')->havingRaw('SUM(nilai_rp) > 0')->selectRaw('psn_id, SUM(nilai_rp) AS total')
            ->pluck('total', 'psn_id')->map(fn ($v) => (float) $v)->all();
    }

    /**
     * @return array{nilai: ?float, sumber: string, nilai_investasi: ?float, indikasi: ?float, beda: bool}
     *                                                                                                     sumber: INDIKASI | NILAI_INVESTASI | KOSONG
     */
    public static function pilih(?float $indikasi, $nilaiInvestasi): array
    {
        $nilaiInvestasi = $nilaiInvestasi !== null ? (float) $nilaiInvestasi : null;
        $indikasi = $indikasi > 0 ? $indikasi : null;

        return [
            'nilai' => $indikasi ?? $nilaiInvestasi,
            'sumber' => $indikasi !== null ? 'INDIKASI' : ($nilaiInvestasi !== null ? 'NILAI_INVESTASI' : 'KOSONG'),
            'nilai_investasi' => $nilaiInvestasi,
            'indikasi' => $indikasi,
            'beda' => $indikasi !== null && $nilaiInvestasi !== null && abs($indikasi - $nilaiInvestasi) > self::TOLERANSI_RP,
        ];
    }

    /** Catatan di bawah angka A9 bila sumbernya perlu dijelaskan. */
    public static function catatan(array $k, bool $anomali = false): ?string
    {
        $rp = fn (float $v) => 'Rp '.number_format($v, 0, ',', '.');

        return match (true) {
            $k['beda'] => 'Jumlah indikasi pendanaan per sumber dana di SIRENDA. Nilai investasi di profil PSN: '.$rp($k['nilai_investasi']).'; mohon diselaraskan.',
            $k['sumber'] === 'NILAI_INVESTASI' && $anomali => 'Nilai investasi di SIRENDA ditandai perlu verifikasi; mohon dikoreksi.',
            $k['sumber'] === 'NILAI_INVESTASI' => 'Nilai investasi profil PSN (indikasi pendanaan per sumber dana belum dirinci di SIRENDA).',
            default => null,
        };
    }
}
