<?php

namespace App\Models;

use App\Models\Concerns\DalamCakupanPsn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai beku per cut-off; hanya ditulis oleh SnapshotService.
 */
class SnapshotRisiko extends Model
{
    use DalamCakupanPsn;

    protected $table = 'snapshot_risiko';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function periodeCutoff(): BelongsTo
    {
        return $this->belongsTo(PeriodeCutoff::class);
    }
}
