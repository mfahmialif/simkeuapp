<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use Carbon\Carbon;
use App\Models\Prodi;
use App\Services\Jadwal;
use Illuminate\Http\Request;
use App\Exports\UasSusulanExport;
use App\Models\KeuanganUasSusulan;
use App\Http\Controllers\Controller;
use App\Models\KeuanganUasSusulanMk;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class UasSusulanController extends Controller
{
    // GET /keuangan/uas-susulan
    public function index(Request $request)
    {
        $q = KeuanganUasSusulan::query();
        $q->select('keuangan_uas_susulan.*', 'th_akademik.nama as th_akademik_nama', 'th_akademik.kode as th_akademik_kode', 'th_akademik.semester as th_akademik_semester');
        $q->join('th_akademik', 'keuangan_uas_susulan.th_akademik_id', 'th_akademik.id');

        // Pencarian umum
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($x) use ($s) {
                $x->where('nim', 'like', "%{$s}%")
                    ->orWhere('tanggal', 'like', "%{$s}%")
                    ->orWhere('th_akademik.nama', 'like', "%{$s}%")
                    ->orWhere('th_akademik.kode', 'like', "%{$s}%")
                    ->orWhere('th_akademik.semester', 'like', "%{$s}%");
            });
        }

        // Filter spesifik (opsional)
        if ($request->filled('th_akademik_id'))       $q->where('th_akademik_id', $request->th_akademik_id);

        // Sorting (whitelist)
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed   = ['id', 'tanggal', 'nim', 'keterangan', 'th_akademik_id', 'created_at', 'updated_at', 'th_akademik_nama', 'th_akademik_kode', 'th_akademik_semester'];
        if (!in_array($sortKey, $allowed, true)) $sortKey = 'id';

        $q->orderBy($sortKey, $sortOrder);

        $data = $q->paginate($request->integer('limit') ?: 10);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data uas-susulan berhasil diambil.',
        ]);
    }

    // POST /keuangan/uas-susulan
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'          => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::create([
            'tanggal'      => $request->tanggal,
            'nim'          => $request->nim,
            'th_akademik_id' => $request->th_akademik_id,
            'keterangan'   => $request->keterangan,
            'user_id'      => Auth::user()->id,
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik'),
            'message' => 'Uas Susulan berhasil dibuat.',
        ], 201);
    }


    // POST /keuangan/uas-susulan/full
    public function storeFull(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'          => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
            'jadwal_kuliah_id' => 'required|array'
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::create([
            'tanggal'      => $request->tanggal,
            'nim'          => $request->nim,
            'th_akademik_id' => $request->th_akademik_id,
            'keterangan'   => $request->keterangan,
            'user_id'      => Auth::user()->id,
        ]);

        foreach ($request->jadwal_kuliah_id as $jadwal_kuliah_id) {
            KeuanganUasSusulanMk::create([
                'uas_susulan_id' => $data->id,
                'jadwal_kuliah_id' => $jadwal_kuliah_id,
                'user_id' => Auth::user()->id,
            ]);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil dibuat.',
        ], 201);
    }


    // GET /keuangan/uas-susulan/{id}
    public function show($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }
        $data->load('th_akademik', 'uasSusulanMk');
        return response()->json($data, 200);
    }

    // PUT/PATCH /keuangan/uas-susulan/{id}
    public function update(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'           => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        $data->tanggal      = $request->tanggal;
        $data->nim          = $request->nim;
        $data->th_akademik_id = $request->th_akademik_id;
        $data->keterangan   = $request->keterangan;
        $data->user_id      = Auth::user()->id;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil diperbarui.',
        ]);
    }

    // PUT/PATCH /keuangan/uas-susulan/full/{id}
    public function updateFull(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'           => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
            'jadwal_kuliah_id' => 'required|array'
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        $data->tanggal      = $request->tanggal;
        $data->nim          = $request->nim;
        $data->th_akademik_id = $request->th_akademik_id;
        $data->keterangan   = $request->keterangan;
        $data->user_id      = Auth::user()->id;
        $data->save();

        // Hapus semua record yang terkait
        KeuanganUasSusulanMk::where('uas_susulan_id', $id)->delete();

        // Simpan ulang jadwal kuliah yang baru
        foreach ($request->jadwal_kuliah_id as $jadwal_kuliah_id) {
            KeuanganUasSusulanMk::create([
                'uas_susulan_id' => $data->id,
                'jadwal_kuliah_id' => $jadwal_kuliah_id,
                'user_id' => Auth::user()->id,
            ]);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil diperbarui.',
        ]);
    }


    // DELETE /keuangan/uas-susulan/{id}
    public function destroy($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Uas Susulan berhasil dihapus.',
        ]);
    }

    // DELETE /keuangan/uas-susulan/full/{id}
    public function destroyFull($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        // Hapus semua record yang terkait
        KeuanganUasSusulanMk::where('uas_susulan_id', $id)->delete();

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Uas Susulan berhasil dihapus.',
        ]);
    }

    public function getJadwalKuliah(Request $request)
    {
        $data = Jadwal::mahasiswa($request->nim, $request->th_akademik_id);
        return response()->json($data);
    }

    public function excel(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                "tanggal_print" => 'required',
                "prodi_id_print" => 'required',
            ]);

            $data = [
                'message' => 200,
                'data' => $dataValidated,
            ];

            $prodi = 'SEMUA PRODI';

            if($request->prodi_id_print != '*'){
                $prodi = Prodi::find($request->prodi_id_print);
                $prodi = $prodi->alias;
            }

            $tanggal = Carbon::create($request->tanggal_print)->format('d-m-Y');

            return Excel::download(new UasSusulanExport($data), "UAS SUSULAN $tanggal $prodi.xlsx");

        } catch (\Throwable $th) {
            $data = [
                'message' => 500,
                'data' => $th->getMessage(),
                'req' => $request->all(),
            ];
        }
        return $data;

    }
}
