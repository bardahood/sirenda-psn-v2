<?php

namespace App\Models;

use App\Models\Concerns\DalamCakupanPsn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai beku per cut-off; hanya ditulis oleh SnapshotService.
 */
class SnapshotKegiatan extends Model
{
    use DalamCakupanPsn;

    protected $table = 'snapshot_kegiatan';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_critical_path' => 'boolean',
            'is_tercapai' => 'boolean',
        ];
    }

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function periodeCutoff(): BelongsTo
    {
        return $this->belongsTo(PeriodeCutoff::class);
    }
}
