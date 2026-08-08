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
use App\Exports\pdf\LaporanHarianDetailPdf;
use App\Exports\LaporanHarianDetailExport;
use App\Exports\PembayaranHarianExport;
use App\Exports\PembayaranBulananExport;
use App\Exports\PembayaranTahunanExport;
use App\Exports\LaporanJumlahMahasiswaExport;
use App\Exports\PembayaranTotalanHarianExport;
use App\Models\KeuanganTagihan;
use App\Services\Helper;
use App\Services\MataUangFormatter;
use App\Services\SemesterPendek;
use App\Services\TagihanLaporanFilter;
use App\Exports\PemasukanTunaiHarianBulananExport;
use App\Exports\PemasukanTunaiHarianTahunanExport;

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
                "include_wisuda_semester_pendek" => "nullable",
            ]);
            $includeWisudaSemesterPendek = TagihanLaporanFilter::includeWisudaSemesterPendek(
                $request->input("include_wisuda_semester_pendek", false),
            );

            if ($dataValidated["action"] == "excel") {
                // LaporanHarianExcel::excel($request->all());
                // $this->excel($request->all());
                $data = $request->all();

                $prodi = isset($data["prodi"]) ? $data["prodi"] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data["tahun_akademik"])
                    ? $data["tahun_akademik"]
                    : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data["jenis_pembayaran"])
                    ? $data["jenis_pembayaran"]
                    : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }

                return Excel::download(
                    new PembayaranHarianExport(
                        $dataValidated["tanggal"],
                        $dataValidated["kategori"],
                        $prodi,
                        $tahunAkademik,
                        $jenisPembayaran,
                        $includeWisudaSemesterPendek,
                    ),
                    "laporanharian.xlsx",
                );
            } elseif ($dataValidated["action"] == "excelTotalanStaff") {
                $data = $request->all();

                $prodi = isset($data["prodi"]) ? $data["prodi"] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data["tahun_akademik"])
                    ? $data["tahun_akademik"]
                    : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data["jenis_pembayaran"])
                    ? $data["jenis_pembayaran"]
                    : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }

                if (\Auth::user()->role->name == "admin") {
                    $userId = null;
                } else {
                    $userId = \Auth::user()->id;
                }

                return Excel::download(
                    new PembayaranTotalanHarianExport(
                        $dataValidated["tanggal"],
                        $dataValidated["kategori"],
                        $prodi,
                        $tahunAkademik,
                        $jenisPembayaran,
                        $userId,
                        $includeWisudaSemesterPendek,
                    ),
                    "laporantotalanharian.xlsx",
                );
            } elseif ($dataValidated["action"] == "excelTotalan") {
                $data = $request->all();

                $prodi = isset($data["prodi"]) ? $data["prodi"] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data["tahun_akademik"])
                    ? $data["tahun_akademik"]
                    : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data["jenis_pembayaran"])
                    ? $data["jenis_pembayaran"]
                    : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }

                return Excel::download(
                    new PembayaranTotalanHarianExport(
                        $dataValidated["tanggal"],
                        $dataValidated["kategori"],
                        $prodi,
                        $tahunAkademik,
                        $jenisPembayaran,
                        false,
                        $includeWisudaSemesterPendek,
                    ),
                    "laporantotalanharian.xlsx",
                );
            } else {
                return LaporanHarianPdf::pdf($request->all());
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "message" => 422,
                "error" => implode(
                    " ",
                    collect($e->errors())->flatten()->toArray(),
                ),
                "req" => $request->all(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
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
                "include_wisuda_semester_pendek" => "nullable",
            ]);
            $includeWisudaSemesterPendek = TagihanLaporanFilter::includeWisudaSemesterPendek(
                $request->input("include_wisuda_semester_pendek", false),
            );

            if ($dataValidated["action"] == "excel") {
                $data = $request->all();
                $prodi = isset($data["prodi"]) ? $data["prodi"] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data["tahun_akademik"])
                    ? $data["tahun_akademik"]
                    : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data["jenis_pembayaran"])
                    ? $data["jenis_pembayaran"]
                    : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }

                return Excel::download(
                    new PembayaranBulananExport(
                        $dataValidated["bulan"],
                        $dataValidated["kategori"],
                        $prodi,
                        $tahunAkademik,
                        $jenisPembayaran,
                        $includeWisudaSemesterPendek,
                    ),
                    "Laporan Bulanan.xlsx",
                );
            } else {
                return LaporanBulananPdf::pdf($request->all());
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
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
                "include_wisuda_semester_pendek" => "nullable",
            ]);
            $includeWisudaSemesterPendek = TagihanLaporanFilter::includeWisudaSemesterPendek(
                $request->input("include_wisuda_semester_pendek", false),
            );

            if ($dataValidated["action"] == "excel") {
                $data = $request->all();
                $prodi = isset($data["prodi"]) ? $data["prodi"] : "";
                if ($prodi == "Semua") {
                    $prodi = "";
                }
                $tahunAkademik = isset($data["tahun_akademik"])
                    ? $data["tahun_akademik"]
                    : "";
                if ($tahunAkademik == "Semua") {
                    $tahunAkademik = "";
                }
                $jenisPembayaran = isset($data["jenis_pembayaran"])
                    ? $data["jenis_pembayaran"]
                    : "";
                if ($jenisPembayaran == "Semua") {
                    $jenisPembayaran = "";
                }

                return Excel::download(
                    new PembayaranTahunanExport(
                        $dataValidated["tahun"],
                        $dataValidated["kategori"],
                        $prodi,
                        $tahunAkademik,
                        $jenisPembayaran,
                        $includeWisudaSemesterPendek,
                    ),
                    "Laporan Tahunan.xlsx",
                );
            } else {
                return LaporanTahunanPdf::pdf($request->all());
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "message" => 422,
                "error" => implode(
                    " ",
                    collect($e->errors())->flatten()->toArray(),
                ),
                "req" => $request->all(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function rekap(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                "tahun_rekap" => "required",
                "bulan_rekap" => "required",
                "include_wisuda_semester_pendek" => "nullable",
            ]);
            $bulan = explode("-", $dataValidated["bulan_rekap"])[0];
            return Excel::download(
                new RekapExport($dataValidated),
                "Rekap-$bulan.xlsx",
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "message" => 422,
                "error" => implode(
                    " ",
                    collect($e->errors())->flatten()->toArray(),
                ),
                "req" => $request->all(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function rekapTahunan(Request $request)
    {
        try {
            //code...
            $dataValidated = $request->validate([
                "tahun_rekap" => "required",
                "include_wisuda_semester_pendek" => "nullable",
            ]);
            return Excel::download(
                new RekapTahunanExport($dataValidated),
                "Rekap tahunan.xlsx",
            );
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function getDataMahasiswaBayar($thAkademikId, $prodiId, $jkId)
    {
        $thAkademik = ThAkademik::find($thAkademikId);
        $thAkademikKode = substr($thAkademik->kode, 0, 4);

        $dataResult = [];

        $semester = Mahasiswa::getSemester($thAkademikId, $prodiId, $jkId)
            ->data;

        foreach ($semester as $smt) {
            $getDataMahasiswaBySemester = Mahasiswa::getMahasiswaBySemester(
                $thAkademikId,
                $prodiId,
                $jkId,
                $smt,
            )->data;
            $count = $getDataMahasiswaBySemester->count;
            $getDataMahasiswaBySemester =
                $getDataMahasiswaBySemester->mahasiswa;
            $nim = collect($getDataMahasiswaBySemester)->pluck("nim")->values();

            $sudahBayar = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->where(function ($query) {
                    $query->orWhere("kt.nama", "LIKE", "%registrasi%");
                    $query->orWhere("kt.nama", "LIKE", "%daftar ulang%");
                })
                ->where("keuangan_pembayaran.th_akademik_id", $thAkademik->id)
                ->whereIn("keuangan_pembayaran.nim", $nim)
                ->select("keuangan_pembayaran.nim")
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
                "tahun_akademik" => "nullable",
                "prodi" => "nullable",
                "jenis_kelamin" => "nullable",
            ]);

            $thAkademikId = isset($dataValidated["tahun_akademik"])
                ? $dataValidated["tahun_akademik"]
                : "semua";
            $prodiId = isset($dataValidated["prodi"])
                ? $dataValidated["prodi"]
                : "semua";
            $jenisKelamin = isset($dataValidated["jenis_kelamin"])
                ? $dataValidated["jenis_kelamin"]
                : "semua";
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
            return Excel::download(
                new LaporanJumlahMahasiswaExport($this->data, $jkId),
                "Laporan yang Sudah Bayar dan Belum.xlsx",
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "message" => 422,
                "error" => implode(
                    " ",
                    collect($e->errors())->flatten()->toArray(),
                ),
                "req" => $request->all(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function dataBayar($ta, $p, $jkId)
    {
        $dataBayar = $this->getDataMahasiswaBayar($ta->id, $p->id, $jkId);

        foreach ($dataBayar as $bayar) {
            // data biasa
            $this->data[$jkId][$ta->kode][$p->nama][$bayar["semester"]] = [
                "sudah_bayar" => $bayar["sudah_bayar"],
                "mahasiswa" => $bayar["mahasiswa"],
                "belum_bayar" => $bayar["belum_bayar"],
            ];

            // total per tahun akademik per semester
            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode][$p->nama][
                        "sudah_bayar"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode][$p->nama][
                    "sudah_bayar"
                ] += $bayar["sudah_bayar"];
            } else {
                $this->data[$jkId]["total"][$ta->kode][$p->nama][
                    "sudah_bayar"
                ] = $bayar["sudah_bayar"];
            }

            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode][$p->nama][
                        "belum_bayar"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode][$p->nama][
                    "belum_bayar"
                ] += $bayar["belum_bayar"];
            } else {
                $this->data[$jkId]["total"][$ta->kode][$p->nama][
                    "belum_bayar"
                ] = $bayar["belum_bayar"];
            }

            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode][$p->nama][
                        "mahasiswa"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode][$p->nama]["mahasiswa"] +=
                    $bayar["mahasiswa"];
            } else {
                $this->data[$jkId]["total"][$ta->kode][$p->nama]["mahasiswa"] =
                    $bayar["mahasiswa"];
            }

            // total per tahun akademik
            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode]["total"][
                        "sudah_bayar"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode]["total"][
                    "sudah_bayar"
                ] += $bayar["sudah_bayar"];
            } else {
                $this->data[$jkId]["total"][$ta->kode]["total"]["sudah_bayar"] =
                    $bayar["sudah_bayar"];
            }

            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode]["total"][
                        "belum_bayar"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode]["total"][
                    "belum_bayar"
                ] += $bayar["belum_bayar"];
            } else {
                $this->data[$jkId]["total"][$ta->kode]["total"]["belum_bayar"] =
                    $bayar["belum_bayar"];
            }

            if (
                isset(
                    $this->data[$jkId]["total"][$ta->kode]["total"][
                        "mahasiswa"
                    ],
                )
            ) {
                $this->data[$jkId]["total"][$ta->kode]["total"]["mahasiswa"] +=
                    $bayar["mahasiswa"];
            } else {
                $this->data[$jkId]["total"][$ta->kode]["total"]["mahasiswa"] =
                    $bayar["mahasiswa"];
            }
        }
    }

    /**
     * Pemasukan Tunai Harian - Monthly daily cash income report
     * Returns daily payment totals grouped by tagihan categories
     */
    private function normalizeTagihanName($name)
    {
        return trim(
            preg_replace("/[^A-Z0-9]+/", " ", strtoupper((string) $name)),
        );
    }

    private function compactTagihanName($name)
    {
        return preg_replace("/[^A-Z0-9]+/", "", strtoupper((string) $name));
    }

    private function isUasTagihanName($name)
    {
        $normalized = $this->normalizeTagihanName($name);
        $compact = $this->compactTagihanName($name);

        return preg_match("/\bUAS\b/", $normalized) ||
            strpos($compact, "UASSEMESTER") !== false ||
            strpos($compact, "UJIANAKHIRSEMESTER") !== false;
    }

    private function isUtsTagihanName($name)
    {
        $normalized = $this->normalizeTagihanName($name);
        $compact = $this->compactTagihanName($name);

        return preg_match("/\bUTS\b/", $normalized) ||
            strpos($compact, "UTSSEMESTER") !== false ||
            strpos($compact, "UJIANTENGAHSEMESTER") !== false;
    }

    private function matchesPemasukanFixedCategory($tagihanName, $category)
    {
        if (($category["key"] ?? null) === "uas") {
            return $this->isUasTagihanName($tagihanName);
        }

        if (($category["key"] ?? null) === "uts") {
            return $this->isUtsTagihanName($tagihanName);
        }

        $tagihanUpper = strtoupper((string) $tagihanName);

        foreach ($category["search"] as $searchPattern) {
            $term = trim(str_replace("%", "", strtoupper($searchPattern)));
            if (strpos($tagihanUpper, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getPemasukanTunaiColumns($jenjang = "sarjana")
    {
        // a) SPP per Prodi
        $prodiQuery = Prodi::where("id", "!=", 15);
        if ($jenjang === "sarjana") {
            $prodiQuery->where("jenjang", "S1");
        } elseif ($jenjang === "pascasarjana") {
            $prodiQuery->whereIn("jenjang", ["S2", "S3"]);
        }
        $prodiList = $prodiQuery->orderBy("nama", "asc")->get();

        $columns = [];
        foreach ($prodiList as $p) {
            $columns[] = [
                "key" => "spp_prodi_" . $p->id,
                "label" => "SPP " . $p->alias,
                "type" => "spp",
                "prodi_id" => $p->id,
            ];
        }

        // b) Fixed categories
        $fixedCategories = [
            [
                "key" => "registrasi",
                "label" => "REGISTRASI, DAFTAR ULANG & PENDAFTARAN",
                "type" => "fixed",
                "search" => ["%REGIST%", "%DAFTAR ULANG%", "%PENDAFTARAN%"],
            ],
            [
                "key" => "uas",
                "label" => "UAS",
                "type" => "fixed",
                "search" => [
                    "%UAS%",
                    "%U A S%",
                    "%U.A.S%",
                    "%UJIAN AKHIR SEMESTER%",
                ],
            ],
            [
                "key" => "uts",
                "label" => "UTS",
                "type" => "fixed",
                "search" => [
                    "%UTS%",
                    "%U T S%",
                    "%U.T.S%",
                    "%UJIAN TENGAH SEMESTER%",
                ],
            ],
            [
                "key" => "kkn",
                "label" => "KKN / PPL / PKL",
                "type" => "fixed",
                "search" => ["%KKN%", "%PPL%", "%PKL%"],
            ],
            [
                "key" => "skripsi",
                "label" => "SKRIPSI",
                "type" => "fixed",
                "search" => ["%SKRIPSI%"],
            ],
            [
                "key" => "pmb",
                "label" => "PMB",
                "type" => "fixed",
                "search" => ["%PMB%"],
            ],
            [
                "key" => "double_degree",
                "label" => "PERSYARATAN DOUBLE DEGREE",
                "type" => "fixed",
                "search" => ["%DOUBLE DEGREE%"],
            ],
            [
                "key" => "perpus",
                "label" => "SUMBANGAN PERPUS",
                "type" => "fixed",
                "search" => ["%PERPUS%"],
            ],
            [
                "key" => "kompetensi",
                "label" => "UJI KOMPETENSI",
                "type" => "fixed",
                "search" => ["%KOMPETENSI%"],
            ],
            [
                "key" => "sumbangan_pendidikan",
                "label" => "SUMBANGAN PENDIDIKAN",
                "type" => "fixed",
                "search" => ["%SUMBANGAN PENDIDIKAN%"],
            ],
            [
                "key" => "sp",
                "label" => "SEMESTER PENDEK",
                "type" => "fixed",
                "search" => ["%SEMESTER PENDEK%"],
            ],
            [
                "key" => "wisuda",
                "label" => "WISUDA",
                "type" => "fixed",
                "search" => ["%WISUDA%"],
            ],
        ];

        foreach ($fixedCategories as $fc) {
            $columns[] = $fc;
        }

        // c) Other tagihan
        $otherTagihan = KeuanganTagihan::where([
            ["nama", "NOT LIKE", "%SPP%"],
            ["nama", "NOT LIKE", "%UAS%"],
            ["nama", "NOT LIKE", "%U A S%"],
            ["nama", "NOT LIKE", "%U.A.S%"],
            ["nama", "NOT LIKE", "%UJIAN AKHIR SEMESTER%"],
            ["nama", "NOT LIKE", "%UTS%"],
            ["nama", "NOT LIKE", "%U T S%"],
            ["nama", "NOT LIKE", "%U.T.S%"],
            ["nama", "NOT LIKE", "%UJIAN TENGAH SEMESTER%"],
            ["nama", "NOT LIKE", "%KKN%"],
            ["nama", "NOT LIKE", "%PPL%"],
            ["nama", "NOT LIKE", "%PKL%"],
            ["nama", "NOT LIKE", "%REGIST%"],
            ["nama", "NOT LIKE", "%DAFTAR ULANG%"],
            ["nama", "NOT LIKE", "%PENDAFTARAN%"],
            ["nama", "NOT LIKE", "%SKRIPSI%"],
            ["nama", "NOT LIKE", "%PMB%"],
            ["nama", "NOT LIKE", "%DOUBLE DEGREE%"],
            ["nama", "NOT LIKE", "%PERPUS%"],
            ["nama", "NOT LIKE", "%KOMPETENSI%"],
            ["nama", "NOT LIKE", "%SUMBANGAN PENDIDIKAN%"],
            ["nama", "NOT LIKE", "%SEMESTER PENDEK%"],
            ["nama", "NOT LIKE", "%WISUDA%"],
        ])
            ->get()
            ->unique("nama")
            ->pluck("nama")
            ->filter(
                fn($nama) => !$this->isUasTagihanName($nama) &&
                    !$this->isUtsTagihanName($nama),
            )
            ->values()
            ->toArray();

        foreach ($otherTagihan as $nama) {
            $columns[] = [
                "key" => "other_" . md5($nama),
                "label" => $nama,
                "type" => "other",
                "search" => $nama,
            ];
        }

        return $columns;
    }

    private function filterSemesterPendekPaymentsByJenjang($payments, $jenjang)
    {
        $payments = collect($payments);
        $krsIds = $payments
            ->pluck("krs_id")
            ->filter()
            ->unique()
            ->values();

        if ($krsIds->isEmpty()) {
            return collect();
        }

        try {
            $krsMap = collect(SemesterPendek::searchKrs($krsIds->all()))
                ->keyBy(fn($item) => data_get($item, "id"))
                ->all();
        } catch (\Throwable $th) {
            return collect();
        }

        return $payments
            ->filter(function ($payment) use ($krsMap, $jenjang) {
                $krs = $krsMap[$payment->krs_id] ?? null;

                return $this->laporanHarianSemesterPendekMatchesJenjang(
                    $jenjang,
                    $krs,
                    null,
                );
            })
            ->values();
    }

    private function mapSemesterPendekPaymentsFromTagihan($payments)
    {
        return collect($payments)
            ->map(function ($payment) {
                $payment->krs_id = SemesterPendek::parseKrsIdFromTagihanName(
                    $payment->tagihan_nama,
                );
                $payment->tagihan_nama = "SEMESTER PENDEK";
                $payment->prodi_id = null;

                return $payment;
            })
            ->filter(fn($payment) => !empty($payment->krs_id))
            ->values();
    }

    private function getPemasukanBulanData(
        $year,
        $month,
        $columns,
        $jp,
        $prefetchedPayments = null,
        $pmbData = [],
        $jenjang = "sarjana",
        $jenisPembayaranId = null,
        $userId = null
    ) {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = sprintf("%04d-%02d-01", $year, $month);
        $endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);

        if ($prefetchedPayments === null) {
            $paymentsQuery = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%")
                ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

            if ($jenjang === "sarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->where("p.jenjang", "S1");
            } elseif ($jenjang === "pascasarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->whereIn("p.jenjang", ["S2", "S3"]);
            }

            if ($jenisPembayaranId) {
                $paymentsQuery
                    ->join(
                        "keuangan_jenis_pembayaran_detail as kjpd",
                        "kjpd.pembayaran_id",
                        "=",
                        "keuangan_pembayaran.id",
                    )
                    ->where("kjpd.jenis_pembayaran_id", $jenisPembayaranId);
            }

            if ($userId) {
                $paymentsQuery->where("keuangan_pembayaran.user_id", $userId);
            }

            $payments = $paymentsQuery
                ->selectRaw(
                    "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                )
                ->groupBy(
                    DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                    "kt.nama",
                    "kt.prodi_id",
                    "mu.id",
                    "mu.kode",
                    "mu.nama",
                    "mu.simbol",
                )
                ->get();

            $spPaymentsQuery = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%")
                ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

            if ($jenisPembayaranId) {
                $spPaymentsQuery
                    ->join(
                        "keuangan_jenis_pembayaran_detail as kjpd",
                        "kjpd.pembayaran_id",
                        "=",
                        "keuangan_pembayaran.id",
                    )
                    ->where("kjpd.jenis_pembayaran_id", $jenisPembayaranId);
            }

            if ($userId) {
                $spPaymentsQuery->where("keuangan_pembayaran.user_id", $userId);
            }

            $spPayments = $spPaymentsQuery
                ->selectRaw(
                    "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                )
                ->groupBy(
                    DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                    "kt.nama",
                    "kt.prodi_id",
                    "mu.id",
                    "mu.kode",
                    "mu.nama",
                    "mu.simbol",
                )
                ->get();

            $spPayments = $this->mapSemesterPendekPaymentsFromTagihan(
                $spPayments,
            );
            $spPayments = $this->filterSemesterPendekPaymentsByJenjang(
                $spPayments,
                $jenjang,
            );

            $payments = $payments->concat($spPayments);
        } else {
            $payments = $prefetchedPayments;
        }

        $dataMap = [];
        $totals = [];

        foreach ($columns as $col) {
            $totals[$col["key"]] = 0;
            $totals[$col["key"] . "_by_currency"] = [];
        }
        $totals["jumlah"] = 0;
        $totals["jumlah_by_currency"] = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $row = [
                "no" => $day,
                "tanggal" => $dateStr,
                "jumlah" => 0,
            ];
            foreach ($columns as $col) {
                $row[$col["key"]] = 0;
                $row[$col["key"] . "_by_currency"] = [];
            }
            $row["jumlah_by_currency"] = [];
            $dataMap[$dateStr] = $row;
        }

        $colMap = [];
        foreach ($columns as $col) {
            $colMap[$col["key"]] = $col;
        }

        foreach ($payments as $payment) {
            $tgl = $payment->tgl;
            $namaRaw = $payment->tagihan_nama;
            $namaUpper = strtoupper($namaRaw);
            $prodi_id = $payment->prodi_id;
            $jumlah = (float) $payment->total_jumlah;
            $mataUang = MataUangFormatter::fromColumns($payment);

            if (!isset($dataMap[$tgl])) {
                continue;
            }

            $assignedKey = null;

            if (strpos($namaUpper, "SPP") !== false) {
                $assignedKey = "spp_prodi_" . $prodi_id;
            } else {
                foreach ($columns as $c) {
                    if (
                        $c["type"] === "fixed" &&
                        $this->matchesPemasukanFixedCategory($namaRaw, $c)
                    ) {
                        $assignedKey = $c["key"];
                        break;
                    }
                }
                if (!$assignedKey) {
                    $assignedKey = "other_" . md5($namaRaw);
                }
            }

            if (isset($colMap[$assignedKey])) {
                $dataMap[$tgl][$assignedKey] += $jumlah;
                $dataMap[$tgl]["jumlah"] += $jumlah;
                $totals[$assignedKey] += $jumlah;
                $totals["jumlah"] += $jumlah;
                MataUangFormatter::addToTotals(
                    $dataMap[$tgl][$assignedKey . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $dataMap[$tgl]["jumlah_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totals[$assignedKey . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totals["jumlah_by_currency"],
                    $jumlah,
                    $mataUang,
                );
            }
        }

        // Process PMB Data
        foreach ($pmbData as $pmb) {
            if (!isset($pmb["tanggal_bayar"]) || !isset($pmb["nominal"])) {
                continue;
            }

            $tgl = date("Y-m-d", strtotime($pmb["tanggal_bayar"]));
            $jumlah = (float) $pmb["nominal"];

            if (!isset($dataMap[$tgl])) {
                continue;
            }

            $assignedKey = "pmb";

            if (isset($colMap[$assignedKey])) {
                $mataUang = MataUangFormatter::defaultCurrency();
                $dataMap[$tgl][$assignedKey] += $jumlah;
                $dataMap[$tgl]["jumlah"] += $jumlah;
                $totals[$assignedKey] += $jumlah;
                $totals["jumlah"] += $jumlah;
                MataUangFormatter::addToTotals(
                    $dataMap[$tgl][$assignedKey . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $dataMap[$tgl]["jumlah_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totals[$assignedKey . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totals["jumlah_by_currency"],
                    $jumlah,
                    $mataUang,
                );
            }
        }

        foreach ($dataMap as &$row) {
            foreach ($columns as $col) {
                $currencyKey = $col["key"] . "_by_currency";
                $row[$currencyKey] = MataUangFormatter::normalizeTotals(
                    $row[$currencyKey],
                );
            }
            $row["jumlah_by_currency"] = MataUangFormatter::normalizeTotals(
                $row["jumlah_by_currency"],
            );
        }
        unset($row);

        foreach ($columns as $col) {
            $currencyKey = $col["key"] . "_by_currency";
            $totals[$currencyKey] = MataUangFormatter::normalizeTotals(
                $totals[$currencyKey],
            );
        }
        $totals["jumlah_by_currency"] = MataUangFormatter::normalizeTotals(
            $totals["jumlah_by_currency"],
        );

        $data = array_values($dataMap);

        $bulanNames = [
            1 => "JANUARI",
            2 => "FEBRUARI",
            3 => "MARET",
            4 => "APRIL",
            5 => "MEI",
            6 => "JUNI",
            7 => "JULI",
            8 => "AGUSTUS",
            9 => "SEPTEMBER",
            10 => "OKTOBER",
            11 => "NOVEMBER",
            12 => "DESEMBER",
        ];

        return [
            "title" =>
                "PEMASUKAN TUNAI BULAN " . $bulanNames[$month] . " " . $year,
            "bulan_name" => $bulanNames[$month],
            "data" => $data,
            "totals" => $totals,
        ];
    }

    public function pemasukanTunaiHarian(Request $request)
    {
        try {
            $jp = Helper::getJenisKelaminUser();
            $action = $request->input("action", "json");
            $mode = $request->input("mode", "bulanan"); // 'bulanan' atau 'tahunan'
            $jenjang = $request->input("jenjang", "sarjana");
            $jenisPembayaranId = $request->input("jenis_pembayaran_id");
            $userId = $request->input("user_id");

            $jenisPembayaranNama = null;
            $jenisPembayaranKategori = null;
            if ($jenisPembayaranId) {
                $jpModel = \App\Models\KeuanganJenisPembayaran::find(
                    $jenisPembayaranId,
                );
                if ($jpModel) {
                    $nama = strtolower(trim($jpModel->nama));
                    $jenisPembayaranKategori = $jpModel->kategori;
                    if (strpos($nama, "deposit") !== false) {
                        $jenisPembayaranNama = "deposit";
                    } elseif (strpos($nama, "transfer") !== false) {
                        $jenisPembayaranNama = "transfer";
                    } elseif (strpos($nama, "cash") !== false) {
                        $jenisPembayaranNama = "cash";
                    } elseif (strpos($nama, "yayasan") !== false) {
                        $jenisPembayaranNama = "yayasan";
                    }
                }
            }

            $columns = $this->getPemasukanTunaiColumns($jenjang);

            $pmbUrl = rtrim(env("PMB_URL"), "/") . "/simkeu/pembayaran";
            $pmbApiKey = env("PMB_API_KEY");
            $pmbDataAll = [];

            if ($mode === "tahunan") {
                $year = (int) $request->tahun;

                $startDateYear = sprintf("%04d-01-01", $year);
                $endDateYear = sprintf("%04d-12-31", $year);

                $startDate = $startDateYear;
                $endDate = $endDateYear;
            } else {
                $parts = explode("-", $request->bulan);
                if (count($parts) != 2) {
                    throw new \Exception(
                        "Format bulan tidak valid. Gunakan YYYY-MM.",
                    );
                }
                $year = (int) $parts[0];
                $month = (int) $parts[1];

                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $startDate = sprintf("%04d-%02d-01", $year, $month);
                $endDate = sprintf(
                    "%04d-%02d-%02d",
                    $year,
                    $month,
                    $daysInMonth,
                );
            }

            if (!$userId) {
                try {
                    $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders(
                        [
                            "apikey" => $pmbApiKey,
                        ],
                    )->get($pmbUrl, [
                        "start_date" => $startDate,
                        "end_date" => $endDate,
                        "jenjang" => $jenjang,
                        "jenis_kelamin" => $jenisPembayaranKategori,
                        "jenis_pembayaran" => $jenisPembayaranNama,
                    ]);

                    if ($pmbResponse->successful()) {
                        $pmbResData = $pmbResponse->json();
                        if (
                            isset($pmbResData["data"]) &&
                            is_array($pmbResData["data"])
                        ) {
                            $pmbDataAll = $pmbResData["data"];
                        }
                    }
                } catch (\Throwable $th) {
                    // Ignore API error, proceed with empty PMB data
                }
            }

            if ($mode === "tahunan") {
                $allPaymentsQuery = KeuanganPembayaran::join(
                    "keuangan_tagihan as kt",
                    "kt.id",
                    "=",
                    "keuangan_pembayaran.tagihan_id",
                )
                    ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                    ->whereBetween("keuangan_pembayaran.tanggal", [
                        $startDateYear . " 00:00:00",
                        $endDateYear . " 23:59:59",
                    ])
                    ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%")
                    ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

                if ($jenjang === "sarjana") {
                    $allPaymentsQuery
                        ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                        ->where("p.jenjang", "S1");
                } elseif ($jenjang === "pascasarjana") {
                    $allPaymentsQuery
                        ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                        ->whereIn("p.jenjang", ["S2", "S3"]);
                }

                if ($jenisPembayaranId) {
                    $allPaymentsQuery
                        ->join(
                            "keuangan_jenis_pembayaran_detail as kjpd",
                            "kjpd.pembayaran_id",
                            "=",
                            "keuangan_pembayaran.id",
                        )
                        ->where("kjpd.jenis_pembayaran_id", $jenisPembayaranId);
                }

                if ($userId) {
                    $allPaymentsQuery->where(
                        "keuangan_pembayaran.user_id",
                        $userId,
                    );
                }

                $allPayments = $allPaymentsQuery
                    ->selectRaw(
                        "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                        "kt.nama",
                        "kt.prodi_id",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                    )
                    ->get();

                $spPaymentsQuery = KeuanganPembayaran::join(
                    "keuangan_tagihan as kt",
                    "kt.id",
                    "=",
                    "keuangan_pembayaran.tagihan_id",
                )
                    ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                    ->whereBetween("keuangan_pembayaran.tanggal", [
                        $startDateYear . " 00:00:00",
                        $endDateYear . " 23:59:59",
                    ])
                    ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%")
                    ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

                if ($jenisPembayaranId) {
                    $spPaymentsQuery
                        ->join(
                            "keuangan_jenis_pembayaran_detail as kjpd",
                            "kjpd.pembayaran_id",
                            "=",
                            "keuangan_pembayaran.id",
                        )
                        ->where("kjpd.jenis_pembayaran_id", $jenisPembayaranId);
                }

                if ($userId) {
                    $spPaymentsQuery->where(
                        "keuangan_pembayaran.user_id",
                        $userId,
                    );
                }

                $spPayments = $spPaymentsQuery
                    ->selectRaw(
                        "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                        "kt.nama",
                        "kt.prodi_id",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                    )
                    ->get();

                $spPayments = $this->mapSemesterPendekPaymentsFromTagihan(
                    $spPayments,
                );
                $spPayments = $this->filterSemesterPendekPaymentsByJenjang(
                    $spPayments,
                    $jenjang,
                );

                $allPayments = $allPayments->concat($spPayments);

                $paymentsByMonth = [];
                foreach ($allPayments as $payment) {
                    $m = (int) date("m", strtotime($payment->tgl));
                    if (!isset($paymentsByMonth[$m])) {
                        $paymentsByMonth[$m] = [];
                    }
                    $paymentsByMonth[$m][] = $payment;
                }

                $pmbByMonth = [];
                foreach ($pmbDataAll as $item) {
                    if (isset($item["tanggal_bayar"])) {
                        $m = (int) date("m", strtotime($item["tanggal_bayar"]));
                        if (!isset($pmbByMonth[$m])) {
                            $pmbByMonth[$m] = [];
                        }
                        $pmbByMonth[$m][] = $item;
                    }
                }

                $allData = [];
                for ($m = 1; $m <= 12; $m++) {
                    $monthPayments = $paymentsByMonth[$m] ?? [];
                    $monthPmb = $pmbByMonth[$m] ?? [];
                    $allData[$m] = $this->getPemasukanBulanData(
                        $year,
                        $m,
                        $columns,
                        $jp,
                        $monthPayments,
                        $monthPmb,
                        $jenjang,
                        $jenisPembayaranId,
                        $userId,
                    );
                }

                $columnHeaders = [];
                foreach ($columns as $col) {
                    $columnHeaders[] = [
                        "key" => $col["key"],
                        "label" => $col["label"],
                    ];
                }

                if ($action === "excel") {
                    return Excel::download(
                        new PemasukanTunaiHarianTahunanExport(
                            $columnHeaders,
                            $allData,
                            $year,
                        ),
                        "Pemasukan_Tunai_Harian_Tahun_" . $year . ".xlsx",
                    );
                }

                return response()->json([
                    "status" => true,
                    "message" => "Data tahunan berhasil diambil",
                    "columns" => $columnHeaders,
                    "all_data" => $allData,
                    "jenis_kelamin" => $jp->kategori ?? "Semua",
                ]);
            } else {
                $monthData = $this->getPemasukanBulanData(
                    $year,
                    $month,
                    $columns,
                    $jp,
                    null,
                    $pmbDataAll,
                    $jenjang,
                    $jenisPembayaranId,
                    $userId,
                );

                $columnHeaders = [];
                foreach ($columns as $col) {
                    $columnHeaders[] = [
                        "key" => $col["key"],
                        "label" => $col["label"],
                    ];
                }

                if ($action === "excel") {
                    return Excel::download(
                        new PemasukanTunaiHarianBulananExport(
                            $columnHeaders,
                            $monthData["data"],
                            $monthData["totals"],
                            $monthData["title"],
                        ),
                        "Pemasukan_Tunai_Harian_" . $request->bulan . ".xlsx",
                    );
                }

                return response()->json([
                    "status" => true,
                    "message" => "Data bulanan berhasil diambil",
                    "title" => $monthData["title"],
                    "columns" => $columnHeaders,
                    "data" => $monthData["data"],
                    "totals" => $monthData["totals"],
                    "jenis_kelamin" => $jp->kategori ?? "Semua",
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    private function getLaporanHarianPaymentFilterMeta($jenisPembayaranId)
    {
        $jenisPembayaranNama = null;
        $jenisPembayaranKategori = null;
        $jenisPembayaranLabel = "Semua";

        if ($jenisPembayaranId) {
            $jpModel = \App\Models\KeuanganJenisPembayaran::find(
                $jenisPembayaranId,
            );

            if ($jpModel) {
                $nama = strtolower(trim($jpModel->nama));
                $jenisPembayaranKategori = $jpModel->kategori;
                $jenisPembayaranLabel = $jpModel->nama;

                if (strpos($nama, "deposit") !== false) {
                    $jenisPembayaranNama = "deposit";
                } elseif (strpos($nama, "transfer") !== false) {
                    $jenisPembayaranNama = "transfer";
                } elseif (strpos($nama, "cash") !== false) {
                    $jenisPembayaranNama = "cash";
                } elseif (strpos($nama, "yayasan") !== false) {
                    $jenisPembayaranNama = "yayasan";
                }
            }
        }

        return [
            "nama" => $jenisPembayaranNama,
            "kategori" => $jenisPembayaranKategori,
            "label" => $jenisPembayaranLabel,
        ];
    }

    private function normalizeLaporanHarianJenisKelamin($value)
    {
        $normalized = strtoupper(trim((string) ($value ?? "")));

        if (in_array($normalized, ["L", "LAKI-LAKI", "LAKI LAKI", "PUTRA", "PRIA"])) {
            return "L";
        }

        if (in_array($normalized, ["P", "PEREMPUAN", "PUTRI", "WANITA"])) {
            return "P";
        }

        return $normalized ?: "-";
    }

    private function normalizeLaporanHarianProdi($value)
    {
        $prodi = trim((string) ($value ?? ""));
        $prodi = preg_replace("/^S[0-9]\s*-\s*/i", "", $prodi);

        return $prodi !== "" ? $prodi : "-";
    }

    private function laporanHarianMatchesJenjang($jenjang, ...$values)
    {
        $signals = collect($values)
            ->flatten()
            ->filter(fn($value) => trim((string) $value) !== "")
            ->map(fn($value) => strtoupper(trim((string) $value)))
            ->values();

        if ($signals->isEmpty()) {
            return false;
        }

        $isSarjana = $signals->contains(
            fn($value) => $value === "S1" ||
                $value === "S-1" ||
                $value === "S.1" ||
                strpos($value, "S1-") === 0 ||
                strpos($value, "S1 ") === 0 ||
                strpos($value, "SARJANA") !== false,
        );

        $isPascasarjana = $signals->contains(
            fn($value) => $value === "S2" ||
                $value === "S3" ||
                $value === "S-2" ||
                $value === "S-3" ||
                $value === "S.2" ||
                $value === "S.3" ||
                strpos($value, "S2-") === 0 ||
                strpos($value, "S3-") === 0 ||
                strpos($value, "S2 ") === 0 ||
                strpos($value, "S3 ") === 0 ||
                strpos($value, "PASCA") !== false ||
                strpos($value, "MAGISTER") !== false ||
                strpos($value, "DOKTOR") !== false,
        );

        if ($jenjang === "pascasarjana") {
            return $isPascasarjana;
        }

        return $isSarjana && !$isPascasarjana;
    }

    private function laporanHarianSemesterPendekMatchesJenjang($jenjang, $krs, $mhs)
    {
        $prodiJenjang = strtoupper(
            trim(
                (string) data_get(
                    $krs,
                    "prodi_jenjang",
                    data_get(
                        $krs,
                        "prodi.jenjang",
                        data_get($krs, "mahasiswa.prodi.jenjang", data_get($mhs, "prodi.jenjang", "")),
                    ),
                ),
            ),
        );

        if ($prodiJenjang !== "") {
            $prodiJenjang = str_replace([".", "-", " "], "", $prodiJenjang);

            if ($jenjang === "pascasarjana") {
                return in_array($prodiJenjang, ["S2", "S3"]);
            }

            return $prodiJenjang === "S1";
        }

        return $this->laporanHarianMatchesJenjang(
            $jenjang,
            data_get($krs, "prodi_nama"),
            data_get($krs, "prodi_alias"),
            data_get($krs, "prodi.nama"),
            data_get($krs, "mahasiswa.prodi.nama"),
            data_get($mhs, "prodi.nama"),
            data_get($mhs, "prodi.alias"),
        );
    }

    private function getLaporanHarianMahasiswaMap($rows)
    {
        $nimList = collect($rows)
            ->pluck("nim")
            ->filter()
            ->unique()
            ->values();

        if ($nimList->isEmpty()) {
            return [];
        }

        try {
            $mahasiswa = Mahasiswa::nim($nimList->toJson(), true);
            return collect($mahasiswa ?: [])
                ->keyBy(fn($m) => data_get($m, "nim"))
                ->all();
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function normalizeLaporanHarianRows($payments, $mahasiswaMap)
    {
        return $payments
            ->map(function ($item) use ($mahasiswaMap) {
                $mhs = $mahasiswaMap[$item->nim] ?? null;

                return [
                    "tanggal_input" => $item->created_at
                        ? date("Y-m-d", strtotime($item->created_at))
                        : null,
                    "tanggal_transaksi" => $item->tanggal
                        ? date("Y-m-d", strtotime($item->tanggal))
                        : null,
                    "kwitansi" => $item->nota ?: $item->nomor,
                    "nim" => $item->nim,
                    "nama" => data_get($mhs, "nama", "-"),
                    "jenis_kelamin" => $this->normalizeLaporanHarianJenisKelamin(
                        data_get(
                            $mhs,
                            "jk.kode",
                            data_get($mhs, "jenis_kelamin", ""),
                        ),
                    ),
                    "prodi" => $this->normalizeLaporanHarianProdi(
                        data_get(
                            $mhs,
                            "prodi.nama",
                            data_get($mhs, "prodi.alias", $item->prodi_nama ?: $item->prodi_alias),
                        ),
                    ),
                    "pembayaran" => $item->tagihan_nama,
                    "nominal" => (float) $item->jumlah,
                    "mata_uang" => MataUangFormatter::fromColumns($item),
                    "metode" => $item->jenis_pembayaran_nama ?: "-",
                    "petugas" => $item->petugas_nama ?: "-",
                    "source" => "SIMKEU",
                ];
            })
            ->values()
            ->toArray();
    }

    private function normalizeLaporanHarianSemesterPendekRows($payments, $jenjang)
    {
        $krsMap = [];
        $krsIds = $payments
            ->pluck("krs_id")
            ->filter()
            ->unique()
            ->values();

        if ($krsIds->isNotEmpty()) {
            try {
                $krsMap = collect(SemesterPendek::searchKrs($krsIds->all()))
                    ->keyBy(fn($item) => data_get($item, "id"))
                    ->all();
            } catch (\Throwable $th) {
                $krsMap = [];
            }
        }

        $paymentNimList = $payments
            ->pluck("nim")
            ->filter()
            ->unique()
            ->values();
        $nimList = collect($krsMap)
            ->map(
                fn($krs) => data_get(
                    $krs,
                    "nim",
                    data_get($krs, "mahasiswa.nim", data_get($krs, "mhs_nim")),
                ),
            )
            ->merge($paymentNimList)
            ->filter()
            ->unique()
            ->values();
        $mahasiswaMap = [];

        if ($nimList->isNotEmpty()) {
            try {
                $mahasiswaMap = collect(Mahasiswa::nim($nimList->toJson(), true) ?: [])
                    ->keyBy(fn($m) => data_get($m, "nim"))
                    ->all();
            } catch (\Throwable $th) {
                $mahasiswaMap = [];
            }
        }

        return $payments
            ->map(function ($item) use ($krsMap, $mahasiswaMap, $jenjang) {
                $krs = $krsMap[$item->krs_id] ?? null;
                $nim = data_get(
                    $krs,
                    "nim",
                    data_get(
                        $krs,
                        "mahasiswa.nim",
                        data_get($krs, "mhs_nim", $item->nim ?? $item->krs_id),
                    ),
                );
                $mhs = $mahasiswaMap[$nim] ?? null;
                $matchesJenjang = $this->laporanHarianSemesterPendekMatchesJenjang(
                    $jenjang,
                    $krs,
                    $mhs,
                );

                if (!$matchesJenjang) {
                    return null;
                }

                return [
                    "tanggal_input" => $item->created_at
                        ? date("Y-m-d", strtotime($item->created_at))
                        : null,
                    "tanggal_transaksi" => $item->tanggal
                        ? date("Y-m-d", strtotime($item->tanggal))
                        : null,
                    "kwitansi" => $item->nota ?? $item->nomor,
                    "nim" => $nim,
                    "nama" => data_get(
                        $krs,
                        "mhs_nama",
                        data_get(
                            $krs,
                            "mahasiswa.nama",
                            data_get($krs, "nama", data_get($mhs, "nama", "-")),
                        ),
                    ),
                    "jenis_kelamin" => $this->normalizeLaporanHarianJenisKelamin(
                        data_get(
                            $krs,
                            "jk_nama",
                            data_get(
                                $krs,
                                "mahasiswa.jk.kode",
                                data_get(
                                    $krs,
                                    "jk.kode",
                                    data_get(
                                        $mhs,
                                        "jk.kode",
                                        data_get($mhs, "jenis_kelamin", ""),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    "prodi" => $this->normalizeLaporanHarianProdi(
                        data_get(
                            $krs,
                            "prodi_nama",
                            data_get(
                                $krs,
                                "mahasiswa.prodi.nama",
                                data_get(
                                    $krs,
                                    "prodi.nama",
                                    data_get(
                                        $mhs,
                                        "prodi.nama",
                                        data_get(
                                            $krs,
                                            "prodi_alias",
                                            data_get(
                                                $krs,
                                                "mahasiswa.prodi.alias",
                                                data_get($krs, "prodi.alias", data_get($mhs, "prodi.alias", "-")),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    "pembayaran" => "SEMESTER PENDEK",
                    "nominal" => (float) $item->jumlah,
                    "mata_uang" => MataUangFormatter::fromColumns($item),
                    "metode" => $item->jenis_pembayaran_nama ?: "-",
                    "petugas" => $item->petugas_nama ?: "-",
                    "source" => "SIMKEU",
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function normalizeLaporanHarianPmbRows($pmbData)
    {
        return collect($pmbData)
            ->map(function ($item) {
                $jenisPembayaran = data_get($item, "jenis_pembayaran", "-");
                $jenisPembayaranUpper = strtoupper((string) $jenisPembayaran);
                $jenisKelamin = data_get($item, "jk", data_get($item, "jenis_kelamin", ""));

                if (!$jenisKelamin) {
                    if (strpos($jenisPembayaranUpper, "PUTRI") !== false) {
                        $jenisKelamin = "P";
                    } elseif (strpos($jenisPembayaranUpper, "PUTRA") !== false) {
                        $jenisKelamin = "L";
                    }
                }

                return [
                    "tanggal_input" => data_get(
                        $item,
                        "created_at",
                        data_get($item, "tanggal_bayar"),
                    )
                        ? date(
                            "Y-m-d",
                            strtotime(
                                data_get(
                                    $item,
                                    "created_at",
                                    data_get($item, "tanggal_bayar"),
                                ),
                            ),
                        )
                        : null,
                    "tanggal_transaksi" => data_get($item, "tanggal_bayar")
                        ? date("Y-m-d", strtotime(data_get($item, "tanggal_bayar")))
                        : null,
                    "kwitansi" => data_get(
                        $item,
                        "kwitansi",
                        data_get(
                            $item,
                            "nota",
                            data_get(
                                $item,
                                "nomor",
                                data_get($item, "id") ? "PMB-" . data_get($item, "id") : "-",
                            ),
                        ),
                    ),
                    "nim" => data_get(
                        $item,
                        "no_daftar",
                        data_get(
                            $item,
                            "nodaftar",
                            data_get(
                                $item,
                                "nomor_pendaftaran",
                                data_get($item, "nim", data_get($item, "siswa_id", "-")),
                            ),
                        ),
                    ),
                    "nama" => data_get(
                        $item,
                        "nama",
                        data_get($item, "nama_lengkap", data_get($item, "nama_siswa", "-")),
                    ),
                    "jenis_kelamin" => $this->normalizeLaporanHarianJenisKelamin($jenisKelamin),
                    "prodi" => $this->normalizeLaporanHarianProdi(
                        data_get(
                            $item,
                            "prodi_nama",
                            data_get(
                                $item,
                                "prodi",
                                data_get($item, "program_studi", data_get($item, "prodi_alias", "-")),
                            ),
                        ),
                    ),
                    "pembayaran" => data_get(
                        $item,
                        "pembayaran",
                        data_get($item, "tagihan", data_get($item, "mhsdari", "PMB") ?: "PMB"),
                    ),
                    "nominal" => (float) data_get($item, "nominal", 0),
                    "mata_uang" => MataUangFormatter::defaultCurrency(),
                    "metode" => $jenisPembayaran,
                    "petugas" => data_get($item, "petugas", "PMB"),
                    "source" => "PMB",
                ];
            })
            ->values()
            ->toArray();
    }

    private function getLaporanHarianDetailData(Request $request)
    {
        $tanggal = $request->input("tanggal", date("Y-m-d"));
        $jenjang = $request->input("jenjang", "sarjana");
        $jenisPembayaranId = $request->input("jenis_pembayaran_id");
        $userId = $request->input("user_id");
        $jp = Helper::getJenisKelaminUser();
        $paymentMeta = $this->getLaporanHarianPaymentFilterMeta(
            $jenisPembayaranId,
        );

        $paymentsQuery = KeuanganPembayaran::join(
            "keuangan_tagihan as kt",
            "kt.id",
            "=",
            "keuangan_pembayaran.tagihan_id",
        )
            ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
            ->leftJoin(
                "keuangan_nota as kn",
                "kn.pembayaran_id",
                "=",
                "keuangan_pembayaran.id",
            )
            ->leftJoin(
                "keuangan_jenis_pembayaran_detail as kjpd",
                "kjpd.pembayaran_id",
                "=",
                "keuangan_pembayaran.id",
            )
            ->leftJoin(
                "keuangan_jenis_pembayaran as kjp",
                "kjp.id",
                "=",
                "kjpd.jenis_pembayaran_id",
            )
            ->leftJoin("users as u", "u.id", "=", "keuangan_pembayaran.user_id")
            ->leftJoin("prodi as p", "p.id", "=", "kt.prodi_id")
            ->whereDate("keuangan_pembayaran.tanggal", $tanggal)
            ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%")
            ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

        if ($jenjang === "sarjana") {
            $paymentsQuery->where("p.jenjang", "S1");
        } elseif ($jenjang === "pascasarjana") {
            $paymentsQuery->whereIn("p.jenjang", ["S2", "S3"]);
        }

        if ($jenisPembayaranId) {
            $paymentsQuery->where(
                "kjpd.jenis_pembayaran_id",
                $jenisPembayaranId,
            );
        }

        if ($userId) {
            $paymentsQuery->where("keuangan_pembayaran.user_id", $userId);
        }

        $payments = $paymentsQuery
            ->select(
                "keuangan_pembayaran.id",
                "keuangan_pembayaran.nomor",
                "keuangan_pembayaran.tanggal",
                "keuangan_pembayaran.created_at",
                "keuangan_pembayaran.nim",
                "keuangan_pembayaran.jumlah",
                "kt.nama as tagihan_nama",
                "p.nama as prodi_nama",
                "p.alias as prodi_alias",
                "kjp.nama as jenis_pembayaran_nama",
                "u.name as petugas_nama",
                "mu.id as mata_uang_id",
                "mu.kode as mata_uang_kode",
                "mu.nama as mata_uang_nama",
                "mu.simbol as mata_uang_simbol",
            )
            ->addSelect(DB::raw("COALESCE(kn.nota, keuangan_pembayaran.nomor) AS nota"))
            ->orderBy("keuangan_pembayaran.created_at")
            ->orderBy("keuangan_pembayaran.id")
            ->get();

        $rows = $this->normalizeLaporanHarianRows(
            $payments,
            $this->getLaporanHarianMahasiswaMap($payments),
        );

        $spPaymentsQuery = KeuanganPembayaran::join(
            "keuangan_tagihan as kt",
            "kt.id",
            "=",
            "keuangan_pembayaran.tagihan_id",
        )
            ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
            ->leftJoin(
                "keuangan_nota as kn",
                "kn.pembayaran_id",
                "=",
                "keuangan_pembayaran.id",
            )
            ->leftJoin(
                "keuangan_jenis_pembayaran_detail as kjpd",
                "kjpd.pembayaran_id",
                "=",
                "keuangan_pembayaran.id",
            )
            ->leftJoin(
                "keuangan_jenis_pembayaran as kjp",
                "kjp.id",
                "=",
                "kjpd.jenis_pembayaran_id",
            )
            ->leftJoin(
                "users as u",
                "u.id",
                "=",
                "keuangan_pembayaran.user_id",
            )
            ->whereDate("keuangan_pembayaran.tanggal", $tanggal)
            ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%")
            ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

        if ($jenisPembayaranId) {
            $spPaymentsQuery->where(
                "kjpd.jenis_pembayaran_id",
                $jenisPembayaranId,
            );
        }

        if ($userId) {
            $spPaymentsQuery->where(
                "keuangan_pembayaran.user_id",
                $userId,
            );
        }

        $spPayments = $spPaymentsQuery
            ->select(
                "keuangan_pembayaran.id",
                "keuangan_pembayaran.nomor",
                "keuangan_pembayaran.tanggal",
                "keuangan_pembayaran.created_at",
                "keuangan_pembayaran.nim",
                "keuangan_pembayaran.jumlah",
                "kt.nama as tagihan_nama",
                "kjp.nama as jenis_pembayaran_nama",
                "u.name as petugas_nama",
                "mu.id as mata_uang_id",
                "mu.kode as mata_uang_kode",
                "mu.nama as mata_uang_nama",
                "mu.simbol as mata_uang_simbol",
            )
            ->addSelect(DB::raw("COALESCE(kn.nota, keuangan_pembayaran.nomor) AS nota"))
            ->orderBy("keuangan_pembayaran.created_at")
            ->orderBy("keuangan_pembayaran.id")
            ->get();

        $spPayments = $this->mapSemesterPendekPaymentsFromTagihan($spPayments);
        $rows = array_merge(
            $rows,
            $this->normalizeLaporanHarianSemesterPendekRows($spPayments, $jenjang),
        );

        if (!$userId) {
            try {
                $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    "apikey" => env("PMB_API_KEY"),
                ])->get(rtrim(env("PMB_URL"), "/") . "/simkeu/pembayaran", [
                    "start_date" => $tanggal,
                    "end_date" => $tanggal,
                    "jenjang" => $jenjang,
                    "jenis_kelamin" => $paymentMeta["kategori"],
                    "jenis_pembayaran" => $paymentMeta["nama"],
                ]);

                if ($pmbResponse->successful()) {
                    $pmbResData = $pmbResponse->json();
                    $rows = array_merge(
                        $rows,
                        $this->normalizeLaporanHarianPmbRows(
                            $pmbResData["data"] ?? [],
                        ),
                    );
                }
            } catch (\Throwable $th) {
                // PMB is optional; keep SIMKEU report available if it fails.
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp(
                ($a["tanggal_input"] ?? "") . ($a["kwitansi"] ?? ""),
                ($b["tanggal_input"] ?? "") . ($b["kwitansi"] ?? ""),
            );
        });

        $no = 1;
        foreach ($rows as &$row) {
            $row["no"] = $no++;
        }
        unset($row);

        $total = collect($rows)->sum("nominal");
        $totalByCurrency = [];
        foreach ($rows as $row) {
            MataUangFormatter::addToTotals(
                $totalByCurrency,
                $row["nominal"],
                $row["mata_uang"] ?? MataUangFormatter::defaultCurrency(),
            );
        }
        $totalByCurrency = MataUangFormatter::normalizeTotals($totalByCurrency);

        return [
            "title" => "LAPORAN HARIAN",
            "tanggal" => $tanggal,
            "kategori" =>
                ($jp->kategori ?? "Semua") .
                " ( Jenjang : " .
                ($jenjang === "sarjana" ? "Sarjana" : "Pascasarjana") .
                ", Jenis Pembayaran : " .
                $paymentMeta["label"] .
                " )",
            "jenis_kelamin" => $jp->kategori ?? "Semua",
            "rows" => $rows,
            "total" => $total,
            "total_by_currency" => $totalByCurrency,
        ];
    }

    public function laporanHarianDetail(Request $request)
    {
        try {
            $data = $this->getLaporanHarianDetailData($request);
            $action = $request->input("action", "json");

            if ($action === "excel") {
                return Excel::download(
                    new LaporanHarianDetailExport($data),
                    "Laporan_Harian_" . $data["tanggal"] . ".xlsx",
                );
            }

            if ($action === "pdf") {
                return LaporanHarianDetailPdf::pdf($data);
            }

            return response()->json([
                "status" => true,
                "message" => "Data laporan harian berhasil diambil",
                "data" => $data["rows"],
                "total" => $data["total"],
                "total_by_currency" => $data["total_by_currency"],
                "title" => $data["title"],
                "tanggal" => $data["tanggal"],
                "kategori" => $data["kategori"],
                "jenis_kelamin" => $data["jenis_kelamin"],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function pemasukanUiiDalwa(Request $request)
    {
        try {
            $jp = Helper::getJenisKelaminUser();

            if (
                $request->has("jenis_kelamin") &&
                $request->jenis_kelamin != ""
            ) {
                $reqJk = strtolower($request->jenis_kelamin);
                if ($reqJk == "putra") {
                    $jp = (object) ["id" => 8, "kategori" => "Putra"];
                } elseif ($reqJk == "putri") {
                    $jp = (object) ["id" => 9, "kategori" => "Putri"];
                } elseif ($reqJk == "%" && auth()->user()->role_id == 1) {
                    $jp = (object) ["id" => "%", "kategori" => "%"];
                }
            }

            $action = $request->input("action", "json");
            $mode = $request->input("mode", "bulanan"); // 'bulanan' atau 'tahunan'
            $jenjang = $request->input("jenjang", "sarjana");
            $userId = $request->input("user_id");

            // Columns setup (Categories)
            $columns = $this->getPemasukanTunaiColumns($jenjang);

            // Initialization
            $data = [];
            foreach ($columns as $col) {
                $data[$col["key"]] = [
                    "label" => $col["label"],
                    "tunai" => 0,
                    "transfer" => 0,
                    "yayasan" => 0,
                    "total" => 0,
                    "tunai_by_currency" => [],
                    "transfer_by_currency" => [],
                    "yayasan_by_currency" => [],
                    "total_by_currency" => [],
                ];
            }
            $data["total_all"] = [
                "label" => "TOTAL",
                "tunai" => 0,
                "transfer" => 0,
                "yayasan" => 0,
                "total" => 0,
                "tunai_by_currency" => [],
                "transfer_by_currency" => [],
                "yayasan_by_currency" => [],
                "total_by_currency" => [],
            ];

            $pmbUrl = rtrim(env("PMB_URL"), "/") . "/simkeu/pembayaran";
            $pmbApiKey = env("PMB_API_KEY");
            $pmbDataAll = [];

            if ($mode === "tahunan") {
                $year = (int) $request->tahun;
                $startDate = sprintf("%04d-01-01", $year);
                $endDate = sprintf("%04d-12-31", $year);
                $title =
                    "LAPORAN PEMASUKAN " .
                    strtoupper($jenjang === "sarjana" ? "S1" : "PASCASARJANA") .
                    " UII DALWA TAHUN $year";
            } else {
                $parts = explode("-", $request->bulan);
                if (count($parts) != 2) {
                    throw new \Exception(
                        "Format bulan tidak valid. Gunakan YYYY-MM.",
                    );
                }
                $year = (int) $parts[0];
                $month = (int) $parts[1];
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $startDate = sprintf("%04d-%02d-01", $year, $month);
                $endDate = sprintf(
                    "%04d-%02d-%02d",
                    $year,
                    $month,
                    $daysInMonth,
                );

                $bulanNames = [
                    1 => "JANUARI",
                    2 => "FEBRUARI",
                    3 => "MARET",
                    4 => "APRIL",
                    5 => "MEI",
                    6 => "JUNI",
                    7 => "JULI",
                    8 => "AGUSTUS",
                    9 => "SEPTEMBER",
                    10 => "OKTOBER",
                    11 => "NOVEMBER",
                    12 => "DESEMBER",
                ];
                $title =
                    "LAPORAN PEMASUKAN " .
                    strtoupper($jenjang === "sarjana" ? "S1" : "PASCASARJANA") .
                    " UII DALWA BULAN " .
                    $bulanNames[$month] .
                    " $year";
            }

            if (!$userId) {
                try {
                    $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders(
                        [
                            "apikey" => $pmbApiKey,
                        ],
                    )
                        ->timeout(5)
                        ->get($pmbUrl, [
                            "start_date" => $startDate,
                            "end_date" => $endDate,
                            "jenjang" => $jenjang,
                            "jenis_kelamin" => $jp->kategori ?? "%",
                        ]);

                    if ($pmbResponse->successful()) {
                        $pmbResData = $pmbResponse->json();
                        if (
                            isset($pmbResData["data"]) &&
                            is_array($pmbResData["data"])
                        ) {
                            $pmbDataAll = $pmbResData["data"];
                        }
                    }
                } catch (\Throwable $th) {
                    // Ignore API error, proceed with empty PMB data
                }
            }

            $bulanNames = [
                1 => "JANUARI",
                2 => "FEBRUARI",
                3 => "MARET",
                4 => "APRIL",
                5 => "MEI",
                6 => "JUNI",
                7 => "JULI",
                8 => "AGUSTUS",
                9 => "SEPTEMBER",
                10 => "OKTOBER",
                11 => "NOVEMBER",
                12 => "DESEMBER",
            ];

            // SIMKEU Payments
            $paymentsQuery = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                ->join(
                    "keuangan_jenis_pembayaran_detail as kjpd",
                    "kjpd.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->join(
                    "keuangan_jenis_pembayaran as kjp",
                    "kjp.id",
                    "=",
                    "kjpd.jenis_pembayaran_id",
                )
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%")
                ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

            if ($jenjang === "sarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->where("p.jenjang", "S1");
            } elseif ($jenjang === "pascasarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->where("p.jenjang", "!=", "S1");
            }

            if ($userId) {
                $paymentsQuery->where("keuangan_pembayaran.user_id", $userId);
            }

            if ($mode === "tahunan") {
                $payments = $paymentsQuery
                    ->selectRaw(
                        "kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, MONTH(keuangan_pembayaran.tanggal) as bulan, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                        "bulan",
                    )
                    ->get();
            } else {
                $payments = $paymentsQuery
                    ->selectRaw(
                        "kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                    )
                    ->get();
            }

            $spPaymentsQuery = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
                ->join(
                    "keuangan_jenis_pembayaran_detail as kjpd",
                    "kjpd.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->join(
                    "keuangan_jenis_pembayaran as kjp",
                    "kjp.id",
                    "=",
                    "kjpd.jenis_pembayaran_id",
                )
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%")
                ->where("keuangan_pembayaran.jk_id", "LIKE", "%$jp->id%");

            if ($userId) {
                $spPaymentsQuery->where(
                    "keuangan_pembayaran.user_id",
                    $userId,
                );
            }

            if ($mode === "tahunan") {
                $spPayments = $spPaymentsQuery
                    ->selectRaw(
                        "kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, MONTH(keuangan_pembayaran.tanggal) as bulan, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                        "bulan",
                    )
                    ->get();
            } else {
                $spPayments = $spPaymentsQuery
                    ->selectRaw(
                        "kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "mu.id",
                        "mu.kode",
                        "mu.nama",
                        "mu.simbol",
                    )
                    ->get();
            }

            $spPayments = $this->mapSemesterPendekPaymentsFromTagihan(
                $spPayments,
            );
            $spPayments = $this->filterSemesterPendekPaymentsByJenjang(
                $spPayments,
                $jenjang,
            );

            $payments = $payments->concat($spPayments);

            // Mapping logic for Tunai, Transfer, Yayasan
            $getPaymentType = function ($namaRaw) {
                $nama = strtolower($namaRaw);
                if (
                    strpos($nama, "cash") !== false ||
                    strpos($nama, "tunai") !== false
                ) {
                    return "tunai";
                }
                if (
                    strpos($nama, "transfer") !== false ||
                    strpos($nama, "tf") !== false
                ) {
                    return "transfer";
                }
                if (
                    strpos($nama, "yayasan") !== false ||
                    strpos($nama, "yys") !== false
                ) {
                    return "yayasan";
                }
                return "tunai"; // fallback
            };

            // Structure for monthly data
            $allDataMonths = [];
            if ($mode === "tahunan") {
                for ($m = 1; $m <= 12; $m++) {
                    $mTitle =
                        "LAPORAN PEMASUKAN " .
                        strtoupper(
                            $jenjang === "sarjana" ? "S1" : "PASCASARJANA",
                        ) .
                        " UII DALWA BULAN " .
                        $bulanNames[$m] .
                        " $year";
                    $mData = [];
                    foreach ($columns as $col) {
                        $mData[$col["key"]] = [
                            "label" => $col["label"],
                            "tunai" => 0,
                            "transfer" => 0,
                            "yayasan" => 0,
                            "total" => 0,
                            "tunai_by_currency" => [],
                            "transfer_by_currency" => [],
                            "yayasan_by_currency" => [],
                            "total_by_currency" => [],
                        ];
                    }
                    $allDataMonths[$m] = [
                        "title" => $mTitle,
                        "data_map" => $mData,
                        "total_all" => [
                            "tunai" => 0,
                            "transfer" => 0,
                            "yayasan" => 0,
                            "total" => 0,
                            "tunai_by_currency" => [],
                            "transfer_by_currency" => [],
                            "yayasan_by_currency" => [],
                            "total_by_currency" => [],
                        ],
                    ];
                }
            }

            foreach ($payments as $payment) {
                $namaRaw = $payment->tagihan_nama;
                $namaUpper = strtoupper($namaRaw);
                $prodi_id = $payment->prodi_id;
                $jumlah = (float) $payment->total_jumlah;
                $paymentType = $getPaymentType($payment->jenis_pembayaran_nama);
                $bulanVal = $mode === "tahunan" ? (int) $payment->bulan : null;
                $mataUang = MataUangFormatter::fromColumns($payment);

                $assignedKey = null;

                if (strpos($namaUpper, "SPP") !== false) {
                    $assignedKey = "spp_prodi_" . $prodi_id;
                } else {
                    foreach ($columns as $c) {
                        if (
                            $c["type"] === "fixed" &&
                            $this->matchesPemasukanFixedCategory($namaRaw, $c)
                        ) {
                            $assignedKey = $c["key"];
                            break;
                        }
                    }
                    if (!$assignedKey) {
                        $assignedKey = "other_" . md5($namaRaw);
                    }
                }

                // Add to year/total data
                if (isset($data[$assignedKey])) {
                    $data[$assignedKey][$paymentType] += $jumlah;
                    $data[$assignedKey]["total"] += $jumlah;
                    $data["total_all"][$paymentType] += $jumlah;
                    $data["total_all"]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $data[$assignedKey][$paymentType . "_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data[$assignedKey]["total_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data["total_all"][$paymentType . "_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data["total_all"]["total_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                }

                // Add to month data
                if (
                    $mode === "tahunan" &&
                    $bulanVal &&
                    isset($allDataMonths[$bulanVal]["data_map"][$assignedKey])
                ) {
                    $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                        $paymentType
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                        "total"
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["total_all"][
                        $paymentType
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["total_all"]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                            $paymentType . "_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                            "total_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["total_all"][
                            $paymentType . "_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["total_all"][
                            "total_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                }
            }

            // PMB Data
            foreach ($pmbDataAll as $pmb) {
                if (!isset($pmb["tanggal_bayar"]) || !isset($pmb["nominal"])) {
                    continue;
                }

                $jumlah = (float) $pmb["nominal"];
                $mataUang = MataUangFormatter::defaultCurrency();
                $paymentType = $getPaymentType(
                    $pmb["jenis_pembayaran"] ?? "cash",
                );
                $pmbDate = explode("-", $pmb["tanggal_bayar"]);
                $bulanVal = count($pmbDate) >= 2 ? (int) $pmbDate[1] : 1;

                $assignedKey = "pmb";

                if (isset($data[$assignedKey])) {
                    $data[$assignedKey][$paymentType] += $jumlah;
                    $data[$assignedKey]["total"] += $jumlah;
                    $data["total_all"][$paymentType] += $jumlah;
                    $data["total_all"]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $data[$assignedKey][$paymentType . "_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data[$assignedKey]["total_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data["total_all"][$paymentType . "_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $data["total_all"]["total_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                }

                if (
                    $mode === "tahunan" &&
                    isset($allDataMonths[$bulanVal]["data_map"][$assignedKey])
                ) {
                    $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                        $paymentType
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                        "total"
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["total_all"][
                        $paymentType
                    ] += $jumlah;
                    $allDataMonths[$bulanVal]["total_all"]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                            $paymentType . "_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey][
                            "total_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["total_all"][
                            $paymentType . "_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $allDataMonths[$bulanVal]["total_all"][
                            "total_by_currency"
                        ],
                        $jumlah,
                        $mataUang,
                    );
                }
            }

            $currencyFields = [
                "tunai_by_currency",
                "transfer_by_currency",
                "yayasan_by_currency",
                "total_by_currency",
            ];
            foreach ($data as &$row) {
                foreach ($currencyFields as $field) {
                    $row[$field] = MataUangFormatter::normalizeTotals(
                        $row[$field],
                    );
                }
            }
            unset($row);

            foreach ($allDataMonths as &$monthInfo) {
                foreach ($monthInfo["data_map"] as &$row) {
                    foreach ($currencyFields as $field) {
                        $row[$field] = MataUangFormatter::normalizeTotals(
                            $row[$field],
                        );
                    }
                }
                unset($row);

                foreach ($currencyFields as $field) {
                    $monthInfo["total_all"][$field] =
                        MataUangFormatter::normalizeTotals(
                            $monthInfo["total_all"][$field],
                        );
                }
            }
            unset($monthInfo);

            $tableData = [];
            $no = 1;
            foreach ($columns as $col) {
                $row = $data[$col["key"]];
                $tableData[] = [
                    "no" => $no++,
                    "kategori" => $row["label"],
                    "tunai" => $row["tunai"],
                    "transfer" => $row["transfer"],
                    "yayasan" => $row["yayasan"],
                    "total" => $row["total"],
                    "tunai_by_currency" => $row["tunai_by_currency"],
                    "transfer_by_currency" => $row["transfer_by_currency"],
                    "yayasan_by_currency" => $row["yayasan_by_currency"],
                    "total_by_currency" => $row["total_by_currency"],
                ];
            }

            $formattedAllData = [];
            if ($mode === "tahunan") {
                foreach ($allDataMonths as $m => $mInfo) {
                    // Only include months that have at least some total > 0 if desired?
                    // Actually let's include all 12 or maybe only those with data. Let's include all 12.
                    $mTable = [];
                    $no = 1;
                    foreach ($columns as $col) {
                        $row = $mInfo["data_map"][$col["key"]];
                        $mTable[] = [
                            "no" => $no++,
                            "kategori" => $row["label"],
                            "tunai" => $row["tunai"],
                            "transfer" => $row["transfer"],
                            "yayasan" => $row["yayasan"],
                            "total" => $row["total"],
                            "tunai_by_currency" => $row["tunai_by_currency"],
                            "transfer_by_currency" => $row["transfer_by_currency"],
                            "yayasan_by_currency" => $row["yayasan_by_currency"],
                            "total_by_currency" => $row["total_by_currency"],
                        ];
                    }
                    $formattedAllData[] = [
                        "title" => $mInfo["title"],
                        "data" => $mTable,
                        "totals" => $mInfo["total_all"],
                    ];
                }
            }

            if ($action === "excel") {
                // If tahuan, it exports the yearly total. (Can be modified later if user wants all months in excel).
                return Excel::download(
                    new \App\Exports\PemasukanUiiDalwaExport(
                        $tableData,
                        $data["total_all"],
                        $title,
                    ),
                    "Pemasukan_UII_Dalwa_" . date("YmdHis") . ".xlsx",
                );
            }

            return response()->json([
                "status" => true,
                "message" => "Data Pemasukan UII Dalwa berhasil diambil",
                "title" => $title,
                "data" => $tableData,
                "totals" => $data["total_all"],
                "all_data" => $formattedAllData,
                "jenis_kelamin" => $jp->kategori ?? "Semua",
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
                "trace" => $th->getTraceAsString(),
            ]);
        }
    }
}
