<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranPegawaiAbsensi extends Model
{
    protected $table = 'keuangan_pengeluaran_pegawai_absensi';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
