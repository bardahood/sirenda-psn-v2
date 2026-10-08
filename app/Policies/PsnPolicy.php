<?php

namespace App\Policies;

use App\Models\Psn;
use App\Models\User;

/**
 * Otorisasi halaman Detail Proyek. Izin modul dari PeranSeeder; tanda "¹" (terbatas
 * pada proyek sendiri) dicek terhadap psn_unit_pengampu. Super Admin lolos lewat Gate::before.
 */
class PsnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('portofolio.lihat');
    }

    public function view(User $user, Psn $psn): bool
    {
        return $user->can('detail.lihat') && (! $user->lihatTerbatas() || $user->mengampuPsn($psn->id));
    }

    public function update(User $user, Psn $psn): bool
    {
        return $user->can('detail.input') && $this->dalamCakupanUbah($user, $psn);
    }

    public function verifikasi(User $user, Psn $psn): bool
    {
        return $user->can('detail.verifikasi') && $this->dalamCakupanUbah($user, $psn);
    }

    public function create(User $user): bool
    {
        return $user->can('detail.kelola');
    }

    public function delete(User $user, Psn $psn): bool
    {
        return $user->can('detail.kelola');
    }

    protected function dalamCakupanUbah(User $user, Psn $psn): bool
    {
        return ! $user->ubahTerbatas() || $user->mengampuPsn($psn->id);
    }
}
