<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranRumahTangga extends Model
{
    protected $table = 'keuangan_pengeluaran_rumah_tangga';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
        'nominal' => 'integer',
        'volume' => 'integer',
        'total' => 'integer',
    ];
}
