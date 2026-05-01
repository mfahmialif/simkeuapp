<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyaratTagihan;
use App\Services\TagihanMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SyaratTagihanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Query params (opsional):
     * - search       : cari di nama/keterangan
     * - sort_key     : default 'id'
     * - sort_order   : 'asc'|'desc' (default 'asc')
     * - limit        : default 10
     */
    public function index(Request $request)
    {
        $query = SyaratTagihan::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->orWhere('nama', 'LIKE', "%{$search}%")
                  ->orWhere('keterangan', 'LIKE', "%{$search}%");
            });
        }

        // Filter by smt
        if ($request->filled('smt')) {
            $query->where('smt', $request->smt);
        }

        // Filter by smt_status: 'filled' = sudah diisi, 'empty' = belum diisi
        if ($request->filled('smt_status')) {
            if ($request->smt_status === 'filled') {
                $query->whereNotNull('smt');
            } elseif ($request->smt_status === 'empty') {
                $query->whereNull('smt');
            }
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'nama');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Syarat Tagihan retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string|unique:keuangan_syarat_tagihan,nama|max:255',
            'smt'        => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $item = SyaratTagihan::create([
            'nama'       => $request->nama,
            'smt'        => $request->smt,
            'keterangan' => $request->keterangan,
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'Syarat Tagihan created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $item = SyaratTagihan::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'Syarat Tagihan Not Found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'Syarat Tagihan retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string|unique:keuangan_syarat_tagihan,nama,' . $id . '|max:255',
            'smt'        => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $item = SyaratTagihan::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'Syarat Tagihan Not Found',
            ], 404);
        }

        $item->nama       = $request->nama;
        $item->smt        = $request->smt;
        $item->keterangan = $request->keterangan;
        $item->save();

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'Syarat Tagihan updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $item = SyaratTagihan::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'Syarat Tagihan Not Found',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Syarat Tagihan deleted successfully',
        ]);
    }

    /**
     * Get stats: total, filled smt, empty smt.
     */
    public function stats()
    {
        $total  = SyaratTagihan::count();
        $filled = SyaratTagihan::whereNotNull('smt')->count();
        $empty  = SyaratTagihan::whereNull('smt')->count();

        return response()->json([
            'status' => true,
            'data'   => [
                'total'  => $total,
                'filled' => $filled,
                'empty'  => $empty,
            ],
        ]);
    }

    /**
     * Sync tagihan baru dari database.
     * Insert nama tagihan yang belum ada di tabel keuangan_syarat_tagihan.
     */
    public function sync()
    {
        try {
            $result = TagihanMahasiswa::getUniqueTagihan();

            if (!$result['status'] || empty($result['data'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Tidak ada data tagihan ditemukan.',
                ], 404);
            }

            $inserted = 0;
            $skipped  = 0;

            foreach ($result['data'] as $nama) {
                if (SyaratTagihan::where('nama', $nama)->exists()) {
                    $skipped++;
                    continue;
                }

                // Auto-parse semester dari nama tagihan
                $smt = null;
                if (preg_match('/semester\s+(\d+)/i', $nama, $matches)) {
                    $smt = (int) $matches[1];
                }

                SyaratTagihan::create([
                    'nama'       => $nama,
                    'smt'        => $smt,
                    'keterangan' => $smt ? 'Otomatis terdeteksi dari nama tagihan' : null,
                ]);

                $inserted++;
            }

            return response()->json([
                'status'  => true,
                'message' => "Sync selesai: {$inserted} tagihan baru ditambahkan, {$skipped} sudah ada.",
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
