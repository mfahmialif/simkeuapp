<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProdiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Prodi::query();

        // SEARCH
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->orWhere('kode', 'LIKE', "%{$term}%")
                    ->orWhere('nama', 'LIKE', "%{$term}%")
                    ->orWhere('alias', 'LIKE', "%{$term}%")
                    ->orWhere('jenjang', 'LIKE', "%{$term}%")
                    ->orWhere('akreditasi', 'LIKE', "%{$term}%")
                    ->orWhere('nama_kepala', 'LIKE', "%{$term}%")
                    ->orWhere('aktif', 'LIKE', "%{$term}%");
            });
        }

        // SORTING (whitelist)
        $sortable  = ['id', 'kode', 'nama', 'alias', 'jenjang', 'aktif', 'akreditasi', 'max_sks_skripsi', 'created_at', 'updated_at'];
        $sortKey   = in_array($request->input('sort_key'), $sortable) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        // PAGINATION
        $data = $query->paginate((int) $request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Prodi retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * (tanpa firstOrCreate — cek duplikat manual, lalu create)
     */
    public function store(Request $request)
    {
        // VALIDATION
        $validator = Validator::make($request->all(), [
            'kode'            => 'required|string|max:255|unique:prodi,kode',
            'konim'           => 'nullable|string|max:255',
            'alias'           => 'nullable|string|max:255',
            'nama'            => 'required|string|max:255',
            'aktif'           => 'nullable|string|in:Y,N|max:1',
            'jenjang'         => 'nullable|string|max:255',
            'nidn_kepala'     => 'nullable|string|max:255',
            'nama_kepala'     => 'nullable|string|max:255',
            'akreditasi'      => 'nullable|string|max:255',
            'color'           => 'nullable|string|max:10',
            'max_sks_skripsi' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        // CEK DUPLIKAT (tanpa 'kode' & 'aktif' sebagai kondisi unik)
        // Misal: kombinasi 'nama' + 'jenjang' + (opsional) 'alias'
        $duplicate = Prodi::where('nama', $payload['nama'])
            ->when(isset($payload['jenjang']), fn($q) => $q->where('jenjang', $payload['jenjang']))
            ->when(isset($payload['alias']), fn($q) => $q->where('alias', $payload['alias']))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => false,
                'message' => 'Prodi already exists',
            ], 409); // Conflict
        }

        // CREATE
        $data = new Prodi();
        $data->fill($payload);
        $data->user_id = auth()->id();
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Prodi created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = Prodi::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Prodi Not Found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Prodi retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     * (tanpa firstOrCreate — find + cek duplikat + update)
     */
    public function update(Request $request, $id)
    {
        // VALIDATION
        $validator = Validator::make($request->all(), [
            'kode'            => 'required|string|max:255|unique:prodi,kode,' . $id,
            'konim'           => 'nullable|string|max:255',
            'alias'           => 'nullable|string|max:255',
            'nama'            => 'required|string|max:255',
            'aktif'           => 'nullable|string|in:Y,N|max:1',
            'jenjang'         => 'nullable|string|max:255',
            'nidn_kepala'     => 'nullable|string|max:255',
            'nama_kepala'     => 'nullable|string|max:255',
            'akreditasi'      => 'nullable|string|max:255',
            'color'           => 'nullable|string|max:10',
            'max_sks_skripsi' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = Prodi::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Prodi Not Found',
            ], 404);
        }

        $payload = $validator->validated();

        // CEK DUPLIKAT selain diri sendiri
        $duplicate = Prodi::where('id', '!=', $id)
            ->where('nama', $payload['nama'])
            ->when(isset($payload['jenjang']), fn($q) => $q->where('jenjang', $payload['jenjang']))
            ->when(isset($payload['alias']), fn($q) => $q->where('alias', $payload['alias']))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => false,
                'message' => 'Prodi dengan kombinasi tersebut sudah ada, silakan periksa kembali.',
            ], 409);
        }

        // UPDATE
        $data->fill($payload);
        $data->user_id = auth()->id();
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Prodi updated successfully',
        ], 201); // gunakan 200 kalau ingin lebih RESTful
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = Prodi::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Prodi Not Found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Prodi deleted successfully',
        ]);
    }
}
