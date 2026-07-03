<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPiutangPembayaran extends Model
{
    protected $table = 'keuangan_piutang_pembayaran';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'nominal' => 'integer',
    ];

    public function piutang()
    {
        return $this->belongsTo(KeuanganPiutang::class, 'piutang_id');
    }

    public function pengeluaran()
    {
        return $this->belongsTo(KeuanganPengeluaranPegawaiBulanan::class, 'pengeluaran_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
