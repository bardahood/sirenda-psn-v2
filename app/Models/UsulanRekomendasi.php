<?php

namespace App\Models;

use App\Enums\Rekomendasi;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Rekomendasi resmi atas usulan setelah pleno (dikirim ke PSI). */
class UsulanRekomendasi extends Model
{
    use Auditable, HasJejak, SoftDeletes;

    protected $table = 'usulan_rekomendasi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'ditetapkan_at' => 'datetime'];
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(Penilaian::class);
    }

    public function plenoUsulan(): BelongsTo
    {
        return $this->belongsTo(PlenoUsulan::class);
    }

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    public function enum(): Rekomendasi
    {
        return Rekomendasi::from($this->rekomendasi);
    }

    public function isFinal(): bool
    {
        return $this->status === 'FINAL';
    }
}
