<?php

namespace App\Exports;

use App\Http\Controllers\Operasi\MhsJenisTagihanController;
use App\Services\TagihanMahasiswa;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class LaporanTagihanExport implements FromView
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $nim;
    public $prodi;
    public $nama;
    public $tahun_akademik;
    public $deposit;

    public function __construct($nim, $prodi, $nama, $tahun_akademik, $deposit)
    {
        $this->nim = $nim;
        $this->prodi = $prodi;
        $this->nama = $nama;
        $this->tahun_akademik = $tahun_akademik;
        $this->deposit = $deposit;
    }
    public function view(): View
    {
        $tanggal = date('d-m-Y');
        $nim = $this->nim;
        $prodi = $this->prodi;
        $nama = $this->nama;
        $tahun_akademik = $this->tahun_akademik;
        $deposit = $this->deposit;
        $tagihan = TagihanMahasiswa::tagihan($nim);
        $data = $tagihan['list_tagihan'];
        $status = $data ? true : false;

        // mengambil nama prodi saja
        return view('admin.mhs-tagihan.excel', compact('data', 'tanggal', 'nim', 'prodi', 'nama', 'tahun_akademik', 'status', 'deposit'));
    }
}
