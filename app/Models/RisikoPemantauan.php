<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\DalamCakupanPsn;
use App\Models\Concerns\HasJejak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RisikoPemantauan extends Model
{
    use Auditable, DalamCakupanPsn, HasJejak;

    protected $table = 'risiko_pemantauan';

    protected $guarded = ['id'];

    public function cakupanMelalui(): array
    {
        return ['risiko', 'risiko_id'];
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function risiko(): BelongsTo
    {
        return $this->belongsTo(Risiko::class);
    }

    public function auditPsnId(): ?int
    {
        return Risiko::withoutGlobalScopes()->whereKey($this->risiko_id)->value('psn_id');
    }
}
