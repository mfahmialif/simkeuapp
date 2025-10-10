<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CatatanDepositController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganDeposit::query();

        // Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'LIKE', "%{$search}%")
                  ->orWhere('keterangan', 'LIKE', "%{$search}%");
            });
        }

        // Optional filter by NIM
        if ($request->filled('nim')) {
            $query->where('nim', $request->nim);
        }

        // Sorting (whitelist untuk keamanan)
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'
        $allowedSort = ['id', 'nim', 'jumlah', 'created_at', 'updated_at'];
        if (! in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'id';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Catatan deposit berhasil diambil.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nim'        => 'required|string|max:255',
            'jumlah'     => 'required|numeric',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganDeposit::where('nim',$request->nim)->first();

        if ($data) {
            $data->jumlah += $request->jumlah;
        } else {
            $data = new KeuanganDeposit();
            $data->nim        = $request->nim;
            $data->jumlah     = $request->jumlah;
        }

        $data->keterangan = $request->filled('keterangan') ? $request->keterangan : $data->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Catatan deposit berhasil dibuat.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganDeposit::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan deposit tidak ditemukan.',
            ], 404);
        }

        return response()->json($data, 200);
    }

    /**
     * Display the specified resource.
     */
    public function nim($nim)
    {
        $data = KeuanganDeposit::where('nim', $nim)->first();

        // if (! $data) {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'Catatan deposit tidak ditemukan.',
        //     ], 404);
        // }

        return response()->json($data, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nim'        => 'required|string|max:255',
            'jumlah'     => 'required|numeric',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganDeposit::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan deposit tidak ditemukan.',
            ], 404);
        }

        $data->nim        = $request->nim;
        $data->jumlah     = $request->jumlah;
        $data->keterangan = $request->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Catatan deposit berhasil diperbarui.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganDeposit::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan deposit tidak ditemukan.',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Catatan deposit berhasil dihapus.',
        ]);
    }
}
