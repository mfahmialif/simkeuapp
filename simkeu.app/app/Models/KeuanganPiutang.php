<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPiutang extends Model
{
    protected $table = 'keuangan_piutang';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nominal' => 'integer',
        'default_cicilan' => 'integer',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(KeuanganPiutangPembayaran::class, 'piutang_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
