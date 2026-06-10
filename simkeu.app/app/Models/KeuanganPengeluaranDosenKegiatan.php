<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPengeluaranDosenKegiatan extends Model
{
    protected $table = 'keuangan_pengeluaran_dosen_kegiatan';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
        'nominal' => 'integer',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}
