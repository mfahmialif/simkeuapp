<?php

namespace App\Exports;

use App\Models\Ref;
use App\Models\Prodi;
use App\Services\Helper;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganTagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use App\Models\KeuanganJenisPembayaran;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\KeuanganPembayaranTambahan;

class PembayaranTotalanHarianExport implements FromView
{
    /**
     * @return \Illuminate\Support\Collection
     */

    public $prodi;
    public $tahunAkademik;
    public $jenisPembayaran;
    public $tanggal;
    public $kategori;
    public $userId;

    public function __construct($tanggal, $kategori, $prodi, $tahunAkademik, $jenisPembayaran, $userId = false)
    {
        $this->prodi = $prodi;
        $this->tahunAkademik = $tahunAkademik;
        $this->jenisPembayaran = $jenisPembayaran;
        $this->tanggal = $tanggal;
        $this->kategori = $kategori;
        $this->userId = $userId;
    }

    public function kategori()
    {
        // data tagihan yang bukan ada kaitannya dengan semester
        $tagihanSisa = KeuanganTagihan::where([
            ['nama', 'NOT LIKE', '%SPP%'],
            ['nama', 'NOT LIKE', '%DAFTAR ULANG%'],
            ['nama', 'NOT LIKE', '%REG%'],
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
            "id" => "REG",
            "nama" => "REGISTRASI",
        ];
        $kategori[] = (object) [
            "id" => "UAS",
            "nama" => "UAS",
        ];

        return $kategori;
    }

    public function kategoriPembayaranTambahan($jk)
    {
        // data tagihan yang bukan ada kaitannya dengan semester

        $tagihanSisaTambahan = KeuanganPembayaranTambahan::select(DB::raw('upper(tagihan) as tagihan'))
            ->where([
                ['tagihan', 'NOT LIKE', '%SPP%'],
                ['tagihan', 'NOT LIKE', '%DAFTAR ULANG%'],
                ['tagihan', 'NOT LIKE', '%REG%'],
                ['tagihan', 'NOT LIKE', '%UAS%'],
            ])
            ->where('jenis_kelamin', $jk)
            ->get()->unique('tagihan')->pluck('tagihan')->toArray();

        $tagihanSisaPembayaran = KeuanganTagihan::select(DB::raw('upper(nama) as nama'))
            ->where([
                ['nama', 'NOT LIKE', '%SPP%'],
                ['nama', 'NOT LIKE', '%DAFTAR ULANG%'],
                ['nama', 'NOT LIKE', '%REGIST%'],
                ['nama', 'NOT LIKE', '%UAS%'],
            ])
            ->get()->unique('nama')->pluck('nama')->toArray();

        $diffTagihan = array_diff($tagihanSisaTambahan, $tagihanSisaPembayaran);

        $tagihanSisa = KeuanganTagihan::where([
            ['nama', 'NOT LIKE', '%SPP%'],
            ['nama', 'NOT LIKE', '%DAFTAR ULANG%'],
            ['nama', 'NOT LIKE', '%REG%'],
            ['nama', 'NOT LIKE', '%UAS%'],
        ])->get()->unique('nama')->pluck('nama')->toArray();

        $kategori = [];
        foreach ($tagihanSisa as $ts) {
            $kategori[] = (object) [
                "id" => $ts,
                "nama" => $ts,
                "semester" => false,
            ];
        }

        $kategori[] = (object) [
            "id" => "DAFTAR ULANG",
            "nama" => "DAFTAR ULANG",
            "semester" => true,
        ];
        $kategori[] = (object) [
            "id" => "REG",
            "nama" => "REGISTRASI",
            "semester" => true,
        ];
        $kategori[] = (object) [
            "id" => "UAS",
            "nama" => "UAS",
            "semester" => true,
        ];

        foreach ($diffTagihan as $dt) {
            $kategori[] = (object) [
                "id" => $dt,
                "nama" => $dt,
                "semester" => false,
            ];
        }
        return $kategori;
    }

    public function view(): View
    {
        $pilihTanggal = $this->tanggal;
        $kategori = $this->kategori;
        $prodi = $this->prodi;
        $tahunAkademik = $this->tahunAkademik;
        $jenisPembayaran = $this->jenisPembayaran;
        $userId = $this->userId;

        $jp = Helper::getJenisKelaminUser();
        $jenisKelamin = Ref::where('table', 'JenisKelamin')->get();
        foreach ($jenisKelamin as $key => $value) {
            if ($value->kode == "L") {
                $value->kategori = "Putra";
            }
            if ($value->kode == "P") {
                $value->kategori = "Putri";
            }
        }

        // excel totalan
        $getJenisPembayaran = KeuanganJenisPembayaran::where('kategori', 'LIKE', "%$jp->kategori%")->get();
        $getJenisPembayaran[] = (object) [
            'id' => null,
            'nama' => 'Belum Ada Jenis Pembayaran',
            'kategori' => "Putra & Putri"
        ];

        $jenisTagihan = Prodi::where([
            ["id", "!=", 15],
            ["jenjang", "S1"],
        ])->get();

        $tagihanSisa = $this->kategori();

        foreach ($tagihanSisa as $ts) {
            $jenisTagihan[] = $ts;
        }

        $setoran = KeuanganSetoran::whereDate('tanggal', '=', $pilihTanggal)
            ->where([
                ['status', 'setuju'],
                ['kategori', 'LIKE', "%$jp->kategori%"],
            ])
            ->get();
        $rowspan = $setoran->count();

        // Data Pembayaran Tambahan
        foreach ($jenisKelamin as $item) {
            $jenisTagihanPembayaranTambahan[$item->kode] = Prodi::where([
                ["id", "!=", 15],
                ["jenjang", "S1"],
            ])->get();

            $tagihanSisaPembayaranTambahan[$item->kode] = $this->kategoriPembayaranTambahan($item->kode);

            foreach ($tagihanSisaPembayaranTambahan[$item->kode] as $ts) {
                $jenisTagihanPembayaranTambahan[$item->kode][] = $ts;
            }
        }

        $semuaProdi = Prodi::select('nama')->get()->pluck('nama')->toArray();
        return view(
            'admin.mhs-laporan.excel-totalan-harian',
            compact(
                'pilihTanggal',
                'getJenisPembayaran',
                'jenisTagihan',
                'tagihanSisa',
                'rowspan',
                'setoran',
                'jenisTagihanPembayaranTambahan',
                'semuaProdi',
                'tagihanSisaPembayaranTambahan',
                'jenisKelamin',
                'jp',
                'userId'
            )
        );
    }
}
