<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranUmumRekap extends Model
{
    protected $table = 'keuangan_pengeluaran_umum_rekap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_sementara' => 'integer',
        ];
    }
}
