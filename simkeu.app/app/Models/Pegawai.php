<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $table = 'pegawai';
    protected $guarded = [];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
    ];

    public function dosen()
    {
        return $this->hasOne(Dosen::class, 'pegawai_id');
    }

    public function staff()
    {
        return $this->hasOne(Staff::class, 'pegawai_id');
    }
}
