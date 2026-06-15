<?php

namespace App\Services;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class PimpinanExcelSignature
{
    public static function append(AfterSheet $event): void
    {
        $pimpinan = PimpinanSignatureService::active();
        $imagePath = PimpinanSignatureService::imagePath($pimpinan);

        if (! $pimpinan || ! $imagePath || ! is_file($imagePath)) {
            return;
        }

        $sheet = $event->sheet->getDelegate();
        $highestRow = max($sheet->getHighestDataRow(), 1);
        $highestColumnIndex = max(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 1);
        $startColumnIndex = max(1, $highestColumnIndex - 2);
        $startColumn = Coordinate::stringFromColumnIndex($startColumnIndex);
        $endColumn = Coordinate::stringFromColumnIndex($highestColumnIndex);
        $startRow = $highestRow + 3;

        foreach ([$startRow, $startRow + 1, $startRow + 7] as $row) {
            $sheet->mergeCells("{$startColumn}{$row}:{$endColumn}{$row}");
        }

        $sheet->setCellValue("{$startColumn}{$startRow}", 'Bangil, '.now()->format('d-m-Y'));
        $sheet->setCellValue("{$startColumn}".($startRow + 1), $pimpinan->jabatan);
        $sheet->setCellValue("{$startColumn}".($startRow + 7), $pimpinan->nama);
        $sheet->getStyle("{$startColumn}{$startRow}:{$endColumn}".($startRow + 7))
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("{$startColumn}".($startRow + 7))
            ->getFont()
            ->setBold(true)
            ->setUnderline(true);

        for ($row = $startRow + 2; $row <= $startRow + 6; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $drawing = new Drawing();
        $drawing->setName('Tanda Tangan Pimpinan');
        $drawing->setDescription('Tanda tangan pimpinan');
        $drawing->setPath($imagePath);
        $drawing->setHeight(70);
        $drawing->setCoordinates("{$startColumn}".($startRow + 2));
        $drawing->setOffsetX(18);
        $drawing->setWorksheet($sheet);
    }
}
