<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsnSumberDana extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak;

    protected $table = 'psn_sumber_dana';

    protected $guarded = ['id'];

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(RefSumberDana::class);
    }
}
