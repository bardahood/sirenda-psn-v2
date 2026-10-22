<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Usulan PSN baru. Cakupan akses: direktorat pengampu (unit_kerja_id).
 */
class UsulanPsn extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'usulan_psn';

    protected $guarded = ['id'];

    public const JENIS_PENGUSUL = ['KL' => 'Kementerian/Lembaga', 'PEMDA' => 'Pemerintah Daerah', 'BUMN_SWASTA' => 'BUMN/Swasta'];

    protected function casts(): array
    {
        return ['is_infrastruktur' => 'boolean', 'nilai_investasi_rp' => 'decimal:2'];
    }

    public function kolomCakupanUnit(): string
    {
        return 'unit_kerja_id';
    }

    public function auditPsnId(): ?int
    {
        return $this->psn_id;
    }

    public function klaster(): BelongsTo
    {
        return $this->belongsTo(RefKlaster::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(RefUnitKerja::class);
    }

    public function pengusulInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansi::class, 'pengusul_instansi_id');
    }

    public function lokasi(): HasMany
    {
        return $this->hasMany(UsulanLokasi::class, 'usulan_id');
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(Penilaian::class, 'usulan_id');
    }

    /** Penilaian terbaru. */
    public function penilaianTerakhir(): HasOne
    {
        return $this->hasOne(Penilaian::class, 'usulan_id')->latestOfMany('id');
    }

    /** Data integrasi PSI (null untuk usulan yang diinput langsung di SIRENDA). */
    public function psi(): HasOne
    {
        return $this->hasOne(UsulanPsi::class, 'usulan_id');
    }

    public function jalurKritis(): HasMany
    {
        return $this->hasMany(UsulanJalurKritis::class, 'usulan_id')->orderBy('urutan')->orderBy('id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(UsulanDokumen::class, 'usulan_id')->orderBy('id');
    }

    public function plenoUsulan(): HasMany
    {
        return $this->hasMany(PlenoUsulan::class, 'usulan_id')->orderByDesc('id');
    }

    public function rekomendasiResmi(): HasMany
    {
        return $this->hasMany(UsulanRekomendasi::class, 'usulan_id')->orderByDesc('id');
    }

    /** Rekomendasi resmi FINAL terbaru. */
    public function rekomendasiFinal(): HasOne
    {
        return $this->hasOne(UsulanRekomendasi::class, 'usulan_id')->ofMany(['id' => 'max'], fn ($q) => $q->where('status', 'FINAL'));
    }
}
