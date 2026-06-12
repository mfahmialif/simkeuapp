<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranSaranaPrasarana extends Model
{
    protected $table = 'keuangan_pengeluaran_sarana_prasarana';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
        'nominal' => 'integer',
        'volume' => 'integer',
        'total' => 'integer',
    ];
}
