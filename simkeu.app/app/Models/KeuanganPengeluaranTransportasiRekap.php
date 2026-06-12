<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranTransportasiRekap extends Model
{
    protected $table = 'keuangan_pengeluaran_transportasi_rekap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_sementara' => 'integer',
        ];
    }
}
