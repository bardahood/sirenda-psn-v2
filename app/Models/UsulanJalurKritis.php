<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Langkah jalur kritis proyek dari formulir Kesiapan Proyek PSI. */
class UsulanJalurKritis extends Model
{
    use Auditable, HasJejak;

    protected $table = 'usulan_jalur_kritis';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sebelumnya' => 'array', 'is_jalur_kritis' => 'boolean'];
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }
}
