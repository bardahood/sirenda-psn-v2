<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsnLokasi extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'psn_lokasi';

    protected $guarded = ['id'];

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(RefWilayah::class, 'provinsi_kode');
    }

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(RefWilayah::class, 'kabupaten_kode');
    }
}
