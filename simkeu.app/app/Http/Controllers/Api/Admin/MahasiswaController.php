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
    public function nim(Request $request)
    {
        $nim = $request->nim;
        $whereIn = $request->whereIn;
        return response()->json(Mahasiswa::nim($nim, $whereIn), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $search
     * @return \Illuminate\Http\Response
     */
    public function search($search)
    {
        $data = Mahasiswa::all(null, 30, $search, 'mst_mhs.nim', 'asc');
        return response()->json($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $search
     * @return \Illuminate\Http\Response
     */
    public function getSemester(Request $request)
    {
        $data = Mahasiswa::getSemester($request->th_akademik_id, $request->prodi_id, $request->jk_id);
        return response()->json($data);
    }

    public function updateStatusMahasiswa(Request $request)
    {
        $data = Mahasiswa::updateStatusMahasiswa($request->nim, $request->status_id);
        return response()->json($data);
    }

    public function cekPelanggaran($nim)
    {
        $data = Mahasiswa::cekPelanggaran($nim);
        return response()->json($data);
    }

}
