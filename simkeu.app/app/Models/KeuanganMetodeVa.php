<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeuanganMetodeVa extends Model
{
    public const BYOND_BSI = 'byond_bsi';

    public const ATM_BSI = 'atm_bsi';

    public const ATM_LAIN = 'atm_lain';

    protected $table = 'keuangan_metode_va';

    protected $guarded = [];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function pembayaranBsi()
    {
        return $this->hasMany(KeuanganPembayaranBsi::class, 'metode_va_id');
    }
}
