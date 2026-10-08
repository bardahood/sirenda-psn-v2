<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefWilayah extends Model
{
    protected $table = 'ref_wilayah';

    protected $guarded = ['id'];

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function induk(): BelongsTo
    {
        return $this->belongsTo(RefWilayah::class, 'induk_kode');
    }
}
