<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefKode extends Model
{
    protected $table = 'ref_kode';

    protected $guarded = ['id'];

    public $timestamps = false;
}
