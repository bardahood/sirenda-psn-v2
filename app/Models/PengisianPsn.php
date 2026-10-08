<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengisianPsn extends Model
{
    use Auditable, DalamCakupanPsn;

    // Tabel tanpa kolom created_by/updated_by: transisi status dicatat eksplisit (SUBMIT/VERIFY/RETURN) oleh PengisianService.

    protected $table = 'pengisian_psn';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'diajukan_at' => 'datetime',
            'diverifikasi_at' => 'datetime',
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
