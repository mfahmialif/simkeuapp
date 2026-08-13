<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SiakadBsiPaymentController;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentOrderService;
use App\Services\SiakadPaymentHistoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiakadBsiPaymentControllerTest extends TestCase
{
    public function test_payment_history_returns_bundled_official_payments(): void
    {
        $history = [
            'nim' => '20240001',
            'total_transaksi' => 1,
            'total_pembayaran' => 350000,
            'riwayat' => [['nota' => '130826-00001-L-123']],
        ];
        $service = $this->createMock(SiakadPaymentHistoryService::class);
        $service->expects($this->once())
            ->method('forStudent')
            ->with('20240001')
            ->willReturn($history);

        $response = (new SiakadBsiPaymentController)->paymentHistory('20240001', $service);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['status']);
        $this->assertSame($history, $response->getData(true)['data']);
    }

    public function test_create_order_accepts_only_the_simple_siakad_payload(): void
    {
        $payload = [
            'request_id' => 'SIAKAD-ORDER-SIMPLE',
            'nim' => '20240001',
            'items' => [['tagihan_id' => 10, 'jumlah' => 100000]],
        ];
        $request = Request::create(
            '/api/v1/integrations/siakad/bsi/payment-orders',
            'POST',
            $payload
        );
        $payment = new KeuanganPembayaranBsi;
        $orderService = $this->createMock(BsiPaymentOrderService::class);
        $orderService->expects($this->once())
            ->method('create')
            ->with($payload)
            ->willReturn([$payment, true]);
        $orderService->expects($this->once())
            ->method('data')
            ->with($payment)
            ->willReturn(['request_id' => $payload['request_id']]);

        $response = (new SiakadBsiPaymentController)->store($request, $orderService);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($payload['request_id'], $response->getData(true)['data']['request_id']);
    }

    public function test_create_order_rejects_configuration_fields_managed_by_simkeu(): void
    {
        $request = Request::create('/api/v1/integrations/siakad/bsi/payment-orders', 'POST', [
            'request_id' => 'SIAKAD-ORDER-1',
            'nim' => '20240001',
            'items' => [['tagihan_id' => 10, 'jumlah' => 100000]],
            'data_test' => false,
            'production' => true,
            'payment_mode' => 'close',
            'payment_expiry_minutes' => 60,
            'admin_fee_amount' => 0,
        ]);

        try {
            (new SiakadBsiPaymentController)->store(
                $request,
                $this->createMock(BsiPaymentOrderService::class)
            );
            $this->fail('Request dengan field konfigurasi seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['body'][0] ?? '';

            $this->assertStringContainsString('data_test', $message);
            $this->assertStringContainsString('production', $message);
            $this->assertStringContainsString('dikelola oleh SIMKEU', $message);
        }
    }

    public function test_create_order_rejects_unsupported_item_fields(): void
    {
        $request = Request::create('/api/v1/integrations/siakad/bsi/payment-orders', 'POST', [
            'request_id' => 'SIAKAD-ORDER-2',
            'nim' => '20240001',
            'items' => [[
                'tagihan_id' => 10,
                'jumlah' => 100000,
                'cara_bayar' => 'lunas',
            ]],
        ]);

        try {
            (new SiakadBsiPaymentController)->store(
                $request,
                $this->createMock(BsiPaymentOrderService::class)
            );
            $this->fail('Request dengan field item tambahan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['body'][0] ?? '';

            $this->assertStringContainsString('cara_bayar', $message);
            $this->assertStringContainsString('dikelola oleh SIMKEU', $message);
        }
    }
}
