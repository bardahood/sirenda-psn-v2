<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Surat pengusulan dari aplikasi PSI (dapat memuat beberapa usulan). */
class UsulanSurat extends Model
{
    use Auditable, HasJejak, SoftDeletes;

    protected $table = 'usulan_surat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function usulanPsi(): HasMany
    {
        return $this->hasMany(UsulanPsi::class, 'surat_id');
    }
}
