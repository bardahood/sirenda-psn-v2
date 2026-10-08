<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanLokasi extends Model
{
    protected $table = 'usulan_lokasi';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(RefWilayah::class, 'provinsi_kode');
    }
}
