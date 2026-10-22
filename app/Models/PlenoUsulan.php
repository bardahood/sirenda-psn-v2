<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu usulan dalam agenda pleno beserta keputusan & catatannya. */
class PlenoUsulan extends Model
{
    use Auditable, HasJejak;

    protected $table = 'pleno_usulan';

    protected $guarded = ['id'];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }

    public function pleno(): BelongsTo
    {
        return $this->belongsTo(Pleno::class);
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(Penilaian::class);
    }

    public function labelKeputusan(): ?string
    {
        return $this->keputusan ? (config('psn_dashboard.psi.keputusan_pleno')[$this->keputusan] ?? $this->keputusan) : null;
    }
}
