<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganDispensasi;
use App\Services\Helper;
use App\Services\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;;

class DispensasiTagihanController extends Controller
{
    public function index(Request $request)
    {
        $query = KeuanganDispensasi::join('users', 'keuangan_dispensasi.user_id', '=', 'users.id');
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nim', 'like', '%' . $request->search . '%')
                    ->orWhere('keterangan', 'like', '%' . $request->search . '%')
                    ->orWhere('th_akademik_id', 'like', '%' . $request->search . '%');
            });
        }

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortKey, $sortOrder);

        $query->select('keuangan_dispensasi.*', 'users.name as nama');
        $query = $query->paginate($request->input('limit', 10));
        return response()->json([
            'status' => 'true',
            'data' => $query,
            'message' => 'Data dispensasi keuangan berhasil diambil',
            'jk' => Helper::getJenisKelaminUser()
        ]);
    }
    public function autoComplete(Request $request)
    {
        $data = Mahasiswa::all(null, null, $request->search, null, null, null, null);
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data mahasiswa berhasil diambil'
        ]);
    }
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'th_akademik_id' => 'required|exists:th_akademik,id',
            'nim' => 'required|exists:users,nim',
            'jumlah' => 'required|numeric',
            'batas' => 'required|date',
            'tagihan_id' => 'required|exists:keuangan_tagihan,id',
            'user_id' => 'required|exists:users,id',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ]);
        }

        $data = KeuanganDispensasi::create([
            'th_akademik_id' => $request->th_akademik_id,
            'nim' => $request->nim,
            'jumlah' => $request->jumlah,
            'batas' => $request->batas,
            'tagihan_id' => $request->tagihan_id,
            'user_id' => $request->user_id,
            'keterangan' => $request->keterangan,
        ]);
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data dispensasi keuangan berhasil disimpan'
        ]);
    }
    public function edit($id)
    {
        $data = KeuanganDispensasi::find($id);
        if (!$data) {
            return response()->json([
                'status' => 'false',
                'message' => 'Data dispensasi keuangan tidak ditemukan'
            ], 404);
        }
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data dispensasi keuangan berhasil diambil'
        ]);
    }
    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'th_akademik_id' => 'required|exists:th_akademik,id',
            'nim' => 'required|exists:users,nim',
            'jumlah' => 'required|numeric',
            'batas' => 'required|date',
            'tagihan_id' => 'required|exists:keuangan_tagihan,id',
            'user_id' => 'required|exists:users,id',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ]);
        }

        $data = KeuanganDispensasi::find($id);
        if (!$data) {
            return response()->json([
                'status' => 'false',
                'message' => 'Data dispensasi keuangan tidak ditemukan'
            ]);
        }

        $data->update([
            'th_akademik_id' => $request->th_akademik_id,
            'nim' => $request->nim,
            'jumlah' => $request->jumlah,
            'batas' => $request->batas,
            'tagihan_id' => $request->tagihan_id,
            'user_id' => $request->user_id,
            'keterangan' => $request->keterangan,
        ]);
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data dispensasi keuangan berhasil diupdate'
        ]);
    }
    public function destroy($id)
    {
        $data = KeuanganDispensasi::find($id);
        if (!$data) {
            return response()->json([
                'status' => 'false',
                'message' => 'Data dispensasi keuangan tidak ditemukan'
            ], 404);
        }
        $data->delete();
        return response()->json([
            'status' => 'true',
            'message' => 'Data dispensasi keuangan berhasil dihapus'
        ]);
    }
}
