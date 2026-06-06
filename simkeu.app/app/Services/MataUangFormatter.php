<?php

namespace App\Services;

class MataUangFormatter
{
    public static function defaultCurrency(): array
    {
        return [
            'id' => null,
            'kode' => 'IDR',
            'nama' => 'Rupiah',
            'simbol' => 'Rp',
        ];
    }

    public static function fromTagihan($tagihan): array
    {
        $mataUang = $tagihan?->mata_uang;

        return [
            'id' => $mataUang?->id ?? $tagihan?->mata_uang_id ?? null,
            'kode' => $mataUang?->kode ?? $tagihan?->mata_uang_kode ?? 'IDR',
            'nama' => $mataUang?->nama ?? $tagihan?->mata_uang_nama ?? 'Rupiah',
            'simbol' => $mataUang?->simbol ?? $tagihan?->mata_uang_simbol ?? 'Rp',
        ];
    }

    public static function fromColumns($row): array
    {
        return [
            'id' => data_get($row, 'mata_uang_id'),
            'kode' => data_get($row, 'mata_uang_kode', 'IDR') ?: 'IDR',
            'nama' => data_get($row, 'mata_uang_nama', 'Rupiah') ?: 'Rupiah',
            'simbol' => data_get($row, 'mata_uang_simbol', 'Rp') ?: 'Rp',
        ];
    }

    public static function amount($amount, array $mataUang): string
    {
        $prefix = trim((string) ($mataUang['simbol'] ?: $mataUang['kode'] ?: ''));

        return trim($prefix . ' ' . number_format((float) $amount, 0, ',', '.'));
    }

    public static function addToTotals(array &$totals, $amount, array $mataUang): void
    {
        $kode = strtoupper((string) ($mataUang['kode'] ?? 'IDR'));
        $key = 'kode:' . $kode;

        if (! isset($totals[$key])) {
            $totals[$key] = [
                'key' => $key,
                'mata_uang' => [
                    'id' => $mataUang['id'] ?? null,
                    'kode' => $kode,
                    'nama' => $mataUang['nama'] ?? $kode,
                    'simbol' => $mataUang['simbol'] ?? $kode,
                ],
                'total' => 0,
            ];
        }

        $totals[$key]['total'] += (float) $amount;
    }

    public static function mergeTotals(array ...$groups): array
    {
        $totals = [];

        foreach ($groups as $group) {
            foreach ($group as $row) {
                self::addToTotals(
                    $totals,
                    data_get($row, 'total', 0),
                    data_get($row, 'mata_uang', self::defaultCurrency()),
                );
            }
        }

        return self::normalizeTotals($totals);
    }

    public static function normalizeTotals(array $totals): array
    {
        $rows = array_values($totals);

        usort($rows, function ($left, $right) {
            $leftCode = strtoupper((string) data_get($left, 'mata_uang.kode', 'IDR'));
            $rightCode = strtoupper((string) data_get($right, 'mata_uang.kode', 'IDR'));

            if ($leftCode === $rightCode) {
                return 0;
            }

            if ($leftCode === 'IDR') {
                return -1;
            }

            if ($rightCode === 'IDR') {
                return 1;
            }

            return strcmp($leftCode, $rightCode);
        });

        return $rows;
    }

    public static function formatTotals(array $totals, string $empty = '-'): string
    {
        $formatted = array_map(
            fn ($row) => self::amount(
                data_get($row, 'total', 0),
                data_get($row, 'mata_uang', self::defaultCurrency()),
            ),
            self::normalizeTotals($totals),
        );

        return empty($formatted) ? $empty : implode(' / ', $formatted);
    }

    public static function totalsByCurrency($transaksi, bool $excludeDeposit = false): array
    {
        $totals = [];

        foreach ($transaksi as $item) {
            $jenisPembayaran = $item->jenisPembayaranDetail?->jenisPembayaran?->nama;

            if ($excludeDeposit && strtolower((string) $jenisPembayaran) === 'deposit') {
                continue;
            }

            $mataUang = self::fromTagihan($item->tagihan);
            self::addToTotals($totals, $item->jumlah, $mataUang);
        }

        return self::normalizeTotals($totals);
    }

    public static function terbilangTotals(array $totals): string
    {
        if (empty($totals)) {
            return 'nol rupiah';
        }

        return implode('; ', array_map(function ($row) {
            $terbilang = Helper::terbilang((float) $row['total']);
            if ($terbilang === '') {
                $terbilang = 'nol';
            }

            $mataUang = $row['mata_uang'];
            $satuan = strtolower((string) ($mataUang['nama'] ?: $mataUang['kode'] ?: ''));

            return trim($terbilang . ' ' . $satuan);
        }, $totals));
    }
}
