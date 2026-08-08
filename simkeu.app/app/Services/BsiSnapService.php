<?php

namespace App\Services;

use App\Exceptions\BsiSnapException;
use App\Models\BsiIntegrationSetting;
use App\Models\BsiReconciliation;
use App\Models\KeuanganPembayaranBsi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BsiSnapService
{
    private const CHANNEL_IDS = ['6011', '6014', '6017', '6027', '6099', '6199'];

    public const RESPONSE_CODE_CATALOG = [
        'auth' => [
            ['http_status' => 200, 'code' => '2000000 / 2007300', 'message' => 'Success', 'description' => 'Autentikasi berhasil.'],
            ['http_status' => 400, 'code' => '4007300', 'message' => 'Bad Request', 'description' => 'Header atau request wajib tidak tersedia.'],
            ['http_status' => 400, 'code' => '4007302', 'message' => 'Invalid Field Format', 'description' => 'Format field atau JSON tidak valid.'],
            ['http_status' => 401, 'code' => '4017300', 'message' => 'Unauthorized Client', 'description' => 'Client ID tidak dikenali.'],
            ['http_status' => 401, 'code' => '4017301', 'message' => 'Unauthorized stringToSign', 'description' => 'Timestamp/stringToSign tidak dapat diterima.'],
            ['http_status' => 404, 'code' => '4047311', 'message' => 'Unauthorized Signature', 'description' => 'RSA signature tidak valid.'],
            ['http_status' => 404, 'code' => '4047312', 'message' => 'Invalid Token', 'description' => 'Kode standar BSI untuk token Auth yang tidak valid.'],
            ['http_status' => 500, 'code' => '5007399', 'message' => 'DB Error', 'description' => 'Kegagalan database.'],
            ['http_status' => 504, 'code' => '5007300', 'message' => 'Timeout', 'description' => 'Batas waktu Auth terlampaui.'],
        ],
        'inquiry' => [
            ['http_status' => 200, 'code' => '2002400', 'message' => 'Success', 'description' => 'Inquiry berhasil.'],
            ['http_status' => 400, 'code' => '4002401', 'message' => 'Invalid Field Format', 'description' => 'Format field tidak valid.'],
            ['http_status' => 400, 'code' => '4002402', 'message' => 'Field {xyz} is not exists', 'description' => 'Field wajib tidak tersedia.'],
            ['http_status' => 401, 'code' => '4012400', 'message' => 'Unauthorized Access', 'description' => 'Header, signature, timestamp, atau akses tidak sah.'],
            ['http_status' => 401, 'code' => '4012401', 'message' => 'Invalid Token {accessToken}', 'description' => 'Access token tidak valid atau kedaluwarsa.'],
            ['http_status' => 404, 'code' => '4042411', 'message' => 'Invalid data', 'description' => 'VA tersedia tetapi belum dapat dibayar.'],
            ['http_status' => 404, 'code' => '4042412', 'message' => 'Bill not found', 'description' => 'Tagihan tidak ditemukan.'],
            ['http_status' => 404, 'code' => '4042414', 'message' => 'Bill already paid', 'description' => 'Tagihan sudah dibayar.'],
            ['http_status' => 404, 'code' => '4042419', 'message' => 'Invalid Bill number format / Bill Expired', 'description' => 'Format nomor pembayaran tidak valid.'],
            ['http_status' => 404, 'code' => '4042420', 'message' => 'Bill Expired', 'description' => 'Tagihan sudah kedaluwarsa.'],
            ['http_status' => 500, 'code' => '5002400', 'message' => 'General Error', 'description' => 'Kesalahan umum layanan Inquiry.'],
            ['http_status' => 500, 'code' => '5002499', 'message' => 'DB Error', 'description' => 'Kegagalan database.'],
            ['http_status' => 504, 'code' => '5042400 / 5042468', 'message' => 'Timeout', 'description' => 'Batas waktu Inquiry terlampaui.'],
        ],
        'payment' => [
            ['http_status' => 200, 'code' => '2002500', 'message' => 'Success', 'description' => 'Payment berhasil.'],
            ['http_status' => 400, 'code' => '4002501', 'message' => 'Invalid Field Format', 'description' => 'Format field tidak valid.'],
            ['http_status' => 400, 'code' => '4002502', 'message' => 'Field {xyz} is not exists', 'description' => 'Field wajib tidak tersedia.'],
            ['http_status' => 401, 'code' => '4012500', 'message' => 'Unauthorized Access', 'description' => 'Header, signature, timestamp, atau akses tidak sah.'],
            ['http_status' => 401, 'code' => '4012501', 'message' => 'Invalid Token {accessToken}', 'description' => 'Access token tidak valid atau kedaluwarsa.'],
            ['http_status' => 404, 'code' => '4042511', 'message' => 'Invalid data', 'description' => 'VA belum dapat dibayar atau payload duplikat berbeda.'],
            ['http_status' => 404, 'code' => '4042512', 'message' => 'Bill not found', 'description' => 'Tagihan tidak ditemukan.'],
            ['http_status' => 404, 'code' => '4042513', 'message' => 'Payment Amount not valid', 'description' => 'Nominal tidak sama dengan tagihan.'],
            ['http_status' => 404, 'code' => '4042514', 'message' => 'Bill already paid', 'description' => 'Tagihan sudah dibayar.'],
            ['http_status' => 404, 'code' => '4042519', 'message' => 'Invalid Bill number format', 'description' => 'Format nomor pembayaran tidak valid.'],
            ['http_status' => 500, 'code' => '5002500', 'message' => 'General Error', 'description' => 'Kesalahan umum layanan Payment/Advice.'],
            ['http_status' => 500, 'code' => '5002599', 'message' => 'DB Error', 'description' => 'Kegagalan database.'],
            ['http_status' => 504, 'code' => '5042500 / 5042568', 'message' => 'Timeout', 'description' => 'Batas waktu Payment terlampaui dan dana dapat ditangguhkan BSI.'],
        ],
    ];

    public function __construct(
        private readonly BsiSettingsService $settingsService,
        private readonly BsiPaymentService $paymentService
    ) {}

    public function authenticate(Request $request): array
    {
        $settings = $this->snapSettings('73');
        $this->assertSourceIp($request, $settings, '73');

        $clientId = (string) $request->header('x-client-key', '');
        $timestamp = (string) $request->header('x-timestamp', '');
        $signature = (string) $request->header('x-signature', '');
        $request->attributes->set(
            'bsi_signature_valid',
            $settings->verify_signatures && $signature === '' ? false : null
        );

        if ($clientId === '' || $timestamp === ''
            || ($settings->verify_signatures && $signature === '')
            || ! $this->isJsonRequest($request)) {
            throw new BsiSnapException('4007300', 400, 'Bad Request');
        }

        $payload = $this->jsonPayload($request, '73');
        if (($payload['grantType'] ?? null) !== 'client_credentials') {
            throw new BsiSnapException('4007302', 400, 'Invalid Field Format');
        }

        if (! hash_equals((string) $settings->client_id, $clientId)) {
            throw new BsiSnapException('4017300', 401, 'Unauthorized Client');
        }

        $this->assertTimestamp($timestamp, $settings, '73');

        if ($settings->verify_signatures) {
            $publicKey = openssl_pkey_get_public((string) $settings->bpi_public_key);
            $decodedSignature = base64_decode($signature, true);
            $verified = $publicKey !== false
                && $decodedSignature !== false
                && openssl_verify(
                    $clientId.'|'.$timestamp,
                    $decodedSignature,
                    $publicKey,
                    OPENSSL_ALGO_SHA256
                ) === 1;
            $request->attributes->set('bsi_signature_valid', $verified);

            if (! $verified) {
                throw new BsiSnapException('4047311', 404, 'Unauthorized Signature');
            }
        }

        $token = bin2hex(random_bytes(32));
        Cache::put($this->tokenCacheKey($token), [
            'client_id' => $clientId,
            'setting_id' => $settings->id,
        ], now()->addSeconds(900));

        return [[
            'responseCode' => '2000000',
            'responseMessage' => 'Auth Success',
            'accessToken' => $token,
            'tokenType' => 'BearerToken',
            'expiresIn' => '900',
        ], 200, null];
    }

    public function inquiry(Request $request): array
    {
        $settings = $this->snapSettings('24');
        $payload = $this->verifyTransactionRequest($request, $settings, '24');

        $this->requireFields($payload, [
            'partnerServiceId',
            'customerNo',
            'trxDateInit',
            'virtualAccountNo',
            'inquiryRequestId',
            'sourceBankCode',
        ], '24');

        $externalId = (string) $request->header('x-external-id', '');
        if ($externalId === '' || ! hash_equals($externalId, (string) $payload['inquiryRequestId'])) {
            throw new BsiSnapException('4002401', 400, 'Invalid Field Format');
        }

        $this->assertSourceBankCode($payload, '24');
        $this->parseSnapDate((string) $payload['trxDateInit'], '24');

        if (array_key_exists('amount', $payload)
            && (! is_array($payload['amount'])
                || ! isset($payload['amount']['value'], $payload['amount']['currency'])
                || ! is_numeric($payload['amount']['value'])
                || strtoupper((string) $payload['amount']['currency']) !== 'IDR')) {
            throw new BsiSnapException('4002401', 400, 'Invalid Field Format');
        }

        $customerNo = $this->normalizeCustomerNo((string) $payload['customerNo'], $settings, '24');
        $this->assertTestVirtualAccountAllowed($customerNo, $settings, '24');
        $payment = KeuanganPembayaranBsi::with('details')
            ->where('customer_no', $customerNo)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        if (! $payment) {
            throw new BsiSnapException('4042412', 404, 'Bill not found');
        }

        if ($payment->status === 'pending' && $payment->expired_at?->isPast()) {
            $payment->update(['status' => 'expired']);
        }

        if ($payment->status === 'expired') {
            throw new BsiSnapException('4042420', 404, 'Bill Expired');
        }

        if (in_array($payment->status, ['success', 'posted'], true)) {
            throw new BsiSnapException('4042414', 404, 'Bill already paid');
        }

        if ($payment->status !== 'pending') {
            throw new BsiSnapException('4042411', 404, 'Invalid data');
        }

        $this->assertPartnerAndVirtualAccount($payload, $settings, $customerNo, '24');
        $payment->update(['inquiry_request_id' => $payload['inquiryRequestId']]);

        return [[
            'responseCode' => '2002400',
            'responseMessage' => 'Successful',
            'virtualAccountData' => $this->virtualAccountData(
                $payment,
                $settings,
                'inquiryRequestId',
                (string) $payload['inquiryRequestId'],
                'totalAmount'
            ),
        ], 200, $payment];
    }

    public function payment(Request $request): array
    {
        $settings = $this->snapSettings('25');
        $payload = $this->verifyTransactionRequest($request, $settings, '25');

        $this->requireFields($payload, [
            'partnerServiceId',
            'customerNo',
            'trxDateTime',
            'paidAmount',
            'virtualAccountNo',
            'paymentRequestId',
            'sourceBankCode',
        ], '25');

        if (! is_array($payload['paidAmount'])
            || ! isset($payload['paidAmount']['value'], $payload['paidAmount']['currency'])) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $externalId = (string) $request->header('x-external-id', '');
        $paymentRequestId = (string) $payload['paymentRequestId'];
        if ($externalId === '' || ! hash_equals($externalId, $paymentRequestId)) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        if (strtoupper((string) $payload['paidAmount']['currency']) !== 'IDR'
            || ! is_numeric($payload['paidAmount']['value'])) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $this->assertSourceBankCode($payload, '25');
        $trxDateTime = $this->parseSnapDate((string) $payload['trxDateTime'], '25');

        $customerNo = $this->normalizeCustomerNo((string) $payload['customerNo'], $settings, '25');
        $this->assertTestVirtualAccountAllowed($customerNo, $settings, '25');

        $requestHash = hash('sha256', $this->minifiedBody($request));
        $duplicate = KeuanganPembayaranBsi::where('payment_request_id', $paymentRequestId)->first();
        if ($duplicate) {
            if (! hash_equals((string) $duplicate->payment_request_hash, $requestHash)) {
                throw new BsiSnapException('4042511', 404, 'Invalid data');
            }

            if (is_array($duplicate->payment_response)) {
                return [$duplicate->payment_response, 200, $duplicate];
            }
        }

        return DB::transaction(function () use (
            $customerNo,
            $payload,
            $paymentRequestId,
            $requestHash,
            $request,
            $settings,
            $trxDateTime
        ) {
            $payment = KeuanganPembayaranBsi::where('customer_no', $customerNo)
                ->with(['details.tahunAkademik', 'details.pembayaran'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new BsiSnapException('4042512', 404, 'Bill not found');
            }

            if ($payment->payment_request_id === $paymentRequestId
                && is_array($payment->payment_response)) {
                if (! hash_equals((string) $payment->payment_request_hash, $requestHash)) {
                    throw new BsiSnapException('4042511', 404, 'Invalid data');
                }

                return [$payment->payment_response, 200, $payment];
            }

            if ($payment->status === 'pending' && $payment->expired_at?->isPast()) {
                $payment->update(['status' => 'expired']);
            }

            if ($payment->status === 'expired') {
                throw new BsiSnapException('4042511', 404, 'Invalid data');
            }

            if (in_array($payment->status, ['success', 'posted'], true)) {
                throw new BsiSnapException('4042514', 404, 'Bill already paid');
            }

            if ($payment->status !== 'pending') {
                throw new BsiSnapException('4042511', 404, 'Invalid data');
            }

            $this->assertPartnerAndVirtualAccount($payload, $settings, $customerNo, '25');

            $paidAmount = round((float) $payload['paidAmount']['value'], 2);
            if (abs($paidAmount - (float) $payment->total) >= 0.01) {
                throw new BsiSnapException('4042513', 404, 'Payment Amount not valid');
            }

            $payment->update([
                'status' => 'paid',
                'payment_request_id' => $paymentRequestId,
                'payment_request_hash' => $requestHash,
                'channel_id' => $request->header('channel-id'),
                'source_bank_code' => $payload['sourceBankCode'],
                'trx_date_time' => $trxDateTime,
                'paid_at' => $trxDateTime,
                'bank_reference' => $paymentRequestId,
                'raw_callback' => $payload,
            ]);

            $posted = $this->paymentService->postPayment($payment, null);
            $posted->load('details');

            $response = [
                'responseCode' => '2002500',
                'responseMessage' => 'Successful',
                'virtualAccountData' => $this->virtualAccountData(
                    $posted,
                    $settings,
                    'paymentRequestId',
                    $paymentRequestId,
                    'paidAmount'
                ),
            ];

            $posted->update(['payment_response' => $response]);

            return [$response, 200, $posted->refresh()];
        });
    }

    public function advice(Request $request): array
    {
        $settings = $this->snapSettings('25');
        $payload = $this->verifyTransactionRequest($request, $settings, '25');
        $this->requireFields($payload, [
            'partnerServiceId',
            'customerNo',
            'trxDateTime',
            'paidAmount',
            'virtualAccountNo',
            'paymentRequestId',
            'sourceBankCode',
        ], '25');

        if (! is_array($payload['paidAmount'])
            || ! isset($payload['paidAmount']['value'], $payload['paidAmount']['currency'])
            || ! is_numeric($payload['paidAmount']['value'])
            || strtoupper((string) $payload['paidAmount']['currency']) !== 'IDR') {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $externalId = (string) $request->header('x-external-id', '');
        if ($externalId === '' || ! hash_equals($externalId, (string) $payload['paymentRequestId'])) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $this->assertSourceBankCode($payload, '25');

        $payment = KeuanganPembayaranBsi::where(
            'payment_request_id',
            (string) $payload['paymentRequestId']
        )->first();

        if (! $payment || ! is_array($payment->payment_response)) {
            throw new BsiSnapException('4042512', 404, 'Bill not found');
        }

        $customerNo = $this->normalizeCustomerNo((string) $payload['customerNo'], $settings, '25');
        $this->assertTestVirtualAccountAllowed($customerNo, $settings, '25');
        $this->assertPartnerAndVirtualAccount($payload, $settings, $customerNo, '25');
        $amountMatches = abs((float) $payload['paidAmount']['value'] - (float) $payment->total) < 0.01;

        $trxDateMatches = $this->parseSnapDate((string) $payload['trxDateTime'], '25')
            ->equalTo($payment->trx_date_time);

        if (! hash_equals((string) $payment->customer_no, $customerNo)
            || ! $amountMatches
            || ! $trxDateMatches) {
            throw new BsiSnapException('4042511', 404, 'Invalid data');
        }

        return [$payment->payment_response, 200, $payment];
    }

    public function reconciliation(Request $request): array
    {
        $settings = $this->snapSettings('25');
        $this->assertSourceIp($request, $settings, '25');
        if (! $this->isJsonRequest($request)) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $payload = $this->jsonPayload($request, '25');
        $this->requireFields($payload, ['action', 'kodeBankBI', 'kodeBPI', 'allChecksum', 'data'], '25');

        if (! in_array(strtolower((string) $payload['action']), ['recon', 'rekonsiliasi'], true)
            || trim((string) $payload['kodeBankBI']) !== '451'
            || trim((string) $payload['kodeBPI']) !== (string) $settings->kode_bpi
            || ! preg_match('/^[a-f0-9]{40}$/i', (string) $payload['allChecksum'])
            || ! is_array($payload['data'])) {
            throw new BsiSnapException('4002501', 400, 'Invalid Field Format');
        }

        $secret = (string) ($settings->reconciliation_secret ?: $settings->client_secret);
        $responses = [];

        foreach ($payload['data'] as $item) {
            if (! is_array($item)) {
                $responses[] = ['rc' => false, 'idRekon' => ''];

                continue;
            }

            $reconId = (string) ($item['idRekon'] ?? '');
            $requiredItemFields = [
                'idRekon',
                'wktRekonsiliasi',
                'wktTransaksi',
                'nomorPembayaran',
                'totalPembayaran',
                'kodeFT',
                'statusRekonsiliasi',
                'checksum',
            ];
            $missingItemField = collect($requiredItemFields)->contains(
                fn (string $field) => ! array_key_exists($field, $item)
                    || $item[$field] === ''
                    || $item[$field] === null
            );

            $itemFormatValid = $this->isReconciliationDate($item['wktRekonsiliasi'] ?? null)
                && $this->isReconciliationDate($item['wktTransaksi'] ?? null)
                && is_numeric($item['totalPembayaran'] ?? null)
                && (! array_key_exists('totalSettlement', $item) || is_numeric($item['totalSettlement']))
                && preg_match('/^[a-f0-9]{40}$/i', (string) ($item['checksum'] ?? ''));

            if ($missingItemField || $reconId === '' || ! $itemFormatValid) {
                $responses[] = ['rc' => false, 'idRekon' => $reconId];

                continue;
            }

            $expected = sha1(
                (string) ($item['nomorPembayaran'] ?? '').
                $secret.
                (string) ($item['wktRekonsiliasi'] ?? '').
                (string) ($item['totalPembayaran'] ?? '').
                $reconId.
                (string) ($item['kodeFT'] ?? '')
            );
            $checksumValid = $reconId !== ''
                && hash_equals(strtolower($expected), strtolower((string) ($item['checksum'] ?? '')));

            $payment = KeuanganPembayaranBsi::where(
                'payment_request_id',
                (string) ($item['nomorJurnalPembukuan'] ?? '')
            )->first();

            if (! $payment && filled($item['nomorPembayaran'] ?? null)) {
                $payment = KeuanganPembayaranBsi::where('customer_no', $item['nomorPembayaran'])
                    ->orWhere('bsi_payment_number', $item['nomorPembayaran'])
                    ->latest('id')
                    ->first();
            }

            $amountMatches = $payment
                && abs((float) $payment->total - (float) ($item['totalPembayaran'] ?? 0)) < 0.01;
            $statusMatches = $payment
                && in_array($payment->status, ['success', 'posted'], true)
                && strtoupper(trim((string) $item['statusRekonsiliasi'])) === 'SUKSES';
            $matchStatus = $checksumValid && $payment && $amountMatches && $statusMatches
                ? 'matched'
                : 'mismatch';

            BsiReconciliation::updateOrCreate(
                ['recon_id' => $reconId],
                [
                    'pembayaran_bsi_id' => $payment?->id,
                    'journal_number' => $item['nomorJurnalPembukuan'] ?? null,
                    'payment_number' => $item['nomorPembayaran'] ?? null,
                    'transaction_at' => $this->safeDate($item['wktTransaksi'] ?? null),
                    'reconciled_at' => $this->safeDate($item['wktRekonsiliasi'] ?? null),
                    'payment_amount' => $item['totalPembayaran'] ?? null,
                    'settlement_amount' => $item['totalSettlement'] ?? null,
                    'settlement_code' => $item['kodeFT'] ?? null,
                    'bank_status' => $item['statusRekonsiliasi'] ?? null,
                    'checksum_valid' => $checksumValid,
                    'match_status' => $matchStatus,
                    'payload' => $item,
                ]
            );

            if ($payment) {
                $payment->update(['reconciliation_status' => $matchStatus]);
            }

            $responses[] = ['rc' => $matchStatus === 'matched', 'idRekon' => $reconId];
        }

        return [$responses, 200, null];
    }

    public static function generateTransactionSignature(
        string $httpMethod,
        string $endpoint,
        string $accessToken,
        string $minifiedBody,
        string $timestamp,
        string $clientSecret
    ): string {
        $bodyHash = strtolower(hash('sha256', $minifiedBody));
        $stringToSign = strtoupper($httpMethod).':'.$endpoint.':'.$accessToken.':'.$bodyHash.':'.$timestamp;

        return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
    }

    public function minifiedBody(Request $request): string
    {
        $decoded = json_decode($request->getContent());

        return $decoded === null && trim($request->getContent()) !== 'null'
            ? ''
            : (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function verifyTransactionRequest(
        Request $request,
        BsiIntegrationSetting $settings,
        string $serviceCode
    ): array {
        $this->assertSourceIp($request, $settings, $serviceCode);

        $authorization = (string) $request->header('authorization', '');
        $timestamp = (string) $request->header('x-timestamp', '');
        $signature = (string) $request->header('x-signature', '');
        $endpoint = (string) $request->header('endpoint-url', '');
        $partnerId = (string) $request->header('x-partner-id', '');
        $channelId = (string) $request->header('channel-id', '');
        $externalId = (string) $request->header('x-external-id', '');
        $request->attributes->set(
            'bsi_signature_valid',
            $settings->verify_signatures && $signature === '' ? false : null
        );

        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
            || $timestamp === '' || ($settings->verify_signatures && $signature === '')
            || $endpoint === '') {
            throw new BsiSnapException('401'.$serviceCode.'00', 401, 'Unauthorized Access');
        }

        foreach ([
            'X-PARTNER-ID' => $partnerId,
            'CHANNEL-ID' => $channelId,
            'X-EXTERNAL-ID' => $externalId,
        ] as $header => $value) {
            if ($value === '') {
                throw new BsiSnapException(
                    '400'.$serviceCode.'02',
                    400,
                    'Field '.$header.' is not exists'
                );
            }
        }

        if (! $this->isJsonRequest($request)
            || trim($partnerId) !== (string) $settings->kode_bpi
            || ! in_array($channelId, self::CHANNEL_IDS, true)) {
            throw new BsiSnapException('400'.$serviceCode.'01', 400, 'Invalid Field Format');
        }

        $token = trim($matches[1]);
        $tokenData = Cache::get($this->tokenCacheKey($token));
        if (! is_array($tokenData)
            || (int) ($tokenData['setting_id'] ?? 0) !== (int) $settings->id
            || ! hash_equals((string) $settings->client_id, (string) ($tokenData['client_id'] ?? ''))) {
            throw new BsiSnapException('401'.$serviceCode.'01', 401, 'Invalid Token '.$token);
        }

        $this->assertTimestamp($timestamp, $settings, $serviceCode);

        if ('/'.$request->path() !== $endpoint) {
            throw new BsiSnapException('401'.$serviceCode.'00', 401, 'Unauthorized Access');
        }

        $minified = $this->minifiedBody($request);
        if ($minified === '') {
            throw new BsiSnapException('400'.$serviceCode.'01', 400, 'Invalid Field Format');
        }

        if ($settings->verify_signatures) {
            $expected = self::generateTransactionSignature(
                $request->method(),
                $endpoint,
                $token,
                $minified,
                $timestamp,
                (string) $settings->client_secret
            );
            $signatureValid = hash_equals($expected, trim($signature));
            $request->attributes->set('bsi_signature_valid', $signatureValid);

            if (! $signatureValid) {
                throw new BsiSnapException('401'.$serviceCode.'00', 401, 'Unauthorized Access');
            }
        }

        return $this->jsonPayload($request, $serviceCode);
    }

    private function virtualAccountData(
        KeuanganPembayaranBsi $payment,
        BsiIntegrationSetting $settings,
        string $requestIdKey,
        string $requestId,
        string $amountKey
    ): array {
        $customerNo = (string) $payment->customer_no;
        $partnerServiceId = str_pad((string) $settings->kode_bpi, 8, ' ', STR_PAD_LEFT);

        return [
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
            'virtualAccountNo' => $partnerServiceId.$customerNo,
            'virtualAccountName' => (string) $payment->nama_mahasiswa,
            $requestIdKey => $requestId,
            $amountKey => [
                'value' => number_format((float) $payment->total, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'billDetail' => $payment->details->map(fn ($detail) => [
                'label' => (string) $detail->tagihan_nama,
                'value' => number_format((float) $detail->jumlah, 2, '.', ''),
            ])->values()->all(),
            'referenceNo' => (string) ($payment->reference_no ?: $payment->nomor),
        ];
    }

    private function assertPartnerAndVirtualAccount(
        array $payload,
        BsiIntegrationSetting $settings,
        string $customerNo,
        string $serviceCode
    ): void {
        $partner = str_pad((string) $settings->kode_bpi, 8, ' ', STR_PAD_LEFT);

        if ((string) $payload['partnerServiceId'] !== $partner
            || (string) $payload['virtualAccountNo'] !== $partner.$customerNo) {
            throw new BsiSnapException('404'.$serviceCode.'19', 404, 'Invalid Bill number format');
        }
    }

    private function normalizeCustomerNo(
        string $customerNo,
        BsiIntegrationSetting $settings,
        string $serviceCode
    ): string {
        $customerNo = trim($customerNo);
        $kode = (string) $settings->kode_bpi;

        if (str_starts_with($customerNo, '900'.$kode)) {
            $customerNo = substr($customerNo, 7);
        } elseif (str_starts_with($customerNo, $kode) && strlen($customerNo) > 12) {
            $customerNo = substr($customerNo, 4);
        }

        if (! preg_match('/^\d{5,12}$/', $customerNo)) {
            throw new BsiSnapException('404'.$serviceCode.'19', 404, 'Invalid Bill number format');
        }

        return $customerNo;
    }

    private function snapSettings(string $serviceCode): BsiIntegrationSetting
    {
        $settings = $this->settingsService->settings();

        if (! $settings->enabled) {
            throw new BsiSnapException('500'.$serviceCode.'00', 503, 'Service Unavailable');
        }

        if (blank($settings->kode_bpi)
            || blank($settings->client_id)
            || blank($settings->client_secret)
            || ($settings->verify_signatures && blank($settings->bpi_public_key))) {
            throw new BsiSnapException('500'.$serviceCode.'99', 500, 'DB Error');
        }

        return $settings;
    }

    private function assertTestVirtualAccountAllowed(
        string $customerNo,
        BsiIntegrationSetting $settings,
        string $serviceCode
    ): void {
        if (str_starts_with($customerNo, '9999') && ! $settings->serve_test_va) {
            throw new BsiSnapException('404'.$serviceCode.'12', 404, 'Bill not found');
        }
    }

    private function assertSourceIp(
        Request $request,
        BsiIntegrationSetting $settings,
        string $serviceCode
    ): void {
        if (! $settings->enforce_ip_allowlist) {
            return;
        }

        $sourceIp = (string) ($request->header('cf-connecting-ip') ?: $request->ip());
        if (! in_array($sourceIp, $settings->allowed_ips ?: [], true)) {
            throw new BsiSnapException('401'.$serviceCode.'00', 401, 'Unauthorized Access');
        }
    }

    private function assertTimestamp(
        string $timestamp,
        BsiIntegrationSetting $settings,
        string $serviceCode
    ): void {
        if (! preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/',
            $timestamp
        )) {
            throw new BsiSnapException(
                $this->invalidFieldCode($serviceCode),
                400,
                'Invalid Field Format'
            );
        }

        try {
            $sentAt = Carbon::parse($timestamp);
        } catch (\Throwable) {
            throw new BsiSnapException(
                $this->invalidFieldCode($serviceCode),
                400,
                'Invalid Field Format'
            );
        }

        if (abs(now()->diffInSeconds($sentAt, false)) > (int) $settings->timestamp_tolerance) {
            throw new BsiSnapException(
                $serviceCode === '73' ? '4017301' : '401'.$serviceCode.'00',
                401,
                $serviceCode === '73' ? 'Unauthorized stringToSign' : 'Unauthorized Access'
            );
        }
    }

    private function jsonPayload(Request $request, string $serviceCode): array
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            throw new BsiSnapException(
                $this->invalidFieldCode($serviceCode),
                400,
                'Invalid Field Format'
            );
        }

        return $payload;
    }

    private function requireFields(array $payload, array $fields, string $serviceCode): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                throw new BsiSnapException(
                    $serviceCode === '73' ? '4007300' : '400'.$serviceCode.'02',
                    400,
                    'Field '.$field.' is not exists'
                );
            }
        }
    }

    private function tokenCacheKey(string $token): string
    {
        return 'bsi:snap:token:'.hash('sha256', $token);
    }

    private function assertSourceBankCode(array $payload, string $serviceCode): void
    {
        if ((string) ($payload['sourceBankCode'] ?? '') !== '451') {
            throw new BsiSnapException(
                '400'.$serviceCode.'01',
                400,
                'Invalid Field Format'
            );
        }
    }

    private function parseSnapDate(string $value, string $serviceCode): Carbon
    {
        if (! preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/',
            $value
        )) {
            throw new BsiSnapException(
                '400'.$serviceCode.'01',
                400,
                'Invalid Field Format'
            );
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new BsiSnapException(
                '400'.$serviceCode.'01',
                400,
                'Invalid Field Format'
            );
        }
    }

    private function isJsonRequest(Request $request): bool
    {
        return str_starts_with(
            strtolower((string) $request->header('content-type', '')),
            'application/json'
        );
    }

    private function isReconciliationDate(mixed $value): bool
    {
        if (! is_string($value)
            || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $value);

            return $date !== false && $date->format('Y-m-d H:i:s') === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function invalidFieldCode(string $serviceCode): string
    {
        return $serviceCode === '73' ? '4007302' : '400'.$serviceCode.'01';
    }

    private function safeDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
