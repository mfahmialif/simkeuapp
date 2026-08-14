<?php

namespace Tests\Feature;

use App\Exceptions\BsiSnapException;
use App\Http\Controllers\Api\BsiSnapController;
use App\Models\BsiIntegrationSetting;
use App\Models\BsiReconciliation;
use App\Models\BsiSnapLog;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentOrderService;
use App\Services\BsiPaymentService;
use App\Services\BsiSettingsService;
use App\Services\BsiSnapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class BsiSnapFlowTest extends TestCase
{
    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for the isolated BI-SNAP flow test.');
        }

        $this->createSchema();

        $this->privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAMK5mxNsCfwLyVx2
it/BhCn0bAGfO+vSg9aluy/oqvgMLkEZVtgOiwS0P8EoTldHUTD88ycZkLUfJa5F
OlEBaUlIzRp7zbvReGQL35+bQAOKpg2LFQVqT5X1kblPpT6g5m/2H7v0s+2Y2hSu
LUOJWhbB0rwomvRYOSGhPRpR6rjTAgMBAAECgYEAoX6vp3b1/OyFjsLdwF9NTkJh
hSLb3mQUZWPEECLGELyBbAoo5T2CfF9FdNwJxQbnxlozCx1/z0dKx/KwP9iMyQDC
kbpIP/P/N26zk/2rZFA/rvzzCvJTwuFQfhkXEKXfKZsO8zgwihqdynny3ZoU+Z72
2RzCA5a8e/fRafjReVECQQDsNQ/mSBzF5SBDIgXewBYKhhJKQUD+sKQBdA9K/PYk
2rEq08zY9/vMTYVeHLUbTgL6vdyyq/cgaorJhnxs/OhJAkEA0wqyt0YJKy7VHC6c
0MKXrDgxt1RXC03CO2PLGlTv5AJw3e+c6hBru/YLH926iyR9hpmfXWOvm8yF4D74
inewOwJAbNxXo44AlMPwhoAbrHlirx7zNv2z8q1+KJ1QnwLOflW76T0L38MKbTId
ES6x2Q+vF9iA6meO0YXIyPAOUDyO4QJATycACIUYAz45Z6yD3DhfspQQ8XWMgAzW
AHhZQLMClj3kHxkzfQZTodeSLI143Z7+BXGwt9IflwuKWqTDiwuA6QJBAMNaX0My
YR6c1JFdqBkjbXNNv63vjPIBrwKbi3RugEU5ln3/2L5Ninhh6fFlDMf2zZUYbbgd
oibnlfB8eE82hl8=
-----END PRIVATE KEY-----
PEM;
        $key = openssl_pkey_get_private($this->privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        BsiIntegrationSetting::create([
            'enabled' => true,
            'environment' => 'sandbox',
            'test_mode' => false,
            'test_nims' => [],
            'institution_name' => 'UII Dalwa',
            'kode_bpi' => '5090',
            'client_id' => 'bsi-client',
            'client_secret' => 'bsi-client-secret',
            'bpi_public_key' => $publicKey,
            'reconciliation_secret' => 'recon-secret',
            'payment_expiry_minutes' => 1440,
            'payment_mode' => 'open',
            'timestamp_tolerance' => 300,
            'allowed_ips' => [],
            'enforce_ip_allowlist' => false,
            'verify_signatures' => true,
            'log_payloads' => true,
            'serve_test_va' => false,
            'database_failure_mode' => 'none',
            'siakad_api_key_hash' => hash('sha256', 'siakad-key'),
        ]);
    }

    public function test_auth_inquiry_payment_advice_and_reconciliation_flow(): void
    {
        $payment = KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-20260808-00000001',
            'request_id' => 'SIAKAD-1',
            'request_hash' => str_repeat('a', 64),
            'nim' => '20240001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'jk_id' => 8,
            'jenis_pembayaran_id' => 1,
            'va_number' => '5090123456789012',
            'customer_no' => '123456789012',
            'bsi_payment_number' => '5090123456789012',
            'interbank_va_number' => '9005090123456789012',
            'reference_no' => 'BSI-20260808-00000001',
            'total' => 350000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 3000,
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);
        $payment->details()->create([
            'tagihan_id' => 10,
            'tagihan_nama' => 'UKT',
            'jumlah' => 350000,
            'urutan' => 1,
        ]);

        $service = new BsiSnapService(new BsiSettingsService);

        [$authBody, $authStatus] = $service->authenticate($this->authRequest());
        $this->assertSame(200, $authStatus);
        $this->assertSame('2000000', $authBody['responseCode']);
        $token = $authBody['accessToken'];

        $inquiryId = 'INQ-20260808-000001';
        $inquiryPayload = [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'trxDateInit' => now()->toIso8601String(),
            'virtualAccountNo' => '    5090123456789012',
            'inquiryRequestId' => $inquiryId,
            'sourceBankCode' => '451',
        ];
        [$inquiryBody] = $service->inquiry($this->transactionRequest(
            '/api/bpi-bi-snap/inquiry',
            $inquiryPayload,
            $token,
            $inquiryId
        ));
        $this->assertSame('2002400', $inquiryBody['responseCode']);
        $this->assertSame('350000.00', $inquiryBody['virtualAccountData']['totalAmount']['value']);
        $this->assertSame(353000.0, $payment->payableTotal());
        $this->assertNotContains(
            'BIAYA ADMIN BSI',
            collect($inquiryBody['virtualAccountData']['billDetail'])->pluck('label')->all()
        );

        $paymentId = 'PAY-20260808-000001';
        $trxDateTime = now()->startOfSecond()->toIso8601String();
        $paymentPayload = [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'virtualAccountNo' => '    5090123456789012',
            'virtualAccountName' => 'Mahasiswa Uji',
            'paidAmount' => ['value' => '350000.00', 'currency' => 'IDR'],
            'trxDateTime' => $trxDateTime,
            'paymentRequestId' => $paymentId,
            'sourceBankCode' => '451',
        ];
        [$paymentBody] = $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $paymentPayload,
            $token,
            $paymentId
        ));
        $this->assertSame('2002500', $paymentBody['responseCode']);
        $this->assertSame('success', $payment->refresh()->status);
        $this->assertDatabaseCount('keuangan_pembayaran', 0);

        $advicePayload = [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'virtualAccountNo' => '    5090123456789012',
            'paidAmount' => ['value' => '350000.00', 'currency' => 'IDR'],
            'paymentRequestId' => $paymentId,
            'trxDateTime' => $trxDateTime,
            'sourceBankCode' => '451',
        ];
        [$adviceBody] = $service->advice($this->transactionRequest(
            '/api/bpi-bi-snap/advice',
            $advicePayload,
            $token,
            $paymentId
        ));
        $this->assertSame($paymentBody, $adviceBody);

        $reconId = '123456';
        $reconciledAt = '2026-08-09 09:30:00';
        $settlementCode = 'FT-EXAMPLE';
        $checksum = sha1(
            '123456789012'.
            'recon-secret'.
            $reconciledAt.
            '353000.00'.
            $reconId.
            $settlementCode
        );
        [$reconBody] = $service->reconciliation(Request::create(
            '/api/bpi-bi-snap/reconciliation',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'action' => 'recon',
                'kodeBankBI' => '451',
                'kodeBiller' => '5090',
                'allChecksum' => str_repeat('0', 40),
                'data' => [[
                    'idRekon' => $reconId,
                    'wktRekonsiliasi' => $reconciledAt,
                    'wktTransaksi' => '2026-08-08 10:00:00',
                    'nomorPembayaran' => '123456789012',
                    'nomorInvoice' => 'BSI-20260808-00000001',
                    'totalPembayaran' => '353000.00',
                    'totalSettlement' => '350000.00',
                    'nomorJurnalPembukuan' => 'JURNAL-BSI-BERBEDA',
                    'kodeFT' => $settlementCode,
                    'checksum' => $checksum,
                ]],
            ], JSON_UNESCAPED_SLASHES)
        ));

        $this->assertSame([['rc' => true, 'idRekon' => $reconId]], $reconBody);
        $this->assertSame('matched', BsiReconciliation::first()->match_status);
        $this->assertNull(BsiReconciliation::first()->mismatch_description);
    }

    public function test_interbank_payment_is_recorded_as_atm_lain(): void
    {
        $payment = KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-INTERBANK-1',
            'request_id' => 'SIAKAD-INTERBANK-1',
            'request_hash' => str_repeat('d', 64),
            'nim' => '20240099',
            'nama_mahasiswa' => 'Mahasiswa Antarbank',
            'jk_id' => 8,
            'jenis_pembayaran_id' => 1,
            'va_number' => '5090123456789099',
            'customer_no' => '123456789099',
            'bsi_payment_number' => '5090123456789099',
            'interbank_va_number' => '9005090123456789099',
            'reference_no' => 'BSI-INTERBANK-1',
            'total' => 100000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 3000,
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);

        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        $paymentId = 'PAY-INTERBANK-1';
        $payload = [
            'partnerServiceId' => '    5090',
            'customerNo' => '9005090123456789099',
            'virtualAccountNo' => '9005090123456789099',
            'virtualAccountName' => 'Mahasiswa Antarbank',
            'paidAmount' => ['value' => '100000.00', 'currency' => 'IDR'],
            'trxDateTime' => now()->startOfSecond()->toIso8601String(),
            'paymentRequestId' => $paymentId,
            'sourceBankCode' => '451',
        ];

        [$body] = $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $payload,
            $authBody['accessToken'],
            $paymentId,
            '6011'
        ));

        $this->assertSame('2002500', $body['responseCode']);
        $this->assertSame('atm_lain', $payment->refresh()->metodeVa?->kode);
    }

    public function test_production_reconciliation_matches_when_payment_and_settlement_are_equal(): void
    {
        BsiIntegrationSetting::query()->update(['environment' => 'production']);

        KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-20260812-00000001',
            'request_id' => 'SIAKAD-RECON-MISMATCH',
            'request_hash' => str_repeat('c', 64),
            'nim' => '20240002',
            'nama_mahasiswa' => 'Mahasiswa Rekonsiliasi',
            'jk_id' => 8,
            'jenis_pembayaran_id' => 1,
            'va_number' => '5090123456789014',
            'customer_no' => '123456789014',
            'bsi_payment_number' => '5090123456789014',
            'reference_no' => 'BSI-20260812-00000001',
            'total' => 350000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 3000,
            'production' => true,
            'status' => 'success',
            'expired_at' => now()->addDay(),
        ]);

        $reconId = '987654';
        $reconciledAt = '2026-08-12 10:00:00';
        $settlementCode = 'FT-PRODUCTION';
        $reportedAmount = '353000.00';
        $checksum = sha1(
            '123456789014'.
            'recon-secret'.
            $reconciledAt.
            $reportedAmount.
            $reconId.
            $settlementCode
        );
        $service = new BsiSnapService(new BsiSettingsService);

        [$response] = $service->reconciliation(Request::create(
            '/api/bpi-bi-snap/reconciliation',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'action' => 'recon',
                'kodeBankBI' => '451',
                'kodeBPI' => '5090',
                'allChecksum' => str_repeat('0', 40),
                'data' => [[
                    'idRekon' => $reconId,
                    'wktRekonsiliasi' => $reconciledAt,
                    'wktTransaksi' => '2026-08-12 09:00:00',
                    'nomorPembayaran' => '123456789014',
                    'totalPembayaran' => $reportedAmount,
                    'totalSettlement' => $reportedAmount,
                    'kodeFT' => $settlementCode,
                    'statusRekonsiliasi' => 'SUKSES',
                    'checksum' => $checksum,
                ]],
            ], JSON_UNESCAPED_SLASHES)
        ));

        $reconciliation = BsiReconciliation::firstOrFail();

        $this->assertSame([['rc' => true, 'idRekon' => $reconId]], $response);
        $this->assertSame('matched', $reconciliation->match_status);
        $this->assertNull($reconciliation->mismatch_description);
    }

    public function test_simulation_pending_order_can_be_cancelled_idempotently(): void
    {
        $payment = KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-20260808-00000002',
            'request_id' => 'SIM-BSI-1',
            'request_hash' => str_repeat('b', 64),
            'nim' => '20240001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'jk_id' => 8,
            'jenis_pembayaran_id' => 1,
            'va_number' => '5090123456789013',
            'total' => 100000,
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);
        $payment->details()->create([
            'tagihan_id' => 11,
            'tagihan_nama' => 'Daftar Ulang',
            'jumlah' => 100000,
            'urutan' => 1,
        ]);

        $service = new BsiPaymentOrderService(new BsiPaymentService, new BsiSettingsService);
        $cancelled = $service->cancel('SIM-BSI-1');
        $cancelledAgain = $service->cancel('SIM-BSI-1');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $cancelledAgain->status);
        $this->assertNotNull($payment->refresh()->cancelled_at);
    }

    public function test_inquiry_returns_already_paid_until_a_new_pending_order_exists(): void
    {
        $paid = $this->createSnapPayment('success', 'PAID-1');
        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        $token = $authBody['accessToken'];
        $payload = $this->inquiryPayload('INQ-PAID-1');

        $this->assertSnapException(
            fn () => $service->inquiry($this->transactionRequest(
                '/api/bpi-bi-snap/inquiry',
                $payload,
                $token,
                'INQ-PAID-1'
            )),
            '4042414',
            404,
            'Bill already paid'
        );

        $paid->update(['status' => 'posted']);
        $payload['inquiryRequestId'] = 'INQ-POSTED-1';
        $this->assertSnapException(
            fn () => $service->inquiry($this->transactionRequest(
                '/api/bpi-bi-snap/inquiry',
                $payload,
                $token,
                'INQ-POSTED-1'
            )),
            '4042414',
            404,
            'Bill already paid'
        );

        $this->createSnapPayment('pending', 'PENDING-NEW');
        $payload['inquiryRequestId'] = 'INQ-PENDING-NEW';
        [$body, $status] = $service->inquiry($this->transactionRequest(
            '/api/bpi-bi-snap/inquiry',
            $payload,
            $token,
            'INQ-PENDING-NEW'
        ));

        $this->assertSame(200, $status);
        $this->assertSame('2002400', $body['responseCode']);
    }

    public function test_payment_wrong_amount_keeps_order_pending_and_correct_amount_can_succeed(): void
    {
        BsiIntegrationSetting::firstOrFail()->update(['payment_mode' => 'close']);
        $payment = $this->createSnapPayment('pending', 'WRONG-AMOUNT');
        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        $token = $authBody['accessToken'];
        $payload = $this->paymentPayload('PAY-WRONG-AMOUNT', '349999.00');

        $this->assertSnapException(
            fn () => $service->payment($this->transactionRequest(
                '/api/bpi-bi-snap/payment',
                $payload,
                $token,
                'PAY-WRONG-AMOUNT'
            )),
            '4042513',
            404,
            'Payment Amount not valid'
        );
        $this->assertSame('pending', $payment->refresh()->status);

        $payload = $this->paymentPayload('PAY-CORRECT-AMOUNT', '350000.00');
        [$body, $status] = $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $payload,
            $token,
            'PAY-CORRECT-AMOUNT'
        ));

        $this->assertSame(200, $status);
        $this->assertSame('2002500', $body['responseCode']);
        $this->assertSame('success', $payment->refresh()->status);
    }

    public function test_open_payment_allocates_paid_amount_from_the_first_detail(): void
    {
        $payment = $this->createSnapPayment('pending', 'OPEN-PARTIAL');
        $payment->update(['total' => 1000000]);
        $payment->details()->firstOrFail()->update(['jumlah' => 900000, 'urutan' => 1]);
        $payment->details()->create([
            'tagihan_id' => 11,
            'tagihan_nama' => 'UAS',
            'jumlah' => 100000,
            'urutan' => 2,
        ]);

        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        [$body, $status] = $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $this->paymentPayload('PAY-OPEN-PARTIAL', '900000.00'),
            $authBody['accessToken'],
            'PAY-OPEN-PARTIAL'
        ));

        $this->assertSame(200, $status);
        $this->assertSame('2002500', $body['responseCode']);
        $this->assertSame(900000.0, (float) $payment->refresh()->total);
        $this->assertSame(
            [900000.0, 0.0],
            $payment->details()->orderBy('urutan')->get()->map(fn ($detail) => (float) $detail->jumlah)->all()
        );
    }

    public function test_open_payment_credits_overpayment_to_deposit_once(): void
    {
        $payment = $this->createSnapPayment('pending', 'OPEN-DEPOSIT');
        $payment->update([
            'total' => 1000000,
            'production' => true,
            'admin_fee_bearer' => 'institution',
            'admin_fee_amount' => 0,
        ]);
        $payment->details()->firstOrFail()->update(['jumlah' => 900000, 'urutan' => 1]);
        $payment->details()->create([
            'tagihan_id' => 11,
            'tagihan_nama' => 'UAS',
            'jumlah' => 100000,
            'urutan' => 2,
        ]);

        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        $payload = $this->paymentPayload('PAY-OPEN-DEPOSIT', '1200000.00');

        $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $payload,
            $authBody['accessToken'],
            'PAY-OPEN-DEPOSIT'
        ));
        $service->payment($this->transactionRequest(
            '/api/bpi-bi-snap/payment',
            $payload,
            $authBody['accessToken'],
            'PAY-OPEN-DEPOSIT'
        ));

        $this->assertSame(1200000.0, (float) $payment->refresh()->total);
        $this->assertDatabaseHas('keuangan_deposit', [
            'nim' => '20240001',
            'jumlah' => 200000,
        ]);
        $this->assertDatabaseCount('keuangan_deposit', 1);
    }

    public function test_controller_exposes_general_and_database_error_codes_for_sit(): void
    {
        $controller = app(BsiSnapController::class);
        $service = $this->mock(BsiSnapService::class);
        $service->shouldReceive('inquiry')->once()->andThrow(new RuntimeException('simulated'));
        $service->shouldReceive('payment')->once()->andThrow(new RuntimeException('simulated'));

        $inquiry = $controller->inquiry(
            Request::create('/api/bpi-bi-snap/inquiry', 'POST'),
            $service
        );
        $payment = $controller->payment(
            Request::create('/api/bpi-bi-snap/payment', 'POST'),
            $service
        );

        $this->assertSame(200, $inquiry->getStatusCode());
        $this->assertSame('5002400', $inquiry->getData(true)['responseCode']);
        $this->assertSame(200, $payment->getStatusCode());
        $this->assertSame('5002500', $payment->getData(true)['responseCode']);

        BsiIntegrationSetting::firstOrFail()->update(['database_failure_mode' => 'transactions']);
        $inquiryDb = $controller->inquiry(
            Request::create('/api/bpi-bi-snap/inquiry', 'POST'),
            $service
        );
        $paymentDb = $controller->payment(
            Request::create('/api/bpi-bi-snap/payment', 'POST'),
            $service
        );

        $this->assertSame('5002499', $inquiryDb->getData(true)['responseCode']);
        $this->assertSame(200, $inquiryDb->getStatusCode());
        $this->assertSame('5002599', $paymentDb->getData(true)['responseCode']);
        $this->assertSame(200, $paymentDb->getStatusCode());
    }

    public function test_controller_returns_transaction_rejections_as_http_200_for_smartbilling(): void
    {
        $controller = app(BsiSnapController::class);
        $service = $this->mock(BsiSnapService::class);
        $service->shouldReceive('inquiry')->once()->andThrow(
            new BsiSnapException('4042412', 404, 'Bill not found')
        );
        $service->shouldReceive('payment')->once()->andThrow(
            new BsiSnapException('4042513', 404, 'Payment Amount not valid')
        );
        $service->shouldReceive('advice')->once()->andThrow(
            new BsiSnapException('4042514', 404, 'Bill already paid')
        );

        foreach ([
            'inquiry' => '4042412',
            'payment' => '4042513',
            'advice' => '4042514',
        ] as $operation => $responseCode) {
            $response = $controller->{$operation}(
                Request::create("/api/bpi-bi-snap/{$operation}", 'POST'),
                $service
            );

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($responseCode, $response->getData(true)['responseCode']);
            $this->assertStringStartsWith(
                'application/json',
                (string) $response->headers->get('Content-Type')
            );
        }

        $logs = BsiSnapLog::query()->latest('id')->limit(3)->get();
        $this->assertCount(3, $logs);
        $this->assertTrue($logs->every(
            fn (BsiSnapLog $log) => $log->http_status === 200 && $log->outcome === 'rejected'
        ));
    }

    public function test_test_mode_is_exposed_without_restricting_students(): void
    {
        $settings = BsiIntegrationSetting::firstOrFail();
        $settings->update([
            'test_mode' => true,
        ]);

        $service = new BsiSettingsService;

        $publicData = $service->publicData($settings->refresh());

        $this->assertTrue($publicData['test_mode']);
        $this->assertArrayNotHasKey('test_nims', $publicData);
    }

    public function test_response_code_catalog_contains_every_code_from_specification_v35(): void
    {
        $catalog = BsiSnapService::RESPONSE_CODE_CATALOG;

        $this->assertCount(9, $catalog['auth']);
        $this->assertCount(13, $catalog['inquiry']);
        $this->assertCount(13, $catalog['payment']);
        $this->assertSame(
            [
                '2000000 / 2007300', '4007300', '4007302', '4017300', '4017301',
                '4047311', '4047312', '5007399', '5007300',
            ],
            array_column($catalog['auth'], 'code')
        );
        $this->assertContains('4042420', array_column($catalog['inquiry'], 'code'));
        $this->assertContains('4042513', array_column($catalog['payment'], 'code'));
        $this->assertContains('5042500 / 5042568', array_column($catalog['payment'], 'code'));
    }

    public function test_auth_uses_documented_error_responses(): void
    {
        $service = new BsiSnapService(new BsiSettingsService);

        $badRequest = Request::create('/api/bpi-bi-snap/auth', 'POST');
        $this->assertSnapException(
            fn () => $service->authenticate($badRequest),
            '4007300',
            400,
            'Bad Request'
        );

        $invalidClient = $this->authRequest();
        $invalidClient->headers->set('x-client-key', 'unknown-client');
        $this->assertSnapException(
            fn () => $service->authenticate($invalidClient),
            '4017300',
            401,
            'Unauthorized Client'
        );
        $this->assertFalse($invalidClient->attributes->get('bsi_client_key_matches'));

        $authResponse = app(BsiSnapController::class)->auth($invalidClient, $service);
        $this->assertSame(401, $authResponse->getStatusCode());
        $authLog = BsiSnapLog::latest('id')->firstOrFail();
        $this->assertFalse(data_get(
            $authLog->request_headers,
            'x-client-key-matches-configured'
        ));

        $invalidSignature = $this->authRequest();
        $invalidSignature->headers->set('x-signature', base64_encode('invalid'));
        $this->assertSnapException(
            fn () => $service->authenticate($invalidSignature),
            '4047311',
            404,
            'Unauthorized Signature'
        );
    }

    public function test_inquiry_uses_documented_header_field_token_and_not_found_codes(): void
    {
        $service = new BsiSnapService(new BsiSettingsService);
        [$authBody] = $service->authenticate($this->authRequest());
        $token = $authBody['accessToken'];
        $externalId = 'INQ-CODE-TEST';
        $payload = [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'trxDateInit' => now()->toIso8601String(),
            'virtualAccountNo' => '    5090123456789012',
            'inquiryRequestId' => $externalId,
            'sourceBankCode' => '451',
        ];

        $missingField = $payload;
        unset($missingField['sourceBankCode']);
        $this->assertSnapException(
            fn () => $service->inquiry($this->transactionRequest(
                '/api/bpi-bi-snap/inquiry',
                $missingField,
                $token,
                $externalId
            )),
            '4002402',
            400,
            'Field sourceBankCode is not exists'
        );

        $invalidPartner = $this->transactionRequest(
            '/api/bpi-bi-snap/inquiry',
            $payload,
            $token,
            $externalId
        );
        $invalidPartner->headers->set('x-partner-id', '9999');
        $this->assertSnapException(
            fn () => $service->inquiry($invalidPartner),
            '4002401',
            400,
            'Invalid Field Format'
        );

        $this->assertSnapException(
            fn () => $service->inquiry($this->transactionRequest(
                '/api/bpi-bi-snap/inquiry',
                $payload,
                'invalid-token',
                $externalId
            )),
            '4012401',
            401
        );

        $this->assertSnapException(
            fn () => $service->inquiry($this->transactionRequest(
                '/api/bpi-bi-snap/inquiry',
                $payload,
                $token,
                $externalId
            )),
            '4042412',
            404,
            'Bill not found'
        );
    }

    public function test_h2h_and_reconciliation_credentials_can_be_issued_securely(): void
    {
        $settings = BsiIntegrationSetting::firstOrFail();
        $service = new BsiSettingsService;

        $h2h = $service->rotateH2hCredentials($settings);
        $reconciliationSecret = $service->rotateReconciliationSecret($settings);
        $settings->refresh();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $h2h['client_id']
        );
        $this->assertSame(64, strlen($h2h['client_secret']));
        $this->assertSame(64, strlen($reconciliationSecret));
        $this->assertSame($h2h['client_id'], $settings->client_id);
        $this->assertSame($h2h['client_secret'], $settings->client_secret);
        $this->assertSame($reconciliationSecret, $settings->reconciliation_secret);
        $this->assertArrayNotHasKey('client_secret', $service->publicData($settings));
        $this->assertSame($h2h['client_secret'], $service->adminData($settings)['client_secret']);
        $this->assertSame(
            $reconciliationSecret,
            $service->adminData($settings)['reconciliation_secret']
        );
        $this->assertNotSame(
            $h2h['client_secret'],
            DB::table('bsi_integration_settings')->where('id', $settings->id)->value('client_secret')
        );
    }

    private function authRequest(): Request
    {
        $timestamp = now()->toIso8601String();
        openssl_sign('bsi-client|'.$timestamp, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return Request::create(
            '/api/bpi-bi-snap/auth',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CLIENT_KEY' => 'bsi-client',
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => base64_encode($signature),
            ],
            '{"grantType":"client_credentials"}'
        );
    }

    private function createSnapPayment(string $status, string $suffix): KeuanganPembayaranBsi
    {
        $payment = KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-'.$suffix,
            'request_id' => 'REQUEST-'.$suffix,
            'request_hash' => hash('sha256', $suffix),
            'nim' => '20240001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'jk_id' => 8,
            'jenis_pembayaran_id' => 1,
            'va_number' => '5090123456789012',
            'customer_no' => '123456789012',
            'bsi_payment_number' => '5090123456789012',
            'reference_no' => 'BSI-'.$suffix,
            'total' => 350000,
            'admin_fee_bearer' => 'payer',
            'admin_fee_amount' => 3000,
            'status' => $status,
            'expired_at' => now()->addDay(),
            'paid_at' => in_array($status, ['success', 'posted'], true) ? now() : null,
        ]);
        $payment->details()->create([
            'tagihan_id' => 10,
            'tagihan_nama' => 'UKT',
            'jumlah' => 350000,
            'urutan' => 1,
        ]);

        return $payment;
    }

    private function inquiryPayload(string $inquiryId): array
    {
        return [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'trxDateInit' => now()->toIso8601String(),
            'virtualAccountNo' => '    5090123456789012',
            'inquiryRequestId' => $inquiryId,
            'sourceBankCode' => '451',
        ];
    }

    private function paymentPayload(string $paymentId, string $amount): array
    {
        return [
            'partnerServiceId' => '    5090',
            'customerNo' => '123456789012',
            'virtualAccountNo' => '    5090123456789012',
            'virtualAccountName' => 'Mahasiswa Uji',
            'paidAmount' => ['value' => $amount, 'currency' => 'IDR'],
            'trxDateTime' => now()->startOfSecond()->toIso8601String(),
            'paymentRequestId' => $paymentId,
            'sourceBankCode' => '451',
        ];
    }

    private function assertSnapException(
        callable $callback,
        string $responseCode,
        int $httpStatus,
        ?string $message = null
    ): void {
        try {
            $callback();
            $this->fail('Expected BsiSnapException was not thrown.');
        } catch (BsiSnapException $exception) {
            $this->assertSame($responseCode, $exception->responseCode);
            $this->assertSame($httpStatus, $exception->httpStatus);
            if ($message !== null) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    private function transactionRequest(
        string $endpoint,
        array $payload,
        string $token,
        string $externalId,
        string $channelId = '6099'
    ): Request {
        $timestamp = now()->toIso8601String();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = BsiSnapService::generateTransactionSignature(
            'POST',
            $endpoint,
            $token,
            $body,
            $timestamp,
            'bsi-client-secret'
        );

        return Request::create(
            $endpoint,
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'HTTP_X_PARTNER_ID' => '5090',
                'HTTP_X_EXTERNAL_ID' => $externalId,
                'HTTP_ENDPOINT_URL' => $endpoint,
                'HTTP_CHANNEL_ID' => $channelId,
            ],
            $body
        );
    }

    private function createSchema(): void
    {
        Schema::create('bsi_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled');
            $table->string('environment');
            $table->boolean('test_mode')->default(false);
            $table->json('test_nims')->nullable();
            $table->string('institution_name')->nullable();
            $table->string('kode_bpi', 4)->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('bpi_public_key')->nullable();
            $table->text('reconciliation_secret')->nullable();
            $table->string('reconciliation_email')->nullable();
            $table->unsignedInteger('payment_expiry_minutes');
            $table->string('payment_mode', 10)->default('open');
            $table->string('admin_fee_bearer', 20)->default('institution');
            $table->decimal('admin_fee_amount', 15, 2)->default(2500);
            $table->decimal('sandbox_admin_fee_amount', 15, 2)->default(3000);
            $table->unsignedInteger('timestamp_tolerance');
            $table->json('allowed_ips')->nullable();
            $table->boolean('enforce_ip_allowlist');
            $table->boolean('verify_signatures')->default(true);
            $table->boolean('log_payloads')->default(true);
            $table->boolean('serve_test_va')->default(false);
            $table->string('database_failure_mode', 20)->default('none');
            $table->boolean('auto_transfer_enabled')->default(false);
            $table->string('siakad_api_key_hash')->nullable();
            $table->string('siakad_api_key_hint')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('keuangan_metode_va', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama')->unique();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        DB::table('keuangan_metode_va')->insert([
            ['kode' => 'byond_bsi', 'nama' => 'Byond BSI', 'aktif' => true],
            ['kode' => 'atm_bsi', 'nama' => 'ATM BSI', 'aktif' => true],
            ['kode' => 'atm_lain', 'nama' => 'ATM LAIN', 'aktif' => true],
        ]);

        Schema::create('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->string('request_id');
            $table->string('request_hash', 64);
            $table->string('nim');
            $table->string('nama_mahasiswa')->nullable();
            $table->unsignedBigInteger('jk_id');
            $table->unsignedBigInteger('jenis_pembayaran_id');
            $table->string('va_number');
            $table->string('customer_no')->nullable()->index();
            $table->string('bsi_payment_number')->nullable();
            $table->string('interbank_va_number')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('payment_request_id')->nullable()->unique();
            $table->string('payment_request_hash', 64)->nullable();
            $table->string('inquiry_request_id')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('source_bank_code')->nullable();
            $table->unsignedBigInteger('metode_va_id')->nullable();
            $table->dateTime('trx_date_time')->nullable();
            $table->string('reference_no')->nullable();
            $table->decimal('total', 15, 2);
            $table->string('admin_fee_bearer', 20)->default('institution');
            $table->decimal('admin_fee_amount', 15, 2)->default(2500);
            $table->string('status');
            $table->boolean('data_test')->default(false);
            $table->boolean('production')->default(false);
            $table->boolean('transferred')->default(false);
            $table->dateTime('expired_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('raw_callback')->nullable();
            $table->json('payment_response')->nullable();
            $table->string('reconciliation_status')->nullable();
            $table->timestamps();
        });

        Schema::create('keuangan_pembayaran_bsi_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_bsi_id');
            $table->unsignedBigInteger('tagihan_id')->nullable();
            $table->unsignedBigInteger('th_akademik_id')->nullable();
            $table->string('tagihan_nama');
            $table->decimal('jumlah', 15, 2);
            $table->unsignedInteger('urutan');
            $table->unsignedBigInteger('pembayaran_id')->nullable();
            $table->timestamps();
        });

        Schema::create('th_akademik', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('keuangan_deposit', function (Blueprint $table) {
            $table->id();
            $table->string('nim');
            $table->decimal('jumlah', 15, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('bsi_snap_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_bsi_id')->nullable();
            $table->string('operation', 30);
            $table->string('external_id')->nullable();
            $table->string('response_code', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 30);
            $table->boolean('signature_valid')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('source_ip', 64)->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bsi_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('recon_id')->unique();
            $table->unsignedBigInteger('pembayaran_bsi_id')->nullable();
            $table->string('journal_number')->nullable();
            $table->string('payment_number')->nullable();
            $table->dateTime('transaction_at')->nullable();
            $table->dateTime('reconciled_at')->nullable();
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->decimal('settlement_amount', 15, 2)->nullable();
            $table->string('settlement_code')->nullable();
            $table->string('bank_status')->nullable();
            $table->boolean('checksum_valid');
            $table->string('match_status');
            $table->text('mismatch_description')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }
}
