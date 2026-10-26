<?php

namespace App\Services;

use App\Models\KeuanganSettingAkhirLaporan;
use Illuminate\Support\Collection;

class LaporanCutOffService
{
    /**
     * Cache setting per request untuk performa tinggi
     */
    protected static array $settingsCache = [];

    /**
     * Ambil settings untuk tahun dan bulan tertentu (dengan cache memori per-request)
     */
    public static function getSettingsForMonth(int $tahun, int $bulan): Collection
    {
        $cacheKey = "{$tahun}_{$bulan}";
        if (!isset(self::$settingsCache[$cacheKey])) {
            self::$settingsCache[$cacheKey] = KeuanganSettingAkhirLaporan::where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->get();
        }

        return self::$settingsCache[$cacheKey];
    }

    /**
     * Cek apakah key setting merupakan key global ('all', 'semua', 'global', '*', atau kosong)
     */
    public static function isGlobalKey(?string $key): bool
    {
        $k = strtolower(trim((string) $key));
        return in_array($k, ['all', 'semua', 'global', '*'], true) || empty($k);
    }

    /**
     * Cek apakah nama metode pembayaran / ID pembayaran cocok dengan aturan setting
     */
    public static function isMatchingRule(KeuanganSettingAkhirLaporan $setting, ?string $paymentName, ?int $paymentId = null): bool
    {
        $key = strtolower(trim((string) $setting->jenis_pembayaran_key));

        if (self::isGlobalKey($key)) {
            return true;
        }

        if ($setting->jenis_pembayaran_id && $paymentId) {
            if ((int) $setting->jenis_pembayaran_id === (int) $paymentId) {
                return true;
            }
        }

        $pName = strtolower(trim((string) $paymentName));

        if ($key === 'cash') {
            return in_array($paymentId, [9, 14]) || str_contains($pName, 'cash') || str_contains($pName, 'tunai');
        }

        if ($key === 'transfer') {
            return in_array($paymentId, [8, 13]) || str_contains($pName, 'transfer');
        }

        if ($key === 'va_bsi') {
            return in_array($paymentId, [16, 17]) || str_contains($pName, 'bsi');
        }

        if ($key === 'va_pondok') {
            return str_contains($pName, 'pondok');
        }

        if (str_starts_with($key, 'jp_')) {
            $expectedId = (int) substr($key, 3);
            return $paymentId && (int) $paymentId === $expectedId;
        }

        return false;
    }

    /**
     * Ambil aturan cut-off yang berlaku untuk pembayaran tertentu pada suatu bulan/tahun.
     * Mengutamakan aturan paling spesifik, namun jika ada setting global yang lebih ketat (tanggal cut-off lebih awal),
     * maka setting yang membatasi transaksi tersebut akan diterapkan.
     */
    public static function findMatchingSetting(int $tahun, int $bulan, ?string $paymentName, ?int $paymentId = null): ?KeuanganSettingAkhirLaporan
    {
        $settings = self::getSettingsForMonth($tahun, $bulan);
        if ($settings->isEmpty()) {
            return null;
        }

        $matchSpecific = null;

        // 1. Cek spesifik jenis_pembayaran_id
        if ($paymentId) {
            $matchSpecific = $settings->first(function ($s) use ($paymentId) {
                return $s->jenis_pembayaran_id && (int) $s->jenis_pembayaran_id === (int) $paymentId;
            });
        }

        // 2. Cek spesifik key (cash, transfer, dll selain global)
        if (!$matchSpecific) {
            $matchSpecific = $settings->first(function ($s) use ($paymentName, $paymentId) {
                $k = strtolower(trim((string) $s->jenis_pembayaran_key));
                return !self::isGlobalKey($k) && self::isMatchingRule($s, $paymentName, $paymentId);
            });
        }

        // 3. Cek global 'all' / 'semua'
        $global = $settings->first(function ($s) {
            $k = strtolower(trim((string) $s->jenis_pembayaran_key));
            return self::isGlobalKey($k);
        });

        if ($matchSpecific && $global) {
            return ((int) $matchSpecific->tanggal_akhir <= (int) $global->tanggal_akhir) ? $matchSpecific : $global;
        }

        return $matchSpecific ?: $global;
    }

