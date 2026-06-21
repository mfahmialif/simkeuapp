<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupedRabDetailExport
{
    public function __construct(
        private readonly string $title,
        private readonly string $organizationName,
        private readonly string $rekapName,
        private readonly string $period,
        private readonly array $rows,
        private readonly int $total,
        private readonly string $sheetTitle
    ) {}

    public function spreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle);
        $sheet->setShowGridlines(true);

        $this->addKopDrawing($sheet);
        $this->applyColumnWidths($sheet);

        for ($row = 1; $row <= 8; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(23);
        }

        $sheet->mergeCells('A9:I9');
        $sheet->mergeCells('A10:I10');
        $sheet->mergeCells('A11:I11');
        $sheet->setCellValue('A9', $this->title);
        $sheet->setCellValue('A10', $this->organizationName);
        $sheet->setCellValue('A11', "DARULLUGHA WADDA'WAH");

        $sheet->setCellValue('A12', 'Nama Rekap');
        $sheet->setCellValue('B12', ':');
        $sheet->setCellValue('C12', $this->rekapName);
        $sheet->setCellValue('A13', 'Periode');
        $sheet->setCellValue('B13', ':');
        $sheet->setCellValue('C13', $this->period);

        $headerRow = 15;
        $firstDataRow = 16;
        $rowCount = max(count($this->rows), 1);
        $lastDataRow = $firstDataRow + $rowCount - 1;
        $totalRow = $lastDataRow + 1;

        $sheet->setCellValue("A{$headerRow}", 'No');
        $sheet->setCellValue("B{$headerRow}", 'Uraian Kegiatan');
        $sheet->setCellValue("C{$headerRow}", 'Deskripsi');
        $sheet->setCellValue("D{$headerRow}", 'Vol');
        $sheet->setCellValue("E{$headerRow}", 'Satuan');
        $sheet->mergeCells("F{$headerRow}:G{$headerRow}");
        $sheet->setCellValue("F{$headerRow}", 'Harga Satuan (Rp)');
        $sheet->mergeCells("H{$headerRow}:I{$headerRow}");
        $sheet->setCellValue("H{$headerRow}", 'Jumlah Harga (Rp)');

        if ($this->rows === []) {
            $sheet->setCellValue("A{$firstDataRow}", 1);
            $sheet->setCellValue("B{$firstDataRow}", '-');
            $sheet->setCellValue("C{$firstDataRow}", 'Tidak ada data');
            $sheet->setCellValue("F{$firstDataRow}", 'Rp');
            $sheet->setCellValue("G{$firstDataRow}", 0);
            $sheet->setCellValue("H{$firstDataRow}", 'Rp');
            $sheet->setCellValue("I{$firstDataRow}", 0);
        } else {
            $groups = [];
            $currentGroup = null;

            foreach ($this->rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $group = $rowData['kelompok_anggaran'] ?: '-';

                if ($currentGroup === null || $currentGroup['label'] !== $group) {
                    if ($currentGroup !== null) {
                        $groups[] = $currentGroup;
                    }

                    $currentGroup = [
                        'label' => $group,
                        'start' => $rowNumber,
                        'end' => $rowNumber,
                    ];
                } else {
                    $currentGroup['end'] = $rowNumber;
                }

                $sheet->setCellValue("A{$rowNumber}", $index + 1);
                $sheet->setCellValue("C{$rowNumber}", $rowData['deskripsi']);
                $sheet->setCellValue("D{$rowNumber}", $rowData['volume']);
                $sheet->setCellValue("E{$rowNumber}", $rowData['satuan']);
                $sheet->setCellValue("F{$rowNumber}", 'Rp');
                $sheet->setCellValue("G{$rowNumber}", $rowData['harga_satuan']);
                $sheet->setCellValue("H{$rowNumber}", 'Rp');
                $sheet->setCellValue("I{$rowNumber}", $rowData['jumlah_harga']);
                $sheet->getRowDimension($rowNumber)->setRowHeight(24);
            }

            if ($currentGroup !== null) {
                $groups[] = $currentGroup;
            }

            foreach ($groups as $group) {
                if ($group['start'] < $group['end']) {
                    $sheet->mergeCells("B{$group['start']}:B{$group['end']}");
                }

                $sheet->setCellValue("B{$group['start']}", $group['label']);
            }
        }

        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total');
        $sheet->setCellValue("H{$totalRow}", 'Rp');
        $sheet->setCellValue("I{$totalRow}", $this->total);

        $tableRange = "A{$headerRow}:I{$totalRow}";
        $headerRange = "A{$headerRow}:I{$headerRow}";

        $sheet->getStyle('A9:I11')->getFont()
            ->setName('Times New Roman')
            ->setBold(true)
            ->setSize(13);
        $sheet->getStyle('A9:I11')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A12:C13')->getFont()
            ->setName('Times New Roman')
            ->setSize(12);

        $sheet->getStyle($tableRange)->getFont()
            ->setName('Times New Roman')
            ->setSize(12);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($tableRange)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$firstDataRow}:B{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$firstDataRow}:F{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H{$firstDataRow}:H{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$firstDataRow}:G{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("I{$firstDataRow}:I{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("G{$firstDataRow}:G{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');
        $sheet->getStyle("I{$firstDataRow}:I{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');

        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function addKopDrawing(Worksheet $sheet): void
    {
        $paths = [
            public_path('img/kop uiidalwa mantap.png'),
            base_path('../public_html/img/kop uiidalwa mantap.png'),
            base_path('public_html/img/kop uiidalwa mantap.png'),
        ];

        $path = collect($paths)->first(fn ($candidate) => is_file($candidate));

        if (! $path) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(6);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(805);
        $drawing->setHeight(172);
        $drawing->setWorksheet($sheet);
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 6,
            'B' => 24,
            'C' => 32,
            'D' => 7,
            'E' => 10,
            'F' => 5,
            'G' => 15,
            'H' => 5,
            'I' => 17,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
