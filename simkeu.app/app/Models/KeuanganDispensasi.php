<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganDispensasi extends Model
{
    protected $table = 'keuangan_dispensasi';
    protected $guarded = [];

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

}
