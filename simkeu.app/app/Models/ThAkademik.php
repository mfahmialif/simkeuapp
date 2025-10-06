<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThAkademik extends Model
{
    protected $table = 'th_akademik';
    protected $guarded = [];

    public function alumni()
    {
        return $this->hasMany(Alumni::class, 'th_akademik_id');
    }
}
