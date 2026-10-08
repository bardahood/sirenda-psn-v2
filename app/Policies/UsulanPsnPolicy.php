<?php

namespace App\Policies;

use App\Models\Penilaian;
use App\Models\User;
use App\Models\UsulanPsn;

/**
 * Halaman Perencanaan: Pimpinan L, Tim Koordinasi/PMO K, Direktorat Sektor V¹,
 * Operator K/L I¹ (¹ = usulan dengan direktorat pengampu = unit kerja pengguna).
 */
class UsulanPsnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('perencanaan.lihat');
    }

    public function view(User $user, UsulanPsn $u): bool
    {
        return $user->can('perencanaan.lihat') && (! $user->lihatTerbatas() || $this->milikUnit($user, $u));
    }

    public function create(User $user): bool
    {
        return $user->can('perencanaan.input') && (! $user->ubahTerbatas() || $user->unit_kerja_id !== null);
    }

    public function update(User $user, UsulanPsn $u): bool
    {
        return $user->can('perencanaan.input') && $this->dalamCakupanUbah($user, $u);
    }

    /** Mengisi skor pada penilaian yang masih DRAFT. */
    public function nilai(User $user, Penilaian $p): bool
    {
        return ! $p->isFinal() && $user->can('perencanaan.input') && $this->dalamCakupanUbah($user, $p->usulan);
    }

    /** Menetapkan penilaian menjadi FINAL. */
    public function finalisasi(User $user, Penilaian $p): bool
    {
        return ! $p->isFinal() && $user->can('perencanaan.verifikasi') && $this->dalamCakupanUbah($user, $p->usulan);
    }

    /** Membuka kembali penilaian FINAL / menghapus usulan. */
    public function kelola(User $user): bool
    {
        return $user->can('perencanaan.kelola');
    }

    protected function dalamCakupanUbah(User $user, UsulanPsn $u): bool
    {
        return ! $user->ubahTerbatas() || $this->milikUnit($user, $u);
    }

    protected function milikUnit(User $user, UsulanPsn $u): bool
    {
        return $user->unit_kerja_id !== null && (int) $u->unit_kerja_id === (int) $user->unit_kerja_id;
    }
}
