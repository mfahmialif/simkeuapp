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

    public function test_it_activates_when_combination_of_herreg_and_spp_is_at_least_450k(): void
    {
        $service = new BsiPaymentTransferService;

        $payment = new \App\Models\KeuanganPembayaranBsi(['nim' => '20240001']);
        $detail1 = new \App\Models\KeuanganPembayaranBsiDetail([
            'tagihan_nama' => 'Herregistrasi Mahasiswa',
            'jumlah' => 150000,
        ]);
        $detail2 = new \App\Models\KeuanganPembayaranBsiDetail([
            'tagihan_nama' => 'SPP Semester Gasal',
            'jumlah' => 300000,
        ]);
        $payment->setRelation('details', collect([$detail1, $detail2]));

        $this->assertTrue($service->shouldActivateMahasiswa($payment));
    }

    public function test_it_does_not_activate_when_non_eligible_bill_is_paid(): void
    {
        $service = new BsiPaymentTransferService;

        $payment = new \App\Models\KeuanganPembayaranBsi(['nim' => '20240001']);
        $detail = new \App\Models\KeuanganPembayaranBsiDetail([
            'tagihan_nama' => 'Biaya Wisuda',
            'jumlah' => 1500000,
        ]);
        $payment->setRelation('details', collect([$detail]));

        $this->assertFalse($service->shouldActivateMahasiswa($payment));
    }

    public function test_it_does_not_activate_when_under_450k_and_not_lunas(): void
    {
        $service = new BsiPaymentTransferService;

        $payment = new \App\Models\KeuanganPembayaranBsi(['nim' => '20240001']);
        $detail = new \App\Models\KeuanganPembayaranBsiDetail([
            'tagihan_nama' => 'Herregistrasi Mahasiswa',
            'jumlah' => 150000,
            'sisa_awal' => 500000,
        ]);
        $payment->setRelation('details', collect([$detail]));

        $this->assertFalse($service->shouldActivateMahasiswa($payment));
    }

    public function test_it_activates_when_under_450k_but_bill_is_lunas(): void
    {
        $service = new BsiPaymentTransferService;

        $payment = new \App\Models\KeuanganPembayaranBsi(['nim' => '20240001']);
        $detail = new \App\Models\KeuanganPembayaranBsiDetail([
            'tagihan_nama' => 'Herregistrasi Mahasiswa',
            'jumlah' => 150000,
            'sisa_awal' => 150000,
        ]);
        $payment->setRelation('details', collect([$detail]));

        $this->assertTrue($service->shouldActivateMahasiswa($payment));
    }
}
