<?php

namespace App\Models;

use App\Models\Concerns\DalamCakupanPsn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai beku per cut-off; hanya ditulis oleh SnapshotService.
 */
class SnapshotPsn extends Model
{
    use DalamCakupanPsn;

    protected $table = 'snapshot_psn';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'provinsi_kode' => 'array',
            'unit_kerja_id' => 'array',
            'sumber_dana_id' => 'array',
            'is_aktif' => 'boolean',
            'is_kritis' => 'boolean',
            'pembaruan_terakhir_at' => 'datetime',
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
