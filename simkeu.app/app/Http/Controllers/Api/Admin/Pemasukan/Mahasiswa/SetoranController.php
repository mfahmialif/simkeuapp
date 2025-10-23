<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use Illuminate\Http\Request;
use App\Models\KeuanganSetoran;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SetoranController extends Controller
{
    // GET /keuangan/setoran
    public function index(Request $request)
    {
        $q = KeuanganSetoran::query();

        // Pencarian umum
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($x) use ($s) {
                $x->where('keterangan', 'like', "%{$s}%")
                    ->orWhere('status', 'like', "%{$s}%")
                    ->orWhere('kategori', 'like', "%{$s}%")
                    ->orWhere('user_id', $s)
                    ->orWhere('validator_id', $s);
            });
        }

        // Filter spesifik (opsional)
        if ($request->filled('user_id'))       $q->where('user_id', $request->user_id);
        if ($request->filled('validator_id'))  $q->where('validator_id', $request->validator_id);
        if ($request->filled('status'))        $q->where('status', $request->status);
        if ($request->filled('kategori'))      $q->where('kategori', $request->kategori);
        if ($request->filled('date_from'))     $q->whereDate('tanggal', '>=', $request->date_from);
        if ($request->filled('date_to'))       $q->whereDate('tanggal', '<=', $request->date_to);

        // Sorting (whitelist)
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed   = ['id', 'tanggal', 'user_id', 'validator_id', 'jumlah', 'status', 'kategori', 'created_at', 'updated_at'];
        if (!in_array($sortKey, $allowed, true)) $sortKey = 'id';

        $q->orderBy($sortKey, $sortOrder);

        $data = $q->paginate($request->integer('limit') ?: 10);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data setoran berhasil diambil.',
        ]);
    }

    // POST /keuangan/setoran
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'jumlah'       => 'required|numeric',
            'kategori'     => 'required|string|max:255',
            'keterangan'   => 'nullable|string',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $user = Auth::user();
        $data = KeuanganSetoran::create([
            'tanggal'      => $request->tanggal,
            'jumlah'       => $request->jumlah,
            'kategori'     => $request->kategori,
            'keterangan'   => $request->keterangan,
            'user_id'      => $user->id,
            'validator_id' => $user->id,
            'status'       => 'Belum Divalidasi',
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Setoran berhasil dibuat.',
        ], 201);
    }

    // GET /keuangan/setoran/{id}
    public function show($id)
    {
        $data = KeuanganSetoran::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Setoran tidak ditemukan.'], 404);
        }
        return response()->json($data, 200);
    }

    // PUT/PATCH /keuangan/setoran/{id}
    public function update(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'jumlah'       => 'required|numeric',
            'kategori'     => 'required|string|max:255',
            'keterangan'   => 'nullable|string',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganSetoran::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Setoran tidak ditemukan.'], 404);
        }

        $data->update($request->only([
            'tanggal',
            'jumlah',
            'kategori',
            'keterangan'
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Setoran berhasil diperbarui.',
        ]);
    }

    // DELETE /keuangan/setoran/{id}
    public function destroy($id)
    {
        $data = KeuanganSetoran::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Setoran tidak ditemukan.'], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Setoran berhasil dihapus.',
        ]);
    }

    // PUT/PATCH /keuangan/setoran/{id}/validasi
    public function validasi(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'status'      => 'required|string|max:255',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganSetoran::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Setoran tidak ditemukan.'], 404);
        }

        $data->update($request->only([
            'status'
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Setoran berhasil diperbarui.',
        ]);
    }

}
