<?php

namespace Tests\Unit;

use App\Models\BsiIntegrationSetting;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiSettingsService;
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

    public function test_sandbox_fee_is_fixed_and_charged_to_payer(): void
    {
        $settings = new BsiIntegrationSetting([
            'environment' => 'sandbox',
            'admin_fee_bearer' => 'institution',
            'admin_fee_amount' => 9999,
        ]);

        $configuration = (new BsiSettingsService)->adminFeeConfiguration($settings);

        $this->assertSame('payer', $configuration['bearer']);
        $this->assertSame(3000.0, $configuration['amount']);
        $this->assertTrue($configuration['locked']);
    }

    public function test_production_fee_uses_saved_configuration(): void
    {
        $settings = new BsiIntegrationSetting([
            'environment' => 'production',
            'admin_fee_bearer' => 'institution',
            'admin_fee_amount' => 2750,
        ]);

        $configuration = (new BsiSettingsService)->adminFeeConfiguration($settings);

        $this->assertSame('institution', $configuration['bearer']);
        $this->assertSame(2750.0, $configuration['amount']);
        $this->assertFalse($configuration['locked']);
    }

    public function test_sandbox_sends_principal_to_snap_while_bank_adds_fee(): void
    {
        $settings = new BsiIntegrationSetting(['environment' => 'sandbox']);
        $payment = new KeuanganPembayaranBsi([
            'total' => 250000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 3000,
        ]);
        $service = new BsiSettingsService;

        $this->assertSame(250000.0, $service->snapTransactionAmount($payment, $settings));
        $this->assertSame(250000.0, $service->expectedSettlementAmount($payment, $settings));
        $this->assertSame(253000.0, $payment->payableTotal());
    }
}
