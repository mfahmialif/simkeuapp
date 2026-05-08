<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganPembayaranSemesterPendek extends Model
{
    use HasFactory;

    protected $table = 'keuangan_pembayaran_semester_pendek';

    protected $guarded = [];

    public function thAkademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jenisPembayaran()
    {
        return $this->belongsTo(KeuanganJenisPembayaran::class, 'jenis_pembayaran_id');
    }
}
