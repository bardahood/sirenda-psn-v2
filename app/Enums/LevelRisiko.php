<?php

namespace App\Enums;

/**
 * Level risiko residual. Nilai enum = label yang disimpan di basis data.
 */
enum LevelRisiko: string
{
    case Rendah = 'Rendah';
    case Sedang = 'Sedang';
    case Tinggi = 'Tinggi';
    case SangatTinggi = 'Sangat Tinggi';

    public function urutan(): int
    {
        return match ($this) {
            self::Rendah => 1,
            self::Sedang => 2,
            self::Tinggi => 3,
            self::SangatTinggi => 4,
        };
    }

    public function atLeast(self $lain): bool
    {
        return $this->urutan() >= $lain->urutan();
    }

    public function warna(): string
    {
        return match ($this) {
            self::Rendah => 'bg-green-600',
            self::Sedang => 'bg-amber-600',
            self::Tinggi => 'bg-orange-700',
            self::SangatTinggi => 'bg-red-700',
        };
    }
}
