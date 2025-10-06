<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ref;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RefController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Ref::query();

        // Search
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->orWhere('table', 'LIKE', "%{$term}%")
                    ->orWhere('kode', 'LIKE', "%{$term}%")
                    ->orWhere('nama', 'LIKE', "%{$term}%")
                    ->orWhere('param', 'LIKE', "%{$term}%")
                    ->orWhere('keterangan', 'LIKE', "%{$term}%");
            });
        }

        // Sorting
        $sortable  = ['id', 'table', 'kode', 'nama', 'param', 'keterangan', 'created_at', 'updated_at'];
        $sortKey   = in_array($request->input('sort_key'), $sortable) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        // Pagination
        $data = $query->paginate((int) $request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Referensi retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'table'      => 'required|string|max:255',
            'kode'       => 'required|string|max:255',
            'nama'       => 'required|string|max:255',
            'param'      => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        // Cek duplikat (table + kode harus unik)
        $exists = Ref::where('table', $payload['table'])
            ->where('kode', $payload['kode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Referensi already exists',
            ], 409);
        }

        $data = new Ref();
        $data->fill($payload);
        $data->user_id = auth()->id();
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Referensi created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = Ref::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Referensi Not Found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Referensi retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'table'      => 'required|string|max:255',
            'kode'       => 'required|string|max:255',
            'nama'       => 'required|string|max:255',
            'param'      => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = Ref::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Referensi Not Found',
            ], 404);
        }

        $payload = $validator->validated();

        // Cek duplikat selain record ini
        $exists = Ref::where('id', '!=', $id)
            ->where('table', $payload['table'])
            ->where('kode', $payload['kode'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Referensi with same table & kode already exists',
            ], 409);
        }

        $data->fill($payload);
        $data->user_id = auth()->id();
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => null,
            'message' => 'Referensi updated successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = Ref::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Referensi Not Found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Referensi deleted successfully',
        ]);
    }
}
