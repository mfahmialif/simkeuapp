<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Services\Dosen;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class DosenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $data = Dosen::all(null, 30,null,null,null);
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
        return response()->json(Dosen::find($id), 200);
    }
    public function search($search)
    {
        $data = Dosen::all(null, 30, $search, 'mst_dosen.nama', 'asc');  
        return response()->json($data);  
    }

}
