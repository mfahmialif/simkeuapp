<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BsiSnapLog;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentOrderService;
use App\Services\BsiPaymentService;
use App\Services\BsiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BsiIntegrationSettingController extends Controller
{
    public function simulationBills(string $nim, BsiPaymentService $service): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $service->availableTagihan($nim),
        ]);
    }

    public function simulationStore(
        Request $request,
        BsiPaymentOrderService $orderService,
    ): JsonResponse {
        $validated = $request->validate([
            'request_id' => 'required|string|max:255',
            'nim' => 'required|string|max:255',
            'items' => 'required|array|min:1|max:100',
            'items.*.tagihan_id' => 'required|integer|distinct',
            'items.*.jumlah' => 'required|numeric|min:0.01',
        ]);

        [$payment, $created] = $orderService->create($validated);

        return response()->json([
            'status' => true,
            'created' => $created,
            'message' => $created
                ? 'Simulasi payment order BSI berhasil dibuat.'
                : 'Simulasi dengan request_id tersebut sudah ada.',
            'data' => $orderService->data($payment),
        ], $created ? 201 : 200);
    }

    public function simulationCancel(
        string $requestId,
        BsiPaymentOrderService $orderService,
    ): JsonResponse {
        $payment = $orderService->cancel($requestId);

        return response()->json([
            'status' => true,
            'message' => 'Payment order simulasi berhasil dibatalkan.',
            'data' => $orderService->data($payment),
        ]);
    }

    public function simulationPayments(Request $request): JsonResponse
    {
        BsiPaymentService::expirePending();
        $limit = max(1, min(100, (int) $request->input('limit', 20)));

        return response()->json([
            'status' => true,
            'data' => KeuanganPembayaranBsi::with('details')
                ->where('data_test', true)
                ->latest('id')
                ->paginate($limit),
        ]);
    }

    public function summary(): JsonResponse
    {
        $thirtyDaysAgo = now()->subDays(30);

        return response()->json([
            'status' => true,
            'data' => [
                'pending' => KeuanganPembayaranBsi::where('status', 'pending')->count(),
                'success_30_days' => KeuanganPembayaranBsi::where('status', 'success')
                    ->where('paid_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'success_amount_30_days' => (float) KeuanganPembayaranBsi::where('status', 'success')
                    ->where('paid_at', '>=', $thirtyDaysAgo)
                    ->sum('total'),
                'inquiry_count' => BsiSnapLog::where('operation', 'inquiry')->count(),
                'payment_count' => BsiSnapLog::where('operation', 'payment')->count(),
                'failed_count' => BsiSnapLog::whereIn('outcome', ['rejected', 'failed'])->count(),
                'test_transactions' => KeuanganPembayaranBsi::where('data_test', true)->count(),
            ],
        ]);
    }

    public function show(BsiSettingsService $service): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $service->publicData($service->settings()),
        ]);
    }

    public function update(Request $request, BsiSettingsService $service): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'test_mode' => 'required|boolean',
            'institution_name' => 'nullable|string|max:255',
            'kode_bpi' => 'nullable|digits:4',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|min:8|max:2000',
            'bpi_public_key' => 'nullable|string|max:10000',
            'reconciliation_secret' => 'nullable|string|min:8|max:2000',
            'reconciliation_email' => 'nullable|email:rfc|max:255',
            'payment_expiry_minutes' => 'required|integer|min:5|max:10080',
            'timestamp_tolerance' => 'required|integer|min:0|max:3600',
            'allowed_ips' => 'nullable|array|max:30',
            'allowed_ips.*' => 'required|ip',
            'enforce_ip_allowlist' => 'required|boolean',
        ]);

        if (filled($validated['bpi_public_key'] ?? null)
            && ! $service->validPublicKey($validated['bpi_public_key'])) {
            return response()->json([
                'status' => false,
                'message' => 'Public key BPI bukan RSA public key yang valid.',
                'errors' => ['bpi_public_key' => ['Public key BPI tidak valid.']],
            ], 422);
        }

        $settings = $service->settings();

        foreach (['client_secret', 'reconciliation_secret'] as $secret) {
            if (blank($validated[$secret] ?? null)) {
                unset($validated[$secret]);
            }
        }

        $settings->update([
            ...$validated,
            'allowed_ips' => $validated['allowed_ips'] ?? BsiSettingsService::DEFAULT_ALLOWED_IPS,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Konfigurasi BSI berhasil disimpan.',
            'data' => $service->publicData($settings->refresh()),
        ]);
    }

    public function rotateSiakadKey(Request $request, BsiSettingsService $service): JsonResponse
    {
        $settings = $service->settings();
        $plain = $service->rotateSiakadKey($settings, $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'API key SIAKAD berhasil dibuat. Salin sekarang karena tidak akan ditampilkan lagi.',
            'data' => [
                'api_key' => $plain,
                'hint' => substr($plain, -8),
            ],
        ]);
    }

    public function rotateH2hCredentials(
        Request $request,
        BsiSettingsService $service
    ): JsonResponse {
        $settings = $service->settings();
        $credentials = $service->rotateH2hCredentials($settings, $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'Client ID dan Client Secret baru berhasil diterbitkan. Salin keduanya ke portal BSI sekarang.',
            'data' => $credentials,
        ]);
    }

    public function rotateReconciliationSecret(
        Request $request,
        BsiSettingsService $service
    ): JsonResponse {
        $settings = $service->settings();
        $secret = $service->rotateReconciliationSecret($settings, $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'Secret rekonsiliasi baru berhasil diterbitkan. Salin ke portal BSI sekarang.',
            'data' => ['reconciliation_secret' => $secret],
        ]);
    }

    public function validateConfiguration(BsiSettingsService $service): JsonResponse
    {
        $settings = $service->settings();
        $readiness = $service->readiness($settings);

        if (filled($settings->bpi_public_key)) {
            $readiness['bpi_public_key_valid'] = $service->validPublicKey($settings->bpi_public_key);
        } else {
            $readiness['bpi_public_key_valid'] = false;
        }

        return response()->json([
            'status' => (bool) ($readiness['ready'] && $readiness['bpi_public_key_valid']),
            'message' => $readiness['ready'] && $readiness['bpi_public_key_valid']
                ? 'Konfigurasi BSI lengkap dan valid.'
                : 'Konfigurasi BSI belum lengkap atau public key tidak valid.',
            'data' => $readiness,
        ], $readiness['ready'] && $readiness['bpi_public_key_valid'] ? 200 : 422);
    }
}
