<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BsiReconciliation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'transaction_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'payment_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'checksum_valid' => 'boolean',
        'payload' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(KeuanganPembayaranBsi::class, 'pembayaran_bsi_id');
    }
}
