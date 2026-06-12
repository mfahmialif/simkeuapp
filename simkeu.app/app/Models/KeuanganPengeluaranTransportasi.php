<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranTransportasi extends Model
{
    protected $table = 'keuangan_pengeluaran_transportasi';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
        'nominal' => 'integer',
        'volume' => 'integer',
        'total' => 'integer',
    ];
}
