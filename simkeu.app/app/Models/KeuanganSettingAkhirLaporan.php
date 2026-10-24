<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganSettingAkhirLaporan extends Model
{
    use HasFactory;

    protected $table = 'keuangan_setting_akhir_laporan';

    protected $guarded = [];

    protected $casts = [
        'tahun' => 'integer',
        'bulan' => 'integer',
        'tanggal_akhir' => 'integer',
        'jenis_pembayaran_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jenisPembayaran()
    {
        return $this->belongsTo(KeuanganJenisPembayaran::class, 'jenis_pembayaran_id');
    }

    /**
     * Ambil semua setting untuk tahun & bulan tertentu
     */
    public static function getSettingsForMonth($tahun, $bulan)
    {
        return self::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get();
    }

    /**
     * Ambil tanggal cut off efektif untuk tahun, bulan, dan jenis pembayaran tertentu.
     * Jika tidak disetting, return default jumlah hari dalam bulan tersebut (akhir kalender).
     */
    public static function getEffectiveCutOffDay($tahun, $bulan, $jenisPembayaranKey = null, $jenisPembayaranId = null)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $bulan, (int) $tahun);
        $settings = self::getSettingsForMonth($tahun, $bulan);

        if ($settings->isEmpty()) {
            return $daysInMonth;
        }

        // Cari setting yang paling spesifik
        if ($jenisPembayaranId) {
            $settingId = $settings->firstWhere('jenis_pembayaran_id', $jenisPembayaranId);
            if ($settingId) {
                return (int) $settingId->tanggal_akhir;
            }
        }

        if ($jenisPembayaranKey && $jenisPembayaranKey !== 'all') {
            $settingKey = $settings->firstWhere('jenis_pembayaran_key', $jenisPembayaranKey);
            if ($settingKey) {
                return (int) $settingKey->tanggal_akhir;
            }
        }

        // Cari setting global ('all')
        $globalSetting = $settings->first(function ($item) {
            return $item->jenis_pembayaran_key === 'all' || empty($item->jenis_pembayaran_key);
        });

        if ($globalSetting) {
            return (int) $globalSetting->tanggal_akhir;
        }

        return $daysInMonth;
    }

    /**
     * Ambil range tanggal limpahan (carry-over) dari bulan sebelumnya ke bulan $tahun-$bulan.
     * Return array:
     * [
     *    'has_carry_over' => bool,
     *    'prev_year' => int,
     *    'prev_month' => int,
     *    'prev_cut_off' => int,
     *    'dates' => ['2026-01-30', '2026-01-31'],
     *    'settings' => Collection
     * ]
     */
    public static function getCarryOverFromPreviousMonth($tahun, $bulan)
    {
        $prevYear = (int) $tahun;
        $prevMonth = (int) $bulan - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear -= 1;
        }

        $daysInPrevMonth = cal_days_in_month(CAL_GREGORIAN, $prevMonth, $prevYear);
        $prevSettings = self::getSettingsForMonth($prevYear, $prevMonth);

        if ($prevSettings->isEmpty()) {
            return [
                'has_carry_over' => false,
                'prev_year' => $prevYear,
                'prev_month' => $prevMonth,
                'dates' => [],
                'settings' => collect(),
            ];
        }

        // Cari tanggal cut-off paling awal dari semua setting bulan sebelumnya
        $minCutOff = $prevSettings->min('tanggal_akhir');

        if (!$minCutOff || $minCutOff >= $daysInPrevMonth) {
            return [
                'has_carry_over' => false,
                'prev_year' => $prevYear,
                'prev_month' => $prevMonth,
                'dates' => [],
                'settings' => $prevSettings,
            ];
        }

        $dates = [];
        for ($d = $minCutOff + 1; $d <= $daysInPrevMonth; $d++) {
            $dates[] = sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $d);
        }

        return [
            'has_carry_over' => !empty($dates),
            'prev_year' => $prevYear,
            'prev_month' => $prevMonth,
            'prev_cut_off' => $minCutOff,
            'dates' => $dates,
            'settings' => $prevSettings,
        ];
    }
}
