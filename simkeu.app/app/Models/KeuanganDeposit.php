<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganDeposit extends Model
{
    use HasFactory;

    protected $table = "keuangan_deposit";
    protected $guarded = [];
}
