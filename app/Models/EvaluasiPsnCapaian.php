<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rincian kertas kerja evaluasi PSN (tabel anak evaluasi_psn). */
class EvaluasiPsnCapaian extends Model
{
    use Auditable, HasJejak;

    protected $table = 'evaluasi_psn_capaian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['target_akhir' => 'float', 'target_kumulatif' => 'float', 'realisasi_kumulatif' => 'float'];
    }

    public function evaluasi(): BelongsTo
    {
        return $this->belongsTo(EvaluasiPsn::class, 'evaluasi_psn_id');
    }

    public function auditPsnId(): ?int
    {
        return $this->evaluasi?->psn_id;
    }
}
