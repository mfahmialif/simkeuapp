<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranTambahan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_pembayaran_tambahan';
    protected $guarded = [];

    public function keuanganNota()
    {
        return $this->hasOne(KeuanganNota::class, 'pembayaran_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
