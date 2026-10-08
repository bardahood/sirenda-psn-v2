<?php

namespace App\Enums;

enum Rekomendasi: string
{
    case Direkomendasikan = 'DIREKOMENDASIKAN';
    case Dipertimbangkan = 'DIPERTIMBANGKAN';
    case TidakDirekomendasikan = 'TIDAK_DIREKOMENDASIKAN';
    case Ditolak = 'DITOLAK';
    case BelumLengkap = 'BELUM_LENGKAP';
    case AmbangBelumDitetapkan = 'AMBANG_BELUM_DITETAPKAN';

    public function label(): string
    {
        return match ($this) {
            self::Direkomendasikan => 'Direkomendasikan',
            self::Dipertimbangkan => 'Dipertimbangkan',
            self::TidakDirekomendasikan => 'Tidak direkomendasikan',
            self::Ditolak => 'Ditolak',
            self::BelumLengkap => 'Penilaian belum lengkap',
            self::AmbangBelumDitetapkan => 'Ambang rekomendasi belum ditetapkan',
        };
    }

    /** Kelas badge (teks gelap di latar muda, kontras >= 4,5:1). */
    public function badge(): string
    {
        return match ($this) {
            self::Direkomendasikan => 'badge bg-green-50 text-green-800 ring-green-600/40',
            self::Dipertimbangkan => 'badge bg-amber-50 text-amber-900 ring-amber-600/40',
            self::TidakDirekomendasikan, self::Ditolak => 'badge bg-red-50 text-red-800 ring-red-600/40',
            default => 'badge bg-slate-100 text-slate-700 ring-slate-500/40',
        };
    }
}
