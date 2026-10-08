<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefStatusPsn extends Model
{
    protected $table = 'ref_status_psn';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }
}
