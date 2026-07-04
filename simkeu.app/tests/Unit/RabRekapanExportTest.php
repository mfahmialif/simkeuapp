<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Admin\Pengeluaran\RabController;
use ReflectionMethod;
use Tests\TestCase;

class RabRekapanExportTest extends TestCase
{
    public function test_rekapan_sheet_adds_category_column_and_merges_expected_columns(): void
    {
        $controller = new RabController;
        $method = new ReflectionMethod($controller, 'rekapanSpreadsheet');
        $method->setAccessible(true);

        $spreadsheet = $method->invoke($controller, 'BAROKAH ACARA JAMIAH JANUARI 2026', [
            [1, 'RAB 1', 'Acara A', '2026-01-02', '2026-01-05', 5000000, 5000000, 0, '', ''],
            [2, 'RAB 1', 'Acara B', '2026-01-03', '2026-01-05', 1500000, 1200000, 300000, '', ''],
            [3, 'RAB 2', 'Acara C', '2026-01-04', null, 700000, 500000, 200000, '', ''],
            [4, 'RAB 2', 'Acara D', '2026-01-05', null, 900000, 900000, 0, '', ''],
        ], [
            'rab' => 8100000,
            'laporan' => 7600000,
        ]);

        $sheet = $spreadsheet->getActiveSheet();
        $merges = $sheet->getMergeCells();

        $this->assertSame('NO', $sheet->getCell('A1')->getValue());
        $this->assertSame('KATEGORI', $sheet->getCell('B2')->getValue());
        $this->assertSame('NAMA ACARA', $sheet->getCell('C2')->getValue());
        $this->assertSame('TANGGAL PENCAIRAN', $sheet->getCell('E2')->getValue());
        $this->assertArrayHasKey('C1:J1', $merges);
        $this->assertArrayHasKey('B3:B4', $merges);
        $this->assertArrayHasKey('B5:B6', $merges);
        $this->assertArrayHasKey('E3:E4', $merges);
        $this->assertArrayNotHasKey('E5:E6', $merges);
        $this->assertSame('RAB 1', $sheet->getCell('B3')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A7')->getValue());
        $this->assertArrayHasKey('A7:E7', $merges);
        $this->assertSame(8100000, $sheet->getCell('F7')->getValue());
        $this->assertSame(7600000, $sheet->getCell('G7')->getValue());
        $this->assertSame(500000, $sheet->getCell('H7')->getValue());

        $spreadsheet->disconnectWorksheets();
    }
}
