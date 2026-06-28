<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranUmum extends Model
{
    protected $table = 'keuangan_pengeluaran_umum';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
        'nominal' => 'integer',
        'total' => 'integer',
    ];
}
