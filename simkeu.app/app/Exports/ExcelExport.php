<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class ExcelExport extends DefaultValueBinder implements
    FromCollection,
    ShouldAutoSize,
    WithHeadings,
    WithStyles,
    WithCustomValueBinder,
    WithColumnFormatting,
    IValueBinder
{

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->data->values()->map(function ($item, $index) {
            $row = $item->toArray();
            // Sisipkan 'nomer' di awal
            return array_merge(['no' => $index + 1], $row);
        });
    }

    public function headings(): array
    {
        $header = [];

        $firstRow = $this->collection()[0] ?? [];

        foreach (array_keys($firstRow) as $key) {
            $header[] = strtoupper(str_replace('_', ' ', $key));
        }

        return $header;
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'], // biru tua
                    'endColor'   => ['rgb' => '1E3A8A'], // wajib di Excel 4.x agar tidak fallback jadi putih
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'alignment' => ['wrapText' => true],
                ],
            ],
        ];

        return $styles;
    }


    public function columnFormats(): array
    {
        return [
            'H' => '"Rp" #,##0',
            'I' => '"Rp" #,##0',
            'J' => '"Rp" #,##0',
        ];
    }


    // public function bindValue(Cell $cell, $value): bool
    // {
    //     $stringColumn = ["E", "F", "I"];

    //     if (in_array($cell->getColumn(), $stringColumn)) {
    //         $cell->setValueExplicit($value, DataType::TYPE_STRING);
    //         return true;
    //     }

    //     return parent::bindValue($cell, $value);
    // }
}
