<?php

namespace Tests\Unit;

use App\Services\BsiPaymentTransferService;
use PHPUnit\Framework\TestCase;

class BsiPaymentTransferServiceTest extends TestCase
{
    public function test_it_builds_stable_ledger_numbers(): void
    {
        $service = new BsiPaymentTransferService;

        $this->assertSame(
            'BSI-20260809-00000042-03',
            $service->postedPaymentNumber('BSI-20260809-00000042', 3)
        );
    }

    public function test_it_resolves_semester_from_nim_and_academic_year(): void
    {
        $service = new BsiPaymentTransferService;

        $this->assertSame(4, $service->resolveSemester('20240001', '20252'));
        $this->assertSame(1, $service->resolveSemester('20250001', '20251'));
        $this->assertNull($service->resolveSemester('INVALID', '20252'));
        $this->assertNull($service->resolveSemester('20250001', '20253'));
    }
}
