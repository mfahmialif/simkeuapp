<?php

namespace Tests\Unit;

use App\Services\MataUangFormatter;
use PHPUnit\Framework\TestCase;

class MataUangFormatterTest extends TestCase
{
    public function test_it_keeps_currency_totals_separate_and_merges_same_codes(): void
    {
        $totals = [];

        MataUangFormatter::addToTotals($totals, 10000, [
            'id' => 1,
            'kode' => 'IDR',
            'nama' => 'Rupiah',
            'simbol' => 'Rp',
        ]);
        MataUangFormatter::addToTotals(
            $totals,
            5000,
            MataUangFormatter::defaultCurrency(),
        );
        MataUangFormatter::addToTotals($totals, 25, [
            'id' => 2,
            'kode' => 'USD',
            'nama' => 'Dolar',
            'simbol' => '$',
        ]);

        $normalized = MataUangFormatter::normalizeTotals($totals);

        $this->assertCount(2, $normalized);
        $this->assertSame('IDR', $normalized[0]['mata_uang']['kode']);
        $this->assertSame(15000.0, $normalized[0]['total']);
        $this->assertSame('USD', $normalized[1]['mata_uang']['kode']);
        $this->assertSame(25.0, $normalized[1]['total']);
        $this->assertSame(
            'Rp 15.000 / $ 25',
            MataUangFormatter::formatTotals($normalized),
        );
    }
}
