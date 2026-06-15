<?php

namespace App\Exports;

use App\Exports\Concerns\AddsPimpinanSignature;
use App\Http\Controllers\Operasi\MhsJenisTagihanController;
use App\Services\TagihanMahasiswa;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;

class LaporanTagihanExport implements FromView, WithEvents
{
    use AddsPimpinanSignature;
    /**
     * @return \Illuminate\Support\Collection
     */
    public $nim;
    public $prodi;
    public $nama;
    public $tahun_akademik;
    public $deposit;
    public $scope;
    public $cekNilai;

    public function __construct($nim, $prodi, $nama, $tahun_akademik, $deposit, $scope = 'semua', $cekNilai = null)
    {
        $this->nim = $nim;
        $this->prodi = $prodi;
        $this->nama = $nama;
        $this->tahun_akademik = $tahun_akademik;
        $this->deposit = $deposit;
        $this->scope = $scope;
        $this->cekNilai = $cekNilai;
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
        $data = TagihanMahasiswa::markPaymentEligibility($tagihan['list_tagihan'] ?? [], $nim, $this->cekNilai);
        $groups = TagihanMahasiswa::getTagihanGroupsForScope(
            $data,
            'semester_ini',
            $tagihan['semester'] ?? null,
            $tagihan['angkatan'] ?? null
        );
        $status = $data ? true : false;

        // mengambil nama prodi saja
        return view('admin.mhs-tagihan.excel', compact('data', 'groups', 'tanggal', 'nim', 'prodi', 'nama', 'tahun_akademik', 'status', 'deposit'));
    }
}
