<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Evaluasi keberlanjutan status satu PSN untuk satu tahun RKP (docs/evaluasi-status-psn.md). */
class EvaluasiPsn extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak, SoftDeletes;

    protected $table = 'evaluasi_psn';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'form_diterima_at' => 'date',
            'tanpa_isu' => 'boolean',
            'd2_fasilitas' => 'array',
            'pic' => 'array',
            'c1_tipologi' => 'array',
        ];
    }

    public function psn(): BelongsTo
    {
        return $this->belongsTo(Psn::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(EvaluasiPsnRiwayat::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    public function capaian(): HasMany
    {
        return $this->hasMany(EvaluasiPsnCapaian::class);
    }

    public function milestone(): HasMany
    {
        return $this->hasMany(EvaluasiPsnMilestone::class)->orderBy('urutan')->orderBy('id');
    }

    public function isu(): HasMany
    {
        return $this->hasMany(EvaluasiPsnIsu::class)->orderBy('urutan')->orderBy('id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(EvaluasiPsnLampiran::class)->orderBy('urutan')->orderBy('id');
    }

    /** Status Akhir: Indikasi Status bila sudah diisi; bila belum, Tindak Lanjut; bila belum, Proses Pengecekan. */
    public function hitungStatusAkhir(): string
    {
        return $this->indikasi_status ?: ($this->tindak_lanjut ?: 'PROSES_PENGECEKAN');
    }
}
