<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganWisudaSync extends Model
{
    protected $table = 'keuangan_wisuda_sync';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'synced_at' => 'datetime',
        'tanggal_sidang' => 'date',
    ];

    public function pembayaran()
    {
        return $this->belongsTo(KeuanganPembayaran::class, 'pembayaran_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }
}
