<?php

namespace Tests\Unit;

use App\Services\BsiPaymentService;
use App\Services\TagihanMahasiswa;
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

    public function test_bsi_transactions_never_reserve_official_bills(): void
    {
        $service = new BsiPaymentService;

        $this->assertSame(0.0, $service->reservedAmount('20240001', 10));
        $this->assertSame(0.0, $service->reservedAmount('20240001', 10, 99));
    }

    public function test_tagihan_are_split_at_the_students_current_semester(): void
    {
        $tagihan = [
            ['id' => 1, 'th_akademik_kode' => '20231', 'sisa' => 100000],
            ['id' => 2, 'th_akademik_kode' => '20232', 'sisa' => 200000],
            ['id' => 3, 'th_akademik_kode' => '20241', 'sisa' => 300000],
        ];

        $groups = TagihanMahasiswa::splitTagihanBySemester($tagihan, 4, '20221');

        $this->assertSame([1, 2], array_column($groups['semester_ini'], 'id'));
        $this->assertSame([3], array_column($groups['semester_depan'], 'id'));
        $this->assertSame([3, 4], array_column($groups['semester_ini'], 'semester_tagihan'));
        $this->assertSame([5], array_column($groups['semester_depan'], 'semester_tagihan'));
    }

    public function test_open_payment_is_allocated_from_the_first_detail(): void
    {
        $service = new BsiPaymentService;

        $this->assertSame(
            ['amounts' => [900000.0, 0.0], 'remainder' => 0.0],
            $service->allocateFromTop([900000, 100000], 900000)
        );
        $this->assertSame(
            ['amounts' => [900000.0, 50000.0], 'remainder' => 0.0],
            $service->allocateFromTop([900000, 100000], 950000)
        );
    }

    public function test_open_payment_returns_overpayment_as_remainder(): void
    {
        $service = new BsiPaymentService;

        $this->assertSame(
            ['amounts' => [900000.0, 100000.0], 'remainder' => 200000.0],
            $service->allocateFromTop([900000, 100000], 1200000)
        );
    }
}
