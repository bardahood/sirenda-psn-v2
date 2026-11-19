<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Operasi kiriman balik Dashboard -> SIRENDA PEKS3 (App\Services\Peks3KeluarService). */
class Peks3Keluar extends Model
{
    protected $table = 'peks3_keluar';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kirim' => 'array',
            'lokal' => 'array',
            'berikutnya_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'diterima_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function psn()
    {
        return $this->belongsTo(Psn::class)->withoutGlobalScopes();
    }
}
