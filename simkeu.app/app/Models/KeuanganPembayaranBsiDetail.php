<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranBsiDetail extends Model
{
    protected $table = 'keuangan_pembayaran_bsi_detail';

    protected $guarded = [];

    protected $casts = [
        'jumlah_tagihan' => 'decimal:2',
        'sisa_awal' => 'decimal:2',
        'jumlah' => 'decimal:2',
    ];

    public function pembayaranBsi()
    {
        return $this->belongsTo(KeuanganPembayaranBsi::class, 'pembayaran_bsi_id');
    }

    public function tagihan()
    {
        return $this->belongsTo(KeuanganTagihan::class, 'tagihan_id');
    }

    public function tahunAkademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function pembayaran()
    {
        return $this->belongsTo(KeuanganPembayaran::class, 'pembayaran_id');
    }
}
