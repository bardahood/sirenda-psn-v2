<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Sidang pleno pembahasan hasil penilaian usulan PSN. */
class Pleno extends Model
{
    use Auditable, HasJejak, SoftDeletes;

    protected $table = 'pleno';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function agenda(): HasMany
    {
        return $this->hasMany(PlenoUsulan::class)->orderBy('urutan')->orderBy('id');
    }

    public function isSelesai(): bool
    {
        return $this->status === 'SELESAI';
    }
}
