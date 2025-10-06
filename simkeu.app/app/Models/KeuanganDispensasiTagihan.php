<?php

namespace App\Models;

use App\Http\Services\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganDispensasiTagihan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_dispensasi_tagihan';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mahasiswa()
    {
        return Mahasiswa::nim($this->mhs_nim);
    }

    public function mhsJenisTagihan()
    {
        return $this->belongsTo(MhsJenisTagihan::class);
    }

    public function mhsTransaksiTagihan()
    {
        return $this->hasOne(MhsTransaksiTagihan::class);
    }
}
