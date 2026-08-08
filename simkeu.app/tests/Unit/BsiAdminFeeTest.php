<?php

namespace Tests\Unit;

use App\Models\KeuanganPembayaranBsi;
use PHPUnit\Framework\TestCase;

class BsiAdminFeeTest extends TestCase
{
    public function test_institution_bears_admin_fee(): void
    {
        $payment = new KeuanganPembayaranBsi([
            'total' => 10000,
            'admin_fee_bearer' => 'institution',
            'admin_fee_amount' => 2500,
        ]);

        $this->assertSame(10000.0, $payment->payableTotal());
        $this->assertSame(7500.0, $payment->expectedSettlementTotal());
    }

    public function test_payer_bears_admin_fee(): void
    {
        $payment = new KeuanganPembayaranBsi([
            'total' => 10000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 2500,
        ]);

        $this->assertSame(12500.0, $payment->payableTotal());
        $this->assertSame(10000.0, $payment->expectedSettlementTotal());
    }
}
