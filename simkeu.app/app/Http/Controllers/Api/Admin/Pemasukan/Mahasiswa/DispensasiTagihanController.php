<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganDispensasi;
use App\Models\KeuanganDispensasiTagihan;
use App\Services\Helper;
use App\Services\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;;

class DispensasiTagihanController extends Controller
{
    public function index(Request $request)
    {
        $query = KeuanganDispensasiTagihan::leftJoin('users', 'keuangan_dispensasi_tagihan.user_id', '=', 'users.id')
            ->leftJoin('keuangan_tagihan', 'keuangan_dispensasi_tagihan.jenis_tagihan_id', '=', 'keuangan_tagihan.id')
            ->leftJoin('th_akademik', 'keuangan_dispensasi_tagihan.th_akademik_id', '=', 'th_akademik.id');
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nim', 'LIKE',"%$request->search%")
                    ->orWhere('keterangan', 'LIKE',"%$request->search%");
            });
        }

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortKey, $sortOrder);

        $query->select('keuangan_dispensasi_tagihan.*', 'users.name as nama', 'keuangan_tagihan.nama as tagihan', 'th_akademik.kode as th_akademik');
        $query = $query->paginate($request->input('limit', 10));
        return response()->json([
            'status' => 'true',
            'data' => $query,
            'message' => 'Data dispensasi Tagihan keuangan berhasil diambil',
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
        Log::info($request->all());
        $validator = Validator::make($request->all(), [
            'th_akademik_id' => 'required|exists:th_akademik,id',
            'nim' => 'required',
            'jumlah' => 'required',
            'batas' => 'required|date',
            'jenis' => 'required',
            'jenis_tagihan_id' => 'required|exists:keuangan_tagihan,id',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ]);
        }
        $idUsers = Auth::user()->id;
        if (is_array($request->jenis_tagihan_id) && is_array($request->jumlah)) {
            foreach ($request->jenis_tagihan_id as $key => $value) {
                $data = KeuanganDispensasiTagihan::create([
                    'th_akademik_id' => $request->th_akademik_id,
                    'nim' => $request->nim,
                    'jenis' => $request->jenis,
                    'jumlah' => $request->jumlah[$key],
                    'batas' => $request->batas,
                    'jenis_tagihan_id' => $value,
                    'user_id' => $idUsers,
                    'keterangan' => $request->keterangan,
                ]);
            }
            return response()->json([
                'status' => 'true',
                'data' => $data,
                'message' => 'Data dispensasi keuangan berhasil disimpan'
            ]);
        }else {
            Log::info('bukan array');
        }
        $data = KeuanganDispensasiTagihan::create([
            'th_akademik_id' => $request->th_akademik_id,
            'nim' => $request->nim,
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'batas' => $request->batas,
            'jenis_tagihan_id' => $request->jenis_tagihan_id,
            'user_id' => $idUsers,
            'keterangan' => $request->keterangan,
        ]);
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data dispensasi keuangan berhasil disimpan'
        ]);
    }
    public function show($id)
    {
        $data = KeuanganDispensasiTagihan::find($id);
        if (!$data) {
            return response()->json([
                'status' => 'false',
                'message' => 'Data dispensasi keuangan tidak ditemukan'
            ]);
        }
        return response()->json([
            'status' => 'true',
            'data' => $data,
            'message' => 'Data dispensasi keuangan berhasil diambil'
        ]);
    }
    public function gabung(Request $request) {
        
        Log::info('masuk join');
        Log::info('join ',$request->all());

        $validator = Validator::make($request->all(), [
            'jenis_tagihan_id' => 'required',
            'id'=>'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ]);
        }

        $data = KeuanganDispensasiTagihan::join('keuangan_tagihan', 'keuangan_dispensasi_tagihan.jenis_tagihan_id', '=', 'keuangan_tagihan.id')->where('keuangan_dispensasi_tagihan.jenis_tagihan_id', $request->jenis_tagihan_id)->where('keuangan_dispensasi_tagihan.id', $request->id)->select('keuangan_dispensasi_tagihan.*','keuangan_tagihan.id as id_tagihan','keuangan_tagihan.nama as nama_tagihan','keuangan_tagihan.jumlah as jumlah_tagihan','keuangan_dispensasi_tagihan.jumlah as jumlah_dispensasi')->get();
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
            'nim' => 'required',
            'jenis' => 'required',
            'jumlah' => 'required',
            'batas' => 'required|date',
            'jenis_tagihan_id' => 'required|exists:keuangan_tagihan,id',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'false',
                'message' => $validator->errors()
            ]);
        }

        $data = KeuanganDispensasiTagihan::find($id);
        if (!$data) {
            return response()->json([
                'status' => 'false',
                'message' => 'Data dispensasi keuangan tidak ditemukan'
            ]);
        }
         $idUsers = Auth::user()->id;
        $data->update([
            'th_akademik_id' => $request->th_akademik_id,
            'nim' => $request->nim,
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'batas' => $request->batas,
            'jenis_tagihan_id' => $request->jenis_tagihan_id,
            'user_id' => $idUsers,
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
        $data = KeuanganDispensasiTagihan::find($id);
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
