<?php

namespace App\Exports\RekapExportSheet;

use App\Exports\Concerns\AddsPimpinanSignature;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;

class RekapTahunan implements FromView, WithTitle, WithEvents
{
    use AddsPimpinanSignature;
    protected $tahun;
    protected $prodi;
    protected $tanggal;
    protected $jumlahBulan;

    public function __construct($tahun, $prodi, $tanggal, $jumlahBulan)
    {
        $this->tahun = $tahun;
        $this->prodi = $prodi;
        $this->tanggal = $tanggal;
        $this->jumlahBulan = $jumlahBulan;
    }

    public function view(): View
    {
        $tahun = $this->tahun;
        $prodi = $this->prodi;
        $tanggal = $this->tanggal;
        $jumlahBulan = $this->jumlahBulan;
        return view('admin.mhs-laporan.rekap.excel-tahunan', compact('tahun', 'prodi', 'tanggal', 'jumlahBulan'));
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Tahunan';
    }
}
