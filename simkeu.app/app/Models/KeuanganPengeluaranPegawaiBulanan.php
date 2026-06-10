<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranPegawaiBulanan extends Model
{
    protected $table = 'keuangan_pengeluaran_pegawai_bulanan';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
