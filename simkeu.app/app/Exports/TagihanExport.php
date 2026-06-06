<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TagihanExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles
{
    public function __construct(
        private array $rows,
        private bool $includeNim = false
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headings = [
            'No',
            'Tahun Akademik',
            'Tahun Angkatan',
            'Prodi',
            'Double Degree',
            'Kelas',
            'Formulir',
            'Kode Tagihan',
            'Nama Tagihan',
            'Mata Uang ID',
            'Kode Mata Uang',
            'Simbol Mata Uang',
            'Jumlah',
        ];

        if ($this->includeNim) {
            array_splice($headings, 1, 0, ['NIM']);
        }

        return $headings;
    }

    public function columnFormats(): array
    {
        return [
            $this->includeNim ? 'N' : 'M' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                    'endColor' => ['rgb' => '1E3A8A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
