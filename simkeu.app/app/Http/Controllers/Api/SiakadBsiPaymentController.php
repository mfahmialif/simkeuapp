<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentOrderService;
use App\Services\BsiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiakadBsiPaymentController extends Controller
{
    public function bills(string $nim, BsiPaymentService $service): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $service->availableTagihan($nim),
        ]);
    }

    public function store(
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
                ? 'Payment order BSI berhasil dibuat.'
                : 'Payment order dengan request_id tersebut sudah ada.',
            'data' => $orderService->data($payment),
        ], $created ? 201 : 200);
    }

    public function show(string $requestId, BsiPaymentOrderService $orderService): JsonResponse
    {
        BsiPaymentService::expirePending();

        $payment = KeuanganPembayaranBsi::with('details')
            ->where('request_id', $requestId)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $orderService->data($payment),
        ]);
    }

    public function cancel(string $requestId, BsiPaymentOrderService $orderService): JsonResponse
    {
        $payment = $orderService->cancel($requestId);

        return response()->json([
            'status' => true,
            'message' => 'Payment order BSI berhasil dibatalkan.',
            'data' => $orderService->data($payment),
        ]);
    }
}
