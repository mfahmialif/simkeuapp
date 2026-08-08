<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BsiSnapException;
use App\Http\Controllers\Controller;
use App\Models\BsiSnapLog;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiSettingsService;
use App\Services\BsiSnapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BsiSnapController extends Controller
{
    public function __construct(private readonly BsiSettingsService $settingsService) {}

    public function auth(Request $request, BsiSnapService $service): JsonResponse
    {
        return $this->dispatch(
            'auth',
            $request,
            fn () => $service->authenticate($request),
            '5007300',
            '5007399',
            504,
            'Timeout'
        );
    }

    public function inquiry(Request $request, BsiSnapService $service): JsonResponse
    {
        return $this->dispatch(
            'inquiry',
            $request,
            fn () => $service->inquiry($request),
            '5002400',
            '5002499'
        );
    }

    public function payment(Request $request, BsiSnapService $service): JsonResponse
    {
        return $this->dispatch(
            'payment',
            $request,
            fn () => $service->payment($request),
            '5002500',
            '5002599'
        );
    }

    public function advice(Request $request, BsiSnapService $service): JsonResponse
    {
        return $this->dispatch(
            'advice',
            $request,
            fn () => $service->advice($request),
            '5002500',
            '5002599'
        );
    }

    public function reconciliation(Request $request, BsiSnapService $service): JsonResponse
    {
        return $this->dispatch(
            'reconciliation',
            $request,
            fn () => $service->reconciliation($request),
            '5002500',
            '5002599'
        );
    }

    private function dispatch(
        string $operation,
        Request $request,
        callable $handler,
        string $generalErrorCode,
        string $databaseErrorCode,
        int $generalHttpStatus = 500,
        string $generalMessage = 'General Error'
    ): JsonResponse {
        $startedAt = microtime(true);
        $payment = null;

        try {
            if ($this->simulateDatabaseFailure($operation)) {
                $body = [
                    'responseCode' => $databaseErrorCode,
                    'responseMessage' => 'DB Error',
                ];
                $this->writeLog($operation, $request, $body, 500, 'failed', $startedAt, null);

                return response()->json($body, 500, [], JSON_UNESCAPED_SLASHES);
            }

            [$body, $httpStatus, $payment] = $handler();
            $this->writeLog($operation, $request, $body, $httpStatus, 'success', $startedAt, $payment);

            return response()->json($body, $httpStatus, [], JSON_UNESCAPED_SLASHES);
        } catch (BsiSnapException $exception) {
            $body = $exception->responseBody();
            $this->writeLog(
                $operation,
                $request,
                $body,
                $exception->httpStatus,
                'rejected',
                $startedAt,
                $payment
            );

            return response()->json($body, $exception->httpStatus, [], JSON_UNESCAPED_SLASHES);
        } catch (QueryException $exception) {
            report($exception);
            $body = [
                'responseCode' => $databaseErrorCode,
                'responseMessage' => 'DB Error',
            ];
            $this->writeLog($operation, $request, $body, 500, 'failed', $startedAt, $payment);

            return response()->json($body, 500, [], JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            report($exception);
            $body = [
                'responseCode' => $generalErrorCode,
                'responseMessage' => $generalMessage,
            ];
            $this->writeLog(
                $operation,
                $request,
                $body,
                $generalHttpStatus,
                'failed',
                $startedAt,
                $payment
            );

            return response()->json($body, $generalHttpStatus, [], JSON_UNESCAPED_SLASHES);
        }
    }

    private function writeLog(
        string $operation,
        Request $request,
        array $response,
        int $httpStatus,
        string $outcome,
        float $startedAt,
        ?KeuanganPembayaranBsi $payment
    ): void {
        try {
            $payload = json_decode($request->getContent(), true);
            $logPayloads = (bool) $this->settingsService->settings()->log_payloads;
            BsiSnapLog::create([
                'pembayaran_bsi_id' => $payment?->id,
                'operation' => $operation,
                'external_id' => $request->header('x-external-id')
                    ?: data_get($payload, 'paymentRequestId')
                    ?: data_get($payload, 'inquiryRequestId'),
                'response_code' => is_string($response['responseCode'] ?? null)
                    ? $response['responseCode']
                    : null,
                'http_status' => $httpStatus,
                'outcome' => $outcome,
                'signature_valid' => $request->attributes->get('bsi_signature_valid'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'source_ip' => $request->header('cf-connecting-ip') ?: $request->ip(),
                'request_headers' => [
                    'x-timestamp' => $request->header('x-timestamp'),
                    'x-client-key' => $this->mask($request->header('x-client-key')),
                    'x-partner-id' => $request->header('x-partner-id'),
                    'channel-id' => $request->header('channel-id'),
                    'x-external-id' => $request->header('x-external-id'),
                    'endpoint-url' => $request->header('endpoint-url'),
                    'authorization' => $request->header('authorization') ? 'Bearer ***' : null,
                    'x-signature' => $request->header('x-signature') ? '***' : null,
                ],
                'request_payload' => $logPayloads && is_array($payload) ? $payload : null,
                'response_payload' => $logPayloads ? $this->redactResponse($response) : null,
                'requested_at' => now(),
            ]);
        } catch (Throwable $logException) {
            report($logException);
        }
    }

    private function simulateDatabaseFailure(string $operation): bool
    {
        $settings = $this->settingsService->settings();
        if (! $settings->enabled) {
            return false;
        }

        $mode = (string) ($settings->database_failure_mode ?: 'none');

        return $mode === 'all'
            || ($mode === 'transactions'
                && in_array($operation, ['inquiry', 'payment', 'advice', 'reconciliation'], true));
    }

    private function redactResponse(array $response): array
    {
        if (isset($response['accessToken'])) {
            $response['accessToken'] = '***';
        }

        return $response;
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strlen($value) <= 8
            ? '***'
            : substr($value, 0, 4).'***'.substr($value, -4);
    }
}
