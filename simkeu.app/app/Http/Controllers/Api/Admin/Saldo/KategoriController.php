<?php
namespace App\Http\Controllers\Api\Admin\Saldo;

use App\Http\Controllers\Controller;
use App\Models\KeuanganSaldo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KategoriController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganSaldo::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('nama', 'LIKE', "%$request->search%")
                  ->orWhere('kode', 'LIKE', "%$request->search%")
                  ->orWhere('saldo', 'LIKE', "%$request->search%");
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
            'message' => 'Keuangan Saldo retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string|max:255',
            'kode'       => 'required|string|unique:keuangan_saldo,kode|max:255',
            'saldo'      => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = new KeuanganSaldo();
        $data->nama       = $request->nama;
        $data->kode       = $request->kode;
        $data->saldo      = (float) $request->saldo;
        $data->keterangan = $request->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Keuangan Saldo created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = KeuanganSaldo::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo not found',
            ], 404);
        }

        return response()->json($data, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = KeuanganSaldo::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string|max:255',
            'kode'       => 'required|string|unique:keuangan_saldo,kode,' . $id . '|max:255',
            'saldo'      => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data->nama       = $request->nama;
        $data->kode       = $request->kode;
        $data->saldo      = (float) $request->saldo;
        $data->keterangan = $request->keterangan;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Keuangan Saldo updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = KeuanganSaldo::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Keuangan Saldo not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Keuangan Saldo deleted successfully',
        ]);
    }
}
