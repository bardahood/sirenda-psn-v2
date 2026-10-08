<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Mengisi kolom jejak created_by/updated_by/deleted_by dari pengguna yang login.
 */
trait HasJejak
{
    public static function bootHasJejak(): void
    {
        static::creating(function ($model) {
            if ($id = Auth::id()) {
                $model->created_by ??= $id;
                $model->updated_by ??= $id;
            }
        });

        static::updating(function ($model) {
            if ($id = Auth::id()) {
                $model->updated_by = $id;
            }
        });

        static::deleting(function ($model) {
            if (($id = Auth::id()) && in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting()) {
                $model->deleted_by = $id;
                $model->saveQuietly();
            }
        });
    }
}
