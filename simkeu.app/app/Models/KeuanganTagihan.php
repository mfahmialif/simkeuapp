<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganTagihan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_tagihan';
    protected $guarded = [];

    public function th_akademik()
    {
        return $this->belongsTo(ThAkademik::class, 'th_akademik_id');
    }

    public function th_angkatan()
    {
        return $this->belongsTo(ThAkademik::class, 'th_angkatan_id');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'prodi_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Ref::class, 'kelas_id');
    }

    public function form_schadule()
    {
        return $this->belongsTo(FormSchadule::class, 'form_schadule_id');
    }
}
