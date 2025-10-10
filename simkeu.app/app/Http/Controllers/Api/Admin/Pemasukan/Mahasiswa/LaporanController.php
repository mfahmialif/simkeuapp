<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\pdf\LaporanHarianPdf;
use App\Exports\PembayaranHarianExport;
use App\Exports\PembayaranTotalanHarianExport;

class LaporanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function harian(Request $request){
        try {
            $dataValidated = $request->validate([
                "tanggal" => "required",
                "kategori" => "required",
                "action" => "required",
            ]);
    
            if ($dataValidated['action'] == "excel") {
                // LaporanHarianExcel::excel($request->all());
                // $this->excel($request->all());
                $data = $request->all();
    
                $prodi = isset($data['prodi']) ? $data['prodi'] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data['tahun_akademik']) ? $data['tahun_akademik'] : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data['jenis_pembayaran']) ? $data['jenis_pembayaran'] : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }
    
                return Excel::download(new PembayaranHarianExport($dataValidated['tanggal'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran), 'laporanharian.xlsx');
            } else if ($dataValidated['action'] == "excelTotalan") {
                $data = $request->all();
    
                $prodi = isset($data['prodi']) ? $data['prodi'] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data['tahun_akademik']) ? $data['tahun_akademik'] : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data['jenis_pembayaran']) ? $data['jenis_pembayaran'] : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }
    
                dd("asd");
                return Excel::download(new PembayaranTotalanHarianExport($dataValidated['tanggal'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran), 'laporantotalanharian.xlsx');
            } else {
                LaporanHarianPdf::pdf($request->all());
            }
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
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
}
