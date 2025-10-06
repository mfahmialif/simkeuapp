<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSchadule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormSchaduleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Query params (opsional):
     * - search       : cari di kode/nama/semester
     * - sort_key     : default 'id'
     * - sort_order   : 'asc'|'desc' (default 'desc')
     * - limit        : default 10
     * - aktif        : filter 'Y' atau 'T'
     * - start_date   : filter tgl_mulai >= (format Y-m-d atau Y-m-d H:i:s)
     * - end_date     : filter tgl_selesai <= (format Y-m-d atau Y-m-d H:i:s)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = FormSchadule::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->orWhere('kode', 'LIKE', "%{$search}%")
                  ->orWhere('nama', 'LIKE', "%{$search}%")
                  ->orWhere('semester', 'LIKE', "%{$search}%");
            });
        }

        // Filter aktif
        if ($request->filled('aktif')) {
            $aktif = strtoupper($request->aktif);
            if (in_array($aktif, ['Y', 'T'])) {
                $query->where('aktif', $aktif);
            }
        }

        // Filter tanggal (opsional)
        if ($request->filled('start_date')) {
            $query->where('tgl_mulai', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('tgl_selesai', '<=', $request->input('end_date'));
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'
        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'FormSchadule retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Body:
     * - kode (required, unique)
     * - nama (required)
     * - tgl_mulai (nullable, date)
     * - tgl_selesai (nullable, date >= tgl_mulai)
     * - semester (nullable, string)
     * - aktif (nullable, in:Y,T; default Y)
     */
    public function store(Request $request)
    {
        $rules = [
            'kode'        => 'required|string|unique:form_schadule,kode|max:255',
            'nama'        => 'required|string|max:255',
            'tgl_mulai'   => 'nullable|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'semester'    => 'nullable|string|max:255',
            'aktif'       => 'nullable|in:Y,T',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $item              = new FormSchadule();
        $item->kode        = $request->kode;
        $item->nama        = $request->nama;
        $item->tgl_mulai   = $request->tgl_mulai;
        $item->tgl_selesai = $request->tgl_selesai;
        $item->semester    = $request->semester;
        $item->aktif       = $request->aktif ?? 'Y';
        $item->user_id     = $request->user()->id ?? $request->input('user_id'); // fallback jika dipost manual
        $item->save();

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'FormSchadule created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $item = FormSchadule::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'FormSchadule Not Found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'FormSchadule retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * Body:
     * - kode (required, unique except current)
     * - nama (required)
     * - tgl_mulai (nullable, date)
     * - tgl_selesai (nullable, date >= tgl_mulai)
     * - semester (nullable, string)
     * - aktif (nullable, in:Y,T)
     */
    public function update(Request $request, $id)
    {
        $rules = [
            // contoh unique sesuai gaya kamu sebelumnya:
            // 'kode' => 'required|string|unique:th_akademik,kode,' . $id . '|max:255',
            // disesuaikan untuk tabel ini:
            'kode'        => 'required|string|unique:form_schadule,kode,' . $id . '|max:255',
            'nama'        => 'required|string|max:255',
            'tgl_mulai'   => 'nullable|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'semester'    => 'nullable|string|max:255',
            'aktif'       => 'nullable|in:Y,T',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $item = FormSchadule::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'FormSchadule Not Found',
            ], 404);
        }

        $item->kode        = $request->kode;
        $item->nama        = $request->nama;
        $item->tgl_mulai   = $request->tgl_mulai;
        $item->tgl_selesai = $request->tgl_selesai;
        $item->semester    = $request->semester;
        if ($request->filled('aktif')) {
            $item->aktif = $request->aktif;
        }
        // opsional: jangan timpa user_id saat update kecuali dikirim eksplisit
        if ($request->filled('user_id')) {
            $item->user_id = $request->input('user_id');
        }

        $item->save();

        return response()->json([
            'status'  => true,
            'data'    => $item,
            'message' => 'FormSchadule updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $item = FormSchadule::find($id);

        if (! $item) {
            return response()->json([
                'status'  => false,
                'message' => 'FormSchadule Not Found',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'status'  => true,
            'message' => 'FormSchadule deleted successfully',
        ]);
    }
}
