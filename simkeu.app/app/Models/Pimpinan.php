<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pimpinan extends Model
{
    protected $table = 'pimpinan';

    protected $guarded = [];

    protected $casts = [
        'tanggal_awal_menjabat' => 'date:Y-m-d',
        'tanggal_akhir_menjabat' => 'date:Y-m-d',
    ];
}
