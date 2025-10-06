<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganSaldo extends Model
{
    use HasFactory;

    protected $table = 'keuangan_saldo';
    protected $guarded = [];
}
