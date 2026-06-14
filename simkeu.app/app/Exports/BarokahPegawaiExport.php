<?php

namespace App\Exports;

use App\Exports\BarokahPegawaiExportSheet\BarokahPegawaiSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BarokahPegawaiExport implements WithMultipleSheets
{
    public function __construct(private readonly array $sheetData)
    {
    }

    public function sheets(): array
    {
        return array_map(
            fn (array $sheet) => new BarokahPegawaiSheet(
                $sheet['title'],
                $sheet['headings'],
                $sheet['rows']
            ),
            $this->sheetData
        );
    }
}
