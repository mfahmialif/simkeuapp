<?php

namespace App\Exports;

use App\Exports\Concerns\AddsPimpinanSignature;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PemasukanUiiDalwaExport implements FromView, WithColumnWidths, WithStyles, WithEvents
{
    use AddsPimpinanSignature;
    public $data;
    public $totals;
    public $title;

    public function __construct($data, $totals, $title)
    {
        $this->data = $data;
        $this->totals = $totals;
        $this->title = $title;
    }

    public function view(): View
    {
        return view('export.pemasukan-uii-dalwa', [
            'data' => $this->data,
            'totals' => $this->totals,
            'title' => $this->title,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // NO
            'B' => 35,  // KATEGORI
            'C' => 20,  // TUNAI
            'D' => 20,  // TRANSFER
            'E' => 20,  // YAYASAN
            'F' => 20,  // TOTAL
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
            2    => ['font' => ['bold' => true]],
        ];
    }
}
