<?php

namespace App\Exports\pdf;

class SlipBarokahPegawaiPdf
{
    public static function pdf(array $data)
    {
        $fpdf = new CustomKopFpdf('L', 'mm', 'A5');
        $fpdf->SetAutoPageBreak(true, 52);
        $fpdf->AddPage();
        $fpdf->setDataSign([
            'nama' => @\Auth::user()->name ?? 'TTD',
        ]);

        self::body($data, $fpdf);

        $binary = $fpdf->Output('S');
        $fileName = 'slip-barokah-'.self::safeFileName((string) (data_get($data, 'pegawai.nama') ?: 'pegawai')).'.pdf';

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$fileName.'"');
    }

    public static function body(array $data, CustomKopFpdf $fpdf): void
    {
        $pegawai = data_get($data, 'pegawai', []);
        $modules = collect(data_get($data, 'modules', []));
        $rows = collect(data_get($data, 'rows', []));
        $total = (int) data_get($data, 'stats.total', 0);

        $fpdf->SetFont('Helvetica', 'B', 12);
        $fpdf->Cell(0, 8, '', 0, 1, 'C');
        $fpdf->Cell(0, 8, 'SLIP BAROKAH', 0, 1, 'C');
        $fpdf->Ln(3);

        $fpdf->SetFont('Helvetica', '', 9);

        $leftWidth = 35;
        $colonWidth = 3;
        $rightWidth = 60;

        $fpdf->Cell($leftWidth, 5, 'Nama', 0, 0);
        $fpdf->Cell($colonWidth, 5, ':', 0, 0);
        $fpdf->Cell($rightWidth, 5, (string) data_get($pegawai, 'nama', '-'), 0, 0);

        $fpdf->Cell($leftWidth, 5, 'Periode', 0, 0);
        $fpdf->Cell($colonWidth, 5, ':', 0, 0);
        $fpdf->Cell($rightWidth, 5, (string) data_get($data, 'filters.label', '-'), 0, 1);

        $fpdf->Cell($leftWidth, 5, 'NIY', 0, 0);
        $fpdf->Cell($colonWidth, 5, ':', 0, 0);
        $fpdf->Cell($rightWidth, 5, (string) data_get($pegawai, 'kode', '-'), 0, 0);

        $fpdf->Cell($leftWidth, 5, 'Unit', 0, 0);
        $fpdf->Cell($colonWidth, 5, ':', 0, 0);
        $fpdf->Cell($rightWidth, 5, (string) data_get($pegawai, 'unit', '-'), 0, 1);

        $fpdf->Cell($leftWidth, 5, 'Tipe', 0, 0);
        $fpdf->Cell($colonWidth, 5, ':', 0, 0);
        $fpdf->Cell($rightWidth, 5, ucfirst((string) data_get($pegawai, 'tipe', '-')), 0, 1);

        $fpdf->Ln(4);

        $fpdf->SetFont('Helvetica', 'B', 10);
        $fpdf->Cell(0, 6, 'Rincian Barokah', 0, 1);

        $fpdf->SetFont('Helvetica', '', 9);
        foreach ($modules as $module) {
            self::rowSalaryRp(
                $fpdf,
                (string) data_get($module, 'short_label', data_get($module, 'label', '-')),
                (int) data_get($module, 'total', 0)
            );
        }

        $fpdf->Cell(105, 1, '', 'B', 1);
        $fpdf->Ln(2);

        if ($rows->isNotEmpty()) {
            self::tableHeader($fpdf);
            foreach ($rows as $row) {
                self::tableRow($fpdf, [
                    (string) data_get($row, 'tanggal_label', '-'),
                    (string) data_get($row, 'module_label', '-'),
                    (string) data_get($row, 'deskripsi', '-'),
                    (string) data_get($row, 'detail_text', '-'),
                    'Rp. '.number_format((int) data_get($row, 'total', 0), 0, ',', '.'),
                ]);
            }
        } else {
            $fpdf->SetFont('Helvetica', 'I', 9);
            $fpdf->Cell(0, 6, 'Belum ada pengeluaran pada periode ini.', 0, 1);
        }

        if ($fpdf->GetY() + 25 > 94) {
            $fpdf->AddPage();
            $fpdf->SetY(38);
        }

        $fpdf->Ln(4);
        $fpdf->SetFont('Helvetica', 'B', 9);
        self::rowSalaryRp($fpdf, 'Total Diterima', $total);

        $fpdf->Ln(4);
        $fpdf->SetFont('Helvetica', 'B', 12);
        $fpdf->Cell(0, 6, 'Penerimaan Barokah: Rp. '.number_format($total, 0, ',', '.'), 1, 1, 'C');

        $fpdf->SetFont('Helvetica', 'I', 10);
        $fpdf->Cell(0, 5, 'Terbilang: '.ucfirst(self::terbilang($total)).' rupiah', 1, 1, 'C');
        $fpdf->Ln(8);
    }

    private static function tableHeader(CustomKopFpdf $fpdf): void
    {
        if ($fpdf->GetY() + 8 > 94) {
            $fpdf->AddPage();
            $fpdf->SetY(38);
        }

        $fpdf->SetFont('Helvetica', 'B', 8);

        $headers = ['Tanggal', 'Modul', 'Deskripsi', 'Rincian', 'Total'];
        $widths = self::tableWidths();

        foreach ($headers as $index => $header) {
            $fpdf->Cell($widths[$index], 6, $header, 1, 0, $index === 4 ? 'R' : 'L');
        }

        $fpdf->Ln();
        $fpdf->SetFont('Helvetica', '', 8);
    }

    private static function tableRow(CustomKopFpdf $fpdf, array $values): void
    {
        $widths = self::tableWidths();
        $aligns = ['L', 'L', 'L', 'L', 'R'];
        $lineHeight = 4;
        $lineCounts = [];

        foreach ($values as $index => $value) {
            $lineCounts[] = self::lineCount($fpdf, (string) $value, $widths[$index] - 2);
        }

        $height = max($lineCounts) * $lineHeight;
        self::checkPageBreak($fpdf, $height);

        $x = $fpdf->GetX();
        $y = $fpdf->GetY();

        foreach ($values as $index => $value) {
            $fpdf->Rect($x, $y, $widths[$index], $height);
            $fpdf->SetXY($x + 1, $y + 1);
            $fpdf->MultiCell($widths[$index] - 2, $lineHeight, (string) $value, 0, $aligns[$index]);
            $x += $widths[$index];
            $fpdf->SetXY($x, $y);
        }

        $fpdf->SetXY(10, $y + $height);
    }

    private static function tableWidths(): array
    {
        return [24, 24, 50, 67, 25];
    }

    private static function lineCount(CustomKopFpdf $fpdf, string $text, float $width): int
    {
        $text = trim($text);
        if ($text === '') {
            return 1;
        }

        $lines = 1;
        $current = '';

        foreach (preg_split('/\s+/', $text) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($fpdf->GetStringWidth($candidate) <= $width) {
                $current = $candidate;
                continue;
            }

            $lines++;
            $current = $word;
        }

        return $lines;
    }

    private static function checkPageBreak(CustomKopFpdf $fpdf, float $height): void
    {
        if ($fpdf->GetY() + $height > 94) {
            $fpdf->AddPage();
            $fpdf->SetY(38);
            self::tableHeader($fpdf);
        }
    }

    private static function rowSalaryRp(CustomKopFpdf $fpdf, string $label, int $value): void
    {
        $fpdf->Cell(60, 6, $label, 0, 0);
        $fpdf->Cell(5, 6, ':', 0, 0);
        $fpdf->Cell(40, 6, 'Rp. '.number_format($value, 0, ',', '.'), 0, 1, 'R');
    }

    private static function terbilang($angka)
    {
        $angka = abs((int) $angka);
        $baca = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka === 0) {
            return 'nol';
        }

        if ($angka < 12) {
            return $baca[$angka];
        }

        if ($angka < 20) {
            return self::terbilang($angka - 10).' belas';
        }

        if ($angka < 100) {
            return self::terbilang((int) ($angka / 10)).' puluh '.self::terbilang($angka % 10);
        }

        if ($angka < 200) {
            return 'seratus '.self::terbilang($angka - 100);
        }

        if ($angka < 1000) {
            return self::terbilang((int) ($angka / 100)).' ratus '.self::terbilang($angka % 100);
        }

        if ($angka < 2000) {
            return 'seribu '.self::terbilang($angka - 1000);
        }

        if ($angka < 1000000) {
            return self::terbilang((int) ($angka / 1000)).' ribu '.self::terbilang($angka % 1000);
        }

        if ($angka < 1000000000) {
            return self::terbilang((int) ($angka / 1000000)).' juta '.self::terbilang($angka % 1000000);
        }

        return (string) $angka;
    }

    private static function safeFileName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: 'pegawai';

        return trim($value, '-');
    }
}
