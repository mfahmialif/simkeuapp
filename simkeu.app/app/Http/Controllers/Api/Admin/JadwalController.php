<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $data = Jadwal::table($request);
        return response()->json($data);
    }
    
    public function dosenTable(Request $request)
    {
        $data = Jadwal::dosenTable($request);
        return response()->json($data);
    }

    public function show($id)
    {
        $data = Jadwal::find($id);
        return response()->json($data);
    }
}
