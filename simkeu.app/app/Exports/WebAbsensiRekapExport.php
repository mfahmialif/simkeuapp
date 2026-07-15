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
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class WebAbsensiRekapExport extends DefaultValueBinder implements
    FromCollection,
    ShouldAutoSize,
    WithHeadings,
    WithStyles,
    WithColumnFormatting
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = collect($this->data)->map(function ($item, $index) {
            $hari = is_array($item['rekap_per_kategori'] ?? null)
                ? array_reduce($item['rekap_per_kategori'], fn($acc, $kat) => $acc + (int) ($kat['jumlah'] ?? 0), 0)
                : 0;
            $jam = (float) ($item['total_jam_keseluruhan']['total_jam'] ?? 0);
            $barokah = (float) ($item['total_perolehan_dana'] ?? 0);

            return [
                $index + 1,
                $item['user']['name'] ?? $item['user']['nama'] ?? '-',
                $item['user']['departemen'] ?? '-',
                $item['jam_seharusnya_datang'] ?? '13:00:00',
                $item['jam_seharusnya_pulang'] ?? '19:00:00',
                $hari,
                $jam,
                $barokah,
            ];
        });

        $totalHari = $rows->sum(fn($r) => $r[5]);
        $totalJam = $rows->sum(fn($r) => $r[6]);
        $totalBarokah = $rows->sum(fn($r) => $r[7]);

        $rows->push([
            '',
            'TOTAL KESELURUHAN',
            '',
            '',
            '',
            $totalHari,
            $totalJam,
            $totalBarokah,
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            [
                'NO',
                'NAMA',
                'DEPARTEMEN',
                'JAM SEHARUSNYA DATANG',
                'JAM SEHARUSNYA PULANG',
                'TOTAL',
                '',
                '',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                'HARI',
                'JAM',
                'BAROKAH',
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0',
            'G' => '#,##0.00',
            'H' => '"Rp "#,##0_-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRows = count($this->data) + 3; // 2 rows headers + data rows + 1 summary row

        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');
        $sheet->mergeCells('E1:E2');
        $sheet->mergeCells('F1:H1');

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF198754'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ];

        $sheet->getStyle('A1:H2')->applyFromArray($headerStyle);

        // Data Borders
        $sheet->getStyle('A3:H' . $totalRows)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFE0E0E0'],
                ],
            ],
        ]);

        // Alignment
        $sheet->getStyle('A3:A' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C3:E' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F3:H' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Total Row Styling
        $sheet->mergeCells('B' . $totalRows . ':E' . $totalRows);
        $sheet->getStyle('A' . $totalRows . ':H' . $totalRows)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF0F8FF'],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(25);

        return [];
    }
}
