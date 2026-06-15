<?php

namespace App\Exports\RekapExportSheet;

use App\Exports\Concerns\AddsPimpinanSignature;
use App\Http\Services\Prodi;
use App\Models\MhsTransaksiTagihan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;

class RekapHarian implements FromView, WithTitle, WithEvents
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

        $tanggal = $this->tanggal;
        $prodi = $this->prodi;
        $tahun = $this->tahun;
        return view('admin.mhs-laporan.rekap.excel-harian', compact('tanggal', 'prodi', 'tahun'));
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Harian';
    }
}
