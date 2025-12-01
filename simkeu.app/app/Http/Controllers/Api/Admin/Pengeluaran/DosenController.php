<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Services\Dosen;
use App\Exports\ExcelExport;
use Illuminate\Http\Request;
use App\Exports\pdf\SlipGajiPdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\KeuanganPengeluaranDosen;
use Illuminate\Support\Facades\Validator;

class DosenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $dosenApi = Dosen::all();

        DB::statement("DROP TEMPORARY TABLE IF EXISTS temp_dosen");

        DB::statement("
            CREATE TEMPORARY TABLE temp_dosen (
                id INT PRIMARY KEY,
                nama VARCHAR(255),
                kode VARCHAR(255),
                nama_prodi VARCHAR(255)
            )
        ");

        foreach ($dosenApi as $d) {
            DB::table('temp_dosen')->insert([
                'id'   => $d->id,
                'nama' => $d->nama,
                'kode' => $d->kode,
                'nama_prodi' => $d->nama_prodi,
            ]);
        }

        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.*',
            'temp_dosen.nama as nama_dosen',
            'temp_dosen.kode as kode_dosen',
            'temp_dosen.nama_prodi as nama_prodi_dosen',
        ]);

        $query->join('temp_dosen', 'temp_dosen.kode', '=', 'keuangan_pengeluaran_dosen.dosen_kode');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_pengeluaran_dosen.transport', 'LIKE', "%$request->search%");
                $q->orWhere('keuangan_pengelauran_dosen.barokah', 'LIKE', "%$request->search%");
                $q->orWhere('keuangan_pengelauran_dosen.total', 'LIKE', "%$request->search%");
                $q->orWhere('temp_dosen.nama_dosen', 'LIKE', "%$request->search%");
                $q->orWhere('temp_dosen.nama_prodi_dosen', 'LIKE', "%$request->search%");
            });
        }

        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_dosen.tanggal', [
                $tanggalMulai,
                $tanggalAkhir
            ]);
        } elseif ($tanggalMulai && !$tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '>=', $tanggalMulai);
        } elseif (!$tanggalMulai && $tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '<=', $tanggalAkhir);
        }

        if ($request->filled('kode')) {
            $query->where('temp_dosen.kode', $request->kode);
        }

        // Sorting biarkan apa adanya
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pengeluaran Dosen retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required',
            'dosen_kode' => 'required',
            'hari' => 'required',
            'jam' => 'required',
            'transport' => 'nullable',
            'barokah' => 'nullable',
            'total' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = new KeuanganPengeluaranDosen();
        $data->tanggal = $request->tanggal;
        $data->dosen_kode = $request->dosen_kode;
        $data->hari = $request->hari;
        $data->jam = $request->jam;
        $data->transport = $request->transport;
        $data->barokah = $request->barokah;
        $data->total = $request->total;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pengeluaran Dosen created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganPengeluaranDosen::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Pengeluaran Dosen not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pengeluaran Dosen retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = KeuanganPengeluaranDosen::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Pengeluaran Dosen not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tanggal' => 'required',
            'hari' => 'required',
            'jam' => 'required',
            'transport' => 'nullable',
            'barokah' => 'nullable',
            'total' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data->tanggal       = $request->tanggal;
        $data->hari       = $request->hari;
        $data->jam       = $request->jam;
        $data->transport      = $request->transport;
        $data->barokah      = $request->barokah;
        $data->total      = $request->total;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pengeluaran Dosen updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganPengeluaranDosen::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Pengeluaran Dosen not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Pengeluaran Dosen deleted successfully',
        ]);
    }

    public function printSlip($id)
    {
        $data = KeuanganPengeluaranDosen::findOrFail($id);
        $dosen = Dosen::kode($data->dosen_kode);
        $data->dosen = $dosen;
        return SlipGajiPdf::pdf($data);
    }

    public function exportExcel(Request $request)
    {
        $dosenApi = Dosen::all();

        DB::statement("DROP TEMPORARY TABLE IF EXISTS temp_dosen");

        DB::statement("
            CREATE TEMPORARY TABLE temp_dosen (
                id INT PRIMARY KEY,
                nama VARCHAR(255),
                kode VARCHAR(255),
                nama_prodi VARCHAR(255)
            )
        ");

        foreach ($dosenApi as $d) {
            DB::table('temp_dosen')->insert([
                'id'   => $d->id,
                'nama' => $d->nama,
                'kode' => $d->kode,
                'nama_prodi' => $d->nama_prodi,
            ]);
        }

        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.tanggal',
            'temp_dosen.nama as dosen',
            'temp_dosen.kode as niy',
            'temp_dosen.nama_prodi as prodi',
            'keuangan_pengeluaran_dosen.jam',
            'keuangan_pengeluaran_dosen.hari',
            'keuangan_pengeluaran_dosen.transport',
            'keuangan_pengeluaran_dosen.barokah',
            'keuangan_pengeluaran_dosen.total',
        ]);

        $query->join('temp_dosen', 'temp_dosen.kode', '=', 'keuangan_pengeluaran_dosen.dosen_kode');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_pengeluaran_dosen.transport', 'LIKE', "%$request->search%");
                $q->orWhere('keuangan_pengelauran_dosen.barokah', 'LIKE', "%$request->search%");
                $q->orWhere('keuangan_pengelauran_dosen.total', 'LIKE', "%$request->search%");
                $q->orWhere('temp_dosen.nama_dosen', 'LIKE', "%$request->search%");
                $q->orWhere('temp_dosen.nama_prodi_dosen', 'LIKE', "%$request->search%");
            });
        }

        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_dosen.tanggal', [
                $tanggalMulai,
                $tanggalAkhir
            ]);
        } elseif ($tanggalMulai && !$tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '>=', $tanggalMulai);
        } elseif (!$tanggalMulai && $tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '<=', $tanggalAkhir);
        }

        if ($request->filled('kode')) {
            $query->where('temp_dosen.kode', $request->kode);
        }

        $data = $query->get();
        return Excel::download(new ExcelExport($data), 'Laporan.xlsx');
    }
}
