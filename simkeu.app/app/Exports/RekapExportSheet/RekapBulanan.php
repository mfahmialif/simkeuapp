<?php

namespace App\Exports\RekapExportSheet;

use App\Exports\Concerns\AddsPimpinanSignature;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;

class RekapBulanan implements FromView, WithTitle, WithEvents
{
    use AddsPimpinanSignature;
    protected $tahun;
    protected $prodi;
    protected $tanggal;

    public function __construct($tahun, $prodi, $tanggal)
    {
        $this->tahun = $tahun;
        $this->prodi = $prodi;
        $this->tanggal = $tanggal;
    }

    public function view(): View
    {
        $tahun = $this->tahun;
        $prodi = $this->prodi;
        $tanggal = $this->tanggal;
        return view('admin.mhs-laporan.rekap.excel-bulanan', compact('tahun','prodi','tanggal'));
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Bulanan';
    }
}