    /**
     * Menentukan apakah suatu transaksi pembayaran masuk ke dalam periode pembukuan targetYear & targetMonth.
     *
     * Kasus:
     * 1. Transaksi ada di tanggal bulan berjalan ($targetYear, $targetMonth):
     *    - Jika ada setting cut-off di bulan ini dan tanggal transaksi > tanggal_akhir:
     *      -> RETURN FALSE (karena dialihkan ke bulan berikutnya).
     *    - Jika tidak ada cut-off atau tanggal <= tanggal_akhir:
     *      -> RETURN TRUE.
     *
     * 2. Transaksi ada di tanggal bulan sebelumnya (M-1):
     *    - Jika ada setting cut-off di bulan sebelumnya dan tanggal transaksi > tanggal_akhir bulan sebelumnya:
     *      -> RETURN TRUE (karena dialihkan masuk ke bulan berjalan).
     *    - Jika tidak:
     *      -> RETURN FALSE.
     *
     * 3. Tanggal lainnya:
     *    -> RETURN FALSE.
     */
    public static function isPaymentInAccountingMonth(
        string $paymentDateStr,
        ?string $paymentName,
        ?int $paymentId,
        int $targetYear,
        int $targetMonth
    ): bool {
        $timestamp = strtotime($paymentDateStr);
        if (!$timestamp) return false;

        $pYear = (int) date('Y', $timestamp);
        $pMonth = (int) date('n', $timestamp);
        $pDay = (int) date('j', $timestamp);

        // Kasus 1: Tanggal transaksi berada di kalender targetMonth
        if ($pYear === $targetYear && $pMonth === $targetMonth) {
            $curSetting = self::findMatchingSetting($targetYear, $targetMonth, $paymentName, $paymentId);
            if ($curSetting) {
                // Jika tanggal melebihi cut-off, maka masuk ke bulan berikutnya
                if ($pDay > (int) $curSetting->tanggal_akhir) {
                    return false;
                }
            }
            return true;
        }

        // Kasus 2: Tanggal transaksi berada di kalender bulan sebelumnya
        $prevYear = $targetYear;
        $prevMonth = $targetMonth - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear -= 1;
        }

        if ($pYear === $prevYear && $pMonth === $prevMonth) {
            $prevSetting = self::findMatchingSetting($prevYear, $prevMonth, $paymentName, $paymentId);
            if ($prevSetting) {
                // Jika tanggal melebihi cut-off bulan sebelumnya, maka MASUK ke bulan berjalan
                if ($pDay > (int) $prevSetting->tanggal_akhir) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Ambil informasi periode cut off dan daftar tanggal carry-over dari bulan sebelumnya.
     */
    public static function getMonthPeriodMeta(int $targetYear, int $targetMonth): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);

        $prevYear = $targetYear;
        $prevMonth = $targetMonth - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear -= 1;
        }

        $daysInPrevMonth = cal_days_in_month(CAL_GREGORIAN, $prevMonth, $prevYear);
        $prevSettings = self::getSettingsForMonth($prevYear, $prevMonth);
        $currSettings = self::getSettingsForMonth($targetYear, $targetMonth);

        $carryOverDates = [];
        $hasCarryOver = false;

        if ($prevSettings->isNotEmpty()) {
            $minCutOff = $prevSettings->min('tanggal_akhir');
            if ($minCutOff && $minCutOff < $daysInPrevMonth) {
                $hasCarryOver = true;
                for ($d = $minCutOff + 1; $d <= $daysInPrevMonth; $d++) {
                    $carryOverDates[] = sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $d);
                }
            }
        }

        $earliestDate = !empty($carryOverDates) ? $carryOverDates[0] : sprintf('%04d-%02d-01', $targetYear, $targetMonth);
        $latestDate = sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $daysInMonth);

        $bulanNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $nextYear = $targetYear;
        $nextMonth = $targetMonth + 1;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear += 1;
        }

        $hasCurrentCutOff = false;
        $currentCutOffDates = [];
        $currentMinCutOff = null;
        if ($currSettings->isNotEmpty()) {
            $currentMinCutOff = $currSettings->min('tanggal_akhir');
            if ($currentMinCutOff && $currentMinCutOff < $daysInMonth) {
                $hasCurrentCutOff = true;
                for ($d = $currentMinCutOff + 1; $d <= $daysInMonth; $d++) {
                    $currentCutOffDates[] = sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $d);
                }
            }
        }

        return [
            'has_carry_over' => $hasCarryOver,
            'carry_over_dates' => $carryOverDates,
            'prev_month' => $prevMonth,
            'prev_year' => $prevYear,
            'prev_month_name' => $bulanNames[$prevMonth] ?? "Bulan $prevMonth",
            'current_month' => $targetMonth,
            'current_year' => $targetYear,
            'current_month_name' => $bulanNames[$targetMonth] ?? "Bulan $targetMonth",
            'next_month' => $nextMonth,
            'next_year' => $nextYear,
            'next_month_name' => $bulanNames[$nextMonth] ?? "Bulan $nextMonth",
            'has_current_cut_off' => $hasCurrentCutOff,
            'current_cut_off_dates' => $currentCutOffDates,
            'current_min_cut_off' => $currentMinCutOff,
            'current_settings' => $currSettings,
            'prev_settings' => $prevSettings,
            'query_start_date' => $earliestDate,
            'query_end_date' => $latestDate,
            'days_in_current_month' => $daysInMonth,
        ];
    }
}
