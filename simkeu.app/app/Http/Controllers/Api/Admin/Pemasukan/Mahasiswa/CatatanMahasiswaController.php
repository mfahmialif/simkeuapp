<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganCatatanMahasiswa;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CatatanMahasiswaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganCatatanMahasiswa::query()
            ->select('keuangan_catatan_mahasiswa.*')
            ->leftJoin('th_akademik', 'keuangan_catatan_mahasiswa.th_akademik_id', '=', 'th_akademik.id')
            ->with([
                'th_akademik:id,nama,kode,semester',
                'user:id,name,username',
                'tagihan:id,nama',
                'pembayaran:id,nomor,tanggal,jumlah',
            ]);

        // Filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('keuangan_catatan_mahasiswa.nim', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_catatan_mahasiswa.catatan', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_catatan_mahasiswa.tanggal', 'LIKE', "%{$search}%")
                    ->orWhere('th_akademik.nama', 'LIKE', "%{$search}%")
                    ->orWhere('th_akademik.kode', 'LIKE', "%{$search}%");
            });
        }

        // Filter spesifik
        if ($request->filled('th_akademik_id')) {
            $query->where('keuangan_catatan_mahasiswa.th_akademik_id', $request->th_akademik_id);
        }

        if ($request->filled('nim')) {
            $query->where('keuangan_catatan_mahasiswa.nim', $request->nim);
        }

        // Filter gender chunk jika relevan
        $query = Helper::whereMahasiswaJkChunk($query, 'keuangan_catatan_mahasiswa.nim');

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed   = ['id', 'tanggal', 'nim', 'catatan', 'th_akademik_id', 'created_at', 'updated_at'];
        if (!in_array($sortKey, $allowed, true)) {
            $sortKey = 'id';
        }

        $query->orderBy("keuangan_catatan_mahasiswa.{$sortKey}", $sortOrder);

        $limit = $request->integer('limit') ?: 10;
        $data  = $query->paginate($limit);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data catatan mahasiswa berhasil diambil.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nim'            => 'required|string|max:255',
            'th_akademik_id' => 'nullable|numeric',
            'tanggal'        => 'nullable|date',
            'catatan'        => 'nullable|string',
            'tagihan_id'     => 'nullable|numeric',
            'pembayaran_id'  => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $catatan = KeuanganCatatanMahasiswa::create([
            'nim'            => strtoupper(trim($request->nim)),
            'th_akademik_id' => $request->th_akademik_id ?: null,
            'pembayaran_id'  => $request->pembayaran_id ?: null,
            'tagihan_id'     => $request->tagihan_id ?: null,
            'tanggal'        => $request->tanggal ?: date('Y-m-d'),
            'catatan'        => $request->catatan ?: null,
            'user_id'        => Auth::id(),
        ]);

        $catatan->load(['th_akademik', 'user', 'tagihan', 'pembayaran']);

        return response()->json([
            'status'  => true,
            'data'    => $catatan,
            'message' => 'Catatan mahasiswa berhasil disimpan.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganCatatanMahasiswa::with([
            'th_akademik',
            'user:id,name,username',
            'tagihan:id,nama',
            'pembayaran:id,nomor,tanggal,jumlah',
        ])->find($id);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan mahasiswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = KeuanganCatatanMahasiswa::find($id);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan mahasiswa tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nim'            => 'required|string|max:255',
            'th_akademik_id' => 'nullable|numeric',
            'tanggal'        => 'nullable|date',
            'catatan'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data->update([
            'nim'            => strtoupper(trim($request->nim)),
            'th_akademik_id' => $request->th_akademik_id ?: $data->th_akademik_id,
            'tanggal'        => $request->tanggal ?: $data->tanggal,
            'catatan'        => $request->has('catatan') ? $request->catatan : $data->catatan,
        ]);

        $data->load(['th_akademik', 'user', 'tagihan', 'pembayaran']);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Catatan mahasiswa berhasil diperbarui.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganCatatanMahasiswa::find($id);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan mahasiswa tidak ditemukan.',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Catatan mahasiswa berhasil dihapus.',
        ]);
    }
}
