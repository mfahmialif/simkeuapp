<?php

namespace App\Exports\BarokahPegawaiExportSheet;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BarokahPegawaiSheet extends DefaultValueBinder implements
    FromArray,
    WithCustomValueBinder,
    WithEvents,
    WithHeadings,
    WithStyles,
    WithTitle,
    IValueBinder
{
    private const TEXT_HEADINGS = [
        'Kode',
        'Jenis Pembayaran',
        'Keterangan',
        'Keterangan Sempro',
    ];

    private const MONEY_KEYWORDS = [
        'Transport',
        'Barokah',
        'Nominal',
        'Total',
    ];

    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $sheetHeadings,
        private readonly array $sheetRows
    ) {
    }

    public function array(): array
    {
        return $this->sheetRows;
    }

    public function headings(): array
    {
        return $this->sheetHeadings;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', $this->sheetTitle), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '334155'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(max(count($this->sheetHeadings), 1));
                $lastRow = max(count($this->sheetRows) + 1, 1);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);

                foreach ($this->sheetHeadings as $index => $heading) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $dimension = $sheet->getColumnDimension($column);

                    $dimension->setAutoSize(false);
                    $dimension->setWidth($this->columnWidth($heading));

                    if ($this->isMoneyHeading($heading) && $lastRow > 1) {
                        $sheet->getStyle("{$column}2:{$column}{$lastRow}")
                            ->getNumberFormat()
                            ->setFormatCode('"Rp" #,##0');
                    }
                }
            },
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        $heading = $this->sheetHeadings[Coordinate::columnIndexFromString($cell->getColumn()) - 1] ?? null;

        if (in_array($heading, self::TEXT_HEADINGS, true) && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function isMoneyHeading(string $heading): bool
    {
        if (str_starts_with($heading, 'Hari Transport')) {
            return false;
        }

        foreach (self::MONEY_KEYWORDS as $keyword) {
            if (str_contains($heading, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function columnWidth(string $heading): int
    {
        if ($heading === 'No') {
            return 7;
        }

        if ($heading === 'Tanggal') {
            return 14;
        }

        if (in_array($heading, ['Nama Pegawai', 'Nama / Keterangan', 'Nama Kegiatan'], true)) {
            return 28;
        }

        if (in_array($heading, ['Keterangan', 'Keterangan Sempro'], true)) {
            return 34;
        }

        if (in_array($heading, ['Kode', 'Tipe', 'Periode', 'Jenis Pembayaran'], true)) {
            return 16;
        }

        if ($this->isMoneyHeading($heading)) {
            return 19;
        }

        return 17;
    }
}
