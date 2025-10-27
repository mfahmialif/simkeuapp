<?php
namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\KeuanganJenisPembayaran;
use Illuminate\Support\Facades\Validator;

class JenisPembayaranController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = KeuanganJenisPembayaran::query();

        if ($request->filled('nama')) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('nama', 'LIKE', "%$request->search%");
                $q->orWhere('kategori', 'LIKE', "%$request->search%");
            });
        }

        $query->where('kategori', 'LIKE', "%" . Helper::getJenisKelaminUser()->kategori . "%");

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Jenis Pembayaran retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'           => 'required|string|max:255',
            'nomer_rekening' => 'nullable|string|max:255',
            'kategori'       => 'nullable|string|max:255',
            'keterangan'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }

        $data                 = new KeuanganJenisPembayaran();
        $data->nama           = $request->nama;
        $data->nomer_rekening = $request->nomer_rekening;
        $data->kategori       = $request->category;
        $data->keterangan     = $request->keterangan;

        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Jenis Keaungan created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = KeuanganJenisPembayaran::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Jenis Pembayaran Not Found',
            ], 404);
        }

        return response()->json($data, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Validasi data yang dikirimkan
        $validator = Validator::make($request->all(), [
            'nama'           => 'required|string|max:255',
            'nomer_rekening' => 'nullable|string|max:255',
            'kategori'       => 'nullable|string|max:255',
            'keterangan'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganJenisPembayaran::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Jenis Pembayaran Not Found',
            ], 404);
        }

        $data->nama           = $request->nama;
        $data->nomer_rekening = $request->nomer_rekening;
        $data->kategori       = $request->kategori;
        $data->keterangan     = $request->keterangan;
        $data->save();

        // Kembalikan response sukses dengan data data yang telah diperbarui
        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Jenis Pembayaran updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = KeuanganJenisPembayaran::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Jenis Pembayaran Not Found',
            ], 404);
        }

        $data->delete();
        return response()->json([
            'status'  => true,
            'message' => 'Jenis Pembayaran deleted successfully',
        ]);
    }
}
