<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganUasSusulan extends Model
{
    protected $table = 'keuangan_uas_susulan';
    protected $guarded = [];

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
