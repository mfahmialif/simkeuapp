<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeuanganMetodeVa;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentOrderService;
use App\Services\BsiPaymentService;
use App\Services\SiakadPaymentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiakadBsiPaymentController extends Controller
{
    private const CREATE_ORDER_FIELDS = ['request_id', 'nim', 'items', 'metode_va_id'];

    private const CREATE_ORDER_ITEM_FIELDS = ['tagihan_id', 'jumlah'];

    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => KeuanganMetodeVa::query()
                ->where('aktif', true)
                ->orderBy('id')
                ->get(['id', 'kode', 'nama', 'keterangan'])
                ->values(),
        ]);
    }

    public function bills(string $nim, BsiPaymentService $service): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $service->availableTagihan($nim),
        ]);
    }

    public function paymentHistory(
        string $nim,
        SiakadPaymentHistoryService $service,
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'data' => $service->forStudent($nim),
        ]);
    }

    public function store(
        Request $request,
        BsiPaymentOrderService $orderService,
    ): JsonResponse {
        $this->assertSupportedCreateOrderFields($request);

        $validated = $request->validate([
            'request_id' => 'required|string|max:255',
            'nim' => 'required|string|max:255',
            'metode_va_id' => [
                'nullable',
                'integer',
                Rule::exists('keuangan_metode_va', 'id')
                    ->where(fn ($query) => $query->where('aktif', true)),
            ],
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

    private function assertSupportedCreateOrderFields(Request $request): void
    {
        $unexpectedFields = array_values(array_diff(
            array_keys($request->all()),
            self::CREATE_ORDER_FIELDS
        ));
        $unexpectedItemFields = collect($request->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->flatMap(fn (array $item) => array_diff(
                array_keys($item),
                self::CREATE_ORDER_ITEM_FIELDS
            ))
            ->unique()
            ->values()
            ->all();

        if ($unexpectedFields === [] && $unexpectedItemFields === []) {
            return;
        }

        $messages = [];
        if ($unexpectedFields !== []) {
            $messages[] = 'Field body tidak didukung: '.implode(', ', $unexpectedFields).'.';
        }
        if ($unexpectedItemFields !== []) {
            $messages[] = 'Field item tidak didukung: '.implode(', ', $unexpectedItemFields).'.';
        }

        throw ValidationException::withMessages([
            'body' => implode(' ', $messages).
                ' Konfigurasi environment, data test, mode pembayaran, expiry, dan biaya admin dikelola oleh SIMKEU.',
        ]);
    }

    public function updatePaymentMethod(
        string $requestId,
        Request $request,
        BsiPaymentOrderService $orderService,
    ): JsonResponse {
        $unexpectedFields = array_values(array_diff(
            array_keys($request->all()),
            ['metode_va_id']
        ));

        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages([
                'body' => 'Field body tidak didukung: '.implode(', ', $unexpectedFields).'.',
            ]);
        }

        $validated = $request->validate([
            'metode_va_id' => [
                'required',
                'integer',
                Rule::exists('keuangan_metode_va', 'id')
                    ->where(fn ($query) => $query->where('aktif', true)),
            ],
        ]);

        $payment = $orderService->updatePaymentMethod(
            $requestId,
            (int) $validated['metode_va_id']
        );

        return response()->json([
            'status' => true,
            'message' => 'Metode pembayaran payment order berhasil diperbarui.',
            'data' => $orderService->data($payment),
        ]);
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
