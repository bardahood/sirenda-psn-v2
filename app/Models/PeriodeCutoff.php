<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeCutoff extends Model
{
    protected $table = 'periode_cutoff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_cutoff' => 'date',
            'batas_pengisian' => 'date',
            'diterbitkan_at' => 'datetime',
        ];
    }

    public function isTerbit(): bool
    {
        return $this->status === 'TERBIT';
    }

    /** Cut-off terbit sebelum cut-off ini (pembanding Δ). */
    public function sebelumnya(): ?self
    {
        return self::where('status', 'TERBIT')->where('tanggal_cutoff', '<', $this->tanggal_cutoff)->orderByDesc('tanggal_cutoff')->first();
    }

    public static function terbaru(): ?self
    {
        return self::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff')->first();
    }

    public function snapshotPsn(): HasMany
    {
        return $this->hasMany(SnapshotPsn::class);
    }

    public function pengisian(): HasMany
    {
        return $this->hasMany(PengisianPsn::class);
    }
}
