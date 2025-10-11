<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Models\Prodi;
use App\Models\ThAkademik;
use App\Services\Mahasiswa;
use App\Exports\RekapExport;
use Illuminate\Http\Request;
use Illuminate\Support\FacadesDB;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Exports\RekapTahunanExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\pdf\LaporanHarianPdf;
use App\Exports\pdf\LaporanBulananPdf;
use App\Exports\pdf\LaporanTahunanPdf;
use App\Exports\PembayaranHarianExport;
use App\Exports\PembayaranBulananExport;
use App\Exports\PembayaranTahunanExport;
use App\Exports\LaporanJumlahMahasiswaExport;
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

    public function harian(Request $request)
    {
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

                return Excel::download(new PembayaranTotalanHarianExport($dataValidated['tanggal'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran), 'laporantotalanharian.xlsx');
            } else {
                return LaporanHarianPdf::pdf($request->all());
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function bulanan(Request $request)
    {
        try {
            //code...
            $dataValidated = $request->validate([
                "bulan" => "required",
                "kategori" => "required",
                "action" => "required",
            ]);

            if ($dataValidated['action'] == "excel") {
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

                return Excel::download(new PembayaranBulananExport($dataValidated['bulan'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran), 'Laporan Bulanan.xlsx');
            } else {
                return LaporanBulananPdf::pdf($request->all());
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function tahunan(Request $request)
    {
        try {
            //code...
            $dataValidated = $request->validate([
                "tahun" => "required",
                "kategori" => "required",
                "action" => "required",
            ]);

            if ($dataValidated['action'] == "excel") {
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

                return Excel::download(new PembayaranTahunanExport($dataValidated['tahun'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran), 'Laporan Tahunan.xlsx');
            } else {
                return LaporanTahunanPdf::pdf($request->all());
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function rekap(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                'tahun_rekap' => 'required',
                'bulan_rekap' => 'required',
            ]);
            $bulan = explode("-", $dataValidated['bulan_rekap'])[0];
            return Excel::download(new RekapExport($dataValidated), "Rekap-$bulan.xlsx");
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function rekapTahunan(Request $request)
    {
        try {
            //code...
            $dataValidated = $request->validate([
                'tahun_rekap' => 'required',
            ]);
            return Excel::download(new RekapTahunanExport($dataValidated), "Rekap tahunan.xlsx");
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function getDataMahasiswaBayar($thAkademikId, $prodiId, $jkId)
    {

        $thAkademik = ThAkademik::find($thAkademikId);
        $thAkademikKode = substr($thAkademik->kode, 0, 4);

        $dataResult = [];

        $semester = Mahasiswa::getSemester($thAkademikId, $prodiId, $jkId)->data;

        foreach ($semester as $smt) {
            $getDataMahasiswaBySemester = Mahasiswa::getMahasiswaBySemester($thAkademikId, $prodiId, $jkId, $smt)->data;
            $count = $getDataMahasiswaBySemester->count;
            $getDataMahasiswaBySemester = $getDataMahasiswaBySemester->mahasiswa;
            $nim = collect($getDataMahasiswaBySemester)->pluck('nim')->values();

            $sudahBayar = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->where(function ($query) {
                    $query->orWhere('kt.nama', 'LIKE', '%registrasi%');
                    $query->orWhere('kt.nama', 'LIKE', '%daftar ulang%');
                })
                ->where('keuangan_pembayaran.th_akademik_id', $thAkademik->id)
                ->whereIn('keuangan_pembayaran.nim', $nim)
                ->select('keuangan_pembayaran.nim')
                ->count();

            $dataResult[] = [
                "semester" => $smt,
                "mahasiswa" => $count,
                "sudah_bayar" => $sudahBayar,
                "belum_bayar" => $count - $sudahBayar,
            ];
        }
        return $dataResult;
    }

    public $data = [];

    public function jumlahMahasiswaBayar(Request $request)
    {
        try {
            //code...
            $dataValidated = $request->validate([
                'tahun_akademik' => 'nullable',
                'prodi' => 'nullable',
                'jenis_kelamin' => 'nullable',
            ]);

            $thAkademikId = isset($dataValidated['tahun_akademik']) ? $dataValidated['tahun_akademik'] : "semua";
            $prodiId = isset($dataValidated['prodi']) ? $dataValidated['prodi'] : "semua";
            $jenisKelamin = isset($dataValidated['jenis_kelamin']) ? $dataValidated['jenis_kelamin'] : "semua";
            if ($jenisKelamin == "semua") {
                $jkId = [8, 9];
            } else {
                $jkId = [$jenisKelamin];
            }

            foreach ($jkId as $jk) {
                if ($thAkademikId == "semua") {
                    $thAkademik = ThAkademik::all();

                    if ($prodiId == "semua") {
                        $prodi = Prodi::all();
                        foreach ($thAkademik as $ta) {
                            foreach ($prodi as $p) {
                                $this->dataBayar($ta, $p, $jk);
                            }
                        }
                    } else {
                        $p = Prodi::find($prodiId);
                        foreach ($thAkademik as $ta) {
                            $this->dataBayar($ta, $p, $jk);
                        }
                    }
                } else {
                    $ta = ThAkademik::find($thAkademikId);

                    if ($prodiId == "semua") {
                        $prodi = Prodi::all();
                        foreach ($prodi as $p) {
                            $this->dataBayar($ta, $p, $jk);
                        }
                    } else {
                        $p = Prodi::find($prodiId);
                        $this->dataBayar($ta, $p, $jk);
                    }
                }
            }
            return Excel::download(new LaporanJumlahMahasiswaExport($this->data, $jkId), "Laporan yang Sudah Bayar dan Belum.xlsx");
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function dataBayar($ta, $p, $jkId)
    {
        $dataBayar = $this->getDataMahasiswaBayar($ta->id, $p->id, $jkId);

        foreach ($dataBayar as $bayar) {
            // data biasa
            $this->data[$jkId][$ta->kode][$p->nama][$bayar['semester']] = [
                "sudah_bayar" => $bayar["sudah_bayar"],
                "mahasiswa" => $bayar["mahasiswa"],
                "belum_bayar" => $bayar["belum_bayar"],
            ];

            // total per tahun akademik per semester
            if (isset($this->data[$jkId]['total'][$ta->kode][$p->nama]['sudah_bayar'])) {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['sudah_bayar'] += $bayar['sudah_bayar'];
            } else {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['sudah_bayar'] = $bayar['sudah_bayar'];
            }

            if (isset($this->data[$jkId]['total'][$ta->kode][$p->nama]['belum_bayar'])) {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['belum_bayar'] += $bayar['belum_bayar'];
            } else {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['belum_bayar'] = $bayar['belum_bayar'];
            }

            if (isset($this->data[$jkId]['total'][$ta->kode][$p->nama]['mahasiswa'])) {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['mahasiswa'] += $bayar['mahasiswa'];
            } else {
                $this->data[$jkId]['total'][$ta->kode][$p->nama]['mahasiswa'] = $bayar['mahasiswa'];
            }

            // total per tahun akademik
            if (isset($this->data[$jkId]['total'][$ta->kode]['total']['sudah_bayar'])) {
                $this->data[$jkId]['total'][$ta->kode]['total']['sudah_bayar'] += $bayar['sudah_bayar'];
            } else {
                $this->data[$jkId]['total'][$ta->kode]['total']['sudah_bayar'] = $bayar['sudah_bayar'];
            }

            if (isset($this->data[$jkId]['total'][$ta->kode]['total']['belum_bayar'])) {
                $this->data[$jkId]['total'][$ta->kode]['total']['belum_bayar'] += $bayar['belum_bayar'];
            } else {
                $this->data[$jkId]['total'][$ta->kode]['total']['belum_bayar'] = $bayar['belum_bayar'];
            }

            if (isset($this->data[$jkId]['total'][$ta->kode]['total']['mahasiswa'])) {
                $this->data[$jkId]['total'][$ta->kode]['total']['mahasiswa'] += $bayar['mahasiswa'];
            } else {
                $this->data[$jkId]['total'][$ta->kode]['total']['mahasiswa'] = $bayar['mahasiswa'];
            }
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
