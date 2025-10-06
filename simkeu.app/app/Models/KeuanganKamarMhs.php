<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganKamarMhs extends Model
{
    use HasFactory;

    protected $table = 'keuangan_kamar_mhs';
    protected $guarded = [];

    public function kamar(){
        return $this->belongsTo(KeuanganKamar::class, 'kamar_id', 'id');
    }
}