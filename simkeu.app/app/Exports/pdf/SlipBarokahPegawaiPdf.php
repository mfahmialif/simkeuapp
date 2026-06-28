<?php

namespace App\Exports\pdf;

class SlipBarokahPegawaiPdf
{
    private const DETAIL_BOTTOM = 140;

    private const DETAIL_TOP = 34;

    public static function pdf(array $data)
    {
        $fpdf = new class('L', 'mm', 'A5') extends CustomKopFpdf
        {
            public function Footer() {}
        };

        $fpdf->SetAutoPageBreak(false);
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
        $rows = collect(data_get($data, 'rows', []));
        $total = (int) data_get($data, 'stats.total', 0);
        $isDosen = strtolower((string) data_get($pegawai, 'tipe')) === 'dosen';

        $fpdf->SetY(self::DETAIL_TOP);
        $fpdf->SetFont('Helvetica', 'B', 12);
        $fpdf->Cell(0, 6, 'SLIP BAROKAH', 0, 1, 'C');
        $fpdf->Ln(1);

        $fpdf->SetFont('Helvetica', '', 9);

        $leftWidth = 30;
        $colonWidth = 3;
        $rightWidth = 62;
        $rowHeight = 4.2;

        $fpdf->Cell($leftWidth, $rowHeight, 'Nama', 0, 0);
        $fpdf->Cell($colonWidth, $rowHeight, ':', 0, 0);
        $fpdf->Cell($rightWidth, $rowHeight, self::fitCellText($fpdf, (string) data_get($pegawai, 'nama', '-'), $rightWidth), 0, 0);

        $fpdf->Cell($leftWidth, $rowHeight, 'Periode', 0, 0);
        $fpdf->Cell($colonWidth, $rowHeight, ':', 0, 0);
        $fpdf->Cell($rightWidth, $rowHeight, (string) data_get($data, 'filters.label', '-'), 0, 1);

        $fpdf->Cell($leftWidth, $rowHeight, 'NIY', 0, 0);
        $fpdf->Cell($colonWidth, $rowHeight, ':', 0, 0);
        $fpdf->Cell($rightWidth, $rowHeight, (string) data_get($pegawai, 'kode', '-'), 0, 0);

        $fpdf->Cell($leftWidth, $rowHeight, $isDosen ? 'Prodi' : 'Unit', 0, 0);
        $fpdf->Cell($colonWidth, $rowHeight, ':', 0, 0);
        $fpdf->Cell($rightWidth, $rowHeight, self::fitCellText($fpdf, (string) data_get($pegawai, 'unit', '-'), $rightWidth), 0, 1);

        if (! $isDosen) {
            $fpdf->Cell($leftWidth, $rowHeight, 'Tipe', 0, 0);
            $fpdf->Cell($colonWidth, $rowHeight, ':', 0, 0);
            $fpdf->Cell($rightWidth, $rowHeight, ucfirst((string) data_get($pegawai, 'tipe', '-')), 0, 1);
        }

        $fpdf->Ln(2);

        $fpdf->SetFont('Helvetica', 'B', 10);
        $fpdf->Cell(95, 4.8, 'Rincian Barokah', 0, 0);
        $fpdf->Cell(0, 4.8, 'Total: Rp. '.number_format($total, 0, ',', '.'), 0, 1, 'R');

        $fpdf->Ln(1);

        if ($rows->isNotEmpty()) {
            self::listHeader($fpdf);

            foreach ($rows as $index => $row) {
                self::listRow($fpdf, $index + 1, [
                    (string) data_get($row, 'tanggal_label', '-'),
                    (string) data_get($row, 'deskripsi', '-'),
                    (string) data_get($row, 'detail_text', '-'),
                    'Rp. '.number_format((int) data_get($row, 'total', 0), 0, ',', '.'),
                ]);
            }
        } else {
            $fpdf->SetFont('Helvetica', 'I', 8);
            $fpdf->Cell(0, 5, 'Belum ada pengeluaran pada periode ini.', 0, 1);
        }

        self::finalBlock($fpdf, $total);
    }

