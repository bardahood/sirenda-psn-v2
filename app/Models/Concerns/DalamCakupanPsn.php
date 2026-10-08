<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CakupanAksesScope;

/**
 * Membatasi query model ber-psn_id pada PSN dalam cakupan pengguna terbatas
 * (lihat CakupanAksesScope). Pembatasan berlaku di level query.
 */
trait DalamCakupanPsn
{
    public static function bootDalamCakupanPsn(): void
    {
        static::addGlobalScope(new CakupanAksesScope);
    }

    /** Kolom yang menunjuk PSN; Psn sendiri memakai 'id'. */
    public function kolomCakupanPsn(): string
    {
        return 'psn_id';
    }
}
