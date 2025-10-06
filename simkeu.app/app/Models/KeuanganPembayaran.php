<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaran extends Model
{
    protected $table = 'keuangan_pembayaran';
    protected $guarded = [];

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function tagihan()
    {
        return $this->belongsTo(KeuanganTagihan::class, 'tagihan_id');
    }

    public function keuanganNota()
    {
        return $this->hasOne(KeuanganNota::class, 'pembayaran_id');
    }

    public function jenisPembayaranDetail()
    {
        return $this->hasOne(KeuanganJenisPembayaranDetail::class, 'pembayaran_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
