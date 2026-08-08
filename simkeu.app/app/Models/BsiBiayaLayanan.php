<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BsiBiayaLayanan extends Model
{
    protected $table = 'bsi_biaya_layanan';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'datetime',
        'jumlah' => 'decimal:2',
        'direkonsiliasi_pada' => 'datetime',
    ];

    public function pembayaranBsi()
    {
        return $this->belongsTo(KeuanganPembayaranBsi::class, 'pembayaran_bsi_id');
    }

    public function rekonsiliasi()
    {
        return $this->belongsTo(BsiReconciliation::class, 'bsi_reconciliation_id');
    }
}
