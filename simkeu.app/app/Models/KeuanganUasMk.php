<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganUaSusulansMk extends Model
{
    protected $table = 'keuangan_uas_mk';
    protected $guarded = [];

    public function susulan(){
        return $this->belongsTo(KeuanganUasSusulan::class, 'uas_susulan_id');
    }

}
