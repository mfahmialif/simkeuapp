<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Mahasiswa;
use Illuminate\Http\Request;
use App\Services\TagihanMahasiswa;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LaporanTagihanExport;
use App\Exports\pdf\LaporanTagihanPdf;
use Illuminate\Support\Facades\Validator;

class CekTagihanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nim'           => 'required|string|max:255',
            'cekNilai'      => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->messages(),
            ], 200);
        }

        $validate = $validator->validated();
        $data = TagihanMahasiswa::tagihan($validate['nim']);
        $nilai = true;

        if ($request->cekNilai == 1) {
            $hasSkripsi = false;
            $cekNilai   = [
                'status'  => true,
                'message' => 'Tanpa cek kelengkapan',
            ];

            if (isset($data['list_tagihan'])) {
                foreach ($data['list_tagihan'] as $tagihan) {
                    if (stripos($tagihan['nama'], 'skripsi') !== false) {
                        $hasSkripsi = true;
                        break;
                    }
                }
            }

            if ($hasSkripsi) {
                $cekNilai = Mahasiswa::cekNilai($validate['nim']);
                if (!$cekNilai->status) {
                    $nilai = false;
                    // return response()->json([
                    //     'status'  => false,
                    //     'message' => $cekNilai->message,
                    // ], 200);
                }
            }
        }
        return response()->json([
            'status' => true,
            'data' => $data,
            'cekNilai' => $nilai,
            'request' => $request->all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function pdf(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                'nim' => 'required',
                'prodi' => 'required',
                'nama' => 'required',
                'tahun_akademik' => 'required',
                'deposit' => 'nullable',
            ]);

            return LaporanTagihanPdf::pdf($dataValidated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function excel(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                'nim' => 'required',
                'prodi' => 'nullable',
                'nama' => 'nullable',
                'tahun_akademik' => 'nullable',
                'deposit' => 'nullable',
            ]);

            return Excel::download(new LaporanTagihanExport($dataValidated['nim'], $dataValidated['prodi'], $dataValidated['nama'], $dataValidated['tahun_akademik'], $dataValidated['deposit']), 'Cek Tagihan.xlsx');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
