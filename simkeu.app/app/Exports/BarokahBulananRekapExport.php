<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BarokahBulananRekapExport extends DefaultValueBinder implements
    FromArray,
    WithColumnFormatting,
    WithColumnWidths,
    WithCustomValueBinder,
    WithDrawings,
    WithEvents,
    IValueBinder
{
    private const HEADER_ROW = 14;
    private const FIRST_DATA_ROW = 15;

    private string $title;

    private array $headings;

    private array $rows;

    private array $amountColumns;

    private ?array $totalRow;

    private array $textColumns;

    public function __construct(
        string $title,
        array $headings,
        array $rows,
        array $amountColumns = [],
        ?array $totalRow = null,
        array $textColumns = []
    ) {
        $this->title = $title;
        $this->headings = array_values($headings);
        $this->rows = array_values($rows);
        $this->amountColumns = array_values($amountColumns);
        $this->totalRow = $totalRow === null ? null : array_values($totalRow);
        $this->textColumns = array_values($textColumns);
    }

    public function array(): array
    {
        $blank = array_fill(0, $this->columnCount(), '');
        $rows = array_fill(0, 12, $blank);

        $titleRow = $blank;
        $titleRow[2] = $this->title;
        $rows[] = $titleRow;

        $rows[] = $this->normalizeRow($this->headings);

        foreach ($this->rows as $row) {
            $rows[] = $this->normalizeRow($row);
        }

        if ($this->totalRow !== null) {
            $rows[] = $this->normalizeRow($this->totalRow);
        }

        return $rows;
    }

    public function drawings()
    {
        $path = public_path('img/kop uiidalwa mantap.png');

        if (! is_file($path)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('B1');
        $drawing->setCoordinates2('J12');
        $drawing->setEditAs(BaseDrawing::EDIT_AS_ONECELL);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(957);
        $drawing->setHeight(213);

        return [$drawing];
    }

    public function columnWidths(): array
    {
        $defaults = [
            'A' => 8.78,
            'B' => 5,
            'C' => 57.78,
            'D' => 19.78,
            'E' => 25.66,
            'F' => 24,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 24,
            'K' => 24,
        ];

        $widths = [];
        for ($index = 1; $index <= $this->lastColumnIndex(); $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $widths[$column] = $defaults[$column] ?? 18;
        }

        return $widths;
    }

    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->amountColumns as $columnNumber) {
            $column = Coordinate::stringFromColumnIndex($columnNumber + 1);
            $formats[$column] = '_-"Rp"* #,##0_-;_-"Rp"* -#,##0_-;_-"Rp"* "-"_-;_-@_-';
        }

        foreach ($this->textColumns as $columnNumber) {
            $column = Coordinate::stringFromColumnIndex($columnNumber + 1);
            $formats[$column] = '@';
        }

        return $formats;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (
            $cell->getRow() >= self::FIRST_DATA_ROW
            && in_array($cell->getColumn(), $this->textColumnLetters(), true)
        ) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex($this->lastColumnIndex());
                $lastRow = $sheet->getHighestRow();

                for ($row = 1; $row <= 12; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(15);
                }

                $sheet->getStyle('C13')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('C13')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $headerRange = 'B'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW;
                $sheet->getStyle($headerRange)->getFont()->setBold(true);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                if ($lastRow >= self::HEADER_ROW) {
                    $tableRange = 'B'.self::HEADER_ROW.':'.$lastColumn.$lastRow;
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('000000');
                    $sheet->getStyle($tableRange)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                }

                if ($lastRow >= self::FIRST_DATA_ROW) {
                    $sheet->getStyle('B'.self::FIRST_DATA_ROW.':B'.$lastRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                if ($this->totalRow !== null) {
                    $totalRow = self::FIRST_DATA_ROW + count($this->rows);
                    $sheet->getStyle('B'.$totalRow.':'.$lastColumn.$totalRow)
                        ->getFont()->setBold(true);
                }

                $sheet->setTopLeftCell('A1');
                $sheet->setSelectedCell('A1');
            },
        ];
    }

    private function normalizeRow(array $row): array
    {
        $normalized = array_merge([''], array_values($row));

        return array_pad($normalized, $this->columnCount(), '');
    }

    private function columnCount(): int
    {
        return count($this->headings) + 1;
    }

    private function lastColumnIndex(): int
    {
        return $this->columnCount();
    }

    private function textColumnLetters(): array
    {
        return array_map(
            fn ($columnNumber) => Coordinate::stringFromColumnIndex($columnNumber + 1),
            $this->textColumns
        );
    }
}
