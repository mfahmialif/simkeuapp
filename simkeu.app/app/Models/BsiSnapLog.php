<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BsiSnapLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'signature_valid' => 'boolean',
        'requested_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(KeuanganPembayaranBsi::class, 'pembayaran_bsi_id');
    }
}
