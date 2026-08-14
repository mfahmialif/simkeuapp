<?php

namespace Tests\Unit;

use App\Models\KeuanganMetodeVa;
use App\Services\BsiVaPaymentMethodService;
use PHPUnit\Framework\TestCase;

class BsiVaPaymentMethodServiceTest extends TestCase
{
    public function test_it_maps_supported_bsi_payment_channels(): void
    {
        $service = new BsiVaPaymentMethodService;

        $this->assertSame(
            KeuanganMetodeVa::BYOND_BSI,
            $service->resolveCode('6027', '5090123456789012', '5090')
        );
        $this->assertSame(
            KeuanganMetodeVa::ATM_BSI,
            $service->resolveCode('6011', '5090123456789012', '5090')
        );
        $this->assertSame(
            KeuanganMetodeVa::ATM_LAIN,
            $service->resolveCode('6011', '9005090123456789012', '5090')
        );
        $this->assertNull($service->resolveCode('6099', '5090123456789012', '5090'));
    }
}
