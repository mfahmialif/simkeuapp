<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganSyaratTagihan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_syarat_tagihan';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
