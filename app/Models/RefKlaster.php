<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefKlaster extends Model
{
    protected $table = 'ref_klaster';

    protected $guarded = ['id'];

    public $timestamps = false;
}
