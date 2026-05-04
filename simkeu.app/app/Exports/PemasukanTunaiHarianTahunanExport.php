<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PemasukanTunaiHarianTahunanExport implements FromView, WithColumnWidths, WithStyles
{
    public $columns;
    public $allData;
    public $year;

    public function __construct($columns, $allData, $year)
    {
        $this->columns = $columns;
        $this->allData = $allData; // Array of 12 months data
        $this->year = $year;
    }

    public function view(): View
    {
        return view('export.pemasukan-tunai-harian-tahunan', [
            'columns' => $this->columns,
            'allData' => $this->allData,
            'year' => $this->year,
        ]);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,   // Space / Month Name
            'B' => 5,   // No
            'C' => 12,  // Tanggal
        ];
        
        $currentCol = 'D';
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
            // General styles can be applied via Blade inline styles
        ];
    }
}
