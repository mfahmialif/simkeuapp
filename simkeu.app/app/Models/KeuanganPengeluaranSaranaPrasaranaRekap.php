<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranSaranaPrasaranaRekap extends Model
{
    protected $table = 'keuangan_pengeluaran_sarana_prasarana_rekap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_sementara' => 'integer',
        ];
    }
}
