<?php
namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ProfilController extends Controller
{
    /**
     * Display a single data of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        return response()->json([
            'status' => true,
            'user'   => $request->user()->load('role'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // Validasi data yang dikirimkan
        $user = $request->user();
        $id   = $user->id;

        $rules = [
            'name'                  => 'required|string|max:255',
            'jenis_kelamin'         => 'required|string|max:255',
            'username'              => 'required|unique:users,username,' . $id,
            'email'                 => 'nullable|email|unique:users,email,' . $id,
            'password'              => 'nullable|string|min:6|confirmed',
            'password_confirmation' => 'required_with:password|string|min:6',
            'avatar'                => 'nullable|mimes:jpeg,png,jpg,gif,webp,ico|max:' . (1024 * 5),

        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        try {
            \DB::beginTransaction();
            $user->username      = $request->username;
            $user->name          = $request->name;
            $user->email         = $request->email;
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
            \DB::commit();
            return response()->json([
                'status'  => true,
                'data'    => $user->load('role'),
                'message' => 'User updated successfully',
            ]);
        } catch (\Throwable $th) {
            \DB::rollback();
            return response()->json([
                'status'  => true,
                'data'    => $user->load('role'),
                'message' => 'User updated failed',
                'error'   => $th->getMessage(),
            ]);
        }

    }

}
