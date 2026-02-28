<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganCatatanHarian extends Model
{
    use HasFactory;

    protected $table = 'keuangan_catatan_harian';
    protected $guarded = [];

    public function saldo()
    {
        return $this->belongsTo(KeuanganSaldo::class);
    }
}
