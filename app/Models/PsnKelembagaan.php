<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsnKelembagaan extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak;

    protected $table = 'psn_kelembagaan';

    protected $guarded = ['id'];

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(RefPenanggungJawab::class);
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansi::class);
    }
}
