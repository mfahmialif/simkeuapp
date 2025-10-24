<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganUasSusulanMk extends Model
{
    protected $table = 'keuangan_uas_susulan_mk';
    protected $guarded = [];

    public function uasSusulan(){
        return $this->belongsTo(KeuanganUasSusulan::class, 'uas_susulan_id');
    }

}
