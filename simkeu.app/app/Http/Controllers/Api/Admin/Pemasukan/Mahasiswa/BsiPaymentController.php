<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

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
                'jenisPembayaran',
                'postedBy',
                'rejectedBy',
            ]),
        ]);
    }

    public function post(
        Request $request,
        KeuanganPembayaranBsi $paymentBsi,
        BsiPaymentService $service
    ): JsonResponse {
        $validated = $request->validate([
            'confirm_review' => 'nullable|boolean',
        ]);

        $payment = $service->postPayment(
            $paymentBsi,
            (int) $request->user()->id,
            (bool) ($validated['confirm_review'] ?? false)
        );

        return response()->json([
            'status' => true,
            'message' => 'Pembayaran BSI berhasil diposting ke transaksi mahasiswa.',
            'data' => $payment,
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
}