    private static function listHeader(CustomKopFpdf $fpdf): void
    {
        if ($fpdf->GetY() + 5 > self::contentBottom($fpdf)) {
            $fpdf->AddPage();
            $fpdf->SetY(self::DETAIL_TOP);
        }

        $fpdf->SetFont('Helvetica', 'B', 7.5);

        $headers = ['No', 'Tanggal', 'Deskripsi', 'Rincian', 'Total'];
        $widths = self::listWidths();

        foreach ($headers as $index => $header) {
            $fpdf->Cell($widths[$index], 4.5, $header, 0, 0, $index === 4 ? 'R' : 'L');
        }

        $fpdf->Ln();
        $fpdf->Line(10, $fpdf->GetY(), 200, $fpdf->GetY());
        $fpdf->Ln(0.8);
        $fpdf->SetFont('Helvetica', '', 7.5);
    }

    private static function listRow(CustomKopFpdf $fpdf, int $number, array $values): void
    {
        $values = array_merge([(string) $number], $values);
        $widths = self::listWidths();
        $aligns = ['L', 'L', 'L', 'L', 'R'];
        $lineHeight = 3.3;
        $padding = 0.7;
        $lineCounts = [];

        foreach ($values as $index => $value) {
            if ($index === 4) {
                $lineCounts[] = 1;

                continue;
            }

            $lineCounts[] = self::lineCount($fpdf, (string) $value, $widths[$index] - ($padding * 2));
        }

        $height = max(5.5, (max($lineCounts) * $lineHeight) + ($padding * 2) + 1);
        self::checkPageBreak($fpdf, $height);

        $x = $fpdf->GetX();
        $y = $fpdf->GetY();

        foreach ($values as $index => $value) {
            $fpdf->SetXY($x + $padding, $y + $padding);

            if ($index === 4) {
                self::currencyListCell($fpdf, (string) $value, $widths[$index] - ($padding * 2), $lineHeight);
            } else {
                $fpdf->MultiCell($widths[$index] - ($padding * 2), $lineHeight, (string) $value, 0, $aligns[$index]);
            }

            $x += $widths[$index];
            $fpdf->SetXY($x, $y);
        }

        $fpdf->SetDrawColor(180, 180, 180);
        $fpdf->Line(10, $y + $height, 200, $y + $height);
        $fpdf->SetDrawColor(0, 0, 0);
        $fpdf->SetXY(10, $y + $height + 0.7);
    }

    private static function listWidths(): array
    {
        return [8, 25, 70, 62, 25];
    }

    private static function currencyListCell(CustomKopFpdf $fpdf, string $value, float $width, float $height): void
    {
        $amount = trim(preg_replace('/^Rp\.?\s*/i', '', $value) ?: $value);
        $labelWidth = 5.5;

        $fpdf->Cell($labelWidth, $height, 'Rp.', 0, 0, 'L');
        $fpdf->Cell($width - $labelWidth, $height, $amount, 0, 0, 'R');
    }

