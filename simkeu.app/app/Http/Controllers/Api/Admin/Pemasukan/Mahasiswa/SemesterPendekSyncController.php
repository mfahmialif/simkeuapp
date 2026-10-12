<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganJenisPembayaranDetail;
use App\Models\KeuanganNota;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganPembayaranSemesterPendek;
use App\Models\KeuanganTagihan;
use App\Models\ThAkademik;
use App\Models\User;
use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Services\SemesterPendek;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SemesterPendekSyncController extends Controller
{
    /**
     * List semester pendek payments with synchronization status.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = (int) ($request->input('limit', 10));
            $page = (int) ($request->input('page', 1));
            $sortKey = $request->input('sort_key', 'id');
            $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $baseQuery = $this->buildBaseQuery($request);

            // Sorting
            $sortColumns = [
                'id' => 'keuangan_pembayaran_semester_pendek.id',
                'tanggal' => 'keuangan_pembayaran_semester_pendek.tanggal',
                'jumlah' => 'keuangan_pembayaran_semester_pendek.jumlah',
                'nomor' => 'keuangan_pembayaran_semester_pendek.nomor',
                'krs_id' => 'keuangan_pembayaran_semester_pendek.krs_id',
            ];
            $sortColumn = $sortColumns[$sortKey] ?? 'keuangan_pembayaran_semester_pendek.id';
            $baseQuery->orderBy($sortColumn, $sortOrder);

            // Handle status_sinkron filter
            $statusSinkron = $request->input('status_sinkron', 'all');

            if ($statusSinkron !== 'all') {
                $allRecords = (clone $baseQuery)->get();
                $allEnriched = $this->enrichPayments($allRecords, false);

                $filtered = $allEnriched->filter(function ($item) use ($statusSinkron) {
                    if ($statusSinkron === 'synced') {
                        return $item['is_synced'] === true;
                    } elseif ($statusSinkron === 'not_synced') {
                        return $item['is_synced'] === false;
                    } elseif ($statusSinkron === 'no_tagihan') {
                        return $item['has_tagihan'] === false;
                    }
                    return true;
                })->values();

                $total = $filtered->count();
                $offset = ($page - 1) * $limit;
                $pageIds = $filtered->slice($offset, $limit)->pluck('id')->values()->all();

                $pageRecords = (clone $baseQuery)->whereIn('keuangan_pembayaran_semester_pendek.id', $pageIds)->get();
                $items = $this->enrichPayments($pageRecords, true);

                return response()->json([
                    'status' => true,
                    'total' => $total,
                    'data' => [
                        'current_page' => $page,
                        'data' => $items,
                        'total' => $total,
                        'per_page' => $limit,
                        'last_page' => (int) ceil($total / max(1, $limit)),
                    ],
                ]);
            }

            // Normal pagination
            $paginated = $baseQuery->paginate($limit, ['*'], 'page', $page);
            $items = $this->enrichPayments(collect($paginated->items()));

            return response()->json([
                'status' => true,
                'total' => $paginated->total(),
                'data' => [
                    'current_page' => $paginated->currentPage(),
                    'data' => $items,
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]);
        } catch (\Throwable $th) {
            Log::error('Error fetching semester pendek sync items: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal memuat data semester pendek: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistics for summary cards.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $baseQuery = $this->buildBaseQuery($request);
            $records = $baseQuery->get();

            $enriched = $this->enrichPayments($records, false);

            $totalCount = $enriched->count();
            $totalAmount = (float) $enriched->sum('jumlah');

            $syncedItems = $enriched->where('is_synced', true);
            $syncedCount = $syncedItems->count();
            $syncedAmount = (float) $syncedItems->sum('jumlah');

            $notSyncedItems = $enriched->where('is_synced', false);
            $notSyncedCount = $notSyncedItems->count();
            $notSyncedAmount = (float) $notSyncedItems->sum('jumlah');

            $noTagihanItems = $enriched->where('has_tagihan', false);
            $noTagihanCount = $noTagihanItems->count();
            $noTagihanAmount = (float) $noTagihanItems->sum('jumlah');

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => [
                        'count' => $totalCount,
                        'amount' => $totalAmount,
                    ],
                    'synced' => [
                        'count' => $syncedCount,
                        'amount' => $syncedAmount,
                    ],
                    'not_synced' => [
                        'count' => $notSyncedCount,
                        'amount' => $notSyncedAmount,
                    ],
                    'no_tagihan' => [
                        'count' => $noTagihanCount,
                        'amount' => $noTagihanAmount,
                    ],
                ],
            ]);
        } catch (\Throwable $th) {
            Log::error('Error fetching semester pendek statistics: ' . $th->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Gagal memuat statistik semester pendek: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Filter options for dropdowns.
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $jenisPembayaranList = KeuanganJenisPembayaran::orderBy('nama')
                ->get(['id', 'nama', 'kode'])
                ->map(fn($jp) => ['id' => $jp->id, 'nama' => $jp->nama]);

            $thAkademikList = ThAkademik::orderBy('kode', 'desc')
                ->get(['id', 'nama', 'kode']);

            $periodes = KeuanganPembayaranSemesterPendek::whereNotNull('periode_id')
                ->distinct()
                ->pluck('periode_id')
                ->values();

            $petugasList = User::whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('keuangan_pembayaran_semester_pendek')
                    ->whereNotNull('user_id')
                    ->distinct();
            })
                ->orderBy('name')
                ->get(['id', 'name', 'username']);

            return response()->json([
                'status' => true,
                'data' => [
                    'jenis_pembayaran_list' => $jenisPembayaranList,
                    'th_akademik_list' => $thAkademikList,
                    'periode_list' => $periodes,
                    'petugas_list' => $petugasList,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal memuat filter options: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronize a single SP payment to individual bills (keuangan_pembayaran).
     */
    public function sinkronkan(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $nimManual = $request->input('nim');

        if (! $id) {
            return response()->json([
                'status' => false,
                'message' => 'Parameter ID pembayaran semester pendek diperlukan.',
            ], 422);
        }

        try {
            $spPayment = KeuanganPembayaranSemesterPendek::with(['thAkademik', 'user', 'jenisPembayaran'])->find($id);

            if (! $spPayment) {
                return response()->json([
                    'status' => false,
                    'message' => "Pembayaran Semester Pendek ID #{$id} tidak ditemukan.",
                ], 404);
            }

            $result = $this->performSyncPayment($spPayment, $nimManual);

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => $result['pembayaran'],
            ]);
        } catch (\Throwable $th) {
            Log::error("Error syncing SP payment ID {$id}: " . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal sinkronisasi: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch synchronize SP payments.
     */
    public function sinkronkanBatch(Request $request): JsonResponse
    {
        try {
            $ids = $request->input('ids', []);
            $syncAllFiltered = (bool) $request->input('sync_all_filtered', false);

            if ($syncAllFiltered) {
                $baseQuery = $this->buildBaseQuery($request);
                $records = $baseQuery->get();
                $enriched = $this->enrichPayments($records);
                $targetRecords = $enriched->where('is_synced', false);
                $ids = $targetRecords->pluck('id')->values()->all();
            }

            if (empty($ids) || ! is_array($ids)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tidak ada data pembayaran semester pendek yang dipilih untuk disinkronkan.',
                ], 422);
            }

            $successCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $errors = [];

            $payments = KeuanganPembayaranSemesterPendek::whereIn('id', $ids)->get();

            foreach ($payments as $spPayment) {
                try {
                    $res = $this->performSyncPayment($spPayment);
                    if ($res['status'] === 'synced') {
                        $successCount++;
                    } elseif ($res['status'] === 'skipped') {
                        $skippedCount++;
                    }
                } catch (\Throwable $th) {
                    $errorCount++;
                    $errors[] = [
                        'id' => $spPayment->id,
                        'nomor' => $spPayment->nomor,
                        'message' => $th->getMessage(),
                    ];
                }
            }

            $message = "Sinkronisasi selesai. Berhasil: {$successCount}, Dilewati (sudah sinkron): {$skippedCount}";
            if ($errorCount > 0) {
                $message .= ", Gagal: {$errorCount}";
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'total' => count($ids),
                    'synced' => $successCount,
                    'skipped' => $skippedCount,
                    'failed' => $errorCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Throwable $th) {
            Log::error('Error in batch sync semester pendek: ' . $th->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Gagal sinkronisasi batch: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Perform the actual synchronization of a single SP payment.
     */
    private function performSyncPayment(KeuanganPembayaranSemesterPendek $spPayment, ?string $nimManual = null): array
    {
        $krsId = (int) $spPayment->krs_id;

        // 1. Find existing tagihan
        $tagihan = $this->findTagihanByKrsId($krsId);

        // 2. If tagihan not found, try to resolve NIM and create tagihan
        if (! $tagihan) {
            $nim = $nimManual ? strtoupper(trim($nimManual)) : $this->resolveNimFromKrs($krsId);

            if (! $nim) {
                throw new \RuntimeException("Tagihan dan NIM untuk KRS ID {$krsId} tidak ditemukan. Silakan masukkan NIM mahasiswa.");
            }

            $tagihan = $this->createTagihanForSp($spPayment, $nim, $krsId);
        }

        $nim = strtoupper(trim((string) $tagihan->nim));

        // 3. Check if already exists in keuangan_pembayaran
        $existing = KeuanganPembayaran::where(function ($query) use ($spPayment, $tagihan, $nim) {
            if ($spPayment->nomor) {
                $query->where('nomor', $spPayment->nomor);
            } else {
                $query->where('tagihan_id', $tagihan->id)
                    ->where('nim', $nim)
                    ->whereDate('tanggal', $spPayment->tanggal)
                    ->where('jumlah', $spPayment->jumlah);
            }
        })->first();

        if ($existing) {
            return [
                'status' => 'skipped',
                'message' => 'Pembayaran ini sudah pernah disinkronkan sebelumnya.',
                'pembayaran' => $existing,
            ];
        }

        // 4. Create in keuangan_pembayaran within transaction
        $pembayaranBaru = DB::transaction(function () use ($spPayment, $tagihan, $nim) {
            $timestamps = [
                'created_at' => $spPayment->created_at ?: now(),
                'updated_at' => $spPayment->updated_at ?: now(),
            ];

            $nomor = $spPayment->nomor ?: Helper::generateNumber();

            $pembayaran = KeuanganPembayaran::create([
                'nomor' => $nomor,
                'tanggal' => $spPayment->tanggal,
                'th_akademik_id' => $tagihan->th_akademik_id ?: $spPayment->th_akademik_id,
                'tagihan_id' => $tagihan->id,
                'nim' => $nim,
                'smt' => 0,
                'jml_sks' => 1,
                'jumlah' => $spPayment->jumlah,
                'jk_id' => $spPayment->jk_id ?: 8,
                'user_id' => $spPayment->user_id ?: Auth::id(),
            ] + $timestamps);

            if ($spPayment->jenis_pembayaran_id) {
                KeuanganJenisPembayaranDetail::firstOrCreate(
                    ['pembayaran_id' => $pembayaran->id],
                    ['jenis_pembayaran_id' => $spPayment->jenis_pembayaran_id] + $timestamps
                );
            }

            KeuanganNota::firstOrCreate(
                ['pembayaran_id' => $pembayaran->id],
                ['nota' => $nomor] + $timestamps
            );

            // Sync with SIAKAD
            try {
                SemesterPendek::syncTagihan($tagihan, $nim);
            } catch (\Throwable $th) {
                Log::warning("Gagal syncTagihan SIAKAD untuk tagihan {$tagihan->id}: " . $th->getMessage());
            }

            return $pembayaran;
        });

        return [
            'status' => 'synced',
            'message' => 'Pembayaran berhasil disinkronkan ke tagihan perorangan.',
            'pembayaran' => $pembayaranBaru,
        ];
    }

    /**
     * Build base query with filters.
     */
    private function buildBaseQuery(Request $request)
    {
        $query = KeuanganPembayaranSemesterPendek::query()
            ->with(['thAkademik', 'user', 'jenisPembayaran']);

        // Date filter: exact date
        if ($request->filled('tanggal')) {
            $query->whereDate('keuangan_pembayaran_semester_pendek.tanggal', $request->tanggal);
        }

        // Date range filter
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('keuangan_pembayaran_semester_pendek.tanggal', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('keuangan_pembayaran_semester_pendek.tanggal', '<=', $request->tanggal_akhir);
        }

        // Gender filter
        $jkScope = $request->header('X-Simkeu-Jk-Scope') ?: $request->cookie('simkeuJkScope');
        if ($request->filled('jk_id')) {
            $jkId = $request->jk_id;
            if ($jkId === 'putra' || $jkId === '8' || $jkId === 8) {
                $query->where('keuangan_pembayaran_semester_pendek.jk_id', 8);
            } elseif ($jkId === 'putri' || $jkId === '9' || $jkId === 9) {
                $query->where('keuangan_pembayaran_semester_pendek.jk_id', 9);
            }
        } elseif ($request->filled('jenis_kelamin') && $request->jenis_kelamin !== 'all') {
            $jk = strtolower(trim((string) $request->jenis_kelamin));
            if ($jk === 'putra' || $jk === 'laki-laki' || $jk === '8') {
                $query->where('keuangan_pembayaran_semester_pendek.jk_id', 8);
            } elseif ($jk === 'putri' || $jk === 'perempuan' || $jk === '9') {
                $query->where('keuangan_pembayaran_semester_pendek.jk_id', 9);
            }
        } elseif ($jkScope && in_array(strtolower($jkScope), ['putra', 'putri'])) {
            $query->where('keuangan_pembayaran_semester_pendek.jk_id', strtolower($jkScope) === 'putra' ? 8 : 9);
        } else {
            $jkUser = Helper::getJenisKelaminUser();
            if ($jkUser && $jkUser->id !== '%' && !empty($jkUser->id)) {
                $query->where('keuangan_pembayaran_semester_pendek.jk_id', $jkUser->id);
            }
        }

        // Payment method filter
        if ($request->filled('jenis_pembayaran_id')) {
            $query->where('keuangan_pembayaran_semester_pendek.jenis_pembayaran_id', $request->jenis_pembayaran_id);
        }

        // Periode filter
        if ($request->filled('periode_id')) {
            $query->where('keuangan_pembayaran_semester_pendek.periode_id', $request->periode_id);
        }

        // User / Petugas filter
        if ($request->filled('user_id')) {
            $query->where('keuangan_pembayaran_semester_pendek.user_id', $request->user_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('keuangan_pembayaran_semester_pendek.nomor', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_pembayaran_semester_pendek.krs_id', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Enrich payments collection with tagihan, sync status, and student info.
     */
    private function enrichPayments($collection, bool $loadStudentProfile = true)
    {
        if ($collection->isEmpty()) {
            return collect();
        }

        $krsIds = $collection->pluck('krs_id')->filter()->unique()->values()->all();
        $nomorList = $collection->pluck('nomor')->filter()->unique()->values()->all();

        // 1. Fetch all matching tagihans
        $tagihans = KeuanganTagihan::where('nama', 'LIKE', 'SEMESTER PENDEK%')
            ->where(function ($query) use ($krsIds) {
                foreach ($krsIds as $id) {
                    $query->orWhere('nama', 'LIKE', "% - {$id}")
                        ->orWhere('nama', 'LIKE', "% - {$id} - %")
                        ->orWhere('nama', 'LIKE', "%-{$id}")
                        ->orWhere('nama', 'LIKE', "%-{$id}-%");
                }
            })
            ->get();

        $tagihanMap = [];
        foreach ($tagihans as $t) {
            $krsId = SemesterPendek::parseKrsIdFromTagihanName($t->nama);
            if ($krsId) {
                $tagihanMap[$krsId] = $t;
            }
        }

        // 2. Fetch existing payments from keuangan_pembayaran
        $tagihanIds = $tagihans->pluck('id')->values()->all();
        $existingPayments = KeuanganPembayaran::where(function ($query) use ($tagihanIds, $nomorList) {
            if (! empty($tagihanIds)) {
                $query->whereIn('tagihan_id', $tagihanIds);
            }
            if (! empty($nomorList)) {
                $query->orWhereIn('nomor', $nomorList);
            }
        })->get();

        // 3. Resolve NIMs from tagihans or Siakad
        $nimMap = [];
        $krsToFetch = [];
        foreach ($collection as $item) {
            $tagihan = $tagihanMap[$item->krs_id] ?? null;
            if ($tagihan && $tagihan->nim) {
                $nimMap[$item->krs_id] = strtoupper(trim((string) $tagihan->nim));
            } elseif ($loadStudentProfile) {
                $krsToFetch[] = (int) $item->krs_id;
            }
        }

        if ($loadStudentProfile && ! empty($krsToFetch)) {
            try {
                $searchKrs = SemesterPendek::searchKrs(array_unique($krsToFetch));
                if (is_array($searchKrs)) {
                    foreach ($searchKrs as $kItem) {
                        $kId = $kItem->id ?? $kItem->krs_id ?? null;
                        $kNim = $kItem->nim ?? $kItem->mahasiswa_nim ?? null;
                        if ($kId && $kNim) {
                            $nimMap[(int) $kId] = strtoupper(trim((string) $kNim));
                        }
                    }
                }
            } catch (\Throwable $th) {
                // Ignore Siakad lookup errors
            }
        }

        // 4. Resolve Mahasiswa profile (names)
        $mhsMap = [];
        if ($loadStudentProfile) {
            $uniqueNims = array_unique(array_filter(array_values($nimMap)));
            foreach ($uniqueNims as $nim) {
                $mhs = Mahasiswa::nim($nim);
                if ($mhs) {
                    $mhsMap[$nim] = $mhs;
                }
            }
        }

        // 5. Build enriched response items
        return $collection->map(function ($item) use ($tagihanMap, $existingPayments, $nimMap, $mhsMap) {
            $tagihan = $tagihanMap[$item->krs_id] ?? null;
            $nim = $nimMap[$item->krs_id] ?? ($tagihan?->nim ? strtoupper(trim((string) $tagihan->nim)) : null);
            $mhs = $nim ? ($mhsMap[$nim] ?? null) : null;

            // Check if synced
            $syncedPayment = $existingPayments->first(function ($p) use ($item, $tagihan, $nim) {
                if ($item->nomor && $p->nomor === $item->nomor) {
                    return true;
                }
                if ($tagihan && $p->tagihan_id == $tagihan->id) {
                    if ((float) $p->jumlah === (float) $item->jumlah) {
                        return true;
                    }
                    if ($nim && $p->nim === $nim && Carbon::parse($p->tanggal)->isSameDay(Carbon::parse($item->tanggal))) {
                        return true;
                    }
                }
                return false;
            });

            $isSynced = ! empty($syncedPayment);

            return [
                'id' => $item->id,
                'nomor' => $item->nomor,
                'tanggal' => $item->tanggal ? Carbon::parse($item->tanggal)->format('Y-m-d H:i:s') : null,
                'tanggal_formatted' => $item->tanggal ? Carbon::parse($item->tanggal)->translatedFormat('d M Y') : '-',
                'jumlah' => (float) $item->jumlah,
                'jumlah_formatted' => 'Rp ' . number_format((float) $item->jumlah, 0, ',', '.'),
                'krs_id' => $item->krs_id,
                'periode_id' => $item->periode_id,
                'jk_id' => $item->jk_id,
                'jenis_kelamin' => $item->jk_id == 9 ? 'Putri' : 'Putra',
                'jenis_pembayaran' => [
                    'id' => $item->jenisPembayaran?->id ?? $item->jenis_pembayaran_id,
                    'nama' => $item->jenisPembayaran?->nama ?? 'Tunai',
                ],
                'th_akademik' => [
                    'id' => $item->thAkademik?->id ?? $item->th_akademik_id,
                    'nama' => $item->thAkademik?->nama ?? '-',
                    'kode' => $item->thAkademik?->kode ?? '-',
                ],
                'petugas' => [
                    'id' => $item->user?->id ?? $item->user_id,
                    'name' => $item->user?->name ?? 'System',
                ],
                'nim' => $nim,
                'nama_mahasiswa' => $mhs?->nama ?? $mhs?->mhs_nama ?? '-',
                'prodi' => [
                    'id' => $mhs?->prodi_id ?? $tagihan?->prodi_id ?? null,
                    'nama' => $mhs?->prodi?->nama ?? $mhs?->prodi_nama ?? '-',
                    'alias' => $mhs?->prodi?->alias ?? $mhs?->prodi_alias ?? '-',
                ],
                'has_tagihan' => ! empty($tagihan),
                'tagihan' => $tagihan ? [
                    'id' => $tagihan->id,
                    'nama' => $tagihan->nama,
                    'jumlah' => (float) $tagihan->jumlah,
                    'jumlah_formatted' => 'Rp ' . number_format((float) $tagihan->jumlah, 0, ',', '.'),
                    'nim' => $tagihan->nim,
                ] : null,
                'is_synced' => $isSynced,
                'pembayaran_terkait' => $syncedPayment ? [
                    'id' => $syncedPayment->id,
                    'nomor' => $syncedPayment->nomor,
                    'tanggal' => $syncedPayment->tanggal,
                    'jumlah' => (float) $syncedPayment->jumlah,
                ] : null,
            ];
        });
    }

    /**
     * Find tagihan in keuangan_tagihan by KRS ID.
     */
    private function findTagihanByKrsId(int $krsId, ?string $nim = null): ?KeuanganTagihan
    {
        $query = KeuanganTagihan::where('nama', 'LIKE', 'SEMESTER PENDEK%')
            ->where(function ($q) use ($krsId) {
                $q->where('nama', 'LIKE', "% - {$krsId}")
                    ->orWhere('nama', 'LIKE', "% - {$krsId} - %")
                    ->orWhere('nama', 'LIKE', "%-{$krsId}")
                    ->orWhere('nama', 'LIKE', "%-{$krsId}-%");
            });

        if ($nim) {
            $query->where('nim', strtoupper(trim($nim)));
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Resolve NIM from SIAKAD KRS.
     */
    private function resolveNimFromKrs(int $krsId): ?string
    {
        try {
            $krsDetail = SemesterPendek::krsDetail($krsId);
            if ($krsDetail && isset($krsDetail->nim)) {
                return strtoupper(trim((string) $krsDetail->nim));
            }

            $searchKrs = SemesterPendek::searchKrs([$krsId]);
            if (is_array($searchKrs) && ! empty($searchKrs)) {
                $item = $searchKrs[0];
                $nim = $item->nim ?? $item->mahasiswa_nim ?? null;
                if ($nim) {
                    return strtoupper(trim((string) $nim));
                }
            }
        } catch (\Throwable $th) {
            Log::warning("Gagal resolve NIM dari SIAKAD untuk KRS {$krsId}: " . $th->getMessage());
        }

        return null;
    }

    /**
     * Create a missing tagihan for Semester Pendek.
     */
    private function createTagihanForSp(KeuanganPembayaranSemesterPendek $spPayment, string $nim, int $krsId): KeuanganTagihan
    {
        $mhs = Mahasiswa::nim($nim);
        $thAkademikId = $spPayment->th_akademik_id ?: ($mhs->th_akademik_id ?? 25);
        $thAngkatanId = $mhs->th_akademik_id ?? $thAkademikId;
        $prodiId = $mhs->prodi_id ?? 1;
        $kelasId = $mhs->kelas_id ?? 6;

        $kodeTagihan = "{$thAkademikId}{$thAngkatanId}{$prodiId}{$kelasId}4";
        $namaTagihan = "SEMESTER PENDEK - Periode " . ($spPayment->periode_id ?: 1) . " - {$krsId}";

        return KeuanganTagihan::create([
            'nim' => $nim,
            'th_akademik_id' => $thAkademikId,
            'th_angkatan_id' => $thAngkatanId,
            'prodi_id' => $prodiId,
            'double_degree' => 0,
            'kelas_id' => $kelasId,
            'form_schadule_id' => 4,
            'kode' => $kodeTagihan,
            'nama' => $namaTagihan,
            'jumlah' => $spPayment->jumlah,
            'mata_uang_id' => 1,
            'x_sks' => 'Y',
            'user_id' => $spPayment->user_id ?: Auth::id(),
        ]);
    }
}
