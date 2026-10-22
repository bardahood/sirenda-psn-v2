<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log pertukaran data dengan aplikasi PSI (tidak diaudit ulang; tabel ini sendiri adalah log). */
class IntegrasiPsiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'integrasi_psi_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'berhasil' => 'boolean', 'created_at' => 'datetime'];
    }
}
