<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Risiko extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'risiko';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_titik_kritis' => 'boolean',
        ];
    }

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function pemantauan(): HasMany
    {
        return $this->hasMany(RisikoPemantauan::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(RefKode::class, 'kategori_id');
    }
}
