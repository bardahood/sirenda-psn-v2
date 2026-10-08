<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kegiatan extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'kegiatan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_critical_path' => 'boolean',
        ];
    }

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'parent_id');
    }

    public function turunan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'parent_id');
    }

    public function target(): HasMany
    {
        return $this->hasMany(KegiatanTarget::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(RefKode::class, 'jenis_id');
    }
}
