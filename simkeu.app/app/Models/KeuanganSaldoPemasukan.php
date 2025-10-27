<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganSaldoPemasukan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_saldo_pemasukan';
    protected $guarded = [];

    public function saldo()
    {
        return $this->belongsTo(KeuanganSaldo::class);
    }
}
