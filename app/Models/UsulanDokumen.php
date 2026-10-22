<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dokumen usulan (PDF usulan, surat pengusulan bertanda tangan, lampiran) yang tersimpan di PSI. */
class UsulanDokumen extends Model
{
    use Auditable, HasJejak;

    protected $table = 'usulan_dokumen';

    protected $guarded = ['id'];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPsn::class, 'usulan_id');
    }

    public function auditPsnId(): ?int
    {
        return UsulanPsn::withoutGlobalScopes()->whereKey($this->usulan_id)->value('psn_id');
    }

    public const JENIS = ['PDF_USULAN' => 'Dokumen PDF Usulan', 'SURAT_PENGUSULAN' => 'Surat Pengusulan PSN', 'LAMPIRAN' => 'Lampiran Pendukung'];
}
