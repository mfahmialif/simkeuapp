<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ThAkademikController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ThAkademik::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('kode', 'LIKE', "%$request->search%");
                $q->orWhere('nama', 'LIKE', "%$request->search%");
                $q->orWhere('semester', 'LIKE', "%$request->search%");
            });
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'ThAkademik retrieved successfully',
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
            'kode'     => 'required|string|unique:th_akademik,kode|max:255',
            'nama'     => 'required|string|max:255',
            'semester' => 'required|string|in:Ganjil,Genap',
            'aktif'    => 'nullable|string|in:Y,T|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }

        $data = ThAkademik::firstOrCreate(
            $request->except('_method', '_token', 'kode', 'aktif'),
            array_merge(
                $request->except(['_method', '_token']),
                ['user_id' => auth()->id()]
            )
        );

        if (! $data->wasRecentlyCreated) {
            return response()->json([
                'status'  => false,
                'message' => 'ThAkademik already exists',
            ], 409);
        }

        return response()->json([
            'status'  => true,
            // 'data'    => $data,
            'message' => 'ThAkademik created successfully',
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
        return response()->json(ThAkademik::find($id), 200);
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
        $validator = Validator::make($request->all(), [
            'kode'     => 'required|string|unique:th_akademik,kode,' . $id . '|max:255',
            'nama'     => 'required|string|max:255',
            'semester' => 'required|string|in:Ganjil,Genap',
            'aktif'    => 'nullable|string|in:Y,T|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = ThAkademik::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'ThAkademik Not Found',
            ], 404);
        }

        $check = ThAkademik::where([
            'id'       => ['!=', $id],
            'nama'     => $request->nama,
            'semester' => $request->semester,
        ])->exists();

        if ($check) {
            return response()->json([
                'status'  => true,
                'data'    => $data,
                'message' => 'ThAkademik sudah ada, silahkan edit',
            ], 409);
        }

        $data->fill(array_merge(
            $request->except(['_method', '_token']),
            ['user_id' => auth()->id()]
        ))->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'ThAkademik updated successfully',
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = ThAkademik::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'ThAkademik Not Found',
            ], 404);
        }

        $data->delete();
        return response()->json([
            'status'  => true,
            'message' => 'ThAkademik deleted successfully',
        ]);
    }
}
