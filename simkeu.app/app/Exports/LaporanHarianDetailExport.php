<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanHarianDetailExport implements FromView, WithColumnWidths, WithStyles
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view("export.laporan-harian-detail", [
            "data" => $this->data,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            "A" => 5,
            "B" => 14,
            "C" => 14,
            "D" => 18,
            "E" => 18,
            "F" => 28,
            "G" => 8,
            "H" => 14,
            "I" => 32,
            "J" => 16,
            "K" => 16,
            "L" => 26,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ["font" => ["bold" => true]],
            2 => ["font" => ["bold" => true]],
            3 => ["font" => ["bold" => true]],
            6 => ["font" => ["bold" => true]],
            10 => ["font" => ["bold" => true]],
        ];
    }
}
