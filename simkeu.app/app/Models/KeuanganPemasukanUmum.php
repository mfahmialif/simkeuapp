<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganPemasukanUmum extends Model
{
    use HasFactory;

    protected $table = 'keuangan_pemasukan_umum';

    protected $guarded = [];

    protected $casts = [
        'tanggal'  => 'datetime',
        'nominal'  => 'double',
        'lampiran' => 'array',
    ];

    protected $appends = [
        'lampiran_list',
    ];

    public static function generateNoTransaksi(?Carbon $date = null): string
    {
        $date = $date ?: Carbon::now();
        $month = $date->format('m');
        $year = $date->format('Y');

        $pattern = "%/PU/{$month}/{$year}";

        // Cari transaksi dengan no_transaksi tertinggi di bulan dan tahun ini
        $lastRecord = self::where('no_transaksi', 'like', $pattern)
            ->orderByRaw("CAST(SUBSTRING_INDEX(no_transaksi, '/', 1) AS UNSIGNED) DESC")
            ->first();

        $nextNumber = 1;
        if ($lastRecord) {
            $raw = $lastRecord->getRawOriginal('no_transaksi') ?? $lastRecord->getAttributes()['no_transaksi'] ?? $lastRecord->no_transaksi;
            if (!empty($raw)) {
                $parts = explode('/', $raw);
                if (isset($parts[0]) && is_numeric($parts[0])) {
                    $nextNumber = ((int) $parts[0]) + 1;
                }
            }
        }

        return sprintf('%04d/PU/%s/%s', $nextNumber, $month, $year);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $raw = $model->getAttributes()['no_transaksi'] ?? null;
            if (empty($raw)) {
                $tgl = $model->tanggal ? Carbon::parse($model->tanggal) : Carbon::now();
                $model->attributes['no_transaksi'] = self::generateNoTransaksi($tgl);
            }
        });
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function jenisPembayaran()
    {
        return $this->belongsTo(KeuanganJenisPembayaran::class, 'jenis_pembayaran_id');
    }

    public function getNoTransaksiAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->getAttributes()['no_transaksi'] ?? null;
    }

    public function getLampiranListAttribute(): array
    {
        $lampiran = $this->lampiran;
        if (is_string($lampiran)) {
            $decoded = json_decode($lampiran, true);
            $lampiran = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($lampiran)) {
            return [];
        }

        $list = [];
        foreach ($lampiran as $index => $item) {
            if (is_string($item)) {
                $item = [
                    'name' => basename($item),
                    'path' => $item,
                ];
            }

            if (!is_array($item) || empty($item['path'])) {
                continue;
            }

            $list[] = [
                'index' => $index,
                'name'  => $item['name'] ?? basename($item['path']),
                'path'  => $item['path'],
                'size'  => $item['size'] ?? 0,
                'mime'  => $item['mime'] ?? '',
                'url'   => url('/api/admin/pemasukan/mahasiswa/pemasukan-umum/file/' . $this->id . '/' . $index),
            ];
        }

        return $list;
    }
}
