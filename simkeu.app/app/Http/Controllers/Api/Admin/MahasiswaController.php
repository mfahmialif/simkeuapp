<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Services\Mahasiswa;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MahasiswaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $data = Mahasiswa::table($request);
        return response()->json($data);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return response()->json(Mahasiswa::find($id), 200);
    }


    /**
     * Display the specified resource.
     *
     * @param  string  $nim
     * @return \Illuminate\Http\Response
     */
    public function nim($nim)
    {
        return response()->json(Mahasiswa::nim($nim), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $search
     * @return \Illuminate\Http\Response
     */
    public function search($search)
    {
        $data = Mahasiswa::all(null, 30, $search, 'mst_mhs.nama', 'asc');
        return response()->json($data);
    }

}
