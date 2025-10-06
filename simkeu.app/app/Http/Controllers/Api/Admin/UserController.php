<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = User::with('role', 'prodi');

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('prodi_id')) {
            $query->where('prodi_id', $request->prodi_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('username', 'LIKE', "%$request->search%");
                $q->orWhere('name', 'LIKE', "%$request->search%");
                $q->orWhere('email', 'LIKE', "%$request->search%");
                $q->orWhere('jenis_kelamin', 'LIKE', "%$request->search%");
            });
        }

        $query->selectRaw('*, CONCAT("' . asset('avatar') . '/", avatar) as avatar_url');

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);

        $users = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $users,
            'message' => 'Users retrieved successfully',
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
            'fullname'              => 'nullable|string|max:255',
            'name'                  => 'required|string|max:255',
            'jenis_kelamin'         => 'required|string|max:255',
            'username'              => 'required|unique:users,username',
            'email'                 => 'nullable|email|unique:users,email',
            'hp'                    => 'nullable|string|max:255',
            'role_id'               => 'required|exists:role,id',
            'prodi_id'              => 'nullable|exists:prodi,id',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
            'avatar'                => 'nullable|mimes:jpeg,png,jpg,gif,webp,ico|max:' . (1024 * 5),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }

        $user                = new User();
        $user->fullname      = $request->fullname;
        $user->username      = $request->username;
        $user->name          = $request->name;
        $user->email         = $request->email;
        $user->role_id       = $request->role_id;
        $user->jenis_kelamin = $request->jenis_kelamin;
        $user->hp            = $request->hp;
        $user->password      = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $file        = $request->file('avatar');
            $extension   = $file->getClientOriginalExtension();
            $filename    = $request->username . '-' . time() . '.' . $extension;
            $destination = public_path('avatar');

            if (! file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $filename);
            $user->avatar = $filename;
        }

        $user->save();

        return response()->json([
            'status'  => true,
            'data'    => $user->load('role'),
            'message' => 'User created successfully',
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
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'User Not Found',
            ], 404);
        }

        return response()->json($user->load('role', 'prodi'), 200);
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
        // return [
        //     'status'  => false,
        //     'message' => $request->all(),
        // ];
        // Validasi data yang dikirimkan
        $rules = [
            'fullname'      => 'nullable|string|max:255',
            'name'          => 'required|string|max:255',
            'jenis_kelamin' => 'required|string|max:255',
            'hp'            => 'nullable|string|max:255',
            'username'      => 'required|unique:users,username,' . $id,
            'email'         => 'nullable|email|unique:users,email,' . $id,
            'role_id'       => 'required|exists:role,id',
            'prodi_id'      => 'nullable|exists:prodi,id',
            'avatar'        => 'nullable|mimes:jpeg,png,jpg,gif,webp,ico|max:' . (1024 * 5),
        ];

        if ($request->filled('password')) {
            $rules['password']              = 'string|min:5|confirmed';
            $rules['password_confirmation'] = 'required|string|min:5';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        // Mencari user berdasarkan ID
        $user = User::find($id);

        // Jika user tidak ditemukan, kembalikan response 404
        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'User Not Found',
            ], 404);
        }

        $user                = User::find($id);
        $user->fullname      = $request->fullname;
        $user->username      = $request->username;
        $user->name          = $request->name;
        $user->email         = $request->email;
        $user->hp            = $request->hp;
        $user->role_id       = $request->role_id;
        $user->prodi_id      = $request->prodi_id;
        $user->jenis_kelamin = $request->jenis_kelamin;
        $user->password      = $request->password ? bcrypt($request->password) : $user->password;

        if ($request->hasFile('avatar')) {
            $file        = $request->file('avatar');
            $extension   = $file->getClientOriginalExtension();
            $filename    = $request->username . '-' . time() . '.' . $extension;
            $destination = public_path('avatar');

            if (! file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            if ($user->avatar && file_exists(public_path('avatar/' . $user->avatar))) {
                unlink(public_path('avatar/' . $user->avatar));
            }

            $file->move($destination, $filename);
            $user->avatar = $filename;
        }

        $user->save();

        // Kembalikan response sukses dengan data user yang telah diperbarui
        return response()->json([
            'status'  => true,
            'data'    => $user->load('role'),
            'message' => 'User updated successfully',
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
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'User Not Found',
            ], 404);
        }

        $user->delete();
        return response()->json([
            'status'  => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
