<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganSaldoPengeluaran extends Model
{
    use HasFactory;

    protected $table = 'keuangan_saldo_pengeluaran';

    protected $guarded = [];

    protected $casts = [
        'lampiran' => 'array',
    ];

    public function saldo()
    {
        return $this->belongsTo(KeuanganSaldo::class);
    }
}
