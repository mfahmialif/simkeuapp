<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranBsi extends Model
{
    protected $table = 'keuangan_pembayaran_bsi';

    protected $guarded = [];

    protected $casts = [
        'total' => 'decimal:2',
        'data_test' => 'boolean',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'posted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'raw_request' => 'array',
        'raw_callback' => 'array',
        'payment_response' => 'array',
        'trx_date_time' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(KeuanganPembayaranBsiDetail::class, 'pembayaran_bsi_id')
            ->orderBy('urutan');
    }

    public function callbacks()
    {
        return $this->hasMany(KeuanganPembayaranBsiCallback::class, 'pembayaran_bsi_id')
            ->latest('id');
    }

    public function jenisPembayaran()
    {
        return $this->belongsTo(KeuanganJenisPembayaran::class, 'jenis_pembayaran_id');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function snapLogs()
    {
        return $this->hasMany(BsiSnapLog::class, 'pembayaran_bsi_id')->latest('id');
    }

    public function reconciliations()
    {
        return $this->hasMany(BsiReconciliation::class, 'pembayaran_bsi_id')->latest('id');
    }
}
