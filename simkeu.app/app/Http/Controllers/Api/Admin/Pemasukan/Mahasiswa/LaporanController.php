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
use App\Models\KeuanganTagihan;
use App\Services\Helper;
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
            } else if ($dataValidated['action'] == "excelTotalanStaff") {
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

                if (\Auth::user()->role->name == 'admin') {
                    $userId = null;
                } else {
                    $userId = \Auth::user()->id;
                }

                return Excel::download(new PembayaranTotalanHarianExport($dataValidated['tanggal'], $dataValidated['kategori'], $prodi, $tahunAkademik, $jenisPembayaran, $userId), 'laporantotalanharian.xlsx');
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
     * Pemasukan Tunai Harian - Monthly daily cash income report
     * Returns daily payment totals grouped by tagihan categories
     */
    private function getPemasukanTunaiColumns($jenjang = 'sarjana')
    {
        // a) SPP per Prodi
        $prodiQuery = Prodi::where("id", "!=", 15);
        if ($jenjang === 'sarjana') {
            $prodiQuery->where("jenjang", "S1");
        } elseif ($jenjang === 'pascasarjana') {
            $prodiQuery->whereIn('jenjang', ['S2', 'S3']);
        }
        $prodiList = $prodiQuery->orderBy('nama', 'asc')->get();

        $columns = [];
        foreach ($prodiList as $p) {
            $columns[] = [
                'key' => 'spp_prodi_' . $p->id,
                'label' => 'SPP ' . $p->alias,
                'type' => 'spp',
                'prodi_id' => $p->id,
            ];
        }

        // b) Fixed categories
        $fixedCategories = [
            ['key' => 'registrasi', 'label' => 'REGISTRASI & DAFTAR ULANG', 'type' => 'fixed', 'search' => ['%REGIST%', '%DAFTAR ULANG%']],
            ['key' => 'uas', 'label' => 'UAS', 'type' => 'fixed', 'search' => ['%UAS%']],
            ['key' => 'kkn', 'label' => 'KKN / PPL / PKL', 'type' => 'fixed', 'search' => ['%KKN%', '%PPL%', '%PKL%']],
            ['key' => 'skripsi', 'label' => 'SKRIPSI', 'type' => 'fixed', 'search' => ['%SKRIPSI%']],
            ['key' => 'pmb', 'label' => 'PMB', 'type' => 'fixed', 'search' => ['%PMB%']],
            ['key' => 'double_degree', 'label' => 'PERSYARATAN DOUBLE DEGREE', 'type' => 'fixed', 'search' => ['%DOUBLE DEGREE%']],
            ['key' => 'perpus', 'label' => 'SUMBANGAN PERPUS', 'type' => 'fixed', 'search' => ['%PERPUS%']],
            ['key' => 'kompetensi', 'label' => 'UJI KOMPETENSI', 'type' => 'fixed', 'search' => ['%KOMPETENSI%']],
            ['key' => 'sp', 'label' => 'SEMESTER PENDEK', 'type' => 'fixed', 'search' => ['%SEMESTER PENDEK%']],
            ['key' => 'wisuda', 'label' => 'WISUDA', 'type' => 'fixed', 'search' => ['%WISUDA%']],
        ];

        foreach ($fixedCategories as $fc) {
            $columns[] = $fc;
        }

        // c) Other tagihan
        $otherTagihan = KeuanganTagihan::where([
            ['nama', 'NOT LIKE', '%SPP%'],
            ['nama', 'NOT LIKE', '%UAS%'],
            ['nama', 'NOT LIKE', '%KKN%'],
            ['nama', 'NOT LIKE', '%PPL%'],
            ['nama', 'NOT LIKE', '%PKL%'],
            ['nama', 'NOT LIKE', '%REGIST%'],
            ['nama', 'NOT LIKE', '%DAFTAR ULANG%'],
            ['nama', 'NOT LIKE', '%SKRIPSI%'],
            ['nama', 'NOT LIKE', '%PMB%'],
            ['nama', 'NOT LIKE', '%DOUBLE DEGREE%'],
            ['nama', 'NOT LIKE', '%PERPUS%'],
            ['nama', 'NOT LIKE', '%KOMPETENSI%'],
            ['nama', 'NOT LIKE', '%SEMESTER PENDEK%'],
            ['nama', 'NOT LIKE', '%WISUDA%'],
        ])->get()->unique('nama')->pluck('nama')->toArray();

        foreach ($otherTagihan as $nama) {
            $columns[] = [
                'key' => 'other_' . md5($nama),
                'label' => $nama,
                'type' => 'other',
                'search' => $nama,
            ];
        }

        return $columns;
    }

    private function getPemasukanBulanData($year, $month, $columns, $jp, $prefetchedPayments = null, $pmbData = [], $jenjang = 'sarjana', $jenisPembayaranId = null)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        if ($prefetchedPayments === null) {
            $paymentsQuery = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->whereBetween('keuangan_pembayaran.tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('keuangan_pembayaran.jk_id', 'LIKE', "%$jp->id%");

            if ($jenjang === 'sarjana') {
                $paymentsQuery->join('prodi as p', 'p.id', '=', 'kt.prodi_id')
                    ->where('p.jenjang', 'S1');
            } elseif ($jenjang === 'pascasarjana') {
                $paymentsQuery->join('prodi as p', 'p.id', '=', 'kt.prodi_id')
                    ->whereIn('p.jenjang', ['S2', 'S3']);
            }

            if ($jenisPembayaranId) {
                $paymentsQuery->join('keuangan_jenis_pembayaran_detail as kjpd', 'kjpd.pembayaran_id', '=', 'keuangan_pembayaran.id')
                    ->where('kjpd.jenis_pembayaran_id', $jenisPembayaranId);
            }

            $payments = $paymentsQuery->selectRaw('DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, SUM(keuangan_pembayaran.jumlah) as total_jumlah')
                ->groupBy(DB::raw('DATE(keuangan_pembayaran.tanggal)'), 'kt.nama', 'kt.prodi_id')
                ->get();
        } else {
            $payments = $prefetchedPayments;
        }

        $dataMap = [];
        $totals = [];

        foreach ($columns as $col) {
            $totals[$col['key']] = 0;
        }
        $totals['jumlah'] = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $row = [
                'no' => $day,
                'tanggal' => $dateStr,
                'jumlah' => 0,
            ];
            foreach ($columns as $col) {
                $row[$col['key']] = 0;
            }
            $dataMap[$dateStr] = $row;
        }

        $colMap = [];
        foreach ($columns as $col) {
            $colMap[$col['key']] = $col;
        }

        foreach ($payments as $payment) {
            $tgl = $payment->tgl;
            $namaRaw = $payment->tagihan_nama;
            $namaUpper = strtoupper($namaRaw);
            $prodi_id = $payment->prodi_id;
            $jumlah = (float) $payment->total_jumlah;

            if (!isset($dataMap[$tgl])) continue;

            $assignedKey = null;

            if (strpos($namaUpper, 'SPP') !== false) {
                $assignedKey = 'spp_prodi_' . $prodi_id;
            } else {
                foreach ($columns as $c) {
                    if ($c['type'] === 'fixed') {
                        foreach ($c['search'] as $searchPattern) {
                            $term = trim(str_replace('%', '', strtoupper($searchPattern)));
                            if (strpos($namaUpper, $term) !== false) {
                                $assignedKey = $c['key'];
                                break 2;
                            }
                        }
                    }
                }
                if (!$assignedKey) {
                    $assignedKey = 'other_' . md5($namaRaw);
                }
            }

            if (isset($colMap[$assignedKey])) {
                $dataMap[$tgl][$assignedKey] += $jumlah;
                $dataMap[$tgl]['jumlah'] += $jumlah;
                $totals[$assignedKey] += $jumlah;
                $totals['jumlah'] += $jumlah;
            }
        }

        // Process PMB Data
        foreach ($pmbData as $pmb) {
            if (!isset($pmb['tanggal_bayar']) || !isset($pmb['nominal'])) continue;
            
            $tgl = date('Y-m-d', strtotime($pmb['tanggal_bayar']));
            $jumlah = (float) $pmb['nominal'];

            if (!isset($dataMap[$tgl])) continue;

            $assignedKey = 'pmb';

            if (isset($colMap[$assignedKey])) {
                $dataMap[$tgl][$assignedKey] += $jumlah;
                $dataMap[$tgl]['jumlah'] += $jumlah;
                $totals[$assignedKey] += $jumlah;
                $totals['jumlah'] += $jumlah;
            }
        }

        $data = array_values($dataMap);

        $bulanNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];

        return [
            'title' => 'PEMASUKAN TUNAI BULAN ' . $bulanNames[$month] . ' ' . $year,
            'bulan_name' => $bulanNames[$month],
            'data' => $data,
            'totals' => $totals,
        ];
    }

    public function pemasukanTunaiHarian(Request $request)
    {
        try {
            $jp = Helper::getJenisKelaminUser();
            $action = $request->input('action', 'json');
            $mode = $request->input('mode', 'bulanan'); // 'bulanan' atau 'tahunan'
            $jenjang = $request->input('jenjang', 'sarjana');
            $jenisPembayaranId = $request->input('jenis_pembayaran_id');
            
            $jenisPembayaranNama = null;
            $jenisPembayaranKategori = null;
            if ($jenisPembayaranId) {
                $jpModel = \App\Models\KeuanganJenisPembayaran::find($jenisPembayaranId);
                if ($jpModel) {
                    $nama = strtolower(trim($jpModel->nama));
                    $jenisPembayaranKategori = $jpModel->kategori;
                    if (strpos($nama, 'deposit') !== false) {
                        $jenisPembayaranNama = 'deposit';
                    } elseif (strpos($nama, 'transfer') !== false) {
                        $jenisPembayaranNama = 'transfer';
                    } elseif (strpos($nama, 'cash') !== false) {
                        $jenisPembayaranNama = 'cash';
                    } elseif (strpos($nama, 'yayasan') !== false) {
                        $jenisPembayaranNama = 'yayasan';
                    }
                }
            }
            
            $columns = $this->getPemasukanTunaiColumns($jenjang);

            $pmbUrl = rtrim(env('pmb_url'), '/') . '/simkeu/pembayaran';
            $pmbApiKey = env('pmb_api_key');
            $pmbDataAll = [];

            if ($mode === 'tahunan') {
                $year = (int) $request->tahun;
                
                $startDateYear = sprintf('%04d-01-01', $year);
                $endDateYear = sprintf('%04d-12-31', $year);
                
                $startDate = $startDateYear;
                $endDate = $endDateYear;
            } else {
                $parts = explode('-', $request->bulan);
                if (count($parts) != 2) throw new \Exception("Format bulan tidak valid. Gunakan YYYY-MM.");
                $year = (int) $parts[0];
                $month = (int) $parts[1];
                
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $startDate = sprintf('%04d-%02d-01', $year, $month);
                $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
            }

            try {
                $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'apikey' => $pmbApiKey
                ])->get($pmbUrl, [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'jenjang' => $jenjang,
                    'jenis_kelamin' => $jenisPembayaranKategori,
                    'jenis_pembayaran' => $jenisPembayaranNama,
                ]);
                
                if ($pmbResponse->successful()) {
                    $pmbResData = $pmbResponse->json();
                    if (isset($pmbResData['data']) && is_array($pmbResData['data'])) {
                        $pmbDataAll = $pmbResData['data'];
                    }
                }
            } catch (\Throwable $th) {
                // Ignore API error, proceed with empty PMB data
            }

            if ($mode === 'tahunan') {
                $allPaymentsQuery = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->whereBetween('keuangan_pembayaran.tanggal', [$startDateYear . ' 00:00:00', $endDateYear . ' 23:59:59'])
                    ->where('keuangan_pembayaran.jk_id', 'LIKE', "%$jp->id%");

                if ($jenjang === 'sarjana') {
                    $allPaymentsQuery->join('prodi as p', 'p.id', '=', 'kt.prodi_id')
                        ->where('p.jenjang', 'S1');
                } elseif ($jenjang === 'pascasarjana') {
                    $allPaymentsQuery->join('prodi as p', 'p.id', '=', 'kt.prodi_id')
                        ->whereIn('p.jenjang', ['S2', 'S3']);
                }

                if ($jenisPembayaranId) {
                    $allPaymentsQuery->join('keuangan_jenis_pembayaran_detail as kjpd', 'kjpd.pembayaran_id', '=', 'keuangan_pembayaran.id')
                        ->where('kjpd.jenis_pembayaran_id', $jenisPembayaranId);
                }

                $allPayments = $allPaymentsQuery->selectRaw('DATE(keuangan_pembayaran.tanggal) as tgl, kt.nama as tagihan_nama, kt.prodi_id, SUM(keuangan_pembayaran.jumlah) as total_jumlah')
                    ->groupBy(DB::raw('DATE(keuangan_pembayaran.tanggal)'), 'kt.nama', 'kt.prodi_id')
                    ->get();

                $paymentsByMonth = [];
                foreach ($allPayments as $payment) {
                    $m = (int) date('m', strtotime($payment->tgl));
                    if (!isset($paymentsByMonth[$m])) {
                        $paymentsByMonth[$m] = [];
                    }
                    $paymentsByMonth[$m][] = $payment;
                }

                $pmbByMonth = [];
                foreach ($pmbDataAll as $item) {
                    if (isset($item['tanggal_bayar'])) {
                        $m = (int) date('m', strtotime($item['tanggal_bayar']));
                        if (!isset($pmbByMonth[$m])) $pmbByMonth[$m] = [];
                        $pmbByMonth[$m][] = $item;
                    }
                }

                $allData = [];
                for ($m = 1; $m <= 12; $m++) {
                    $monthPayments = $paymentsByMonth[$m] ?? [];
                    $monthPmb = $pmbByMonth[$m] ?? [];
                    $allData[$m] = $this->getPemasukanBulanData($year, $m, $columns, $jp, $monthPayments, $monthPmb, $jenjang, $jenisPembayaranId);
                }
                
                $columnHeaders = [];
                foreach ($columns as $col) {
                    $columnHeaders[] = [
                        'key' => $col['key'],
                        'label' => $col['label'],
                    ];
                }
                
                if ($action === 'excel') {
                    return Excel::download(new PemasukanTunaiHarianTahunanExport($columnHeaders, $allData, $year), 'Pemasukan_Tunai_Harian_Tahun_'.$year.'.xlsx');
                }
                
                return response()->json([
                    'status' => true,
                    'message' => 'Data tahunan berhasil diambil',
                    'columns' => $columnHeaders,
                    'all_data' => $allData,
                    'jenis_kelamin' => $jp->kategori ?? 'Semua',
                ]);
            } else {
                $monthData = $this->getPemasukanBulanData($year, $month, $columns, $jp, null, $pmbDataAll, $jenjang, $jenisPembayaranId);
                
                $columnHeaders = [];
                foreach ($columns as $col) {
                    $columnHeaders[] = [
                        'key' => $col['key'],
                        'label' => $col['label'],
                    ];
                }
                
                if ($action === 'excel') {
                    return Excel::download(new PemasukanTunaiHarianBulananExport($columnHeaders, $monthData['data'], $monthData['totals'], $monthData['title']), 'Pemasukan_Tunai_Harian_'.$request->bulan.'.xlsx');
                }
                
                return response()->json([
                    'status' => true,
                    'message' => 'Data bulanan berhasil diambil',
                    'title' => $monthData['title'],
                    'columns' => $columnHeaders,
                    'data' => $monthData['data'],
                    'totals' => $monthData['totals'],
                    'jenis_kelamin' => $jp->kategori ?? 'Semua',
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
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
