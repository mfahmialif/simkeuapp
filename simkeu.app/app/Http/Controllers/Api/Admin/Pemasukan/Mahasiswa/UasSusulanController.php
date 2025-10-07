<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganUasSusulan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UasSususlanController extends Controller
{
    // GET /keuangan/uas-susulan
    public function index(Request $request)
    {
        $q = KeuanganUasSusulan::query();

        $q->join('th_akademik', 'keuangan_uas_susulan.th_akademik_id', '=', 'th_akademik.id');

        // Pencarian umum
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($x) use ($s) {
                $x->where('keuangan_uas_susulan.nim', 'like', "%{$s}%")
                  ->orWhere('keuangan_uas_susulan.tanggal', 'like', "%{$s}%")
                  ->orWhere('th_akademik.kode', 'like', "%{$s}%");
            });
        }

        // Filter opsional
        if ($request->filled('th_akademik_id')) $q->where('keuangan_uas_susulan.th_akademik_id', $request->th_akademik_id);
        if ($request->filled('user_id'))        $q->where('keuangan_uas_susulan.user_id', $request->user_id);
        if ($request->filled('date_from'))      $q->whereDate('keuangan_uas_susulan.tanggal', '>=', $request->date_from);
        if ($request->filled('date_to'))        $q->whereDate('keuangan_uas_susulan.tanggal', '<=', $request->date_to);

        $q->select('keuangan_uas_susulan.*','th_akademik.kode as th_akademik_kide');

        // Sorting (whitelist)
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        
        $q->orderBy($sortKey, $sortOrder);

        $data = $q->paginate($request->integer('limit') ?: 10);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data UAS susulan berhasil diambil.',
        ]);
    }

    // POST /keuangan/uas-susulan
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'th_akademik_id' => 'required|integer',
            'tanggal'        => 'required|date',
            'nim'            => 'required|string|max:255',
            'keterangan'     => 'nullable|string',
            'user_id'        => 'required|integer',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::create($request->only([
            'th_akademik_id','tanggal','nim','keterangan','user_id',
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data UAS susulan berhasil dibuat.',
        ], 201);
    }

    // GET /keuangan/uas-susulan/{id}
    public function show($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (! $data) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }
        return response()->json($data, 200);
    }

    // PUT/PATCH /keuangan/uas-susulan/{id}
    public function update(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'th_akademik_id' => 'required|integer',
            'tanggal'        => 'required|date',
            'nim'            => 'required|string|max:255',
            'keterangan'     => 'nullable|string',
            'user_id'        => 'required|integer',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::find($id);
        if (! $data) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $data->update($request->only([
            'th_akademik_id','tanggal','nim','keterangan','user_id',
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data UAS susulan berhasil diperbarui.',
        ]);
    }

    // DELETE /keuangan/uas-susulan/{id}
    public function destroy($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (! $data) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Data UAS susulan berhasil dihapus.',
        ]);
    }
}
