<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganJenisPembayaranDetail extends Model
{
    use HasFactory;

    protected $table = 'keuangan_jenis_pembayaran_detail';
    protected $guarded = [];

    public function jenisPembayaran()
    {
        return $this->belongsTo(KeuanganJenisPembayaran::class, 'jenis_pembayaran_id');
    }
}
