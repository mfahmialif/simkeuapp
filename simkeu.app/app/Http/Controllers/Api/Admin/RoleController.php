<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Role::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', "%$request->search%");
            });
        }

        if ($user->role->name === 'staff') {
            $query->where('name', '!=', 'admin');
            $query->where('name', '!=', 'staff');
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);

        $role = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $role,
            'message' => 'Role retrieved successfully',
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
        $rules = [
            'name'       => 'required|string|unique:role,name|max:255',
            'keterangan' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }
        $role             = new Role();
        $role->name       = $request->name;
        $role->keterangan = $request->keterangan;

        $role->save();

        return response()->json([
            'status'  => true,
            'data'    => $role,
            'message' => 'Role created successfully',
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
        return response()->json(Role::find($id), 200);
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
        $rules = [
            'name'       => 'required|unique:role,name,' . $id,
            'keterangan' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        // Mencari role berdasarkan ID
        $role = Role::find($id);

        // Jika role tidak ditemukan, kembalikan response 404
        if (! $role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role Not Found',
            ], 404);
        }

        $role             = Role::find($id);
        $role->name       = $request->name;
        $role->keterangan = $request->keterangan;
        $role->save();

        // Kembalikan response sukses dengan data role yang telah diperbarui
        return response()->json([
            'status'  => true,
            'data'    => $role,
            'message' => 'Role updated successfully',
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
        $role = Role::find($id);

        if (! $role) {
            return response()->json([
                'status'  => false,
                'message' => 'Role Not Found',
            ], 404);
        }

        $role->delete();
        return response()->json([
            'status'  => true,
            'message' => 'Role deleted successfully',
        ]);
    }
}
