<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // LOGIN
    public function login(Request $request)
    {
        $rules = [
            'username' => 'required',
            'password' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if (! Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = Auth::user();

        // Hapus token lama jika mau 1 sesi saja
        // $user->tokens()->delete();

        $abilities = [];

        if ($user->role->name === 'admin' || $user->role->name === 'staff') {
            $abilities[] = 'manage:all';
        } else {
            $abilities[] = 'read:AclDemo';
        }

        // Buat token baru
        $token = $user->createToken('api-token', $abilities)->plainTextToken;

        // $bearerToken = BearerToken::set($token);

        return response()->json([
            'status'    => true,
            'token'     => $token,
            'user'      => $user->load('role'),
            'abilities' => $abilities,
            'message'   => 'Logged in successfully',
        ], 200);
    }

    public function register(Request $request)
    {
        $rules = [
            'name'                  => 'required|string|max:255',
            'jenis_kelamin'         => 'required|string|max:255',
            'role_id'               => 'required|exists:role,id',
            'username'              => 'required|unique:users,username',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
            'avatar'                => 'nullable|mimes:jpeg,png,jpg,gif,webp,ico|max:' . (1024 * 5),
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }

        $user                = new User();
        $user->username      = $request->username;
        $user->name          = $request->name;
        $user->role_id       = $request->role_id;
        $user->jenis_kelamin = $request->jenis_kelamin;
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
            'message' => 'Alumni created successfully',
        ], 201);
    }

    // LOGOUT
    public function logout(Request $request)
    {
        try {
            // Get the currently authenticated user
            $user = $request->user();

            // Check if the user is authenticated
            if ($user) {
                // Invalidate the current token by deleting it
                $request->user()->tokens->each(function ($token) {
                    $token->delete();
                });

                return response()->json([
                    'status'  => true,
                    'message' => 'Logged out successfully',
                ]);
            } else {
                return response()->json([
                    'status'  => false,
                    'message' => 'No authenticated user found',
                ], 401);
            }

        } catch (\Throwable $th) {
            // Return error message if any exception occurs
            return response()->json([
                'message' => $th->getMessage(),
            ], 500); // Internal server error
        }
    }

    // GET USER
    public function getUser(Request $request)
    {
        return response()->json([
            'status' => true,
            'user'   => $request->user()->load('role'),
        ]);
    }
}
