<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganNota extends Model
{
    use HasFactory;

    protected $table = "keuangan_nota";
    protected $guarded = [];
}
