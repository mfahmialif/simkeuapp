<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranIDN extends Model
{
    protected $table = 'idn_pembayaran';

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'nim', 'bill_key');
    }

    public function tagihan()
    {
        return $this->belongsTo(KeuanganTagihan::class, 'tagihan_id');
    }
}
