<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataUang extends Model
{
    protected $table = 'mata_uang';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'aktif' => 'boolean',
    ];
}
