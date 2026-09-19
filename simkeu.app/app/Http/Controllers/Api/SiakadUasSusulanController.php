<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeuanganUasSusulan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiakadUasSusulanController extends Controller
{
    /**
     * Get list of UAS Susulan with UAS Susulan MK for SIAKAD.
     *
     * Authentication:
     * - Header: X-SIAKAD-API-KEY: <api_key> (or apikey: <api_key>)
     * - Header: Accept: application/json
     *
     * Supported Filters:
     * - nim: string|array (NIM mahasiswa, e.g. '210101001' or '210101001,210101002')
     * - th_akademik_kode: string (Kode tahun akademik, e.g. '20252')
     * - th_akademik_id: int (ID tahun akademik di SIMKEU)
     * - jadwal_kuliah_id: int (ID jadwal kuliah yang disusulkan)
     * - tanggal: string (YYYY-MM-DD)
     * - tanggal_mulai: string (YYYY-MM-DD)
     * - tanggal_akhir: string (YYYY-MM-DD)
     * - search: string (pencarian umum)
     * - limit: int|string (default 20, 0 / 'all' untuk semua data)
     * - page: int (nomor halaman pagination)
     * - sort_by: string (kolom sorting: id, tanggal, nim, th_akademik_id, created_at)
     * - sort_dir: string (asc / desc)
     */
    public function index(Request $request, ?string $nim = null): JsonResponse
    {
        $nimFilter = $nim ?: $request->input('nim');

        $query = KeuanganUasSusulan::query()
            ->with([
                'th_akademik:id,kode,nama,semester,aktif',
                'uasSusulanMk:id,uas_susulan_id,jadwal_kuliah_id,created_at',
            ]);

        // Filter NIM
        if ($nimFilter) {
            if (is_array($nimFilter)) {
                $query->whereIn('nim', array_map('trim', $nimFilter));
            } elseif (str_contains($nimFilter, ',')) {
                $nims = array_filter(array_map('trim', explode(',', $nimFilter)));
                $query->whereIn('nim', $nims);
            } else {
                $query->where('nim', trim($nimFilter));
            }
        }

        // Filter Kode Tahun Akademik
        if ($request->filled('th_akademik_kode')) {
            $kode = trim((string) $request->input('th_akademik_kode'));
            $query->whereHas('th_akademik', function ($q) use ($kode) {
                $q->where('kode', $kode);
            });
        }

        // Filter ID Tahun Akademik
        if ($request->filled('th_akademik_id')) {
            $query->where('th_akademik_id', (int) $request->input('th_akademik_id'));
        }

        // Filter ID Jadwal Kuliah spesifik
        if ($request->filled('jadwal_kuliah_id')) {
            $jadwalId = (int) $request->input('jadwal_kuliah_id');
            $query->whereHas('uasSusulanMk', function ($q) use ($jadwalId) {
                $q->where('jadwal_kuliah_id', $jadwalId);
            });
        }

        // Filter Tanggal
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal', '>=', $request->input('tanggal_mulai'));
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal', '<=', $request->input('tanggal_akhir'));
        }

        // Pencarian umum
        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('nim', 'like', "%{$s}%")
                    ->orWhere('keterangan', 'like', "%{$s}%")
                    ->orWhereHas('th_akademik', function ($tq) use ($s) {
                        $tq->where('nama', 'like', "%{$s}%")
                            ->orWhere('kode', 'like', "%{$s}%");
                    });
            });
        }

        // Sorting
        $sortKey = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortKey, ['id', 'tanggal', 'nim', 'th_akademik_id', 'created_at'], true)) {
            $query->orderBy($sortKey, $sortDir);
        } else {
            $query->latest('id');
        }

        $limitInput = $request->input('limit', 20);
        $isAll = $limitInput === '0' || $limitInput === 0 || $limitInput === 'all';

        if ($isAll) {
            $items = $query->get();
            $formatted = $items->map(fn ($item) => $this->formatUasSusulan($item))->values();

            return response()->json([
                'status' => true,
                'message' => 'Data UAS Susulan berhasil diambil.',
                'total' => $items->count(),
                'data' => $formatted,
            ]);
        }

        $limit = max(1, min(200, (int) $limitInput));
        $paginated = $query->paginate($limit);

        $formatted = collect($paginated->items())
            ->map(fn ($item) => $this->formatUasSusulan($item))
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Data UAS Susulan berhasil diambil.',
            'data' => $formatted,
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Format response object for a single UAS Susulan record.
     */
    public function formatUasSusulan(KeuanganUasSusulan $item): array
    {
        $mkList = $item->uasSusulanMk ?? collect();
        $jadwalKuliahIds = $mkList->pluck('jadwal_kuliah_id')->filter()->values()->all();

        return [
            'id' => $item->id,
            'nim' => $item->nim,
            'tanggal' => $item->tanggal ? (string) $item->tanggal : null,
            'keterangan' => $item->keterangan,
            'th_akademik_id' => $item->th_akademik_id,
            'th_akademik_kode' => $item->th_akademik?->kode,
            'th_akademik_nama' => $item->th_akademik?->nama,
            'th_akademik_semester' => $item->th_akademik?->semester,
            'jadwal_kuliah_ids' => $jadwalKuliahIds,
            'total_mk' => count($jadwalKuliahIds),
            'mata_kuliah' => $mkList->map(fn ($mk) => [
                'id' => $mk->id,
                'uas_susulan_id' => $mk->uas_susulan_id,
                'jadwal_kuliah_id' => $mk->jadwal_kuliah_id,
                'created_at' => $mk->created_at ? $mk->created_at->toISOString() : null,
            ])->values()->all(),
            'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
            'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
        ];
    }
}
