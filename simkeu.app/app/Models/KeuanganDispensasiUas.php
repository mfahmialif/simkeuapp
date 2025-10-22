<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganDispensasiUas extends Model
{
    protected $table = 'keuangan_dispensasi_uas';
    protected $guarded = [];

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

}
