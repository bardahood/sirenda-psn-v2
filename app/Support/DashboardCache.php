<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache dashboard per (nama panel, cut-off, filter). Pembatalan dilakukan dengan
 * menaikkan nomor versi -- berlaku untuk semua driver cache tanpa perlu tag.
 */
class DashboardCache
{
    protected const KUNCI_VERSI = 'psn_dashboard:versi';

    public static function remember(string $nama, ?string $cutoff, array $filter, string $jenisTtl, \Closure $hitung): mixed
    {
        ksort($filter);
        $kunci = sprintf('psn_dashboard:v%d:%s:%s:%s', self::versi(), $nama, $cutoff ?? '-', md5(json_encode($filter)));

        return Cache::remember($kunci, config("psn_dashboard.cache.{$jenisTtl}"), $hitung);
    }

    public static function versi(): int
    {
        return (int) Cache::get(self::KUNCI_VERSI, 1);
    }

    /** Dipanggil saat snapshot cut-off baru diterbitkan. */
    public static function flush(): void
    {
        Cache::forever(self::KUNCI_VERSI, self::versi() + 1);
    }
}
