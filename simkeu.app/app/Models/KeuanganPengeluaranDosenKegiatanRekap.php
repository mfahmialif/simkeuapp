<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranDosenKegiatanRekap extends Model
{
    protected $table = 'keuangan_pengeluaran_dosen_kegiatan_rekap';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_sementara' => 'integer',
        ];
    }
}