    private static function lineCount(CustomKopFpdf $fpdf, string $text, float $width): int
    {
        $text = trim($text);
        if ($text === '') {
            return 1;
        }

        $width = max(1, $width - (self::cellMargin($fpdf) * 2));
        $lines = 1;
        $current = '';

        foreach (preg_split('/\s+/', $text) as $word) {
            if ($fpdf->GetStringWidth($word) > $width) {
                if ($current !== '') {
                    $lines++;
                    $current = '';
                }

                $chunk = '';
                foreach (str_split($word) as $char) {
                    $candidate = $chunk.$char;
                    if ($chunk !== '' && $fpdf->GetStringWidth($candidate) > $width) {
                        $lines++;
                        $chunk = $char;

                        continue;
                    }

                    $chunk = $candidate;
                }

                $current = $chunk;

                continue;
            }

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

    private static function cellMargin(CustomKopFpdf $fpdf): float
    {
        static $property;

        if (! $property) {
            $property = new \ReflectionProperty(\Codedge\Fpdf\Fpdf\Fpdf::class, 'cMargin');
            $property->setAccessible(true);
        }

        return (float) $property->getValue($fpdf);
    }

    private static function checkPageBreak(CustomKopFpdf $fpdf, float $height): void
    {
        if ($fpdf->GetY() + $height > self::contentBottom($fpdf)) {
            $fpdf->AddPage();
            $fpdf->SetY(self::DETAIL_TOP);
            $fpdf->SetFont('Helvetica', 'B', 10);
            $fpdf->Cell(0, 4.8, 'Rincian Barokah (lanjutan)', 0, 1);
            $fpdf->Ln(1);
            self::listHeader($fpdf);
        }
    }

    private static function rowSalaryRp(CustomKopFpdf $fpdf, string $label, int $value): void
    {
        $fpdf->Cell(60, 4.2, $label, 0, 0);
        $fpdf->Cell(5, 4.2, ':', 0, 0);
        $fpdf->Cell(40, 4.2, 'Rp. '.number_format($value, 0, ',', '.'), 0, 1, 'R');
    }

    private static function finalBlock(CustomKopFpdf $fpdf, int $total): void
    {
        if ($fpdf->GetY() + 42 > self::contentBottom($fpdf)) {
            $fpdf->AddPage();
            $fpdf->SetY(self::DETAIL_TOP);
        }

        $fpdf->Ln(3);
        $fpdf->Line(10, $fpdf->GetY(), 200, $fpdf->GetY());
        $fpdf->Ln(2);

        $fpdf->SetFont('Helvetica', 'B', 9);
        $fpdf->Cell(40, 4.8, 'Total Diterima', 0, 0);
        $fpdf->Cell(3, 4.8, ':', 0, 0);
        $fpdf->Cell(40, 4.8, 'Rp. '.number_format($total, 0, ',', '.'), 0, 1, 'R');

        $fpdf->SetFont('Helvetica', 'I', 8);
        $fpdf->Cell(0, 4.3, 'Terbilang: '.ucfirst(self::terbilang($total)).' rupiah', 0, 1);

        self::signatureBlock($fpdf);
    }

    private static function signatureBlock(CustomKopFpdf $fpdf): void
    {
        $signer = \App\Services\PimpinanSignatureService::documentSigner(
            @\Auth::user()->name ?? 'TTD'
        );

        $fpdf->Ln(4);
        $fpdf->SetFont('Helvetica', '', 9);
        $fpdf->Cell(0, 4, 'Bangil, '.$fpdf->formatDate(\Carbon\Carbon::now()), 0, 1, 'R');

        $signatureY = $fpdf->GetY();
        if ($signer['pimpinan']) {
            \App\Services\PimpinanSignatureService::drawFpdf(
                $fpdf,
                $signer['pimpinan'],
                176,
                $signatureY,
                24,
                14
            );
        }

        $fpdf->SetY($signatureY + 15);
        $fpdf->SetFont('Helvetica', 'BU', 9);
        $fpdf->Cell(0, 4, $signer['nama'], 0, 1, 'R');

        $fpdf->SetFont('Helvetica', '', 8);
        $fpdf->Cell(0, 4, $signer['jabatan'] ?: '', 0, 1, 'R');
    }

    private static function terbilang($angka)
    {
        $angka = abs((int) $angka);

        if ($angka === 0) {
            return 'nol';
        }

        return trim(preg_replace('/\s+/', ' ', self::terbilangRecursive($angka)));
    }

    private static function terbilangRecursive(int $angka): string
    {
        $baca = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka === 0) {
            return '';
        }

        if ($angka < 12) {
            return $baca[$angka];
        }

        if ($angka < 20) {
            return self::terbilangRecursive($angka - 10).' belas';
        }

        if ($angka < 100) {
            return self::terbilangRecursive((int) ($angka / 10)).' puluh '.self::terbilangRecursive($angka % 10);
        }

        if ($angka < 200) {
            return 'seratus '.self::terbilangRecursive($angka - 100);
        }

        if ($angka < 1000) {
            return self::terbilangRecursive((int) ($angka / 100)).' ratus '.self::terbilangRecursive($angka % 100);
        }

        if ($angka < 2000) {
            return 'seribu '.self::terbilangRecursive($angka - 1000);
        }

        if ($angka < 1000000) {
            return self::terbilangRecursive((int) ($angka / 1000)).' ribu '.self::terbilangRecursive($angka % 1000);
        }

        if ($angka < 1000000000) {
            return self::terbilangRecursive((int) ($angka / 1000000)).' juta '.self::terbilangRecursive($angka % 1000000);
        }

        return (string) $angka;
    }

    private static function contentBottom(CustomKopFpdf $fpdf): float
    {
        return self::DETAIL_BOTTOM;
    }

    private static function fitCellText(CustomKopFpdf $fpdf, string $text, float $width): string
    {
        $text = trim($text) ?: '-';

        if ($fpdf->GetStringWidth($text) <= $width) {
            return $text;
        }

        while ($text !== '' && $fpdf->GetStringWidth($text.'...') > $width) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text).'...';
    }

    private static function safeFileName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: 'pegawai';

        return trim($value, '-');
    }
}
