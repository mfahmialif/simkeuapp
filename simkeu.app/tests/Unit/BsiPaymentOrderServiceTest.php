<?php

namespace Tests\Unit;

use App\Models\KeuanganPembayaranBsi;
use App\Models\KeuanganPembayaranBsiDetail;
use App\Services\BsiPaymentOrderService;
use App\Services\BsiPaymentService;
use App\Services\BsiSettingsService;
use Tests\TestCase;

class BsiPaymentOrderServiceTest extends TestCase
{
    public function test_customer_number_uses_nim_without_dots(): void
    {
        $this->assertSame(
            '202002020202',
            BsiPaymentOrderService::customerNumberFromNim('2020.02.02.0202')
        );
    }

    public function test_payment_order_detail_contains_nama_tagihan(): void
    {
        $payment = new KeuanganPembayaranBsi([
            'total' => 100000,
            'admin_fee_amount' => 0,
        ]);
        $payment->setRelation('details', collect([
            new KeuanganPembayaranBsiDetail([
                'tagihan_id' => 10,
                'tagihan_nama' => 'SPP',
                'jumlah' => 100000,
            ]),
        ]));
        $payment->setRelation('metodeVa', null);

        $service = new BsiPaymentOrderService(
            $this->createMock(BsiPaymentService::class),
            $this->createMock(BsiSettingsService::class),
        );

        $this->assertSame('SPP', $service->data($payment)['details'][0]['nama_tagihan']);
    }
}
