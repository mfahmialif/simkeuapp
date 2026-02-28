<?php

namespace App\Http\Controllers\Api\Admin\Saldo;

use App\Http\Controllers\Controller;
use App\Models\KeuanganCatatanHarian;
use App\Models\KeuanganSaldo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CatatanHarianController extends Controller
{
    /**
     * Ringkasan statistik catatan harian
     */
    public function summary()
    {
        $totalPemasukan  = KeuanganCatatanHarian::where('tipe', 'pemasukan')->sum('jumlah');
        $totalPengeluaran = KeuanganCatatanHarian::where('tipe', 'pengeluaran')->sum('jumlah');
        $jumlahCatatan   = KeuanganCatatanHarian::count();
        $catatanHariIni  = KeuanganCatatanHarian::whereDate('tanggal', now()->toDateString())->count();
        $totalSaldo      = KeuanganSaldo::sum('saldo');

        return response()->json([
            'status' => true,
            'data'   => [
                'total_pemasukan'   => (float) $totalPemasukan,
                'total_pengeluaran' => (float) $totalPengeluaran,
                'selisih'           => (float) ($totalPemasukan - $totalPengeluaran),
                'total_saldo'       => (float) $totalSaldo,
                'jumlah_catatan'    => $jumlahCatatan,
                'catatan_hari_ini'  => $catatanHariIni,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganCatatanHarian::with('saldo');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('keterangan', 'LIKE', "%$request->search%")
                  ->orWhere('jumlah', 'LIKE', "%$request->search%")
                  ->orWhereHas('saldo', function ($sq) use ($request) {
                      $sq->where('nama', 'LIKE', "%$request->search%");
                  });
            });
        }

        // Filter tipe
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // Filter saldo
        if ($request->filled('saldo_id')) {
            $query->where('saldo_id', $request->saldo_id);
        }

        // Filter tanggal
        if ($request->filled('tanggal_dari')) {
            $query->where('tanggal', '>=', $request->tanggal_dari);
        }
        if ($request->filled('tanggal_sampai')) {
            $query->where('tanggal', '<=', $request->tanggal_sampai);
        }

        $sortKey   = $request->input('sort_key', 'tanggal');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Catatan Harian retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'saldo_id'   => 'required|exists:keuangan_saldo,id',
            'tipe'       => 'required|in:pemasukan,pengeluaran',
            'jumlah'     => 'required|numeric|min:0',
            'tanggal'    => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = KeuanganCatatanHarian::create($request->only([
                'saldo_id', 'tipe', 'jumlah', 'tanggal', 'keterangan',
            ]));

            // Update saldo
            $saldo = KeuanganSaldo::find($request->saldo_id);
            if ($request->tipe === 'pemasukan') {
                $saldo->saldo += (float) $request->jumlah;
            } else {
                $saldo->saldo -= (float) $request->jumlah;
            }
            $saldo->save();

            DB::commit();

            return response()->json([
                'status'  => true,
                'data'    => $data->load('saldo'),
                'message' => 'Catatan Harian created successfully',
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganCatatanHarian::with('saldo')->find($id);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan Harian not found',
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
        $data = KeuanganCatatanHarian::find($id);
        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan Harian not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'saldo_id'   => 'required|exists:keuangan_saldo,id',
            'tipe'       => 'required|in:pemasukan,pengeluaran',
            'jumlah'     => 'required|numeric|min:0',
            'tanggal'    => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Revert old saldo effect
            $oldSaldo = KeuanganSaldo::find($data->saldo_id);
            if ($data->tipe === 'pemasukan') {
                $oldSaldo->saldo -= (float) $data->jumlah;
            } else {
                $oldSaldo->saldo += (float) $data->jumlah;
            }
            $oldSaldo->save();

            // Update record
            $data->update($request->only([
                'saldo_id', 'tipe', 'jumlah', 'tanggal', 'keterangan',
            ]));

            // Apply new saldo effect
            $newSaldo = KeuanganSaldo::find($request->saldo_id);
            if ($request->tipe === 'pemasukan') {
                $newSaldo->saldo += (float) $request->jumlah;
            } else {
                $newSaldo->saldo -= (float) $request->jumlah;
            }
            $newSaldo->save();

            DB::commit();

            return response()->json([
                'status'  => true,
                'data'    => $data->load('saldo'),
                'message' => 'Catatan Harian updated successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganCatatanHarian::find($id);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Catatan Harian not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Revert saldo effect
            $saldo = KeuanganSaldo::find($data->saldo_id);
            if ($data->tipe === 'pemasukan') {
                $saldo->saldo -= (float) $data->jumlah;
            } else {
                $saldo->saldo += (float) $data->jumlah;
            }
            $saldo->save();

            $data->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Catatan Harian deleted successfully',
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
