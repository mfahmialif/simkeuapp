<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class LaporanJumlahMahasiswaExport implements FromView
{
    /**
     * @return \Illuminate\Support\Collection
     */

    public $data;
    public $jkId;

    public function __construct($data, $jkId)
    {
        $this->data = $data;
        $this->jkId = $jkId;
    }
    public function view(): View
    {
        $data = $this->data;
        $jkId = $this->jkId;
        return view('admin.mhs-laporan.excel-jumlah-mhs-bayar', compact('data', 'jkId'));
    }
}
