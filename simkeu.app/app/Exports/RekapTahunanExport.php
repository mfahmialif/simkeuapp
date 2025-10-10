<?php

namespace App\Exports;

use App\Services\Helper;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganTagihan;
use App\Models\Prodi;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapTahunanExport implements FromView, WithTitle
{
    protected $tahun;

    public function __construct($data)
    {
        $this->tahun = $data['tahun_rekap'];

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

    public function view(): View
    {
        $tahun = $this->tahun;
        $prodi = Prodi::where([
            ["id", "!=", 15],
            ["jenjang", "S1"],
        ])->get();

        $tagihanSisa = $this->kategori();

        $bulan = [
            "JANUARI" => [],
            "FEBRUARI" => [],
            "MARET" => [],
            "APRIL" => [],
            "MEI" => [],
            "JUNI" => [],
            "JULI" => [],
            "AGUSTUS" => [],
            "SEPTEMBER" => [],
            "OKTOBER" => [],
            "NOVEMBER" => [],
            "DESEMBER" => [],
        ];

        $nBulan = 1;
        $totalKeseluruhan = 0;
        $jp = Helper::getJenisKelaminUser();

        foreach ($bulan as $namaBulan => $value) {
            $totalTiapBulan = 0;
            foreach ($prodi as $key => $p) {
                $p = $p->id;
                $transaksi = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->join('mst_mhs as mhs', 'mhs.nim', '=', 'keuangan_pembayaran.nim')
                    ->whereYear('tanggal', '=', $this->tahun)
                    ->whereMonth('tanggal', '=', $nBulan)
                    ->where([
                        ['kt.nama', 'LIKE', '%SPP%'],
                        ['kt.prodi_id', $p],
                        ['mhs.jk_id', 'LIKE', "%$jp->id%"],
                    ])
                    ->select('*', 'kt.jumlah as jumlah_tagihan', 'keuangan_pembayaran.jumlah as dibayar')
                    ->get();
                $totalDibayarSPP = 0;
                foreach ($transaksi as $tr) {
                    if ($tr->nim == $tr->dibayar) {
                        $tr->dibayar = $tr->jumlah_tagihan;
                    }
                    $totalDibayarSPP += $tr->dibayar;
                }
                $bulan[$namaBulan][$p] = $totalDibayarSPP;
                $totalTiapBulan += $totalDibayarSPP;

                if (!isset($bulan['total'][$p])) {
                    $bulan['total'][$p] = $totalDibayarSPP;
                } else {
                    $bulan['total'][$p] += $totalDibayarSPP;
                }
            }

            foreach ($tagihanSisa as $key => $p) {
                $p = $p->id;
                $transaksi = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->join('mst_mhs as mhs', 'mhs.nim', '=', 'keuangan_pembayaran.nim')
                    ->whereMonth('tanggal', '=', $nBulan)
                    ->whereYear('tanggal', '=', $this->tahun)
                    ->where([
                        ['kt.nama', 'LIKE', "%$p%"],
                        ['mhs.jk_id', 'LIKE', "%$jp->id%"],
                    ])
                    ->select('*', 'kt.jumlah as jumlah_tagihan', 'keuangan_pembayaran.jumlah as dibayar')
                    ->get();

                $totalDibayarTagihanSisa = 0;
                foreach ($transaksi as $tr) {
                    if ($tr->nim == $tr->dibayar) {
                        $tr->dibayar = $tr->jumlah_tagihan;
                    }
                    $totalDibayarTagihanSisa += $tr->dibayar;
                }
                $bulan[$namaBulan][$p] = $totalDibayarTagihanSisa;
                $totalTiapBulan += $totalDibayarTagihanSisa;

                if (!isset($bulan['total'][$p])) {
                    $bulan['total'][$p] = $totalDibayarTagihanSisa;
                } else {
                    $bulan['total'][$p] += $totalDibayarTagihanSisa;
                }
            }
            $bulan[$namaBulan]['total'] = $totalTiapBulan;
            $totalKeseluruhan += $totalTiapBulan;
            $nBulan++;
        }
        $bulan['total']['total'] = $totalKeseluruhan;

        foreach ($tagihanSisa as $ts) {
            $prodi[] = $ts;
        }
        $tahun = $this->tahun;

        return view("admin.mhs-laporan.excel-rekap-tahun", compact('tahun', 'prodi', 'bulan'));
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Tahunan';
    }
}
