<?php

namespace Tests\Unit;

use App\Services\BsiPaymentOrderService;
use PHPUnit\Framework\TestCase;

class BsiPaymentOrderServiceTest extends TestCase
{
    public function test_customer_number_uses_nim_without_dots(): void
    {
        $this->assertSame(
            '202002020202',
            BsiPaymentOrderService::customerNumberFromNim('2020.02.02.0202')
        );
    }
}
