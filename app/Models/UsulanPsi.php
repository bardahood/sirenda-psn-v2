<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Amplop integrasi & isian formulir PSI untuk satu usulan (1:1 usulan_psn). */
class UsulanPsi extends Model
{
    use Auditable, HasJejak, SoftDeletes;

    protected $table = 'usulan_psi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'diajukan_at' => 'datetime', 'diterima_at' => 'datetime', 'diperbarui_at' => 'datetime', 'kirim_terakhir_at' => 'datetime',
            'perlu_tinjau_ulang' => 'boolean', 'kontak' => 'array', 'rincian_lain' => 'array', 'kesiapan_lain' => 'array',
            'peringatan_impor' => 'array', 'payload_terakhir' => 'array',
        ];
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }

    public function surat(): BelongsTo
    {
        return $this->belongsTo(UsulanSurat::class, 'surat_id');
    }

    /** Kolom besar & mentah tidak dicatat di jejak audit (payload sudah ada di integrasi_psi_log). */
    public function auditAbaikan(): array
    {
        return ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by', 'payload_terakhir', 'diperbarui_at'];
    }
}
