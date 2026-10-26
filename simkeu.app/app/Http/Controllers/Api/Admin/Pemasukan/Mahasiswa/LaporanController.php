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
use App\Models\KeuanganPemasukanUmum;
use App\Models\KeuanganPengembalianDana;

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

        // d) Pemasukan Umum
        if ($jenjang === "sarjana") {
            $columns[] = [
                "key" => "pemasukan_umum",
                "label" => "PEMASUKAN UMUM",
                "type" => "umum",
            ];
        }

        // e) Pengembalian Dana (paling akhir)
        if ($jenjang === "sarjana") {
            $columns[] = [
                "key" => "pengembalian_dana",
                "label" => "PENGEMBALIAN",
                "type" => "pengembalian",
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
        $periodMeta = \App\Services\LaporanCutOffService::getMonthPeriodMeta((int) $year, (int) $month);
        $daysInMonth = $periodMeta['days_in_current_month'];
        $startDate = $periodMeta['query_start_date'];
        $endDate = $periodMeta['query_end_date'];

        $paymentFilter = $this->resolveJenisPembayaranFilter($jenisPembayaranId);
        $targetJpIds = $paymentFilter["ids"];
        if ($paymentFilter["kategori"]) {
            if ($jp && $jp->id == "%") {
                $jp = clone $jp;
                $jp->id = $paymentFilter["kategori"] === "Putra" ? 8 : 9;
                $jp->kategori = $paymentFilter["kategori"];
            }
        }

        $includePmb = $paymentFilter["include_pmb"] ?? true;
        if (!$includePmb) {
            $pmbData = [];
        } else {
            $pmbData = $this->filterPmbDataByPaymentMethod($pmbData, $paymentFilter);
        }

        if ($prefetchedPayments === null) {
            $paymentsQuery = KeuanganPembayaran::join(
                "keuangan_tagihan as kt",
                "kt.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
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
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%");

            if ($jp && $jp->id != "%") {
                $paymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
            }

            if ($jenjang === "sarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->where("p.jenjang", "S1");
            } elseif ($jenjang === "pascasarjana") {
                $paymentsQuery
                    ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                    ->whereIn("p.jenjang", ["S2", "S3"]);
            }

            if (!empty($targetJpIds)) {
                $paymentsQuery->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $paymentsQuery->where("keuangan_pembayaran.user_id", $userId);
            }

            $payments = $paymentsQuery
                ->selectRaw(
                    "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(kjp.nama, '') as jenis_pembayaran_nama, COALESCE(kjp.id, 0) as jenis_pembayaran_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                )
                ->groupBy(
                    DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                    "kt.nama",
                    "kt.prodi_id",
                    "kjp.nama",
                    "kjp.id",
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
                ->whereBetween("keuangan_pembayaran.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ])
                ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%");

            if ($jp && $jp->id != "%") {
                $spPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
            }

            if (!empty($targetJpIds)) {
                $spPaymentsQuery->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $spPaymentsQuery->where("keuangan_pembayaran.user_id", $userId);
            }

            $spPayments = $spPaymentsQuery
                ->selectRaw(
                    "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, COALESCE(kjp.nama, '') as jenis_pembayaran_nama, COALESCE(kjp.id, 0) as jenis_pembayaran_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                )
                ->groupBy(
                    DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                    "kt.nama",
                    "kt.prodi_id",
                    "kjp.nama",
                    "kjp.id",
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

        $rowNo = 1;
        $firstDayStr = sprintf("%04d-%02d-01", $year, $month);
        $carryOverDatesSet = array_flip($periodMeta['carry_over_dates'] ?? []);

        // Tanggal bulan berjalan (murni tanggal 1 sampai akhir bulan)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $row = [
                "no" => $rowNo++,
                "tanggal" => $dateStr,
                "is_carry_over" => false,
                "label_tanggal" => sprintf("%02d/%02d", $day, $month),
                "jumlah" => 0,
            ];
            if ($day === 1) {
                $row["has_carry_over"] = false;
                $row["carry_over_total"] = 0;
                $row["carry_over_total_by_currency"] = [];
                $row["carry_over_breakdown"] = [];
                $row["original_total"] = 0;
                $row["original_total_by_currency"] = [];
            }
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

        // Helper untuk memasukkan transaksi ke dataMap: limpahan otomatis masuk ke Tanggal 1
        $accumulatePayment = function ($tgl, $assignedKey, $jumlah, $mataUang) use (
            &$dataMap,
            &$totals,
            $colMap,
            $firstDayStr,
            $carryOverDatesSet,
            $periodMeta
        ) {
            if (!$assignedKey || !isset($colMap[$assignedKey])) {
                return;
            }

            $isCarryOver = isset($carryOverDatesSet[$tgl]);
            $targetDate = $isCarryOver ? $firstDayStr : $tgl;

            if (!isset($dataMap[$targetDate])) {
                return;
            }

            $dataMap[$targetDate][$assignedKey] += $jumlah;
            $dataMap[$targetDate]["jumlah"] += $jumlah;
            $totals[$assignedKey] += $jumlah;
            $totals["jumlah"] += $jumlah;

            MataUangFormatter::addToTotals(
                $dataMap[$targetDate][$assignedKey . "_by_currency"],
                $jumlah,
                $mataUang
            );
            MataUangFormatter::addToTotals(
                $dataMap[$targetDate]["jumlah_by_currency"],
                $jumlah,
                $mataUang
            );
            MataUangFormatter::addToTotals(
                $totals[$assignedKey . "_by_currency"],
                $jumlah,
                $mataUang
            );
            MataUangFormatter::addToTotals(
                $totals["jumlah_by_currency"],
                $jumlah,
                $mataUang
            );

            if ($targetDate === $firstDayStr) {
                if ($isCarryOver) {
                    $dataMap[$firstDayStr]["has_carry_over"] = true;
                    $dataMap[$firstDayStr]["carry_over_total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["carry_over_total_by_currency"],
                        $jumlah,
                        $mataUang
                    );

                    if (!isset($dataMap[$firstDayStr]["carry_over_breakdown"][$tgl])) {
                        $dNum = (int) date('j', strtotime($tgl));
                        $mNum = (int) date('n', strtotime($tgl));
                        $yNum = (int) date('Y', strtotime($tgl));
                        $mName = $periodMeta['prev_month_name'] ?? date('F', strtotime($tgl));
                        $bRow = [
                            "tanggal" => $tgl,
                            "label" => sprintf("%02d %s %04d", $dNum, $mName, $yNum),
                            "short_label" => sprintf("%02d/%02d", $dNum, $mNum),
                            "total" => 0,
                            "total_by_currency" => [],
                        ];
                        foreach ($columns as $c) {
                            $bRow[$c["key"]] = 0;
                            $bRow[$c["key"] . "_by_currency"] = [];
                        }
                        $dataMap[$firstDayStr]["carry_over_breakdown"][$tgl] = $bRow;
                    }
                    $dataMap[$firstDayStr]["carry_over_breakdown"][$tgl][$assignedKey] += $jumlah;
                    $dataMap[$firstDayStr]["carry_over_breakdown"][$tgl]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["carry_over_breakdown"][$tgl][$assignedKey . "_by_currency"],
                        $jumlah,
                        $mataUang
                    );
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["carry_over_breakdown"][$tgl]["total_by_currency"],
                        $jumlah,
                        $mataUang
                    );
                } else {
                    $dataMap[$firstDayStr]["original_total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["original_total_by_currency"],
                        $jumlah,
                        $mataUang
                    );

                    if (!isset($dataMap[$firstDayStr]["original_breakdown"])) {
                        $obRow = [
                            "tanggal" => $firstDayStr,
                            "label" => sprintf("01 %s %04d", $periodMeta['current_month_name'] ?? date('F', strtotime($firstDayStr)), $year),
                            "short_label" => sprintf("01/%02d", $month),
                            "total" => 0,
                            "total_by_currency" => [],
                        ];
                        foreach ($columns as $c) {
                            $obRow[$c["key"]] = 0;
                            $obRow[$c["key"] . "_by_currency"] = [];
                        }
                        $dataMap[$firstDayStr]["original_breakdown"] = $obRow;
                    }
                    $dataMap[$firstDayStr]["original_breakdown"][$assignedKey] += $jumlah;
                    $dataMap[$firstDayStr]["original_breakdown"]["total"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["original_breakdown"][$assignedKey . "_by_currency"],
                        $jumlah,
                        $mataUang
                    );
                    MataUangFormatter::addToTotals(
                        $dataMap[$firstDayStr]["original_breakdown"]["total_by_currency"],
                        $jumlah,
                        $mataUang
                    );
                }
            }
        };

        foreach ($payments as $payment) {
            $tgl = $payment->tgl;
            $namaRaw = $payment->tagihan_nama;
            $namaUpper = strtoupper($namaRaw);
            $prodi_id = $payment->prodi_id;
            $jumlah = (float) $payment->total_jumlah;
            $mataUang = MataUangFormatter::fromColumns($payment);

            // Cek apakah pembayaran masuk dalam periode pembukuan bulan ini sesuai setting cut-off
            if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                $tgl,
                $payment->jenis_pembayaran_nama ?? '',
                $payment->jenis_pembayaran_id ? (int) $payment->jenis_pembayaran_id : null,
                (int) $year,
                (int) $month
            )) {
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

            $accumulatePayment($tgl, $assignedKey, $jumlah, $mataUang);
        }

        // Process PMB Data
        foreach ($pmbData as $pmb) {
            if (!isset($pmb["tanggal_bayar"]) || !isset($pmb["nominal"])) {
                continue;
            }

            $tgl = date("Y-m-d", strtotime($pmb["tanggal_bayar"]));
            $jumlah = (float) $pmb["nominal"];
            $pmbMethod = $pmb["metode_pembayaran"] ?? $pmb["jenis_pembayaran"] ?? "cash";

            if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                $tgl,
                $pmbMethod,
                null,
                (int) $year,
                (int) $month
            )) {
                continue;
            }

            $assignedKey = "pmb";
            $mataUang = MataUangFormatter::defaultCurrency();
            $accumulatePayment($tgl, $assignedKey, $jumlah, $mataUang);
        }

        // Process Pemasukan Umum Data
        if ($jenjang === "sarjana") {
            $puQuery = KeuanganPemasukanUmum::whereBetween("tanggal", [
                $startDate . " 00:00:00",
                $endDate . " 23:59:59",
            ]);

            if (!empty($targetJpIds)) {
                $puQuery->whereIn("jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $puQuery->where("petugas_id", $userId);
            } elseif ($jp && $jp->id != '%') {
                $jkTarget = $jp->id == 8 ? 'Laki-laki' : ($jp->id == 9 ? 'Perempuan' : null);
                if ($jkTarget) {
                    $puQuery->whereHas('petugas', function ($q) use ($jkTarget) {
                        $q->whereIn('jenis_kelamin', [$jkTarget, '*']);
                    });
                }
            }

            $puRecords = $puQuery->leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "keuangan_pemasukan_umum.jenis_pembayaran_id")
                ->selectRaw("DATE(keuangan_pemasukan_umum.tanggal) as tgl, COALESCE(kjp.nama, 'Cash') as jenis_pembayaran_nama, keuangan_pemasukan_umum.jenis_pembayaran_id, SUM(keuangan_pemasukan_umum.nominal) as total_nominal")
                ->groupBy(DB::raw("DATE(keuangan_pemasukan_umum.tanggal)"), "kjp.nama", "keuangan_pemasukan_umum.jenis_pembayaran_id")
                ->get();

            $assignedKey = "pemasukan_umum";
            $mataUang = MataUangFormatter::defaultCurrency();
            foreach ($puRecords as $pu) {
                $tgl = $pu->tgl;
                $jumlah = (float) $pu->total_nominal;

                if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                    $tgl,
                    $pu->jenis_pembayaran_nama,
                    $pu->jenis_pembayaran_id,
                    (int) $year,
                    (int) $month
                )) {
                    continue;
                }

                $accumulatePayment($tgl, $assignedKey, $jumlah, $mataUang);
            }
        }

        // Process Pengembalian Dana Data
        if ($jenjang === "sarjana") {
            $pdQuery = KeuanganPengembalianDana::whereBetween("tanggal", [
                $startDate . " 00:00:00",
                $endDate . " 23:59:59",
            ]);

            if (!empty($targetJpIds)) {
                $pdQuery->whereIn("jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $pdQuery->where("petugas_id", $userId);
            } elseif ($jp && $jp->id != '%') {
                $jkTarget = $jp->id == 8 ? 'Laki-laki' : ($jp->id == 9 ? 'Perempuan' : null);
                if ($jkTarget) {
                    $pdQuery->whereHas('petugas', function ($q) use ($jkTarget) {
                        $q->whereIn('jenis_kelamin', [$jkTarget, '*']);
                    });
                }
            }

            $pdRecords = $pdQuery->leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "keuangan_pengembalian_dana.jenis_pembayaran_id")
                ->selectRaw("DATE(keuangan_pengembalian_dana.tanggal) as tgl, COALESCE(kjp.nama, 'Cash') as jenis_pembayaran_nama, keuangan_pengembalian_dana.jenis_pembayaran_id, SUM(keuangan_pengembalian_dana.nominal) as total_nominal")
                ->groupBy(DB::raw("DATE(keuangan_pengembalian_dana.tanggal)"), "kjp.nama", "keuangan_pengembalian_dana.jenis_pembayaran_id")
                ->get();

            $assignedKey = "pengembalian_dana";
            $mataUang = MataUangFormatter::defaultCurrency();
            foreach ($pdRecords as $pd) {
                $tgl = $pd->tgl;
                $nominalPositif = (float) $pd->total_nominal;
                if ($nominalPositif <= 0) {
                    continue;
                }

                if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                    $tgl,
                    $pd->jenis_pembayaran_nama,
                    $pd->jenis_pembayaran_id,
                    (int) $year,
                    (int) $month
                )) {
                    continue;
                }

                // Sifatnya negatif untuk mengurangi pemasukan
                $jumlahNegatif = -$nominalPositif;
                $accumulatePayment($tgl, $assignedKey, $jumlahNegatif, $mataUang);
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

        if (isset($dataMap[$firstDayStr]["carry_over_breakdown"])) {
            foreach ($dataMap[$firstDayStr]["carry_over_breakdown"] as &$bRow) {
                foreach ($columns as $c) {
                    $cKey = $c["key"] . "_by_currency";
                    if (isset($bRow[$cKey])) {
                        $bRow[$cKey] = MataUangFormatter::normalizeTotals($bRow[$cKey]);
                    }
                }
                $bRow["total_by_currency"] = MataUangFormatter::normalizeTotals($bRow["total_by_currency"]);
            }
            unset($bRow);
            $dataMap[$firstDayStr]["carry_over_breakdown"] = array_values($dataMap[$firstDayStr]["carry_over_breakdown"]);
            usort($dataMap[$firstDayStr]["carry_over_breakdown"], fn($a, $b) => strcmp($a['tanggal'], $b['tanggal']));
        }
        if (isset($dataMap[$firstDayStr]["original_breakdown"])) {
            foreach ($columns as $c) {
                $cKey = $c["key"] . "_by_currency";
                if (isset($dataMap[$firstDayStr]["original_breakdown"][$cKey])) {
                    $dataMap[$firstDayStr]["original_breakdown"][$cKey] = MataUangFormatter::normalizeTotals($dataMap[$firstDayStr]["original_breakdown"][$cKey]);
                }
            }
            $dataMap[$firstDayStr]["original_breakdown"]["total_by_currency"] = MataUangFormatter::normalizeTotals($dataMap[$firstDayStr]["original_breakdown"]["total_by_currency"]);
        }
        if (isset($dataMap[$firstDayStr]["carry_over_total_by_currency"])) {
            $dataMap[$firstDayStr]["carry_over_total_by_currency"] = MataUangFormatter::normalizeTotals($dataMap[$firstDayStr]["carry_over_total_by_currency"]);
        }
        if (isset($dataMap[$firstDayStr]["original_total_by_currency"])) {
            $dataMap[$firstDayStr]["original_total_by_currency"] = MataUangFormatter::normalizeTotals($dataMap[$firstDayStr]["original_total_by_currency"]);
        }

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
            "cut_off_info" => $periodMeta,
        ];
    }

    public function pemasukanTunaiHarian(Request $request)
    {
        try {
            $user = auth()->user();
            $roleName = strtolower($user->role->name ?? '');
            $isAdmin = ($user && ($user->role_id == 1 || $roleName === 'admin'));
            $jp = Helper::getJenisKelaminUser();

            if (
                $isAdmin &&
                $request->has("jenis_kelamin") &&
                $request->jenis_kelamin != ""
            ) {
                $reqJk = strtolower($request->jenis_kelamin);
                if (in_array($reqJk, ["putra", "8", "laki-laki"])) {
                    $jp = (object) ["id" => 8, "kategori" => "Putra"];
                } elseif (in_array($reqJk, ["putri", "9", "perempuan"])) {
                    $jp = (object) ["id" => 9, "kategori" => "Putri"];
                } elseif (in_array($reqJk, ["%", "semua"])) {
                    $jp = (object) ["id" => "%", "kategori" => "%"];
                }
            }

            $action = $request->input("action", "json");
            $mode = $request->input("mode", "bulanan"); // 'bulanan' atau 'tahunan'
            $jenjang = $request->input("jenjang", "sarjana");
            $jenisPembayaranId = $request->input("jenis_pembayaran_id");
            $userId = $request->input("user_id");
            $username = $request->input("username");
            $userObj = null;
            if ($userId) {
                $userObj = \App\Models\User::find($userId);
                if (!$username && $userObj) {
                    $username = $userObj->username;
                }
            } elseif ($username) {
                $userObj = \App\Models\User::where("username", $username)->first();
                if ($userObj) {
                    $userId = $userObj->id;
                }
            }

            $paymentFilter = $this->resolveJenisPembayaranFilter($jenisPembayaranId);
            $jenisPembayaranNama = $paymentFilter["nama"];
            $jenisPembayaranKategori = $paymentFilter["kategori"];
            $targetJpIds = $paymentFilter["ids"];
            if ($jenisPembayaranKategori) {
                if ($jp && $jp->id == "%") {
                    $jp = clone $jp;
                    $jp->id = $jenisPembayaranKategori === "Putra" ? 8 : 9;
                    $jp->kategori = $jenisPembayaranKategori;
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

                $periodMeta = \App\Services\LaporanCutOffService::getMonthPeriodMeta($year, $month);
                $startDate = $periodMeta['query_start_date'];
                $endDate = $periodMeta['query_end_date'];
            }

            try {
                $includePmb = $paymentFilter["include_pmb"] ?? true;
                if ($includePmb) {
                    $pmbGender = $jenisPembayaranKategori;
                    if (!$pmbGender && $jp && $jp->id != '%') {
                        $pmbGender = $jp->kategori;
                    }

                    $pmbParams = [
                        "start_date" => $startDate,
                        "end_date" => $endDate,
                        "jenjang" => $jenjang,
                        "jenis_kelamin" => $pmbGender,
                    ];
                    if ($jenisPembayaranNama && !in_array($jenisPembayaranNama, ["cash_transfer", "va_pondok"])) {
                        $pmbParams["jenis_pembayaran"] = $jenisPembayaranNama;
                    }
                    if ($username) {
                        $pmbParams["username"] = $username;
                    }

                    $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders(
                        [
                            "apikey" => $pmbApiKey,
                        ],
                    )->timeout(10)->get($pmbUrl, $pmbParams);

                    if ($pmbResponse->successful()) {
                        $pmbResData = $pmbResponse->json();
                        if (
                            isset($pmbResData["data"]) &&
                            is_array($pmbResData["data"])
                        ) {
                            $pmbDataAll = $this->filterPmbDataByPaymentMethod(
                                $pmbResData["data"],
                                $paymentFilter,
                            );
                        }
                    }
                }
            } catch (\Throwable $th) {
                // Ignore API error, proceed with empty PMB data
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
                    ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%");

                if ($jp && $jp->id != "%") {
                    $allPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
                }

                if ($jenjang === "sarjana") {
                    $allPaymentsQuery
                        ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                        ->where("p.jenjang", "S1");
                } elseif ($jenjang === "pascasarjana") {
                    $allPaymentsQuery
                        ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                        ->whereIn("p.jenjang", ["S2", "S3"]);
                }

                if (!empty($targetJpIds)) {
                    $allPaymentsQuery
                        ->join(
                            "keuangan_jenis_pembayaran_detail as kjpd",
                            "kjpd.pembayaran_id",
                            "=",
                            "keuangan_pembayaran.id",
                        )
                        ->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
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
                    ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%");

                if ($jp && $jp->id != "%") {
                    $spPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
                }

                if (!empty($targetJpIds)) {
                    $spPaymentsQuery
                        ->join(
                            "keuangan_jenis_pembayaran_detail as kjpd",
                            "kjpd.pembayaran_id",
                            "=",
                            "keuangan_pembayaran.id",
                        )
                        ->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
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

                $rincianTahunan = $this->calculateRincianPemasukanTunaiHarian(
                    $startDateYear,
                    $endDateYear,
                    $jp,
                    $jenjang,
                    $paymentFilter,
                    $userId,
                    $pmbDataAll
                );

                return response()->json([
                    "status" => true,
                    "message" => "Data tahunan berhasil diambil",
                    "columns" => $columnHeaders,
                    "all_data" => $allData,
                    "rincian_jenis_pembayaran" => $rincianTahunan,
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

                $rincianJenisPembayaran = $this->calculateRincianPemasukanTunaiHarian(
                    $startDate,
                    $endDate,
                    $jp,
                    $jenjang,
                    $paymentFilter,
                    $userId,
                    $pmbDataAll,
                    $year,
                    $month
                );

                return response()->json([
                    "status" => true,
                    "message" => "Data bulanan berhasil diambil",
                    "title" => $monthData["title"],
                    "columns" => $columnHeaders,
                    "data" => $monthData["data"],
                    "totals" => $monthData["totals"],
                    "rincian_jenis_pembayaran" => $rincianJenisPembayaran,
                    "jenis_kelamin" => $jp->kategori ?? "Semua",
                    "cut_off_info" => $monthData["cut_off_info"] ?? null,
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    private function resolveSingleJenisPembayaranItem($rawItem)
    {
        if (!$rawItem || $rawItem === 'all') {
            return [
                "ids" => null,
                "nama" => null,
                "names" => [],
                "kategori" => null,
                "label" => "Semua",
                "is_composite" => false,
                "include_pmb" => true,
                "is_all" => true,
            ];
        }

        $strVal = strtolower(trim((string) $rawItem));

        if (in_array($strVal, ["cash_transfer_putra", "cash_transfer_putri", "cash_transfer", "cash_transfer_all"])) {
            $kategori = null;
            $label = "Cash + Transfer";

            if ($strVal === "cash_transfer_putra") {
                $kategori = "Putra";
                $label = "Cash + Transfer (Putra)";
            } elseif ($strVal === "cash_transfer_putri") {
                $kategori = "Putri";
                $label = "Cash + Transfer (Putri)";
            } else {
                $label = "Cash + Transfer";
            }

            $query = \App\Models\KeuanganJenisPembayaran::query()
                ->where(function ($q) {
                    $q->where("nama", "like", "%cash%")
                        ->orWhere("nama", "like", "%tunai%")
                        ->orWhere("nama", "like", "%transfer%");
                });

            if ($kategori) {
                $query->where("kategori", $kategori);
            }

            $ids = $query->pluck("id")->map(fn($id) => (int) $id)->values()->toArray();

            if (empty($ids)) {
                if ($kategori === "Putra") {
                    $ids = [8, 9];
                } elseif ($kategori === "Putri") {
                    $ids = [13, 14];
                } else {
                    $ids = [8, 9, 13, 14];
                }
            }

            return [
                "ids" => $ids,
                "nama" => "cash_transfer",
                "names" => ["cash", "transfer"],
                "kategori" => $kategori,
                "label" => $label,
                "is_composite" => true,
                "include_pmb" => true,
            ];
        }

        if (in_array($strVal, ["va_pondok_putra", "va_pondok_putri", "va_pondok", "via_pondok_putra", "via_pondok_putri", "via_pondok", "va_pondok_all"])) {
            $kategori = null;
            $label = "VA Pondok";

            if (strpos($strVal, "putra") !== false) {
                $kategori = "Putra";
                $label = "VA Pondok (Putra)";
            } elseif (strpos($strVal, "putri") !== false) {
                $kategori = "Putri";
                $label = "VA Pondok (Putri)";
            }

            $query = \App\Models\KeuanganJenisPembayaran::query()
                ->where(function ($q) {
                    $q->where("nama", "like", "%pondok%");
                });

            if ($kategori) {
                $query->where("kategori", $kategori);
            }

            $ids = $query->pluck("id")->map(fn($id) => (int) $id)->values()->toArray();

            if (empty($ids)) {
                $ids = [-1];
            }

            return [
                "ids" => $ids,
                "nama" => "va_pondok",
                "names" => ["va_pondok"],
                "kategori" => $kategori,
                "label" => $label,
                "is_composite" => true,
                "include_pmb" => true,
            ];
        }

        if (in_array($strVal, ["va_transfer_putra", "va_transfer_putri", "va_transfer", "va_transfer_all"])) {
            $kategori = null;
            $label = "VA + Transfer";

            if ($strVal === "va_transfer_putra") {
                $kategori = "Putra";
                $label = "VA + Transfer (Putra)";
            } elseif ($strVal === "va_transfer_putri") {
                $kategori = "Putri";
                $label = "VA + Transfer (Putri)";
            } else {
                $label = "VA + Transfer";
            }

            $query = \App\Models\KeuanganJenisPembayaran::query()
                ->where(function ($q) {
                    $q->where("nama", "like", "%transfer%")
                        ->orWhere("nama", "like", "%va%")
                        ->orWhere("nama", "like", "%virtual%");
                });

            if ($kategori) {
                $query->where("kategori", $kategori);
            }

            $ids = $query->pluck("id")->map(fn($id) => (int) $id)->values()->toArray();

            if (empty($ids)) {
                if ($kategori === "Putra") {
                    $ids = [8, 16];
                } elseif ($kategori === "Putri") {
                    $ids = [13, 17];
                } else {
                    $ids = [8, 16, 13, 17];
                }
            }

            return [
                "ids" => $ids,
                "nama" => "va_transfer",
                "names" => ["va_pondok", "transfer"],
                "kategori" => $kategori,
                "label" => $label,
                "is_composite" => true,
                "include_pmb" => true,
            ];
        }

        if (in_array($strVal, ["va_bsi_putra", "va_bsi_putri", "va_bsi", "va-bsi", "va"])) {
            $kategori = null;
            $label = "VA BSI";
            if (strpos($strVal, "putra") !== false) {
                $kategori = "Putra";
                $label = "VA BSI (Putra)";
            } elseif (strpos($strVal, "putri") !== false) {
                $kategori = "Putri";
                $label = "VA BSI (Putri)";
            }

            $query = \App\Models\KeuanganJenisPembayaran::query()
                ->where(function ($q) {
                    $q->where("nama", "like", "%va%")
                        ->orWhere("nama", "like", "%virtual%");
                });

            if ($kategori) {
                $query->where("kategori", $kategori);
            }

            $ids = $query->pluck("id")->map(fn($id) => (int) $id)->values()->toArray();

            if (empty($ids)) {
                $ids = $kategori === "Putra" ? [16] : ($kategori === "Putri" ? [17] : [16, 17]);
            }

            return [
                "ids" => $ids,
                "nama" => "va_bsi",
                "names" => ["va_bsi"],
                "kategori" => $kategori,
                "label" => $label,
                "is_composite" => true,
                "include_pmb" => false,
            ];
        }

        $jpModel = \App\Models\KeuanganJenisPembayaran::find($rawItem);
        $jenisPembayaranNama = null;
        $jenisPembayaranKategori = null;
        $jenisPembayaranLabel = "ID: " . $rawItem;
        $ids = [(int) $rawItem];
        $includePmb = false;

        if ($jpModel) {
            $nama = strtolower(trim($jpModel->nama));
            $jenisPembayaranKategori = $jpModel->kategori;
            $jenisPembayaranLabel = $jpModel->nama . ($jpModel->kategori ? " ({$jpModel->kategori})" : "");

            if (strpos($nama, "deposit") !== false) {
                $jenisPembayaranNama = "deposit";
                $includePmb = false;
            } elseif (strpos($nama, "pondok") !== false) {
                $jenisPembayaranNama = "va_pondok";
                $includePmb = true;
            } elseif (strpos($nama, "transfer") !== false) {
                $jenisPembayaranNama = "transfer";
                $includePmb = true;
            } elseif (strpos($nama, "cash") !== false || strpos($nama, "tunai") !== false) {
                $jenisPembayaranNama = "cash";
                $includePmb = true;
            } elseif (strpos($nama, "yayasan") !== false) {
                $jenisPembayaranNama = "yayasan";
                $includePmb = true;
            } elseif (strpos($nama, "va") !== false || strpos($nama, "virtual") !== false || strpos($nama, "bsi") !== false) {
                $jenisPembayaranNama = "va_bsi";
                $includePmb = false;
            } else {
                $jenisPembayaranNama = $nama;
                $includePmb = false;
            }
        }

        return [
            "ids" => $ids,
            "nama" => $jenisPembayaranNama,
            "names" => $jenisPembayaranNama ? [$jenisPembayaranNama] : [],
            "kategori" => $jenisPembayaranKategori,
            "label" => $jenisPembayaranLabel,
            "is_composite" => false,
            "include_pmb" => $includePmb,
        ];
    }

    private function resolveJenisPembayaranFilter($jenisPembayaranId)
    {
        if (!$jenisPembayaranId || $jenisPembayaranId === 'all') {
            return [
                "ids" => null,
                "nama" => null,
                "names" => [],
                "kategori" => null,
                "label" => "Semua",
                "is_composite" => false,
                "include_pmb" => true,
                "is_all" => true,
            ];
        }

        $items = [];
        if (is_array($jenisPembayaranId)) {
            $items = $jenisPembayaranId;
        } elseif (is_string($jenisPembayaranId) && strpos($jenisPembayaranId, ',') !== false) {
            $items = explode(',', $jenisPembayaranId);
        } else {
            $items = [$jenisPembayaranId];
        }

        $items = array_values(array_filter(array_map(fn($i) => is_string($i) ? trim($i) : $i, $items), fn($i) => $i !== "" && $i !== null));

        if (empty($items) || in_array('all', $items, true)) {
            return [
                "ids" => null,
                "nama" => null,
                "names" => [],
                "kategori" => null,
                "label" => "Semua",
                "is_composite" => false,
                "include_pmb" => true,
                "is_all" => true,
            ];
        }

        if (count($items) === 1) {
            $single = $this->resolveSingleJenisPembayaranItem($items[0]);
            $single["is_all"] = false;
            return $single;
        }

        $mergedIds = [];
        $mergedNames = [];
        $mergedLabels = [];
        $kategoriSet = [];
        $includePmb = false;

        foreach ($items as $item) {
            $resolved = $this->resolveSingleJenisPembayaranItem($item);
            if (!empty($resolved['ids'])) {
                foreach ($resolved['ids'] as $id) {
                    $mergedIds[] = (int) $id;
                }
            }
            if (!empty($resolved['names'])) {
                foreach ($resolved['names'] as $n) {
                    $mergedNames[] = $n;
                }
            } elseif (!empty($resolved['nama'])) {
                $mergedNames[] = $resolved['nama'];
            }
            if (!empty($resolved['label'])) {
                $mergedLabels[] = $resolved['label'];
            }
            if (!empty($resolved['kategori'])) {
                $kategoriSet[] = $resolved['kategori'];
            }
            if (!empty($resolved['include_pmb'])) {
                $includePmb = true;
            }
        }

        $uniqueIds = array_values(array_unique($mergedIds));
        $uniqueNames = array_values(array_unique($mergedNames));
        $uniqueLabels = array_values(array_unique($mergedLabels));
        $uniqueKategori = array_values(array_unique($kategoriSet));

        return [
            "ids" => !empty($uniqueIds) ? $uniqueIds : null,
            "nama" => implode(',', $uniqueNames),
            "names" => $uniqueNames,
            "kategori" => count($uniqueKategori) === 1 ? $uniqueKategori[0] : null,
            "label" => implode(', ', $uniqueLabels),
            "is_composite" => true,
            "include_pmb" => $includePmb,
            "is_all" => false,
        ];
    }

    private function filterPmbDataByPaymentMethod($pmbData, $paymentFilter)
    {
        $includePmb = $paymentFilter["include_pmb"] ?? true;
        if (!$includePmb) {
            return [];
        }

        $names = (array) ($paymentFilter["names"] ?? []);
        if (empty($names)) {
            $nama = strtolower(trim((string) ($paymentFilter["nama"] ?? "")));
            if ($nama && $nama !== "all") {
                $names = explode(',', $nama);
            }
        }

        if (empty($names) || in_array("all", $names)) {
            return $pmbData;
        }

        return collect($pmbData)
            ->filter(function ($item) use ($names) {
                $metode = strtolower(trim((string) data_get(
                    $item,
                    "jenis_pembayaran",
                    data_get($item, "metode_pembayaran", "")
                )));

                foreach ($names as $nama) {
                    $nama = trim($nama);
                    if ($nama === "va_pondok") {
                        if (str_contains($metode, "pondok") &&
                            (str_contains($metode, "va") || str_contains($metode, "via"))) {
                            return true;
                        }
                    } elseif ($nama === "cash_transfer") {
                        $isCash = str_contains($metode, "cash") || str_contains($metode, "tunai");
                        $isTransfer = str_contains($metode, "transfer") || str_contains($metode, "tf");
                        $isPondok = str_contains($metode, "pondok");

                        if (($isCash || $isTransfer) && !$isPondok) {
                            return true;
                        }
                    } elseif ($nama === "cash") {
                        if ((str_contains($metode, "cash") || str_contains($metode, "tunai")) &&
                            !str_contains($metode, "pondok")) {
                            return true;
                        }
                    } elseif ($nama === "transfer") {
                        if ((str_contains($metode, "transfer") || str_contains($metode, "tf")) &&
                            !str_contains($metode, "pondok")) {
                            return true;
                        }
                    } elseif ($nama === "yayasan") {
                        if (str_contains($metode, "yayasan")) {
                            return true;
                        }
                    } elseif ($nama === "va_bsi" || $nama === "va") {
                        if (str_contains($metode, "bsi")) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function classifyPaymentType($namaRaw)
    {
        $nama = strtolower(trim((string) $namaRaw));
        if (str_contains($nama, "pondok") && (str_contains($nama, "va") || str_contains($nama, "via"))) {
            return "va_pondok";
        }
        if (str_contains($nama, "pondok")) {
            return "va_pondok";
        }
        if (str_contains($nama, "cash") || str_contains($nama, "tunai")) {
            return "cash";
        }
        if (str_contains($nama, "transfer") || str_contains($nama, "tf")) {
            return "transfer";
        }
        if (str_contains($nama, "yayasan") || str_contains($nama, "yys")) {
            return "yayasan";
        }
        if (str_contains($nama, "deposit")) {
            return "deposit";
        }
        if (str_contains($nama, "va") && str_contains($nama, "bsi")) {
            return "va_bsi";
        }
        return "other";
    }

    private function formatRincianJenisPembayaran(array $typeTotals, $paymentFilter)
    {
        $getItem = function ($nama, $key) use ($typeTotals) {
            $data = $typeTotals[$key] ?? ["total" => 0, "by_currency" => []];
            return [
                "nama" => $nama,
                "key" => $key,
                "total" => (float) ($data["total"] ?? 0),
                "total_by_currency" => $data["by_currency"] ?? [],
            ];
        };

        $standardKeys = [
            "cash" => "Cash",
            "transfer" => "Transfer",
            "va_pondok" => "VA Pondok",
            "yayasan" => "Yayasan",
            "deposit" => "Deposit",
            "va_bsi" => "VA BSI",
        ];

        $rincian = [];
        $selectedKeys = array_filter((array) ($paymentFilter["names"] ?? []));

        if (!empty($selectedKeys) && !in_array("all", $selectedKeys)) {
            $totalSum = 0;
            $currencyGroups = [];
            foreach ($selectedKeys as $key) {
                if (isset($typeTotals[$key])) {
                    $label = $standardKeys[$key] ?? ucfirst(str_replace("_", " ", $key));
                    $rincian[] = $getItem($label, $key);
                    $totalSum += (float) ($typeTotals[$key]["total"] ?? 0);
                    if (!empty($typeTotals[$key]["by_currency"])) {
                        $currencyGroups[] = $typeTotals[$key]["by_currency"];
                    }
                }
            }

            if (count($rincian) > 1 || count($selectedKeys) > 1) {
                $rincian[] = [
                    "nama" => "Total (" . ($paymentFilter["label"] ?? "Pilihan") . ")",
                    "key" => "total",
                    "is_total" => true,
                    "total" => $totalSum,
                    "total_by_currency" => MataUangFormatter::mergeTotals(...$currencyGroups),
                ];
            }

            return $rincian;
        }

        $totalSum = 0;
        $currencyGroups = [];

        foreach ($standardKeys as $key => $label) {
            if (isset($typeTotals[$key]) && (float) ($typeTotals[$key]["total"] ?? 0) != 0) {
                $rincian[] = $getItem($label, $key);
                $totalSum += (float) $typeTotals[$key]["total"];
                if (!empty($typeTotals[$key]["by_currency"])) {
                    $currencyGroups[] = $typeTotals[$key]["by_currency"];
                }
            }
        }

        foreach ($typeTotals as $key => $data) {
            if (!isset($standardKeys[$key]) && (float) ($data["total"] ?? 0) != 0) {
                $rincian[] = $getItem(ucfirst(str_replace("_", " ", $key)), $key);
                $totalSum += (float) $data["total"];
                if (!empty($data["by_currency"])) {
                    $currencyGroups[] = $data["by_currency"];
                }
            }
        }

        $rincian[] = [
            "nama" => "Total Semua",
            "key" => "total",
            "is_total" => true,
            "total" => $totalSum,
            "total_by_currency" => MataUangFormatter::mergeTotals(...$currencyGroups),
        ];

        return $rincian;
    }

    private function calculateRincianLaporanHarian($rows, $paymentMeta, $jp)
    {
        $typeTotals = [];

        foreach ($rows as $row) {
            $metode = $row["metode"] ?? "";
            $nominal = (float) ($row["nominal"] ?? 0);
            $mataUang = $row["mata_uang"] ?? MataUangFormatter::defaultCurrency();

            $typeKey = $this->classifyPaymentType($metode);

            if (!isset($typeTotals[$typeKey])) {
                $typeTotals[$typeKey] = [
                    "total" => 0,
                    "by_currency" => [],
                ];
            }

            $typeTotals[$typeKey]["total"] += $nominal;
            MataUangFormatter::addToTotals(
                $typeTotals[$typeKey]["by_currency"],
                $nominal,
                $mataUang
            );
        }

        foreach ($typeTotals as &$data) {
            $data["by_currency"] = MataUangFormatter::normalizeTotals($data["by_currency"]);
        }
        unset($data);

        return $this->formatRincianJenisPembayaran($typeTotals, $paymentMeta);
    }

    private function calculateRincianPemasukanTunaiHarian(
        $startDate,
        $endDate,
        $jp,
        $jenjang,
        $paymentFilter,
        $userId,
        $pmbDataAll = [],
        $targetYear = null,
        $targetMonth = null
    ) {
        $typeTotals = [];
        $targetJpIds = $paymentFilter["ids"] ?? null;

        $addPayment = function ($namaRaw, $jumlah, $mataUang) use (&$typeTotals) {
            $typeKey = $this->classifyPaymentType($namaRaw);
            if (!isset($typeTotals[$typeKey])) {
                $typeTotals[$typeKey] = [
                    "total" => 0,
                    "by_currency" => [],
                ];
            }
            $typeTotals[$typeKey]["total"] += (float) $jumlah;
            MataUangFormatter::addToTotals(
                $typeTotals[$typeKey]["by_currency"],
                (float) $jumlah,
                $mataUang
            );
        };

        // 1. KeuanganPembayaran (Tagihan Mahasiswa)
        $paymentsQuery = KeuanganPembayaran::join(
            "keuangan_tagihan as kt",
            "kt.id",
            "=",
            "keuangan_pembayaran.tagihan_id"
        )
            ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
            ->leftJoin("keuangan_jenis_pembayaran_detail as kjpd", "kjpd.pembayaran_id", "=", "keuangan_pembayaran.id")
            ->leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "kjpd.jenis_pembayaran_id")
            ->whereBetween("keuangan_pembayaran.tanggal", [
                $startDate . " 00:00:00",
                $endDate . " 23:59:59",
            ])
            ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%");

        if ($jp && $jp->id != "%") {
            $paymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
        }

        if ($jenjang === "sarjana") {
            $paymentsQuery
                ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                ->where("p.jenjang", "S1");
        } elseif ($jenjang === "pascasarjana") {
            $paymentsQuery
                ->join("prodi as p", "p.id", "=", "kt.prodi_id")
                ->whereIn("p.jenjang", ["S2", "S3"]);
        }

        if (!empty($targetJpIds)) {
            $paymentsQuery->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
        }

        if ($userId) {
            $paymentsQuery->where("keuangan_pembayaran.user_id", $userId);
        }

        $mhsPayments = $paymentsQuery
            ->selectRaw("DATE(keuangan_pembayaran.tanggal) as tgl, COALESCE(kjp.nama, 'Lainnya') as nama, COALESCE(kjp.id, 0) as jenis_pembayaran_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah")
            ->groupBy(DB::raw("DATE(keuangan_pembayaran.tanggal)"), "kjp.nama", "kjp.id", "mu.id", "mu.kode", "mu.nama", "mu.simbol")
            ->get();

        foreach ($mhsPayments as $p) {
            if ($targetYear && $targetMonth) {
                if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth($p->tgl, $p->nama, $p->jenis_pembayaran_id, (int) $targetYear, (int) $targetMonth)) {
                    continue;
                }
            }
            $mataUang = MataUangFormatter::fromColumns($p);
            $addPayment($p->nama, $p->total_jumlah, $mataUang);
        }

        // 2. Semester Pendek
        $spPaymentsQuery = KeuanganPembayaran::join(
            "keuangan_tagihan as kt",
            "kt.id",
            "=",
            "keuangan_pembayaran.tagihan_id"
        )
            ->leftJoin("mata_uang as mu", "mu.id", "=", "kt.mata_uang_id")
            ->leftJoin("keuangan_jenis_pembayaran_detail as kjpd", "kjpd.pembayaran_id", "=", "keuangan_pembayaran.id")
            ->leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "kjpd.jenis_pembayaran_id")
            ->whereBetween("keuangan_pembayaran.tanggal", [
                $startDate . " 00:00:00",
                $endDate . " 23:59:59",
            ])
            ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%");

        if ($jp && $jp->id != "%") {
            $spPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
        }

        if (!empty($targetJpIds)) {
            $spPaymentsQuery->whereIn("kjpd.jenis_pembayaran_id", $targetJpIds);
        }

        if ($userId) {
            $spPaymentsQuery->where("keuangan_pembayaran.user_id", $userId);
        }

        $spPayments = $spPaymentsQuery
            ->selectRaw("DATE(keuangan_pembayaran.tanggal) as tgl, COALESCE(kjp.nama, 'Lainnya') as nama, COALESCE(kjp.id, 0) as jenis_pembayaran_id, kt.nama as tagihan_nama, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah")
            ->groupBy(DB::raw("DATE(keuangan_pembayaran.tanggal)"), "kjp.nama", "kjp.id", "kt.nama", "mu.id", "mu.kode", "mu.nama", "mu.simbol")
            ->get();

        $spPayments = $this->mapSemesterPendekPaymentsFromTagihan($spPayments);
        $spPayments = $this->filterSemesterPendekPaymentsByJenjang($spPayments, $jenjang);

        foreach ($spPayments as $p) {
            if ($targetYear && $targetMonth) {
                if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth($p->tgl, $p->nama, $p->jenis_pembayaran_id, (int) $targetYear, (int) $targetMonth)) {
                    continue;
                }
            }
            $mataUang = MataUangFormatter::fromColumns($p);
            $addPayment($p->nama, $p->total_jumlah, $mataUang);
        }

        // 3. Pemasukan Umum (Sarjana only)
        if ($jenjang === "sarjana") {
            $puQuery = KeuanganPemasukanUmum::leftJoin(
                "keuangan_jenis_pembayaran as kjp",
                "kjp.id",
                "=",
                "keuangan_pemasukan_umum.jenis_pembayaran_id"
            )
                ->whereBetween("keuangan_pemasukan_umum.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ]);

            if (!empty($targetJpIds)) {
                $puQuery->whereIn("keuangan_pemasukan_umum.jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $puQuery->where("keuangan_pemasukan_umum.petugas_id", $userId);
            } elseif ($jp && $jp->id != '%') {
                $jkTarget = $jp->id == 8 ? 'Laki-laki' : ($jp->id == 9 ? 'Perempuan' : null);
                if ($jkTarget) {
                    $puQuery->whereHas('petugas', function ($q) use ($jkTarget) {
                        $q->whereIn('jenis_kelamin', [$jkTarget, '*']);
                    });
                }
            }

            $puRecords = $puQuery
                ->selectRaw("DATE(keuangan_pemasukan_umum.tanggal) as tgl, COALESCE(kjp.nama, 'Tunai') as nama, keuangan_pemasukan_umum.jenis_pembayaran_id, SUM(keuangan_pemasukan_umum.nominal) as total_nominal")
                ->groupBy(DB::raw("DATE(keuangan_pemasukan_umum.tanggal)"), "kjp.nama", "keuangan_pemasukan_umum.jenis_pembayaran_id")
                ->get();

            $mataUang = MataUangFormatter::defaultCurrency();
            foreach ($puRecords as $pu) {
                if ($targetYear && $targetMonth) {
                    if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth($pu->tgl, $pu->nama, $pu->jenis_pembayaran_id, (int) $targetYear, (int) $targetMonth)) {
                        continue;
                    }
                }
                $addPayment($pu->nama, $pu->total_nominal, $mataUang);
            }

            // 4. Pengembalian Dana (Pengurangan, Sarjana only)
            $pdQuery = KeuanganPengembalianDana::leftJoin(
                "keuangan_jenis_pembayaran as kjp",
                "kjp.id",
                "=",
                "keuangan_pengembalian_dana.jenis_pembayaran_id"
            )
                ->whereBetween("keuangan_pengembalian_dana.tanggal", [
                    $startDate . " 00:00:00",
                    $endDate . " 23:59:59",
                ]);

            if (!empty($targetJpIds)) {
                $pdQuery->whereIn("keuangan_pengembalian_dana.jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $pdQuery->where("keuangan_pengembalian_dana.petugas_id", $userId);
            } elseif ($jp && $jp->id != '%') {
                $jkTarget = $jp->id == 8 ? 'Laki-laki' : ($jp->id == 9 ? 'Perempuan' : null);
                if ($jkTarget) {
                    $pdQuery->whereHas('petugas', function ($q) use ($jkTarget) {
                        $q->whereIn('jenis_kelamin', [$jkTarget, '*']);
                    });
                }
            }

            $pdRecords = $pdQuery
                ->selectRaw("DATE(keuangan_pengembalian_dana.tanggal) as tgl, COALESCE(kjp.nama, 'Tunai') as nama, keuangan_pengembalian_dana.jenis_pembayaran_id, SUM(keuangan_pengembalian_dana.nominal) as total_nominal")
                ->groupBy(DB::raw("DATE(keuangan_pengembalian_dana.tanggal)"), "kjp.nama", "keuangan_pengembalian_dana.jenis_pembayaran_id")
                ->get();

            foreach ($pdRecords as $pd) {
                if ($targetYear && $targetMonth) {
                    if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth($pd->tgl, $pd->nama, $pd->jenis_pembayaran_id, (int) $targetYear, (int) $targetMonth)) {
                        continue;
                    }
                }
                $addPayment($pd->nama, -1 * (float) $pd->total_nominal, $mataUang);
            }
        }

        // 5. PMB
        if (!empty($pmbDataAll)) {
            $mataUang = MataUangFormatter::defaultCurrency();
            foreach ($pmbDataAll as $item) {
                $tgl = isset($item["tanggal_bayar"]) ? date("Y-m-d", strtotime($item["tanggal_bayar"])) : null;
                $metode = (string) data_get($item, "jenis_pembayaran", data_get($item, "metode_pembayaran", ""));
                $nominal = (float) data_get($item, "nominal", 0);

                if ($tgl && $targetYear && $targetMonth) {
                    if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth($tgl, $metode, null, (int) $targetYear, (int) $targetMonth)) {
                        continue;
                    }
                }

                $addPayment($metode, $nominal, $mataUang);
            }
        }

        foreach ($typeTotals as &$data) {
            $data["by_currency"] = MataUangFormatter::normalizeTotals($data["by_currency"]);
        }
        unset($data);

        return $this->formatRincianJenisPembayaran($typeTotals, $paymentFilter);
    }

    private function getLaporanHarianPaymentFilterMeta($jenisPembayaranId)
    {
        return $this->resolveJenisPembayaranFilter($jenisPembayaranId);
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

    private function normalizeLaporanHarianPmbRows($pmbData, $defaultPetugas = "PMB")
    {
        return collect($pmbData)
            ->map(function ($item) use ($defaultPetugas) {
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
                    "petugas" => data_get($item, "petugas", data_get($item, "username", $defaultPetugas)),
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
        $username = $request->input("username");
        $userObj = null;
        if ($userId) {
            $userObj = \App\Models\User::find($userId);
            if (!$username && $userObj) {
                $username = $userObj->username;
            }
        } elseif ($username) {
            $userObj = \App\Models\User::where("username", $username)->first();
            if ($userObj) {
                $userId = $userObj->id;
            }
        }
        $user = auth()->user();
        $roleName = strtolower($user->role->name ?? '');
        $isAdmin = ($user && ($user->role_id == 1 || $roleName === 'admin'));
        $jp = Helper::getJenisKelaminUser();

        if (
            $isAdmin &&
            $request->has("jenis_kelamin") &&
            $request->jenis_kelamin != ""
        ) {
            $reqJk = strtolower($request->jenis_kelamin);
            if (in_array($reqJk, ["putra", "8", "laki-laki"])) {
                $jp = (object) ["id" => 8, "kategori" => "Putra"];
            } elseif (in_array($reqJk, ["putri", "9", "perempuan"])) {
                $jp = (object) ["id" => 9, "kategori" => "Putri"];
            } elseif (in_array($reqJk, ["%", "semua"])) {
                $jp = (object) ["id" => "%", "kategori" => "%"];
            }
        }

        $paymentMeta = $this->getLaporanHarianPaymentFilterMeta(
            $jenisPembayaranId,
        );
        $targetJpIds = $paymentMeta["ids"];
        if ($paymentMeta["kategori"]) {
            if ($jp && $jp->id == "%") {
                $jp = clone $jp;
                $jp->id = $paymentMeta["kategori"] === "Putra" ? 8 : 9;
                $jp->kategori = $paymentMeta["kategori"];
            }
        }

        $tStamp = strtotime($tanggal);
        $tYear = (int) date('Y', $tStamp);
        $tMonth = (int) date('n', $tStamp);
        $tDay = (int) date('j', $tStamp);

        $periodMeta = \App\Services\LaporanCutOffService::getMonthPeriodMeta($tYear, $tMonth);
        $hasCarryOverIn = false;
        $carryOverInInfo = null;
        $datesToQuery = [$tanggal];

        if ($tDay === 1 && $periodMeta['has_carry_over'] && !empty($periodMeta['carry_over_dates'])) {
            $hasCarryOverIn = true;
            $datesToQuery = array_merge([$tanggal], $periodMeta['carry_over_dates']);
            $carryOverInInfo = [
                'prev_month_name' => $periodMeta['prev_month_name'],
                'dates' => $periodMeta['carry_over_dates'],
                'total' => 0,
            ];
        }

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
            ->whereIn(DB::raw("DATE(keuangan_pembayaran.tanggal)"), $datesToQuery)
            ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%");

        if ($jp && $jp->id != "%") {
            $paymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
        }

        if ($jenjang === "sarjana") {
            $paymentsQuery->where("p.jenjang", "S1");
        } elseif ($jenjang === "pascasarjana") {
            $paymentsQuery->whereIn("p.jenjang", ["S2", "S3"]);
        }

        if (!empty($targetJpIds)) {
            $paymentsQuery->whereIn(
                "kjpd.jenis_pembayaran_id",
                $targetJpIds,
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
            ->whereIn(DB::raw("DATE(keuangan_pembayaran.tanggal)"), $datesToQuery)
            ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%");

        if ($jp && $jp->id != "%") {
            $spPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
        }

        if (!empty($targetJpIds)) {
            $spPaymentsQuery->whereIn(
                "kjpd.jenis_pembayaran_id",
                $targetJpIds,
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

        try {
            $includePmb = $paymentMeta["include_pmb"] ?? true;
            if ($includePmb) {
                $pmbGender = $paymentMeta["kategori"];
                if (!$pmbGender && $jp && $jp->id != '%') {
                    $pmbGender = $jp->kategori;
                }

                $pmbParams = [
                    "start_date" => $hasCarryOverIn ? ($periodMeta['query_start_date'] ?? $tanggal) : $tanggal,
                    "end_date" => $tanggal,
                    "jenjang" => $jenjang,
                    "jenis_kelamin" => $pmbGender,
                ];
                if ($paymentMeta["nama"] && !in_array($paymentMeta["nama"], ["cash_transfer", "va_pondok"])) {
                    $pmbParams["jenis_pembayaran"] = $paymentMeta["nama"];
                }
                if ($username) {
                    $pmbParams["username"] = $username;
                }

                $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    "apikey" => env("PMB_API_KEY"),
                ])->timeout(10)->get(rtrim(env("PMB_URL"), "/") . "/simkeu/pembayaran", $pmbParams);

                if ($pmbResponse->successful()) {
                    $pmbResData = $pmbResponse->json();
                    $filteredPmb = $this->filterPmbDataByPaymentMethod(
                        $pmbResData["data"] ?? [],
                        $paymentMeta,
                    );
                    $defaultPetugas = $userObj ? $userObj->name : "PMB";
                    $rows = array_merge(
                        $rows,
                        $this->normalizeLaporanHarianPmbRows(
                            $filteredPmb,
                            $defaultPetugas,
                        ),
                    );
                }
            }
        } catch (\Throwable $th) {
            // PMB is optional; keep SIMKEU report available if it fails.
        }

        if ($jenjang === "sarjana") {
            $puQuery = KeuanganPemasukanUmum::with(["petugas", "jenisPembayaran"])
                ->whereIn(DB::raw("DATE(tanggal)"), $datesToQuery);

            if (!empty($targetJpIds)) {
                $puQuery->whereIn("jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $puQuery->where("petugas_id", $userId);
            } elseif ($jp && $jp->id != "%") {
                $jkTarget = $jp->id == 8 ? "Laki-laki" : ($jp->id == 9 ? "Perempuan" : null);
                if ($jkTarget) {
                    $puQuery->whereHas("petugas", function ($q) use ($jkTarget) {
                        $q->whereIn("jenis_kelamin", [$jkTarget, "*"]);
                    });
                }
            }

            $puRecords = $puQuery->orderBy("tanggal")->get();
            $puRows = $puRecords->map(function ($pu) {
                return [
                    "tanggal_input" => $pu->created_at ? date("Y-m-d", strtotime($pu->created_at)) : null,
                    "tanggal_transaksi" => $pu->tanggal ? date("Y-m-d", strtotime($pu->tanggal)) : null,
                    "kwitansi" => $pu->no_transaksi ?: "-",
                    "nim" => "-",
                    "nama" => "Pemasukan Umum",
                    "jenis_kelamin" => "-",
                    "prodi" => "-",
                    "pembayaran" => $pu->keterangan ? ("Pemasukan Umum - " . $pu->keterangan) : "Pemasukan Umum",
                    "nominal" => (float) $pu->nominal,
                    "mata_uang" => MataUangFormatter::defaultCurrency(),
                    "metode" => $pu->jenisPembayaran->nama ?? "Tunai",
                    "petugas" => $pu->petugas->name ?? "-",
                    "source" => "Pemasukan Umum",
                ];
            })->all();

            $rows = array_merge($rows, $puRows);
        }

        if ($jenjang === "sarjana") {
            $pdQuery = KeuanganPengembalianDana::with(["petugas", "jenisPembayaran"])
                ->whereIn(DB::raw("DATE(tanggal)"), $datesToQuery);

            if (!empty($targetJpIds)) {
                $pdQuery->whereIn("jenis_pembayaran_id", $targetJpIds);
            }

            if ($userId) {
                $pdQuery->where("petugas_id", $userId);
            } elseif ($jp && $jp->id != "%") {
                $jkTarget = $jp->id == 8 ? "Laki-laki" : ($jp->id == 9 ? "Perempuan" : null);
                if ($jkTarget) {
                    $pdQuery->whereHas("petugas", function ($q) use ($jkTarget) {
                        $q->whereIn("jenis_kelamin", [$jkTarget, "*"]);
                    });
                }
            }

            $pdRecords = $pdQuery->orderBy("tanggal")->get();
            $pdRows = $pdRecords->map(function ($pd) {
                return [
                    "tanggal_input" => $pd->created_at ? date("Y-m-d", strtotime($pd->created_at)) : null,
                    "tanggal_transaksi" => $pd->tanggal ? date("Y-m-d", strtotime($pd->tanggal)) : null,
                    "kwitansi" => $pd->no_transaksi ?: "-",
                    "nim" => "-",
                    "nama" => "Pengembalian Dana",
                    "jenis_kelamin" => "-",
                    "prodi" => "-",
                    "pembayaran" => $pd->keterangan ? ("Pengembalian Dana - " . $pd->keterangan) : "Pengembalian Dana",
                    "nominal" => - (float) $pd->nominal,
                    "mata_uang" => MataUangFormatter::defaultCurrency(),
                    "metode" => $pd->jenisPembayaran->nama ?? "Tunai",
                    "petugas" => $pd->petugas->name ?? "-",
                    "source" => "Pengembalian Dana",
                ];
            })->all();

            $rows = array_merge($rows, $pdRows);
        }

        usort($rows, function ($a, $b) {
            $orderA = ($a["source"] ?? "") === "Pengembalian Dana" ? 2 : (($a["source"] ?? "") === "Pemasukan Umum" ? 1 : 0);
            $orderB = ($b["source"] ?? "") === "Pengembalian Dana" ? 2 : (($b["source"] ?? "") === "Pemasukan Umum" ? 1 : 0);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp(
                ($a["tanggal_input"] ?? "") . ($a["kwitansi"] ?? ""),
                ($b["tanggal_input"] ?? "") . ($b["kwitansi"] ?? ""),
            );
        });

        $bulanNames = [
            1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
            5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
            9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
        ];

        $settingsThisMonth = \App\Services\LaporanCutOffService::getSettingsForMonth($tYear, $tMonth);
        $isPastCutOff = false;
        $shiftedMonth = null;
        if ($settingsThisMonth->isNotEmpty()) {
            $minCutOff = $settingsThisMonth->min('tanggal_akhir');
            if ($minCutOff && $tDay > $minCutOff) {
                $isPastCutOff = true;
                $nxtM = $tMonth + 1;
                $nxtY = $tYear;
                if ($nxtM > 12) { $nxtM = 1; $nxtY++; }
                $shiftedMonth = [
                    'bulan' => $nxtM,
                    'tahun' => $nxtY,
                    'label' => ($bulanNames[$nxtM] ?? "Bulan $nxtM") . " " . $nxtY,
                    'cut_off_day' => $minCutOff,
                ];
            }
        }

        $filteredRows = [];
        $no = 1;
        $totalCarryOverNominal = 0;

        foreach ($rows as $row) {
            $recDate = $row["tanggal_transaksi"] ?? $row["tanggal_input"] ?? $tanggal;
            $methodName = $row["metode"] ?? "";
            $isCarryOverIn = ($recDate !== $tanggal && in_array($recDate, $periodMeta['carry_over_dates'] ?? []));

            $inMonth = \App\Services\LaporanCutOffService::isPaymentInAccountingMonth($recDate, $methodName, null, $tYear, $tMonth);

            // Jika transaksi berasal dari tanggal limpahan bulan lalu, tetapi metodenya bukan yang di-cut off, skip
            if ($isCarryOverIn && !$inMonth) {
                continue;
            }

            $isPastCutOffRow = (!$isCarryOverIn && !$inMonth);

            $row["no"] = $no++;
            $row["is_carry_over"] = $isPastCutOffRow;
            $row["accounting_period"] = $isPastCutOffRow ? ($shiftedMonth["label"] ?? "Bulan Berikutnya") : null;
            $row["is_carry_over_in"] = $isCarryOverIn;
            $row["carry_over_origin_date"] = $isCarryOverIn ? $recDate : null;
            $row["carry_over_origin_label"] = $isCarryOverIn ? date('d/m/Y', strtotime($recDate)) : null;

            if ($isCarryOverIn) {
                $totalCarryOverNominal += (float) ($row["nominal"] ?? 0);
            }

            $filteredRows[] = $row;
        }
        $rows = $filteredRows;
        if ($carryOverInInfo) {
            $carryOverInInfo['total'] = $totalCarryOverNominal;
        }

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

        $rincianJenisPembayaran = $this->calculateRincianLaporanHarian($rows, $paymentMeta, $jp);

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
            "rincian_jenis_pembayaran" => $rincianJenisPembayaran,
            "is_past_cut_off" => $isPastCutOff,
            "shifted_month" => $shiftedMonth,
            "has_carry_over_in" => $hasCarryOverIn,
            "carry_over_in_info" => $carryOverInInfo,
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
                "rincian_jenis_pembayaran" => $data["rincian_jenis_pembayaran"],
                "title" => $data["title"],
                "tanggal" => $data["tanggal"],
                "kategori" => $data["kategori"],
                "jenis_kelamin" => $data["jenis_kelamin"],
                "is_past_cut_off" => $data["is_past_cut_off"] ?? false,
                "shifted_month" => $data["shifted_month"] ?? null,
                "has_carry_over_in" => $data["has_carry_over_in"] ?? false,
                "carry_over_in_info" => $data["carry_over_in_info"] ?? null,
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
            $user = auth()->user();
            $roleName = strtolower($user->role->name ?? '');
            $isAdmin = ($user && ($user->role_id == 1 || $roleName === 'admin'));
            $jp = Helper::getJenisKelaminUser();

            if (
                $isAdmin &&
                $request->has("jenis_kelamin") &&
                $request->jenis_kelamin != ""
            ) {
                $reqJk = strtolower($request->jenis_kelamin);
                if (in_array($reqJk, ["putra", "8", "laki-laki"])) {
                    $jp = (object) ["id" => 8, "kategori" => "Putra"];
                } elseif (in_array($reqJk, ["putri", "9", "perempuan"])) {
                    $jp = (object) ["id" => 9, "kategori" => "Putri"];
                } elseif (in_array($reqJk, ["%", "semua"])) {
                    $jp = (object) ["id" => "%", "kategori" => "%"];
                }
            }

            $action = $request->input("action", "json");
            $mode = $request->input("mode", "bulanan"); // 'bulanan' atau 'tahunan'
            $jenjang = $request->input("jenjang", "sarjana");
            $userId = $request->input("user_id");
            $username = $request->input("username");
            $userObj = null;
            if ($userId) {
                $userObj = \App\Models\User::find($userId);
                if (!$username && $userObj) {
                    $username = $userObj->username;
                }
            } elseif ($username) {
                $userObj = \App\Models\User::where("username", $username)->first();
                if ($userObj) {
                    $userId = $userObj->id;
                }
            }

            // Columns setup (Categories)
            $columns = $this->getPemasukanTunaiColumns($jenjang);

            $emptyPaymentRow = [
                "tunai" => 0,
                "transfer" => 0,
                "va" => 0,
                "va_transfer" => 0,
                "yayasan" => 0,
                "total" => 0,
                "tunai_by_currency" => [],
                "transfer_by_currency" => [],
                "va_by_currency" => [],
                "va_transfer_by_currency" => [],
                "yayasan_by_currency" => [],
                "total_by_currency" => [],
            ];

            // Initialization
            $data = [];
            foreach ($columns as $col) {
                $data[$col["key"]] = array_merge(["label" => $col["label"]], $emptyPaymentRow);
            }
            $data["total_all"] = array_merge(["label" => "TOTAL"], $emptyPaymentRow);

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
                $periodMeta = \App\Services\LaporanCutOffService::getMonthPeriodMeta($year, $month);
                $startDate = $periodMeta['query_start_date'];
                $endDate = $periodMeta['query_end_date'];

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

            try {
                $pmbParams = [
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "jenjang" => $jenjang,
                    "jenis_kelamin" => $jp->kategori ?? "%",
                ];
                if ($username) {
                    $pmbParams["username"] = $username;
                }

                $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders(
                    [
                        "apikey" => $pmbApiKey,
                    ],
                )
                    ->timeout(10)
                    ->get($pmbUrl, $pmbParams);

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
                ->where("kt.nama", "NOT LIKE", "%SEMESTER PENDEK%");

            if ($jp && $jp->id != "%") {
                $paymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
            }

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
                        "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, kjp.id as jenis_pembayaran_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "kjp.id",
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
                ->where("kt.nama", "LIKE", "%SEMESTER PENDEK%");

            if ($jp && $jp->id != "%") {
                $spPaymentsQuery->where("keuangan_pembayaran.jk_id", $jp->id);
            }

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
                        "DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, kjp.nama as jenis_pembayaran_nama, kjp.id as jenis_pembayaran_id, COALESCE(mu.id, 0) as mata_uang_id, COALESCE(mu.kode, 'IDR') as mata_uang_kode, COALESCE(mu.nama, 'Rupiah') as mata_uang_nama, COALESCE(mu.simbol, 'Rp') as mata_uang_simbol, SUM(keuangan_pembayaran.jumlah) as total_jumlah",
                    )
                    ->groupBy(
                        DB::raw("DATE(keuangan_pembayaran.tanggal)"),
                        "kt.nama",
                        "kt.prodi_id",
                        "kjp.nama",
                        "kjp.id",
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

            // Mapping logic for Tunai, Transfer, VA, Yayasan
            $getPaymentType = function ($namaRaw) {
                $nama = strtolower($namaRaw ?? "");
                if (
                    strpos($nama, "va") !== false ||
                    strpos($nama, "via") !== false ||
                    strpos($nama, "virtual") !== false
                ) {
                    return "va";
                }
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

            $recordPayment = function (&$targetRow, &$totalRow, $paymentType, $jumlah, $mataUang) {
                $targetRow[$paymentType] += $jumlah;
                $targetRow["total"] += $jumlah;
                $totalRow[$paymentType] += $jumlah;
                $totalRow["total"] += $jumlah;

                MataUangFormatter::addToTotals(
                    $targetRow[$paymentType . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $targetRow["total_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totalRow[$paymentType . "_by_currency"],
                    $jumlah,
                    $mataUang,
                );
                MataUangFormatter::addToTotals(
                    $totalRow["total_by_currency"],
                    $jumlah,
                    $mataUang,
                );

                if ($paymentType === "transfer" || $paymentType === "va") {
                    $targetRow["va_transfer"] += $jumlah;
                    $totalRow["va_transfer"] += $jumlah;
                    MataUangFormatter::addToTotals(
                        $targetRow["va_transfer_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                    MataUangFormatter::addToTotals(
                        $totalRow["va_transfer_by_currency"],
                        $jumlah,
                        $mataUang,
                    );
                }
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
                        $mData[$col["key"]] = array_merge(
                            ["label" => $col["label"]],
                            $emptyPaymentRow,
                        );
                    }
                    $allDataMonths[$m] = [
                        "title" => $mTitle,
                        "data_map" => $mData,
                        "total_all" => $emptyPaymentRow,
                    ];
                }
            }

            foreach ($payments as $payment) {
                if ($mode !== "tahunan") {
                    if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                        $payment->tgl ?? '',
                        $payment->jenis_pembayaran_nama,
                        $payment->jenis_pembayaran_id ? (int) $payment->jenis_pembayaran_id : null,
                        (int) $year,
                        (int) $month
                    )) {
                        continue;
                    }
                }

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
                    $recordPayment(
                        $data[$assignedKey],
                        $data["total_all"],
                        $paymentType,
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
                    $recordPayment(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey],
                        $allDataMonths[$bulanVal]["total_all"],
                        $paymentType,
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

                $tglPmb = date("Y-m-d", strtotime($pmb["tanggal_bayar"]));
                $pmbMethod = $pmb["metode_pembayaran"] ?? $pmb["jenis_pembayaran"] ?? "cash";

                if ($mode !== "tahunan") {
                    if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                        $tglPmb,
                        $pmbMethod,
                        null,
                        (int) $year,
                        (int) $month
                    )) {
                        continue;
                    }
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
                    $recordPayment(
                        $data[$assignedKey],
                        $data["total_all"],
                        $paymentType,
                        $jumlah,
                        $mataUang,
                    );
                }

                if (
                    $mode === "tahunan" &&
                    isset($allDataMonths[$bulanVal]["data_map"][$assignedKey])
                ) {
                    $recordPayment(
                        $allDataMonths[$bulanVal]["data_map"][$assignedKey],
                        $allDataMonths[$bulanVal]["total_all"],
                        $paymentType,
                        $jumlah,
                        $mataUang,
                    );
                }
            }

            // Process Pemasukan Umum
            if ($jenjang === "sarjana") {
                $puQuery = KeuanganPemasukanUmum::leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "keuangan_pemasukan_umum.jenis_pembayaran_id")
                    ->whereBetween("keuangan_pemasukan_umum.tanggal", [
                        $startDate . " 00:00:00",
                        $endDate . " 23:59:59",
                    ]);

                if ($userId) {
                    $puQuery->where("keuangan_pemasukan_umum.petugas_id", $userId);
                } elseif ($jp && $jp->id != "%") {
                    $jkTarget = $jp->id == 8 ? "Laki-laki" : ($jp->id == 9 ? "Perempuan" : null);
                    if ($jkTarget) {
                        $puQuery->whereHas("petugas", function ($q) use ($jkTarget) {
                            $q->whereIn("jenis_kelamin", [$jkTarget, "*"]);
                        });
                    }
                }

                if ($mode === "tahunan") {
                    $puRecords = $puQuery->selectRaw("MONTH(keuangan_pemasukan_umum.tanggal) as bulan, kjp.nama as jenis_pembayaran_nama, SUM(keuangan_pemasukan_umum.nominal) as total_jumlah")
                        ->groupBy(DB::raw("MONTH(keuangan_pemasukan_umum.tanggal)"), "kjp.nama")
                        ->get();
                } else {
                    $puRecords = $puQuery->selectRaw("DATE(keuangan_pemasukan_umum.tanggal) as tgl, kjp.nama as jenis_pembayaran_nama, keuangan_pemasukan_umum.jenis_pembayaran_id, SUM(keuangan_pemasukan_umum.nominal) as total_jumlah")
                        ->groupBy(DB::raw("DATE(keuangan_pemasukan_umum.tanggal)"), "kjp.nama", "keuangan_pemasukan_umum.jenis_pembayaran_id")
                        ->get();
                }

                $assignedKey = "pemasukan_umum";
                $mataUang = MataUangFormatter::defaultCurrency();

                foreach ($puRecords as $pu) {
                    if ($mode !== "tahunan") {
                        if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                            $pu->tgl ?? '',
                            $pu->jenis_pembayaran_nama,
                            $pu->jenis_pembayaran_id,
                            (int) $year,
                            (int) $month
                        )) {
                            continue;
                        }
                    }

                    $jumlah = (float) $pu->total_jumlah;
                    if ($jumlah <= 0) {
                        continue;
                    }

                    $paymentType = $getPaymentType($pu->jenis_pembayaran_nama ?? "tunai");
                    $bulanVal = $mode === "tahunan" ? (int) $pu->bulan : null;

                    if (isset($data[$assignedKey])) {
                        $recordPayment(
                            $data[$assignedKey],
                            $data["total_all"],
                            $paymentType,
                            $jumlah,
                            $mataUang,
                        );
                    }

                    if (
                        $mode === "tahunan" &&
                        $bulanVal &&
                        isset($allDataMonths[$bulanVal]["data_map"][$assignedKey])
                    ) {
                        $recordPayment(
                            $allDataMonths[$bulanVal]["data_map"][$assignedKey],
                            $allDataMonths[$bulanVal]["total_all"],
                            $paymentType,
                            $jumlah,
                            $mataUang,
                        );
                    }
                }
            }

            // Process Pengembalian Dana
            if ($jenjang === "sarjana") {
                $pdQuery = KeuanganPengembalianDana::leftJoin("keuangan_jenis_pembayaran as kjp", "kjp.id", "=", "keuangan_pengembalian_dana.jenis_pembayaran_id")
                    ->whereBetween("keuangan_pengembalian_dana.tanggal", [
                        $startDate . " 00:00:00",
                        $endDate . " 23:59:59",
                    ]);

                if ($userId) {
                    $pdQuery->where("keuangan_pengembalian_dana.petugas_id", $userId);
                } elseif ($jp && $jp->id != "%") {
                    $jkTarget = $jp->id == 8 ? "Laki-laki" : ($jp->id == 9 ? "Perempuan" : null);
                    if ($jkTarget) {
                        $pdQuery->whereHas("petugas", function ($q) use ($jkTarget) {
                            $q->whereIn("jenis_kelamin", [$jkTarget, "*"]);
                        });
                    }
                }

                if ($mode === "tahunan") {
                    $pdRecords = $pdQuery->selectRaw("MONTH(keuangan_pengembalian_dana.tanggal) as bulan, kjp.nama as jenis_pembayaran_nama, SUM(keuangan_pengembalian_dana.nominal) as total_jumlah")
                        ->groupBy(DB::raw("MONTH(keuangan_pengembalian_dana.tanggal)"), "kjp.nama")
                        ->get();
                } else {
                    $pdRecords = $pdQuery->selectRaw("DATE(keuangan_pengembalian_dana.tanggal) as tgl, kjp.nama as jenis_pembayaran_nama, keuangan_pengembalian_dana.jenis_pembayaran_id, SUM(keuangan_pengembalian_dana.nominal) as total_jumlah")
                        ->groupBy(DB::raw("DATE(keuangan_pengembalian_dana.tanggal)"), "kjp.nama", "keuangan_pengembalian_dana.jenis_pembayaran_id")
                        ->get();
                }

                $assignedKey = "pengembalian_dana";
                $mataUang = MataUangFormatter::defaultCurrency();

                foreach ($pdRecords as $pd) {
                    if ($mode !== "tahunan") {
                        if (!\App\Services\LaporanCutOffService::isPaymentInAccountingMonth(
                            $pd->tgl ?? '',
                            $pd->jenis_pembayaran_nama,
                            $pd->jenis_pembayaran_id,
                            (int) $year,
                            (int) $month
                        )) {
                            continue;
                        }
                    }

                    $nominalPositif = (float) $pd->total_jumlah;
                    if ($nominalPositif <= 0) {
                        continue;
                    }

                    // Sifatnya negatif untuk mengurangi pemasukan
                    $jumlah = -$nominalPositif;
                    $paymentType = $getPaymentType($pd->jenis_pembayaran_nama ?? "tunai");
                    $bulanVal = $mode === "tahunan" ? (int) $pd->bulan : null;

                    if (isset($data[$assignedKey])) {
                        $recordPayment(
                            $data[$assignedKey],
                            $data["total_all"],
                            $paymentType,
                            $jumlah,
                            $mataUang,
                        );
                    }

                    if (
                        $mode === "tahunan" &&
                        $bulanVal &&
                        isset($allDataMonths[$bulanVal]["data_map"][$assignedKey])
                    ) {
                        $recordPayment(
                            $allDataMonths[$bulanVal]["data_map"][$assignedKey],
                            $allDataMonths[$bulanVal]["total_all"],
                            $paymentType,
                            $jumlah,
                            $mataUang,
                        );
                    }
                }
            }

            $currencyFields = [
                "tunai_by_currency",
                "transfer_by_currency",
                "va_by_currency",
                "va_transfer_by_currency",
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
                    "key" => $col["key"],
                    "kategori" => $row["label"],
                    "tunai" => $row["tunai"],
                    "transfer" => $row["transfer"],
                    "va" => $row["va"],
                    "va_transfer" => $row["va_transfer"],
                    "yayasan" => $row["yayasan"],
                    "total" => $row["total"],
                    "tunai_by_currency" => $row["tunai_by_currency"],
                    "transfer_by_currency" => $row["transfer_by_currency"],
                    "va_by_currency" => $row["va_by_currency"],
                    "va_transfer_by_currency" => $row["va_transfer_by_currency"],
                    "yayasan_by_currency" => $row["yayasan_by_currency"],
                    "total_by_currency" => $row["total_by_currency"],
                ];
            }

            $formattedAllData = [];
            if ($mode === "tahunan") {
                foreach ($allDataMonths as $m => $mInfo) {
                    $mTable = [];
                    $no = 1;
                    foreach ($columns as $col) {
                        $row = $mInfo["data_map"][$col["key"]];
                        $mTable[] = [
                            "no" => $no++,
                            "key" => $col["key"],
                            "kategori" => $row["label"],
                            "tunai" => $row["tunai"],
                            "transfer" => $row["transfer"],
                            "va" => $row["va"],
                            "va_transfer" => $row["va_transfer"],
                            "yayasan" => $row["yayasan"],
                            "total" => $row["total"],
                            "tunai_by_currency" => $row["tunai_by_currency"],
                            "transfer_by_currency" => $row["transfer_by_currency"],
                            "va_by_currency" => $row["va_by_currency"],
                            "va_transfer_by_currency" => $row["va_transfer_by_currency"],
                            "yayasan_by_currency" => $row["yayasan_by_currency"],
                            "total_by_currency" => $row["total_by_currency"],
                        ];
                    }
                    $formattedAllData[] = [
                        "title" => $mInfo["title"],
                        "month" => $m,
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

            $rincianUiiDalwa = [
                [
                    "nama" => "Tunai",
                    "key" => "tunai",
                    "total" => (float) ($data["total_all"]["tunai"] ?? 0),
                    "total_by_currency" => $data["total_all"]["tunai_by_currency"] ?? [],
                ],
                [
                    "nama" => "Transfer",
                    "key" => "transfer",
                    "total" => (float) ($data["total_all"]["transfer"] ?? 0),
                    "total_by_currency" => $data["total_all"]["transfer_by_currency"] ?? [],
                ],
                [
                    "nama" => "Cash + Transfer",
                    "key" => "cash_transfer",
                    "total" => (float) (($data["total_all"]["tunai"] ?? 0) + ($data["total_all"]["transfer"] ?? 0)),
                    "total_by_currency" => MataUangFormatter::mergeTotals(
                        $data["total_all"]["tunai_by_currency"] ?? [],
                        $data["total_all"]["transfer_by_currency"] ?? []
                    ),
                ],
                [
                    "nama" => "VA Pondok",
                    "key" => "va_pondok",
                    "total" => (float) ($data["total_all"]["va"] ?? 0),
                    "total_by_currency" => $data["total_all"]["va_by_currency"] ?? [],
                ],
                [
                    "nama" => "VA + Transfer",
                    "key" => "va_transfer",
                    "total" => (float) ($data["total_all"]["va_transfer"] ?? 0),
                    "total_by_currency" => $data["total_all"]["va_transfer_by_currency"] ?? [],
                ],
                [
                    "nama" => "Yayasan",
                    "key" => "yayasan",
                    "total" => (float) ($data["total_all"]["yayasan"] ?? 0),
                    "total_by_currency" => $data["total_all"]["yayasan_by_currency"] ?? [],
                ],
                [
                    "nama" => "Total Semua",
                    "key" => "total",
                    "is_total" => true,
                    "total" => (float) ($data["total_all"]["total"] ?? 0),
                    "total_by_currency" => $data["total_all"]["total_by_currency"] ?? [],
                ],
            ];

            return response()->json([
                "status" => true,
                "message" => "Data Pemasukan UII Dalwa berhasil diambil",
                "title" => $title,
                "data" => $tableData,
                "totals" => $data["total_all"],
                "rincian_jenis_pembayaran" => $rincianUiiDalwa,
                "all_data" => $formattedAllData,
                "jenis_kelamin" => $jp->kategori ?? "Semua",
                "cut_off_info" => $periodMeta ?? null,
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
