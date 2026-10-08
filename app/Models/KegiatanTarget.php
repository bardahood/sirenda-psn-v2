<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target & realisasi KP/RO per periode (TAHUNAN/TRIWULAN/BULANAN). Akses dibatasi melalui Kegiatan.
 */
class KegiatanTarget extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak;

    protected $table = 'kegiatan_target';

    protected $guarded = ['id'];

    public function cakupanMelalui(): array
    {
        return ['kegiatan', 'kegiatan_id'];
    }

    protected function casts(): array
    {
        return [
            'dilaporkan_at' => 'datetime',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function auditPsnId(): ?int
    {
        return Kegiatan::withoutGlobalScopes()->whereKey($this->kegiatan_id)->value('psn_id');
    }
}
