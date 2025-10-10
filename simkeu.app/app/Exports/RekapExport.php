<?php

namespace App\Exports;

use App\Exports\RekapExportSheet\RekapBulanan;
use App\Exports\RekapExportSheet\RekapHarian;
use App\Exports\RekapExportSheet\RekapTahunan;
use App\Http\Services\Helper;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganTagihan;
use App\Models\Prodi;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapExport implements WithMultipleSheets
{
    protected $tahun;
    protected $bulan;

    public function __construct($data)
    {
        $this->tahun = $data['tahun_rekap'];
        $this->bulan = $data['bulan_rekap'];
    }

    public function kategori()
    {
        // data tagihan yang bukan ada kaitannya dengan semester
        $tagihanSisa = KeuanganTagihan::where([
            ['nama', 'NOT LIKE', '%SPP%'],
            ['nama', 'NOT LIKE', '%DAFTAR ULANG%'],
            ['nama', 'NOT LIKE', '%REGIST%'],
            ['nama', 'NOT LIKE', '%UAS%'],
        ])->get()->unique('nama')->pluck('nama')->toArray();

        $kategori = [];
        foreach ($tagihanSisa as $ts) {
            $kategori[] = (object) [
                "id" => $ts,
                "nama" => $ts,
            ];
        }

        $kategori[] = (object) [
            "id" => "DAFTAR ULANG",
            "nama" => "DAFTAR ULANG",
        ];
        $kategori[] = (object) [
            "id" => "REGIST",
            "nama" => "REGISTRASI",
        ];
        $kategori[] = (object) [
            "id" => "UAS",
            "nama" => "UAS",
        ];

        return $kategori;
    }

    public function sheets(): array
    {
        $prodi = Prodi::where([
            ["id", "!=", 15],
            ["jenjang", "S1"],
        ])->get();

        $tagihanSisa = $this->kategori();

        $bulanRekap = explode('-', $this->bulan);
        $dataBulan = explode(';', $bulanRekap[0]);
        $tanggal = [];
        foreach ($dataBulan as $db) {
            $tanggal[$db] = [];
        }
        $jumlahBulan = count($tanggal);
        // $tanggal2 = [
        //     "JANUARI" => [],
        //     "FEBRUARI" => [],
        //     "MARET" => [],
        //     "APRIL" => [],
        //     "MEI" => [],
        //     "JUNI" => [],
        //     "JULI" => [],
        //     "AGUSTUS" => [],
        //     "SEPTEMBER" => [],
        //     "OKTOBER" => [],
        //     "NOVEMBER" => [],
        //     "DESEMBER" => [],
        // ];

        $nBulan = (int) $bulanRekap[1]; // bulan dalam bentuk angka
        $jp = Helper::getJenisKelaminUser();

        foreach ($tanggal as $bulan => $value) {
            $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $nBulan, $this->tahun);
            for ($i = 1; $i <= $jumlahHari; $i++) {
                $tanggal[$bulan][$i]['original'] = date('Y-m-d', strtotime("$this->tahun-$nBulan-$i"));
                $tanggal[$bulan][$i]['modif'] = date('d/m/Y', strtotime("$this->tahun-$nBulan-$i"));

                $totalHarianSemuaProdi = 0;

                // Untuk SPP tiap prodi
                foreach ($prodi as $key => $p) {
                    $p = $p->id;
                    $transaksi = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                        ->join('mst_mhs as mhs', 'mhs.nim', '=', 'keuangan_pembayaran.nim')
                        ->where([
                            ['tanggal', $tanggal[$bulan][$i]['original']],
                            ['kt.nama', 'LIKE', '%SPP%'],
                            ['kt.prodi_id', $p],
                            ['mhs.jk_id', 'LIKE', "%$jp->id%"],
                        ])
                        ->select('*', 'kt.nama as kt', 'keuangan_pembayaran.jumlah as dibayar', 'kt.jumlah as jumlah_tagihan')
                        ->get();

                    $totalDibayar = 0;
                    foreach ($transaksi as $t) {
                        if ($t->dibayar == $t->nim) {
                            $t->dibayar = $t->jumlah_tagihan;
                        }
                        $totalDibayar += $t->dibayar;
                    }

                    $totalHarianSemuaProdi += $totalDibayar;
                    // total perbulan dan perprodi
                    if (isset($tanggal[$bulan]['total'][$p]) == false) {
                        $tanggal[$bulan]['total'][$p] = $totalDibayar;
                    } else {
                        $tanggal[$bulan]['total'][$p] += $totalDibayar;
                    }
                    // jumlah yang dibayar per bulan dan perprodi
                    $tanggal[$bulan][$i]['transaksi'][$p] = $totalDibayar;

                    if (isset($tanggal['total'][$p]) == false) {
                        $tanggal['total'][$p] = $totalDibayar;
                        $tanggal['total']['total'] = $totalDibayar;
                    } else {
                        $tanggal['total'][$p] += $totalDibayar;
                        $tanggal['total']['total'] += $totalDibayar;
                    }
                }

                // Untuk tagihanSisa
                foreach ($tagihanSisa as $key => $p) {
                    $p = $p->id;
                    $transaksi = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                        ->join('mst_mhs as mhs', 'mhs.nim', '=', 'keuangan_pembayaran.nim')
                        ->where([
                            ['tanggal', $tanggal[$bulan][$i]['original']],
                            ['kt.nama', 'LIKE', "%$p%"],
                            ['mhs.jk_id', 'LIKE', "%$jp->id%"],
                        ])
                        ->select('*', 'kt.nama as kt', 'keuangan_pembayaran.jumlah as dibayar', 'kt.jumlah as jumlah_tagihan')
                        ->get();

                    $totalDibayar = 0;
                    foreach ($transaksi as $t) {
                        if ($t->dibayar == $t->nim) {
                            $t->dibayar = $t->jumlah_tagihan;
                        }
                        $totalDibayar += $t->dibayar;
                    }

                    $totalHarianSemuaProdi += $totalDibayar;
                    // total perbulan dan perprodi
                    if (isset($tanggal[$bulan]['total'][$p]) == false) {
                        $tanggal[$bulan]['total'][$p] = $totalDibayar;
                    } else {
                        $tanggal[$bulan]['total'][$p] += $totalDibayar;
                    }
                    // jumlah yang dibayar per bulan dan perprodi
                    $tanggal[$bulan][$i]['transaksi'][$p] = $totalDibayar;

                    if (isset($tanggal['total'][$p]) == false) {
                        $tanggal['total'][$p] = $totalDibayar;
                        $tanggal['total']['total'] = $totalDibayar;
                    } else {
                        $tanggal['total'][$p] += $totalDibayar;
                        $tanggal['total']['total'] += $totalDibayar;
                    }
                }

                $tanggal[$bulan][$i]['transaksi']['total'] = $totalHarianSemuaProdi;

            }

            $totalBulan = $tanggal[$bulan]['total'];
            $totalBulanDibayar = 0;
            foreach ($totalBulan as $tb) {
                $totalBulanDibayar += $tb;
            }

            // total semua prodi per bulan
            $tanggal[$bulan]['total']['total'] = $totalBulanDibayar;

            $nBulan++;
        }

        $sheets = [];

        // merge variabel prodi (menampung spp) dengan sisa tagihan
        foreach ($tagihanSisa as $ts) {
            $prodi[] = $ts;
        }
        // $prodi = array_merge($prodi, $this->kategori());
        // dd($tanggal, $prodi);
        $sheets[] = new RekapHarian($this->tahun, $prodi, $tanggal);
        $sheets[] = new RekapBulanan($this->tahun, $prodi, $tanggal);
        $sheets[] = new RekapTahunan($this->tahun, $prodi, $tanggal, $jumlahBulan);

        return $sheets;
    }
}
