<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranBsiCallback extends Model
{
    protected $table = 'keuangan_pembayaran_bsi_callback';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
    ];

    public function pembayaranBsi()
    {
        return $this->belongsTo(KeuanganPembayaranBsi::class, 'pembayaran_bsi_id');
    }
}
