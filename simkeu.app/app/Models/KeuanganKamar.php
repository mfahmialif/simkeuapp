<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganKamar extends Model
{
    use HasFactory;

    protected $table = 'keuangan_kamar';
    protected $guarded = [];
}