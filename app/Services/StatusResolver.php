<?php

namespace App\Services;

use App\Enums\LevelRisiko;
use App\Enums\StatusProgres;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Aturan status otomatis dashboard (lihat docs/kamus-indikator.md).
 * Semua ambang dibaca dari config psn_dashboard; konstruktor menerima array
 * agar dapat diuji tanpa kontainer aplikasi.
 */
class StatusResolver
{
    protected array $cfg;

    public function __construct(?array $config = null)
    {
        $this->cfg = $config ?? config('psn_dashboard');
    }

    /**
     * Deviasi = realisasi - rencana (pp). On Track > -5; Berisiko -5 s.d. -20 (inklusif);
     * Terlambat < -20; Tanpa data bila tidak ada pembaruan > N hari dari cut-off atau
     * rencana/realisasi tidak tersedia.
     */
    public function statusProgres(?float $rencana, ?float $realisasi, ?CarbonInterface $pembaruanTerakhir, CarbonInterface $cutoff): StatusProgres
    {
        if ($this->tanpaData($pembaruanTerakhir, $cutoff) || $rencana === null || $realisasi === null) {
            return StatusProgres::TanpaData;
        }

        return $this->statusDariDeviasi($realisasi - $rencana);
    }

    public function statusDariDeviasi(float $deviasi): StatusProgres
    {
        $deviasi = round($deviasi, 4);
        $s = $this->cfg['status_progres'];

        return match (true) {
            $deviasi > $s['on_track_min_deviasi'] => StatusProgres::OnTrack,
            $deviasi >= $s['terlambat_max_deviasi'] => StatusProgres::Berisiko,
            default => StatusProgres::Terlambat,
        };
    }

    /** Lebih dari N hari (bukan sama dengan) antara pembaruan terakhir dan cut-off. */
    public function tanpaData(?CarbonInterface $pembaruanTerakhir, CarbonInterface $cutoff): bool
    {
        if ($pembaruanTerakhir === null) {
            return true;
        }

        return $pembaruanTerakhir->copy()->startOfDay()->diffInDays($cutoff->copy()->startOfDay(), false)
            > $this->cfg['status_progres']['tanpa_data_hari'];
    }

    public function skorRisiko(int $kemungkinan, int $dampak): int
    {
        foreach (['kemungkinan' => $kemungkinan, 'dampak' => $dampak] as $nama => $v) {
            if ($v < 1 || $v > 5) {
                throw new InvalidArgumentException("Nilai {$nama} harus 1-5, diberikan {$v}.");
            }
        }

        return $kemungkinan * $dampak;
    }

    public function levelDariSkor(int $skor): LevelRisiko
    {
        foreach ($this->cfg['risiko']['level'] as $label => [$min, $maks]) {
            if ($skor >= $min && $skor <= $maks) {
                return LevelRisiko::from($label);
            }
        }

        throw new InvalidArgumentException("Skor risiko di luar rentang 1-25: {$skor}.");
    }

    /**
     * Level risiko dari skala 1-5 bila tersedia, jika tidak dari label (data lama).
     *
     * @return array{0: ?LevelRisiko, 1: ?int} [level, skor]
     */
    public function levelRisiko(?int $kemungkinan, ?int $dampak, ?string $label = null): array
    {
        if ($kemungkinan !== null && $dampak !== null) {
            $skor = $this->skorRisiko($kemungkinan, $dampak);

            return [$this->levelDariSkor($skor), $skor];
        }

        $level = $label !== null ? LevelRisiko::tryFrom($label) : null;

        return [$level, $level ? $this->cfg['risiko']['skor_dari_label'][$level->value] : null];
    }

    public function isRisikoKritis(?LevelRisiko $level): bool
    {
        return $level !== null && $level->atLeast(LevelRisiko::from($this->cfg['risiko']['kritis_min_level']));
    }

    /**
     * Delta kartu KPI terhadap cut-off sebelumnya.
     * jenis 'jumlah' => persen perubahan; 'persen' => selisih poin persentase.
     * arahBaik 'naik' (lebih tinggi lebih baik) atau 'turun' (mis. K4).
     *
     * @return array{nilai: ?float, satuan: string, arah: string, baik: ?bool}
     */
    public function delta(?float $sekarang, ?float $sebelumnya, string $jenis = 'jumlah', string $arahBaik = 'naik'): array
    {
        $satuan = $jenis === 'persen' ? 'pp' : '%';

        if ($sekarang === null || $sebelumnya === null || ($jenis === 'jumlah' && $sebelumnya == 0.0)) {
            return ['nilai' => null, 'satuan' => $satuan, 'arah' => 'tidak_tersedia', 'baik' => null];
        }

        $nilai = $jenis === 'persen'
            ? $sekarang - $sebelumnya
            : ($sekarang - $sebelumnya) / abs($sebelumnya) * 100;
        $nilai = round($nilai, 1);

        $arah = $nilai > 0 ? 'naik' : ($nilai < 0 ? 'turun' : 'tetap');

        return [
            'nilai' => $nilai,
            'satuan' => $satuan,
            'arah' => $arah,
            'baik' => $arah === 'tetap' ? null : $arah === $arahBaik,
        ];
    }
}
