<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganJenisPembayaran extends Model
{
    use HasFactory;

    protected $table = 'keuangan_jenis_pembayaran';
    protected $guarded = [];
}
