<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\BsiReconciliation;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentService;
use App\Services\BsiPaymentTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BsiPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        BsiPaymentService::expirePending();

        $query = KeuanganPembayaranBsi::with([
            'details',
            'jenisPembayaran',
            'postedBy',
            'rejectedBy',
        ])->where(function ($query) {
            $query->where('data_test', false)->orWhereNull('data_test');
        });

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($query) use ($search) {
                $query->where('nomor', 'like', "%$search%")
                    ->orWhere('request_id', 'like', "%$search%")
                    ->orWhere('nim', 'like', "%$search%")
                    ->orWhere('nama_mahasiswa', 'like', "%$search%")
                    ->orWhere('va_number', 'like', "%$search%")
                    ->orWhere('bank_reference', 'like', "%$search%");
            });
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('created_at', '<=', $request->tanggal_akhir);
        }

        $limit = max(1, min(100, (int) $request->input('limit', 10)));

        return response()->json([
            'status' => true,
            'data' => $query->latest('id')->paginate($limit),
        ]);
    }

    public function show(KeuanganPembayaranBsi $paymentBsi): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $paymentBsi->load([
                'details.tahunAkademik',
                'details.pembayaran',
                'callbacks',
                'snapLogs',
                'reconciliations',
                'jenisPembayaran',
                'postedBy',
                'rejectedBy',
            ]),
        ]);
    }

    public function reconciliationStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => 'nullable|date',
            'tanggal_akhir' => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);

        $query = BsiReconciliation::query()
            ->when(
                $validated['tanggal_mulai'] ?? null,
                fn ($query, $date) => $query->whereDate('reconciled_at', '>=', $date)
            )
            ->when(
                $validated['tanggal_akhir'] ?? null,
                fn ($query, $date) => $query->whereDate('reconciled_at', '<=', $date)
            );

        $total = (clone $query);
        $matched = (clone $query)->where('match_status', 'matched');
        $mismatch = (clone $query)->where('match_status', '!=', 'matched');

        return response()->json([
            'status' => true,
            'data' => [
                'total' => [
                    'count' => $total->count(),
                    'amount' => (float) (clone $query)->sum('payment_amount'),
                ],
                'matched' => [
                    'count' => $matched->count(),
                    'amount' => (float) (clone $query)->where('match_status', 'matched')->sum('payment_amount'),
                ],
                'mismatch' => [
                    'count' => $mismatch->count(),
                    'amount' => (float) (clone $query)->where('match_status', '!=', 'matched')->sum('payment_amount'),
                ],
            ],
        ]);
    }

    public function synchronizationCandidates(
        Request $request,
        BsiPaymentTransferService $transferService
    ): JsonResponse {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_akhir' => 'nullable|date|after_or_equal:tanggal_mulai',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->applySynchronizationFilters(
            $transferService->eligibleQuery()->with('details'),
            $validated
        );
        $limit = (int) ($validated['limit'] ?? 10);

        return response()->json([
            'status' => true,
            'data' => $query->latest('id')->paginate($limit),
        ]);
    }

    public function synchronize(
        Request $request,
        BsiPaymentTransferService $transferService
    ): JsonResponse {
        $validated = $request->validate([
            'mode' => 'required|in:ids,all',
            'ids' => 'nullable|array|max:1000',
            'ids.*' => 'required|integer|distinct',
            'excluded_ids' => 'nullable|array|max:1000',
            'excluded_ids.*' => 'required|integer|distinct',
            'filters' => 'nullable|array',
            'filters.search' => 'nullable|string|max:255',
            'filters.tanggal_mulai' => 'nullable|date',
            'filters.tanggal_akhir' => 'nullable|date|after_or_equal:filters.tanggal_mulai',
        ]);

        if ($validated['mode'] === 'ids' && empty($validated['ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Pilih minimal satu transaksi untuk disinkronkan.',
            ], 422);
        }

        if ($validated['mode'] === 'all') {
            $query = $this->applySynchronizationFilters(
                $transferService->eligibleQuery(),
                $validated['filters'] ?? []
            );

            if (! empty($validated['excluded_ids'])) {
                $query->whereNotIn('id', $validated['excluded_ids']);
            }

            $ids = $query->orderBy('id')->pluck('id')->all();
        } else {
            $ids = $validated['ids'];
        }

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $payment = KeuanganPembayaranBsi::findOrFail($id);
                $transferService->transfer($payment, (int) $request->user()->id);
                $success++;
                $results[] = ['id' => (int) $id, 'status' => 'success'];
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $results[] = [
                    'id' => (int) $id,
                    'status' => 'failed',
                    'message' => $this->transferErrorMessage($exception),
                ];
            }
        }

        return response()->json([
            'status' => $failed === 0,
            'message' => $failed === 0
                ? "$success transaksi berhasil disinkronkan."
                : "$success transaksi berhasil dan $failed transaksi gagal disinkronkan.",
            'data' => [
                'success_count' => $success,
                'failed_count' => $failed,
                'results' => $results,
            ],
        ]);
    }

    public function reject(
        Request $request,
        KeuanganPembayaranBsi $paymentBsi,
        BsiPaymentService $service
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $payment = $service->rejectPayment(
            $paymentBsi,
            (int) $request->user()->id,
            $validated['reason']
        );

        return response()->json([
            'status' => true,
            'message' => 'Pembayaran BSI berhasil ditolak.',
            'data' => $payment,
        ]);
    }

    private function applySynchronizationFilters($query, array $filters)
    {
        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($query) use ($search) {
                $query->where('nomor', 'like', "%$search%")
                    ->orWhere('request_id', 'like', "%$search%")
                    ->orWhere('nim', 'like', "%$search%")
                    ->orWhere('nama_mahasiswa', 'like', "%$search%")
                    ->orWhere('va_number', 'like', "%$search%")
                    ->orWhere('bank_reference', 'like', "%$search%");
            });
        }

        if (filled($filters['tanggal_mulai'] ?? null)) {
            $query->whereDate('paid_at', '>=', $filters['tanggal_mulai']);
        }

        if (filled($filters['tanggal_akhir'] ?? null)) {
            $query->whereDate('paid_at', '<=', $filters['tanggal_akhir']);
        }

        return $query;
    }

    private function transferErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        return 'Sinkronisasi gagal. Periksa log aplikasi untuk detail teknis.';
    }
}
