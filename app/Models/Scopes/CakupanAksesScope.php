<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Peran dengan hak "¹ terbatas" pada operasi lihat (config psn_dashboard.rbac.peran_lihat_terbatas)
 * hanya melihat PSN yang diampu unit kerjanya (psn_unit_pengampu.unit_kerja_id).
 * Tanpa pengguna login (console, ETL, snapshot) scope tidak diterapkan.
 */
class CakupanAksesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->lihatTerbatas()) {
            return;
        }

        if (! $user->unit_kerja_id) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $psnDalamCakupan = fn ($q) => $q->select('psn_id')->from('psn_unit_pengampu')->where('unit_kerja_id', $user->unit_kerja_id);

        // Tabel tanpa psn_id (mis. kegiatan_target) dibatasi melalui tabel induknya.
        if (method_exists($model, 'cakupanMelalui')) {
            [$tabelInduk, $fk] = $model->cakupanMelalui();
            $builder->whereIn($model->qualifyColumn($fk), fn ($q) => $q->select('id')->from($tabelInduk)->whereIn('psn_id', $psnDalamCakupan));

            return;
        }

        $builder->whereIn($model->qualifyColumn($model->kolomCakupanPsn()), $psnDalamCakupan);
    }
}
