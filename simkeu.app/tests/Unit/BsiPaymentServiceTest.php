<?php

namespace Tests\Unit;

use App\Services\BsiPaymentService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BsiPaymentServiceTest extends TestCase
{
    public function test_it_builds_and_verifies_callback_signatures(): void
    {
        $timestamp = '1781154000';
        $body = '{"callback_id":"CB-1","status":"paid"}';
        $secret = 'test-secret';
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->assertSame($expected, BsiPaymentService::signature($timestamp, $body, $secret));
        $this->assertTrue(BsiPaymentService::verifySignature($timestamp, $body, $expected, $secret));
        $this->assertFalse(BsiPaymentService::verifySignature($timestamp, $body.' ', $expected, $secret));
        $this->assertFalse(BsiPaymentService::verifySignature($timestamp, $body, '', $secret));
    }

    public function test_it_builds_stable_internal_and_ledger_numbers(): void
    {
        $number = BsiPaymentService::buildInternalNumber(
            42,
            Carbon::parse('2026-06-11 09:00:00')
        );

        $this->assertSame('BSI-20260611-00000042', $number);
        $this->assertSame('BSI-20260611-00000042-03', BsiPaymentService::buildPostedPaymentNumber($number, 3));
    }

    public function test_it_resolves_student_semester_from_nim_and_academic_year(): void
    {
        $service = new BsiPaymentService;

        $this->assertSame(1, $service->resolveSemester('20260001', '20261'));
        $this->assertSame(4, $service->resolveSemester('20240001', '20252'));
        $this->assertNull($service->resolveSemester('INVALID', '20261'));
        $this->assertNull($service->resolveSemester('20260001', '20263'));
    }
}
