<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PemasukanTunaiHarianBulananExport implements FromView, WithColumnWidths, WithStyles
{
    public $columns;
    public $data;
    public $totals;
    public $title;

    public function __construct($columns, $data, $totals, $title)
    {
        $this->columns = $columns;
        $this->data = $data;
        $this->totals = $totals;
        $this->title = $title;
    }

    public function view(): View
    {
        return view('export.pemasukan-tunai-harian-bulanan', [
            'columns' => $this->columns,
            'data' => $this->data,
            'totals' => $this->totals,
            'title' => $this->title,
        ]);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,   // No
            'B' => 12,  // Tanggal
        ];
        
        $currentCol = 'C';
        foreach ($this->columns as $col) {
            $labelLen = strlen($col['label']) + 4; // padding
            $widths[$currentCol] = $labelLen < 16 ? 16 : $labelLen;
            $currentCol++;
        }
        
        $widths[$currentCol] = 20; // Jumlah
        
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
            2    => ['font' => ['bold' => true]],
        ];
    }
}
