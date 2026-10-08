<?php

namespace App\Models;

use App\Enums\Rekomendasi;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu sesi penilaian usulan. Kolom skor_* / nilai_akhir / rekomendasi adalah cache
 * hasil ScoringService; sumber kebenaran tetap penilaian_skor.
 */
class Penilaian extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'penilaian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'gate_lulus' => 'boolean', 'nilai_akhir' => 'float'];
    }

    public function cakupanUnitMelalui(): array
    {
        return ['usulan_psn', 'usulan_id'];
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }

    public function isFinal(): bool
    {
        return $this->status === 'FINAL';
    }

    public function rekomendasiEnum(): ?Rekomendasi
    {
        return $this->rekomendasi ? Rekomendasi::tryFrom($this->rekomendasi) : null;
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penilai_id');
    }

    public function skor(): HasMany
    {
        return $this->hasMany(PenilaianSkor::class, 'penilaian_id');
    }
}
