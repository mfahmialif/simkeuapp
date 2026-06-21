<?php

namespace Tests\Unit;

use App\Exports\GroupedRabDetailExport;
use Tests\TestCase;

class GroupedRabDetailExportTest extends TestCase
{
    public function test_it_builds_grouped_rab_table_like_the_requested_format(): void
    {
        $export = new GroupedRabDetailExport(
            'RENCANA ANGGARAN BIAYA (RAB)',
            'RUMAH TANGGA UNIVERSITAS ISLAM INTERNASIONAL',
            'Belanja Umum',
            '18 Juni 2026',
            [
                [
                    'kelompok_anggaran' => 'ATK',
                    'deskripsi' => 'Kertas A4',
                    'volume' => 4,
                    'satuan' => 'box',
                    'harga_satuan' => 235000,
                    'jumlah_harga' => 940000,
                ],
                [
                    'kelompok_anggaran' => 'ATK',
                    'deskripsi' => 'Tinta Epson',
                    'volume' => 9,
                    'satuan' => 'botol',
                    'harga_satuan' => 95000,
                    'jumlah_harga' => 855000,
                ],
                [
                    'kelompok_anggaran' => 'Konsumsi',
                    'deskripsi' => 'Air Mineral',
                    'volume' => 2,
                    'satuan' => 'dus',
                    'harga_satuan' => 50000,
                    'jumlah_harga' => 100000,
                ],
            ],
            1895000,
            'RAB'
        );

        $spreadsheet = $export->spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('No', $sheet->getCell('A15')->getValue());
        $this->assertSame('Uraian Kegiatan', $sheet->getCell('B15')->getValue());
        $this->assertSame('Harga Satuan (Rp)', $sheet->getCell('F15')->getValue());
        $this->assertSame('Jumlah Harga (Rp)', $sheet->getCell('H15')->getValue());
        $this->assertArrayHasKey('F15:G15', $sheet->getMergeCells());
        $this->assertArrayHasKey('H15:I15', $sheet->getMergeCells());
        $this->assertArrayHasKey('B16:B17', $sheet->getMergeCells());
        $this->assertSame('ATK', $sheet->getCell('B16')->getValue());
        $this->assertSame('Kertas A4', $sheet->getCell('C16')->getValue());
        $this->assertSame('Rp', $sheet->getCell('F16')->getValue());
        $this->assertSame(235000, $sheet->getCell('G16')->getValue());
        $this->assertSame('Total', $sheet->getCell('A19')->getValue());
        $this->assertArrayHasKey('A19:G19', $sheet->getMergeCells());
        $this->assertSame(1895000, $sheet->getCell('I19')->getValue());
        $this->assertSame('#,##0', $sheet->getStyle('I16')->getNumberFormat()->getFormatCode());

        $spreadsheet->disconnectWorksheets();
    }
}
