<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BsiPayrollExport extends DefaultValueBinder implements
    FromArray,
    WithColumnFormatting,
    WithColumnWidths,
    WithCustomValueBinder,
    WithEvents,
    WithStrictNullComparison,
    WithStyles,
    IValueBinder
{
    private const HEADINGS = [
        'NO',
        'BENEFICIARY ACCT (35)',
        'BENEFICIARY ACCT NAME',
        'CREDIT AMOUNT CCY',
        'AMOUNT',
        'CUST REF NO',
        'MESSAGE (65)',
        'EXTENDED PAYMENT DETAIL',
        'BENEFICIARY NOTIF EMAIL',
        'SMS NOTIF (100)',
    ];

    private Collection $rows;

    public function __construct($rows, private readonly string $message = 'barokah mengajar')
    {
        $this->rows = collect($rows)->values();
    }

    public function array(): array
    {
        $data = [
            ['BATCH PAYMENT PAYROLL - DATA INPUT', '', '', '', '', '', '', '', '', ''],
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            self::HEADINGS,
        ];

        foreach ($this->payrollRows(true) as $row) {
            $data[] = $row;
        }

        return $data;
    }

    public function clipboardText(): string
    {
        return $this->payrollRows(false)
            ->map(fn (array $row) => implode("\t", array_map(fn ($value) => $this->clipboardValue($value), $row)))
            ->implode("\r\n");
    }

    private function payrollRows(bool $withNumber): Collection
    {
        return $this->rows->map(function ($row, int $index) use ($withNumber) {
            $accountName = $this->value($row, 'beneficiary_acct_name')
                ?: $this->value($row, 'nama_pemilik_rekening')
                ?: $this->value($row, 'nama_pegawai')
                ?: $this->value($row, 'nama_dosen');

            $data = [
                (string) ($this->value($row, 'beneficiary_acct') ?: $this->value($row, 'nomer_rekening')),
                (string) $accountName,
                'IDR',
                (int) $this->value($row, 'amount'),
                '',
                (string) ($this->value($row, 'message') ?: $this->message),
                '',
                '',
                '',
            ];

            return $withNumber ? array_merge([$index + 1], $data) : $data;
        });
    }

    private function clipboardValue($value): string
    {
        return str_replace(["\t", "\r", "\n"], ' ', (string) $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                ],
            ],
            2 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E2F3'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            3 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '000000'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '9CCDD8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            'B3' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']]],
            'D3' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']]],
            'E3' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 34,
            'D' => 20,
            'E' => 16,
            'F' => 26,
            'G' => 20,
            'H' => 28,
            'I' => 24,
            'J' => 20,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:J1');
                $sheet->freezePane('A4');
                $sheet->getRowDimension(3)->setRowHeight(24);

                $sheet->getStyle("A2:J{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:J{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                if ($highestRow >= 4) {
                    $sheet->getStyle("A4:A{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D4:D{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("E4:E{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['B', 'D'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function value($row, string $key)
    {
        return data_get($row, $key);
    }
}
