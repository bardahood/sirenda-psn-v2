<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsnDokumen extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'psn_dokumen';

    protected $guarded = ['id'];

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(RefKode::class, 'kategori_id');
    }
}
