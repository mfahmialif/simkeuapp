<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranRumahTanggaRekap extends Model
{
    protected $table = 'keuangan_pengeluaran_rumah_tangga_rekap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_sementara' => 'integer',
        ];
    }
}
