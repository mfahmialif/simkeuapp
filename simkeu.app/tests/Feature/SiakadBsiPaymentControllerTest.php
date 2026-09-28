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

    public function test_bills_returns_student_bills_with_multi_prasyarat(): void
    {
        $billsData = [
            'nim' => '20240001',
            'nama_mahasiswa' => 'Mahasiswa Test',
            'nama_prodi' => 'S1 Sistem Informasi',
            'nama_kelas' => 'A',
            'semester' => 5,
            'list_tagihan' => [
                [
                    'id' => 10,
                    'nama' => 'Daftar Ulang',
                    'th_akademik_id' => 25,
                    'th_akademik_kode' => '20261',
                    'tahun_akademik' => '2026/2027 Ganjil',
                    'jumlah_tagihan' => 500000.0,
                    'sisa_resmi' => 500000.0,
                    'reservasi_bsi' => 0.0,
                    'tersedia' => 500000.0,
                    'mata_uang_kode' => 'IDR',
                    'tidak_bisa_dibayar' => false,
                    'keterangan_pembayaran' => null,
                    'prasyarat' => [],
                    'prasyarat_string' => null,
                ],
                [
                    'id' => 12,
                    'nama' => 'UAS Semester 5',
                    'th_akademik_id' => 25,
                    'th_akademik_kode' => '20261',
                    'tahun_akademik' => '2026/2027 Ganjil',
                    'jumlah_tagihan' => 1200000.0,
                    'sisa_resmi' => 1200000.0,
                    'reservasi_bsi' => 0.0,
                    'tersedia' => 1200000.0,
                    'mata_uang_kode' => 'IDR',
                    'tidak_bisa_dibayar' => true,
                    'keterangan_pembayaran' => 'Belum melunasi prasyarat: Herregistrasi Semester 5, SPP Semester 5',
                    'prasyarat' => ['Herregistrasi Semester 5', 'SPP Semester 5'],
                    'prasyarat_string' => 'Herregistrasi Semester 5, SPP Semester 5',
                ],
            ],
            'total_tersedia' => 1700000.0,
        ];

        $service = $this->createMock(\App\Services\BsiPaymentService::class);
        $service->expects($this->once())
            ->method('availableTagihan')
            ->with('20240001')
            ->willReturn($billsData);

        $response = (new SiakadBsiPaymentController)->bills('20240001', $service);

        $this->assertSame(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['status']);
        $this->assertEquals($billsData, $responseData['data']);
        $this->assertIsArray($responseData['data']['list_tagihan'][1]['prasyarat']);
        $this->assertCount(2, $responseData['data']['list_tagihan'][1]['prasyarat']);
        $this->assertSame('Herregistrasi Semester 5', $responseData['data']['list_tagihan'][1]['prasyarat'][0]);
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
