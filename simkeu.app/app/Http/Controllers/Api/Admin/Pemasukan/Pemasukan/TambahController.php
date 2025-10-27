<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Pemasukan;

use App\Http\Controllers\Controller;
use App\Models\KeuanganSaldoPemasukan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TambahController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganSaldoPemasukan::join('keuangan_saldo', 'keuangan_saldo_pemasukan.saldo_id', '=', 'keuangan_saldo.id');
        $query->select('keuangan_saldo_pemasukan.*', 'keuangan_saldo.nama as saldo_nama', 'keuangan_saldo.kode as saldo_kode', 'keuangan_saldo.saldo as saldo_saldo');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_saldo.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_saldo.kode', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_saldo.saldo', 'LIKE', "%$request->search%");
            });
        }

        // Sorting biarkan apa adanya
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Keuangan Saldo Pemasukan retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'saldo_id'       => 'required|exists:keuangan_saldo,id',
            'jumlah'      => 'required|numeric|min:0',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = new KeuanganSaldoPemasukan();
        $data->saldo_id       = $request->saldo_id;
        $data->jumlah      = (float) $request->jumlah;
        $data->tanggal = $request->tanggal;
        $data->keterangan = $request->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Keuangan Saldo Pemasukan created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganSaldoPemasukan::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo Pemasukan not found',
            ], 404);
        }

        return response()->json($data, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = KeuanganSaldoPemasukan::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo Pemasukan not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'saldo_id'       => 'required|exists:keuangan_saldo,id',
            'jumlah'      => 'required|numeric|min:0',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data->saldo_id       = $request->saldo_id;
        $data->jumlah      = (float) $request->jumlah;
        $data->tanggal = $request->tanggal;
        $data->keterangan = $request->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Keuangan Saldo Pemasukan updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganSaldoPemasukan::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo Pemasukan not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Keuangan Saldo Pemasukan deleted successfully',
        ]);
    }
}
