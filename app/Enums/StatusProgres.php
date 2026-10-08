<?php

namespace App\Enums;

/**
 * Status progres proyek/RO. Ditampilkan selalu sebagai badge berlabel + warna.
 */
enum StatusProgres: string
{
    case OnTrack = 'ON_TRACK';
    case Berisiko = 'BERISIKO';
    case Terlambat = 'TERLAMBAT';
    case TanpaData = 'TANPA_DATA';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'On Track',
            self::Berisiko => 'Berisiko',
            self::Terlambat => 'Terlambat',
            self::TanpaData => 'Tanpa data',
        };
    }

    /** Kelas warna Tailwind (kontras teks putih >= 4,5:1). */
    public function warna(): string
    {
        return match ($this) {
            self::OnTrack => 'bg-green-600',
            self::Berisiko => 'bg-amber-600',
            self::Terlambat => 'bg-red-600',
            self::TanpaData => 'bg-slate-500',
        };
    }
}
