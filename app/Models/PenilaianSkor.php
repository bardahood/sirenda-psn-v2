<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenilaianSkor extends Model
{
    use Auditable, HasJejak;

    protected $table = 'penilaian_skor';

    protected $guarded = ['id'];

    public function auditPsnId(): ?int
    {
        return null;
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(Penilaian::class);
    }

    public function kriteria(): BelongsTo
    {
        return $this->belongsTo(RefKriteria::class);
    }
}
