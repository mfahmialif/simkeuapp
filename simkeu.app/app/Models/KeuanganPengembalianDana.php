<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganPengembalianDana extends Model
{
    use HasFactory;

    protected $table = 'keuangan_pengembalian_dana';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'datetime',
        'nominal' => 'double',
    ];

    protected $appends = [
        'file_bukti_masuk_url',
        'file_bukti_keluar_url',
    ];

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function getFileBuktiMasukUrlAttribute(): ?string
    {
        if (empty($this->file_bukti_masuk)) {
            return null;
        }

        return url('/api/admin/pemasukan/mahasiswa/pengembalian/file/' . $this->id . '/masuk');
    }

    public function getFileBuktiKeluarUrlAttribute(): ?string
    {
        if (empty($this->file_bukti_keluar)) {
            return null;
        }

        return url('/api/admin/pemasukan/mahasiswa/pengembalian/file/' . $this->id . '/keluar');
    }
}
