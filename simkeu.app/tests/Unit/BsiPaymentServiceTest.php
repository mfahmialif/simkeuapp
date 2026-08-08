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

    public function test_it_builds_stable_internal_numbers(): void
    {
        $number = BsiPaymentService::buildInternalNumber(
            42,
            Carbon::parse('2026-06-11 09:00:00')
        );

        $this->assertSame('BSI-20260611-00000042', $number);
    }
}
