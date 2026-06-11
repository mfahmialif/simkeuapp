<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BsiPaymentController extends Controller
{
    public function tagihan(Request $request, BsiPaymentService $service, ?string $nim = null): JsonResponse
    {
        $nim = $nim ?: (string) $request->query('nim', '');

        if (trim($nim) === '') {
            return response()->json([
                'status' => false,
                'message' => 'Parameter nim wajib diisi.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $service->availableTagihan($nim),
        ]);
    }

    public function store(Request $request, BsiPaymentService $service): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => 'required|string|max:255',
            'nim' => 'required|string|max:255',
            'va_number' => 'required|string|max:255',
            'expired_at' => 'required|date|after:now',
            'items' => 'required|array|min:1',
            'items.*.tagihan_id' => 'required|integer|distinct',
            'items.*.jumlah' => 'required|numeric|min:0.01',
        ]);

        [$payment, $created] = $service->createPending($validated);

        return response()->json([
            'status' => true,
            'created' => $created,
            'message' => $created
                ? 'Transaksi VA BSI berhasil dibuat.'
                : 'Transaksi VA BSI sudah pernah dibuat.',
            'data' => $this->paymentData($payment),
        ], $created ? 201 : 200);
    }

    public function show(string $requestId): JsonResponse
    {
        BsiPaymentService::expirePending();

        $payment = KeuanganPembayaranBsi::with('details')
            ->where('request_id', $requestId)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $this->paymentData($payment),
        ]);
    }

    public function callback(Request $request, BsiPaymentService $service): JsonResponse
    {
        $callback = $service->processCallback($request->all());

        return response()->json([
            'status' => true,
            'message' => $callback->message,
            'data' => [
                'callback_id' => $callback->callback_id,
                'request_id' => $callback->request_id,
                'process_status' => $callback->process_status,
                'processed_at' => $callback->processed_at,
            ],
        ]);
    }

    private function paymentData(KeuanganPembayaranBsi $payment): array
    {
        return [
            'request_id' => $payment->request_id,
            'nomor' => $payment->nomor,
            'nim' => $payment->nim,
            'nama_mahasiswa' => $payment->nama_mahasiswa,
            'va_number' => $payment->va_number,
            'bank_reference' => $payment->bank_reference,
            'total' => $payment->total,
            'status' => $payment->status,
            'expired_at' => $payment->expired_at,
            'paid_at' => $payment->paid_at,
            'posted_at' => $payment->posted_at,
            'details' => $payment->details->map(fn ($detail) => [
                'tagihan_id' => $detail->tagihan_id,
                'tagihan_nama' => $detail->tagihan_nama,
                'jumlah' => $detail->jumlah,
                'cara_bayar' => $detail->cara_bayar,
            ])->values(),
        ];
    }
}
