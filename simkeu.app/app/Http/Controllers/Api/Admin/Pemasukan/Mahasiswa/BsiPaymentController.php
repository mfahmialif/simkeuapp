<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\BsiReconciliation;
use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganMetodeVa;
use App\Models\KeuanganPembayaranBsi;
use App\Models\KeuanganTagihan;
use App\Services\BsiPaymentService;
use App\Services\BsiPaymentTransferService;
use App\Services\BsiSnapService;
use App\Services\Mahasiswa;
use App\Services\TagihanMahasiswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BsiPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        BsiPaymentService::expirePending();

        $query = KeuanganPembayaranBsi::with([
            'details',
            'jenisPembayaran',
            'metodeVa',
            'postedBy',
            'rejectedBy',
            'reconciliations',
        ])->where(function ($query) {
            $query->where('data_test', false)->orWhereNull('data_test');
        });

        if ($request->filled('status') && trim($request->status) !== 'all') {
            $query->where('status', trim($request->status));
        }

        // Filter Sinkronisasi (transferred)
        $syncFilter = $request->input('transferred', $request->input('sinkronisasi'));
        if ($syncFilter !== null && $syncFilter !== '' && $syncFilter !== 'all') {
            if (in_array($syncFilter, ['1', 1, true, 'true', 'transferred', 'sudah'], true)) {
                $query->where('transferred', true);
            } elseif (in_array($syncFilter, ['0', 0, false, 'false', 'untransferred', 'belum'], true)) {
                $query->where(function ($q) {
                    $q->where('transferred', false)->orWhereNull('transferred');
                });
            }
        }

        // Filter Rekonsiliasi
        $reconFilter = $request->input('reconciliation_status', $request->input('rekonsiliasi'));
        if ($reconFilter !== null && $reconFilter !== '' && $reconFilter !== 'all') {
            if ($reconFilter === 'matched') {
                $query->where(function ($q) {
                    $q->where('reconciliation_status', 'matched')
                        ->orWhereHas('reconciliations', function ($rq) {
                            $rq->where('match_status', 'matched');
                        });
                });
            } elseif ($reconFilter === 'mismatch') {
                $query->where(function ($q) {
                    $q->where('reconciliation_status', 'mismatch')
                        ->orWhereHas('reconciliations', function ($rq) {
                            $rq->where('match_status', '!=', 'matched');
                        });
                });
            } elseif (in_array($reconFilter, ['unreconciled', 'none', 'belum'], true)) {
                $query->where(function ($q) {
                    $q->where(function ($sq) {
                        $sq->whereNull('reconciliation_status')
                            ->orWhere('reconciliation_status', '');
                    })->whereDoesntHave('reconciliations');
                });
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($query) use ($search) {
                $query->where('nomor', 'like', "%$search%")
                    ->orWhere('request_id', 'like', "%$search%")
                    ->orWhere('nim', 'like', "%$search%")
                    ->orWhere('nama_mahasiswa', 'like', "%$search%")
                    ->orWhere('va_number', 'like', "%$search%")
                    ->orWhere('bank_reference', 'like', "%$search%")
                    ->orWhere('customer_no', 'like', "%$search%")
                    ->orWhere('bsi_payment_number', 'like', "%$search%");
            });
        }

        $dateBy = $request->input('date_by');
        if ($dateBy === 'created_at') {
            if ($request->filled('tanggal_mulai')) {
                $query->whereDate('created_at', '>=', $request->tanggal_mulai);
            }
            if ($request->filled('tanggal_akhir')) {
                $query->whereDate('created_at', '<=', $request->tanggal_akhir);
            }
        } elseif ($dateBy === 'paid_at') {
            if ($request->filled('tanggal_mulai')) {
                $query->whereDate('paid_at', '>=', $request->tanggal_mulai);
            }
            if ($request->filled('tanggal_akhir')) {
                $query->whereDate('paid_at', '<=', $request->tanggal_akhir);
            }
        } else {
            if ($request->filled('tanggal_mulai') && $request->filled('tanggal_akhir')) {
                $start = $request->tanggal_mulai;
                $end = $request->tanggal_akhir;
                $query->where(function ($q) use ($start, $end) {
                    $q->where(function ($sq) use ($start, $end) {
                        $sq->whereDate('created_at', '>=', $start)
                            ->whereDate('created_at', '<=', $end);
                    })->orWhere(function ($sq) use ($start, $end) {
                        $sq->whereNotNull('paid_at')
                            ->whereDate('paid_at', '>=', $start)
                            ->whereDate('paid_at', '<=', $end);
                    });
                });
            } elseif ($request->filled('tanggal_mulai')) {
                $start = $request->tanggal_mulai;
                $query->where(function ($q) use ($start) {
                    $q->whereDate('created_at', '>=', $start)
                        ->orWhere(function ($sq) use ($start) {
                            $sq->whereNotNull('paid_at')->whereDate('paid_at', '>=', $start);
                        });
                });
            } elseif ($request->filled('tanggal_akhir')) {
                $end = $request->tanggal_akhir;
                $query->where(function ($q) use ($end) {
                    $q->whereDate('created_at', '<=', $end)
                        ->orWhere(function ($sq) use ($end) {
                            $sq->whereNotNull('paid_at')->whereDate('paid_at', '<=', $end);
                        });
                });
            }
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
                'metodeVa',
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

        $baseQuery = KeuanganPembayaranBsi::query()->where(function ($query) {
            $query->where('data_test', false)->orWhereNull('data_test');
        });

        if (! empty($validated['tanggal_mulai']) && ! empty($validated['tanggal_akhir'])) {
            $start = $validated['tanggal_mulai'];
            $end = $validated['tanggal_akhir'];
            $baseQuery->where(function ($q) use ($start, $end) {
                $q->where(function ($sq) use ($start, $end) {
                    $sq->whereDate('created_at', '>=', $start)
                        ->whereDate('created_at', '<=', $end);
                })->orWhere(function ($sq) use ($start, $end) {
                    $sq->whereNotNull('paid_at')
                        ->whereDate('paid_at', '>=', $start)
                        ->whereDate('paid_at', '<=', $end);
                });
            });
        } elseif (! empty($validated['tanggal_mulai'])) {
            $start = $validated['tanggal_mulai'];
            $baseQuery->where(function ($q) use ($start) {
                $q->whereDate('created_at', '>=', $start)
                    ->orWhere(function ($sq) use ($start) {
                        $sq->whereNotNull('paid_at')->whereDate('paid_at', '>=', $start);
                    });
            });
        } elseif (! empty($validated['tanggal_akhir'])) {
            $end = $validated['tanggal_akhir'];
            $baseQuery->where(function ($q) use ($end) {
                $q->whereDate('created_at', '<=', $end)
                    ->orWhere(function ($sq) use ($end) {
                        $sq->whereNotNull('paid_at')->whereDate('paid_at', '<=', $end);
                    });
            });
        }

        // Rekonsiliasi (sesuai logika filter index)
        $matchedQuery = (clone $baseQuery)->where(function ($q) {
            $q->where('reconciliation_status', 'matched')
                ->orWhereHas('reconciliations', fn ($rq) => $rq->where('match_status', 'matched'));
        });
        $mismatchQuery = (clone $baseQuery)->where(function ($q) {
            $q->where('reconciliation_status', 'mismatch')
                ->orWhereHas('reconciliations', fn ($rq) => $rq->where('match_status', '!=', 'matched'));
        });
        $unreconciledQuery = (clone $baseQuery)->where(function ($q) {
            $q->where(function ($sq) {
                $sq->whereNull('reconciliation_status')
                    ->orWhere('reconciliation_status', '');
            })->whereDoesntHave('reconciliations');
        });

        // Sinkronisasi
        $syncedQuery = (clone $baseQuery)->where('transferred', true);
        $unsyncedQuery = (clone $baseQuery)->where(function ($q) {
            $q->where('transferred', false)->orWhereNull('transferred');
        });

        // Status Transaksi
        $successQuery = (clone $baseQuery)->where('status', 'success');
        $pendingQuery = (clone $baseQuery)->where('status', 'pending');
        $paidQuery = (clone $baseQuery)->where('status', 'paid');
        $expiredQuery = (clone $baseQuery)->where('status', 'expired');
        $cancelledQuery = (clone $baseQuery)->where('status', 'cancelled');
        $needsReviewQuery = (clone $baseQuery)->where('status', 'needs_review');

        return response()->json([
            'status' => true,
            'data' => [
                'total' => [
                    'count' => (clone $baseQuery)->count(),
                    'amount' => (float) (clone $baseQuery)->sum('total'),
                ],
                // Rekonsiliasi
                'matched' => [
                    'count' => $matchedQuery->count(),
                    'amount' => (float) $matchedQuery->sum('total'),
                ],
                'mismatch' => [
                    'count' => $mismatchQuery->count(),
                    'amount' => (float) $mismatchQuery->sum('total'),
                ],
                'unreconciled' => [
                    'count' => $unreconciledQuery->count(),
                    'amount' => (float) $unreconciledQuery->sum('total'),
                ],
                // Sinkronisasi
                'synced' => [
                    'count' => $syncedQuery->count(),
                    'amount' => (float) $syncedQuery->sum('total'),
                ],
                'unsynced' => [
                    'count' => $unsyncedQuery->count(),
                    'amount' => (float) $unsyncedQuery->sum('total'),
                ],
                // Status
                'success' => [
                    'count' => $successQuery->count(),
                    'amount' => (float) $successQuery->sum('total'),
                ],
                'pending' => [
                    'count' => $pendingQuery->count(),
                    'amount' => (float) $pendingQuery->sum('total'),
                ],
                'paid' => [
                    'count' => $paidQuery->count(),
                    'amount' => (float) $paidQuery->sum('total'),
                ],
                'expired' => [
                    'count' => $expiredQuery->count(),
                    'amount' => (float) $expiredQuery->sum('total'),
                ],
                'cancelled' => [
                    'count' => $cancelledQuery->count(),
                    'amount' => (float) $cancelledQuery->sum('total'),
                ],
                'needs_review' => [
                    'count' => $needsReviewQuery->count(),
                    'amount' => (float) $needsReviewQuery->sum('total'),
                ],
            ],
        ]);
    }

    public function recheckReconciliations(BsiSnapService $snapService): JsonResponse
    {
        $mismatches = BsiReconciliation::where('match_status', '!=', 'matched')->get();
        $matchedCount = 0;
        $stillMismatchCount = 0;

        foreach ($mismatches as $reconciliation) {
            $updated = $snapService->recheckReconciliation($reconciliation);
            if ($updated->match_status === 'matched') {
                $matchedCount++;
            } else {
                $stillMismatchCount++;
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Pemeriksaan ulang rekonsiliasi selesai: {$matchedCount} berhasil cocok, {$stillMismatchCount} masih mismatch.",
            'data' => [
                'rechecked_count' => $mismatches->count(),
                'matched_count' => $matchedCount,
                'still_mismatch_count' => $stillMismatchCount,
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

    public function studentBills(Request $request, BsiPaymentService $paymentService): JsonResponse
    {
        $validated = $request->validate([
            'nim' => 'required|string|max:50',
        ]);

        $nim = strtoupper(trim($validated['nim']));
        $mahasiswa = $paymentService->resolveMahasiswa($nim);

        if (! $mahasiswa) {
            return response()->json([
                'status' => false,
                'message' => "Data mahasiswa dengan NIM {$nim} tidak ditemukan.",
            ], 404);
        }

        $gender = $paymentService->resolveGender($mahasiswa);
        $tagihanData = TagihanMahasiswa::tagihan($nim);
        $rawBills = $tagihanData['list_tagihan'] ?? [];

        $bills = collect($rawBills)->map(function ($bill) {
            $jumlah = (float) data_get($bill, 'jumlah', 0);
            $terbayar = (float) data_get($bill, 'terbayar', 0);
            $sisa = max(0, (float) data_get($bill, 'sisa', $jumlah - $terbayar));

            return [
                'id' => (int) data_get($bill, 'id'),
                'nama' => (string) data_get($bill, 'nama'),
                'th_akademik_id' => (int) data_get($bill, 'th_akademik_id'),
                'tahun_akademik' => (string) (data_get($bill, 'tahun_akademik') ?: data_get($bill, 'th_akademik_kode') ?: data_get($bill, 'th_akademik.kode')),
                'jumlah_tagihan' => $jumlah,
                'terbayar' => $terbayar,
                'sisa' => $sisa,
                'mata_uang_kode' => strtoupper((string) data_get($bill, 'mata_uang_kode', 'IDR')),
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'nim' => $nim,
                'nama' => data_get($mahasiswa, 'nama') ?: ($tagihanData['nama_mhs'] ?? null),
                'nama_ayah' => data_get($mahasiswa, 'nama_ayah') ?: data_get($mahasiswa, 'ayah') ?: ($tagihanData['nama_ayah'] ?? '-'),
                'prodi' => data_get($mahasiswa, 'prodi.nama') ?: ($tagihanData['nama_prodi'] ?? null),
                'kelas' => data_get($mahasiswa, 'kelas.nama') ?: ($tagihanData['nama_kelas'] ?? null),
                'semester' => data_get($mahasiswa, 'semester') ?: ($tagihanData['semester'] ?? null),
                'jk_id' => $gender['id'] ?? 8,
                'kategori' => $gender['kategori'] ?? 'Putra',
                'bills' => $bills,
            ],
        ]);
    }

    public function unreconciledReconciliations(): JsonResponse
    {
        $reconciliations = BsiReconciliation::query()
            ->where(function ($q) {
                $q->whereNull('pembayaran_bsi_id')
                    ->orWhere('match_status', 'mismatch');
            })
            ->latest('id')
            ->limit(100)
            ->get([
                'id',
                'recon_id',
                'pembayaran_bsi_id',
                'payment_number',
                'payment_amount',
                'settlement_amount',
                'settlement_code',
                'bank_status',
                'transaction_at',
                'match_status',
                'mismatch_description',
            ]);

        return response()->json([
            'status' => true,
            'data' => $reconciliations,
        ]);
    }

    public function store(
        Request $request,
        BsiPaymentTransferService $transferService,
        BsiPaymentService $paymentService
    ): JsonResponse {
        $validated = $request->validate([
            'nim' => 'required|string|max:50',
            'nama_mahasiswa' => 'nullable|string|max:255',
            'jk_id' => 'nullable|integer',
            'status' => 'nullable|string|in:success,pending,paid,needs_review',
            'paid_at' => 'nullable|date',
            'va_number' => 'nullable|string|max:50',
            'customer_no' => 'nullable|string|max:50',
            'bsi_payment_number' => 'nullable|string|max:50',
            'bank_reference' => 'nullable|string|max:100',
            'metode_va_id' => 'nullable|integer',
            'recon_id' => 'nullable|string|max:50',
            'production' => 'nullable|boolean',
            'sync_now' => 'nullable|boolean',
            'reconcile_now' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.tagihan_id' => 'required|integer',
            'items.*.jumlah' => 'required|numeric|min:0.01',
            'items.*.tagihan_nama' => 'nullable|string|max:255',
            'items.*.th_akademik_id' => 'nullable|integer',
            'items.*.jumlah_tagihan' => 'nullable|numeric',
            'items.*.sisa_awal' => 'nullable|numeric',
        ]);

        $nim = strtoupper(trim($validated['nim']));
        $mahasiswa = $paymentService->resolveMahasiswa($nim);

        if (! $mahasiswa) {
            return response()->json([
                'status' => false,
                'message' => "Data mahasiswa dengan NIM {$nim} tidak ditemukan.",
            ], 404);
        }

        $gender = $paymentService->resolveGender($mahasiswa);
        $jkId = $validated['jk_id'] ?? ($gender ? $gender['id'] : 8);
        $kategori = ($jkId === 9) ? 'Putri' : 'Putra';
        $namaMahasiswa = trim((string) ($validated['nama_mahasiswa'] ?? data_get($mahasiswa, 'nama') ?: $nim));

        $jenisPembayaran = KeuanganJenisPembayaran::whereIn(DB::raw('LOWER(TRIM(nama))'), [
            'va bsi',
            'va bsi - ' . Str::lower($kategori),
        ])->where('kategori', $kategori)->first();

        if (! $jenisPembayaran) {
            $jenisPembayaran = KeuanganJenisPembayaran::where('id', $jkId === 9 ? 17 : 16)->first();
        }

        $paidAt = ! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : now();
        $status = $validated['status'] ?? 'success';
        $syncNow = (bool) ($validated['sync_now'] ?? false);
        $reconcileNow = (bool) ($validated['reconcile_now'] ?? false);
        $production = $syncNow ? true : (bool) ($validated['production'] ?? true);

        $vaNumber = ! empty($validated['va_number']) ? trim($validated['va_number']) : ('2700' . $nim);
        $customerNo = ! empty($validated['customer_no']) ? trim($validated['customer_no']) : $nim;
        $bsiPaymentNumber = ! empty($validated['bsi_payment_number']) ? trim($validated['bsi_payment_number']) : $vaNumber;
        $bankReference = ! empty($validated['bank_reference']) ? trim($validated['bank_reference']) : ('MANUAL-' . strtoupper(Str::random(10)));

        $requestId = 'MANUAL-' . $nim . '-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
        $requestHash = hash('sha256', $requestId);

        $details = [];
        $total = 0.0;
        foreach ($validated['items'] as $item) {
            $tagihan = KeuanganTagihan::with('thAkademik')->find($item['tagihan_id']);
            $tagihanNama = $item['tagihan_nama'] ?? ($tagihan?->nama ?: "Tagihan #{$item['tagihan_id']}");
            $thAkademikId = $item['th_akademik_id'] ?? ($tagihan?->th_akademik_id ?: 1);
            $jumlahTagihan = (float) ($item['jumlah_tagihan'] ?? ($tagihan?->jumlah ?: $item['jumlah']));
            $sisaAwal = (float) ($item['sisa_awal'] ?? $jumlahTagihan);
            $jumlah = round((float) $item['jumlah'], 2);

            $details[] = [
                'tagihan_id' => $item['tagihan_id'],
                'th_akademik_id' => $thAkademikId,
                'tagihan_nama' => $tagihanNama,
                'jumlah_tagihan' => $jumlahTagihan,
                'sisa_awal' => $sisaAwal,
                'jumlah' => $jumlah,
                'cara_bayar' => ($jumlah >= $sisaAwal - 0.01) ? 'lunas' : 'cicilan',
            ];

            $total += $jumlah;
        }

        if ($total <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Total nominal pembayaran harus lebih dari 0.',
            ], 422);
        }

        try {
            $payment = DB::transaction(function () use (
                $details,
                $total,
                $requestId,
                $requestHash,
                $nim,
                $namaMahasiswa,
                $jkId,
                $jenisPembayaran,
                $vaNumber,
                $customerNo,
                $bsiPaymentNumber,
                $bankReference,
                $validated,
                $paidAt,
                $status,
                $production,
                $reconcileNow,
                $syncNow,
                $transferService,
                $request
            ) {
                $payment = KeuanganPembayaranBsi::create([
                    'request_id' => $requestId,
                    'request_hash' => $requestHash,
                    'nim' => $nim,
                    'nama_mahasiswa' => $namaMahasiswa,
                    'jk_id' => $jkId,
                    'jenis_pembayaran_id' => $jenisPembayaran?->id ?? ($jkId === 9 ? 17 : 16),
                    'va_number' => $vaNumber,
                    'customer_no' => $customerNo,
                    'bsi_payment_number' => $bsiPaymentNumber,
                    'bank_reference' => $bankReference,
                    'metode_va_id' => $validated['metode_va_id'] ?? null,
                    'trx_date_time' => $paidAt,
                    'total' => $total,
                    'admin_fee_bearer' => 'institution',
                    'admin_fee_amount' => 0,
                    'status' => $status,
                    'data_test' => false,
                    'production' => $production,
                    'transferred' => false,
                    'expired_at' => $paidAt->copy()->addDays(7),
                    'paid_at' => in_array($status, ['success', 'paid'], true) ? $paidAt : null,
                    'reconciliation_status' => $reconcileNow ? 'matched' : 'unreconciled',
                    'raw_request' => [
                        'source' => 'manual_input',
                        'user_id' => $request->user()?->id,
                        'user_name' => $request->user()?->name,
                        'recon_id' => $validated['recon_id'] ?? null,
                    ],
                ]);

                $payment->update([
                    'nomor' => BsiPaymentService::buildInternalNumber($payment->id, $paidAt),
                ]);

                foreach ($details as $index => $detail) {
                    $payment->details()->create([
                        'tagihan_id' => $detail['tagihan_id'],
                        'th_akademik_id' => $detail['th_akademik_id'],
                        'tagihan_nama' => $detail['tagihan_nama'],
                        'jumlah_tagihan' => $detail['jumlah_tagihan'],
                        'sisa_awal' => $detail['sisa_awal'],
                        'jumlah' => $detail['jumlah'],
                        'cara_bayar' => $detail['cara_bayar'],
                        'urutan' => $index + 1,
                    ]);
                }

                if ($reconcileNow) {
                    $reconId = ! empty($validated['recon_id']) ? trim($validated['recon_id']) : null;
                    $reconQuery = BsiReconciliation::query();
                    if ($reconId) {
                        $reconQuery->where('recon_id', $reconId);
                    } else {
                        $reconQuery->where(function ($q) use ($bsiPaymentNumber, $customerNo, $vaNumber) {
                            $q->where('payment_number', $bsiPaymentNumber)
                                ->orWhere('payment_number', $customerNo)
                                ->orWhere('payment_number', $vaNumber);
                        });
                    }

                    $existingRecon = $reconQuery->first();
                    if ($existingRecon) {
                        $existingRecon->update([
                            'pembayaran_bsi_id' => $payment->id,
                            'match_status' => 'matched',
                            'mismatch_description' => null,
                            'reconciled_at' => $existingRecon->reconciled_at ?: now(),
                        ]);
                    } elseif ($reconId) {
                        BsiReconciliation::create([
                            'recon_id' => $reconId,
                            'pembayaran_bsi_id' => $payment->id,
                            'payment_number' => $bsiPaymentNumber,
                            'transaction_at' => $paidAt,
                            'reconciled_at' => now(),
                            'payment_amount' => $total,
                            'settlement_amount' => $total,
                            'bank_status' => 'SUKSES',
                            'checksum_valid' => true,
                            'match_status' => 'matched',
                            'mismatch_description' => null,
                        ]);
                    }
                }

                if ($syncNow) {
                    if ($status !== 'success') {
                        $payment->update([
                            'status' => 'success',
                            'paid_at' => $paidAt,
                        ]);
                    }
                    $transferService->transfer($payment, (int) $request->user()->id);
                }

                return $payment;
            });

            return response()->json([
                'status' => true,
                'message' => 'Transaksi BSI manual berhasil disimpan' . ($syncNow ? ' dan disinkronkan ke buku keuangan.' : '.'),
                'data' => $payment->fresh([
                    'details.tahunAkademik',
                    'details.pembayaran',
                    'reconciliations',
                ]),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Gagal menyimpan transaksi manual: ' . $this->transferErrorMessage($e),
            ], 422);
        }
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
