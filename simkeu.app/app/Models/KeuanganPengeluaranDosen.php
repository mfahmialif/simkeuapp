<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranDosen extends Model
{
    protected $table = 'keuangan_pengeluaran_dosen';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
