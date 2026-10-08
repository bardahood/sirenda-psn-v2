<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyek Strategis Nasional. Query otomatis dibatasi cakupan akses pengguna (CakupanAksesScope).
 */
class Psn extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, HasUuids, SoftDeletes;

    protected $table = 'psn';

    protected $guarded = ['id'];

    /** UUID hanya untuk kolom uuid; primary key tetap auto-increment. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Psn memakai kolom id sendiri sebagai kunci cakupan akses. */
    public function kolomCakupanPsn(): string
    {
        return 'id';
    }

    public function auditPsnId(): ?int
    {
        return $this->id;
    }

    protected function casts(): array
    {
        return [
            'investasi_anomali' => 'boolean',
            'nilai_investasi_rp' => 'decimal:2',
            'rencana_investasi_rp' => 'decimal:2',
        ];
    }

    public function klaster(): BelongsTo
    {
        return $this->belongsTo(RefKlaster::class);
    }

    public function statusPsn(): BelongsTo
    {
        return $this->belongsTo(RefStatusPsn::class, 'status_psn_id');
    }

    public function subKlaster(): BelongsTo
    {
        return $this->belongsTo(RefSubKlaster::class);
    }

    public function klasterPkpn(): BelongsTo
    {
        return $this->belongsTo(RefKlasterPkpn::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(RefProgram::class);
    }

    public function lokasi(): HasMany
    {
        return $this->hasMany(PsnLokasi::class);
    }

    public function sumberDana(): HasMany
    {
        return $this->hasMany(PsnSumberDana::class);
    }

    public function unitPengampu(): HasMany
    {
        return $this->hasMany(PsnUnitPengampu::class);
    }

    public function kelembagaan(): HasMany
    {
        return $this->hasMany(PsnKelembagaan::class);
    }

    public function profilItem(): HasMany
    {
        return $this->hasMany(PsnProfilItem::class);
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    public function risiko(): HasMany
    {
        return $this->hasMany(Risiko::class);
    }

    public function regulasi(): HasMany
    {
        return $this->hasMany(Regulasi::class);
    }

    public function isu(): HasMany
    {
        return $this->hasMany(Isu::class);
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(Indikator::class);
    }

    public function trisula(): HasMany
    {
        return $this->hasMany(Trisula::class);
    }

    public function pengisian(): HasMany
    {
        return $this->hasMany(PengisianPsn::class);
    }
}
