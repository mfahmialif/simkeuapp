<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    function index() {
        $data = Jadwal::all();
        return response()->json($data);
    }
    function find(Request $request) {
        $data = Jadwal::find($request->id);
        return response()->json($data);
    }
}
