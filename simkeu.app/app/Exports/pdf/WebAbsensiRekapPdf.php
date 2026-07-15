<?php

namespace App\Exports\pdf;

use App\Exports\pdf\CustomFpdf;

class WebAbsensiRekapPdf
{
    private static function fit($fpdf, $text, $width)
    {
        $text = (string) ($text ?? "-");
        if ($fpdf->GetStringWidth($text) <= $width - 3) {
            return $text;
        }

        while (strlen($text) > 3 && $fpdf->GetStringWidth($text . "...") > $width - 3) {
            $text = substr($text, 0, -1);
        }

        return $text . "...";
    }

    private static function formatRupiah($angka)
    {
        if (!$angka || $angka <= 0) {
            return "-";
        }
        return "Rp " . number_format($angka, 0, ',', '.');
    }

    private static function header($fpdf, $periode)
    {
        $fpdf->SetFillColor(25, 135, 84); // Hijau elegan
        $fpdf->Rect(10, 8, 277, 22, "F");

        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont("Arial", "B", 13);
        $fpdf->SetXY(14, 11);
        $fpdf->Cell(150, 6, "REKAP TOTAL WEB ABSENSI", 0, 1, "L");

        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, "L");
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "Sistem Informasi Keuangan (SIMKEU) - Ringkasan Total Absensi & Barokah", 0, 1, "L");

        $periodeInfo = "-";
        if (!empty($periode['mode']) && $periode['mode'] === 'bulan_tahun') {
            $months = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            $b = (int) ($periode['bulan'] ?? date('n'));
            $t = $periode['tahun'] ?? date('Y');
            $periodeInfo = "Bulan " . ($months[$b] ?? $b) . " " . $t;
        } elseif (!empty($periode['start_date']) && !empty($periode['end_date'])) {
            $periodeInfo = date('d/m/Y', strtotime($periode['start_date'])) . " s/d " . date('d/m/Y', strtotime($periode['end_date']));
        }

        $fpdf->SetFont("Arial", "B", 8);
        $fpdf->SetXY(218, 12);
        $fpdf->Cell(64, 5, "Periode Laporan", 0, 1, "R");
        $fpdf->SetFont("Arial", "", 10);
        $fpdf->SetX(218);
        $fpdf->Cell(64, 7, $periodeInfo, 0, 1, "R");

        $fpdf->SetTextColor(15, 23, 42);
        $fpdf->SetXY(10, 32);
    }

    private static function tableHeader($fpdf, $widths)
    {
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->SetDrawColor(203, 213, 225);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->SetFont("Arial", "B", 7.5);

        // Row 1
        $fpdf->SetX(10);
        // Cell spanning 2 rows drawn using X/Y manipulation
        $startX = $fpdf->GetX();
        $startY = $fpdf->GetY();

        // NO
        $fpdf->Rect($startX, $startY, $widths[0], 12, "DF");
        $fpdf->SetXY($startX, $startY + 3);
        $fpdf->Cell($widths[0], 6, "NO", 0, 0, "C");

        // NAMA
        $startX += $widths[0];
        $fpdf->Rect($startX, $startY, $widths[1], 12, "DF");
        $fpdf->SetXY($startX, $startY + 3);
        $fpdf->Cell($widths[1], 6, "NAMA", 0, 0, "C");

        // DEPARTEMEN
        $startX += $widths[1];
        $fpdf->Rect($startX, $startY, $widths[2], 12, "DF");
        $fpdf->SetXY($startX, $startY + 3);
        $fpdf->Cell($widths[2], 6, "DEPARTEMEN", 0, 0, "C");

        // JAM DATANG
        $startX += $widths[2];
        $fpdf->Rect($startX, $startY, $widths[3], 12, "DF");
        $fpdf->SetXY($startX, $startY + 3);
        $fpdf->Cell($widths[3], 6, "JAM SEHARUSNYA DATANG", 0, 0, "C");

        // JAM PULANG
        $startX += $widths[3];
        $fpdf->Rect($startX, $startY, $widths[4], 12, "DF");
        $fpdf->SetXY($startX, $startY + 3);
        $fpdf->Cell($widths[4], 6, "JAM SEHARUSNYA PULANG", 0, 0, "C");

        // TOTAL GROUP
        $startX += $widths[4];
        $groupWidth = $widths[5] + $widths[6] + $widths[7];
        $fpdf->Rect($startX, $startY, $groupWidth, 6, "DF");
        $fpdf->SetXY($startX, $startY);
        $fpdf->Cell($groupWidth, 6, "TOTAL", 0, 0, "C");

        // Row 2 for TOTAL subcolumns
        $fpdf->SetXY($startX, $startY + 6);
        $fpdf->Cell($widths[5], 6, "HARI", 1, 0, "C", true);
        $fpdf->Cell($widths[6], 6, "JAM", 1, 0, "C", true);
        $fpdf->Cell($widths[7], 6, "BAROKAH", 1, 1, "C", true);
    }

    private static function checkPageBreak($fpdf, $rowHeight, $periode, $widths)
    {
        if ($fpdf->GetY() + $rowHeight > 190) {
            $fpdf->AddPage("L", "A4");
            self::header($fpdf, $periode);
            self::tableHeader($fpdf, $widths);
        }
    }

    public static function pdf(array $data, $periode = null)
    {
        $fpdf = new CustomFpdf("L", "mm", "A4");
        $fpdf->SetMargins(10, 10, 10);
        $fpdf->SetAutoPageBreak(false);
        $fpdf->AddPage("L", "A4");

        self::header($fpdf, $periode);

        // Widths total = 277
        $widths = [10, 65, 35, 42, 42, 22, 25, 36];

        self::tableHeader($fpdf, $widths);

        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->SetDrawColor(226, 232, 240);

        $totalHari = 0;
        $totalJam = 0;
        $totalBarokah = 0;

        foreach ($data as $index => $item) {
            self::checkPageBreak($fpdf, 7, $periode, $widths);

            $hari = is_array($item['rekap_per_kategori'] ?? null)
                ? array_reduce($item['rekap_per_kategori'], fn($acc, $kat) => $acc + (int) ($kat['jumlah'] ?? 0), 0)
                : 0;
            $jam = (float) ($item['total_jam_keseluruhan']['total_jam'] ?? 0);
            $barokah = (float) ($item['total_perolehan_dana'] ?? 0);

            $totalHari += $hari;
            $totalJam += $jam;
            $totalBarokah += $barokah;

            $nama = self::fit($fpdf, $item['user']['name'] ?? $item['user']['nama'] ?? '-', $widths[1]);
            $dept = self::fit($fpdf, $item['user']['departemen'] ?? '-', $widths[2]);
            $datang = self::fit($fpdf, $item['jam_seharusnya_datang'] ?? '13:00:00', $widths[3]);
            $pulang = self::fit($fpdf, $item['jam_seharusnya_pulang'] ?? '19:00:00', $widths[4]);

            // Zebra rows
            $isEven = $index % 2 === 0;
            $fpdf->SetFillColor($isEven ? 255 : 248, $isEven ? 255 : 250, $isEven ? 255 : 252);

            $fpdf->SetX(10);
            $fpdf->SetTextColor(51, 65, 85);
            $fpdf->Cell($widths[0], 6.5, $index + 1, 1, 0, "C", true);
            $fpdf->SetTextColor(15, 23, 42);
            $fpdf->Cell($widths[1], 6.5, $nama, 1, 0, "L", true);
            $fpdf->SetTextColor(71, 85, 105);
            $fpdf->Cell($widths[2], 6.5, $dept, 1, 0, "C", true);
            $fpdf->Cell($widths[3], 6.5, $datang, 1, 0, "C", true);
            $fpdf->Cell($widths[4], 6.5, $pulang, 1, 0, "C", true);
            
            $fpdf->SetTextColor(15, 23, 42);
            $fpdf->Cell($widths[5], 6.5, number_format($hari, 0, ',', '.'), 1, 0, "R", true);
            $fpdf->Cell($widths[6], 6.5, number_format($jam, 2, ',', '.'), 1, 0, "R", true);
            
            $fpdf->SetFont("Arial", "B", 7.5);
            $fpdf->SetTextColor(25, 135, 84);
            $fpdf->Cell($widths[7], 6.5, self::formatRupiah($barokah), 1, 1, "R", true);
            $fpdf->SetFont("Arial", "", 7.5);
        }

        self::checkPageBreak($fpdf, 8, $periode, $widths);

        // Summary Row
        $fpdf->SetX(10);
        $fpdf->SetFont("Arial", "B", 8);
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->SetTextColor(15, 23, 42);
        
        $labelWidth = $widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4];
        $fpdf->Cell($labelWidth, 7, "TOTAL KESELURUHAN", 1, 0, "C", true);
        $fpdf->Cell($widths[5], 7, number_format($totalHari, 0, ',', '.'), 1, 0, "R", true);
        $fpdf->Cell($widths[6], 7, number_format($totalJam, 2, ',', '.'), 1, 0, "R", true);
        
        $fpdf->SetTextColor(25, 135, 84);
        $fpdf->Cell($widths[7], 7, self::formatRupiah($totalBarokah), 1, 1, "R", true);

        // Footer info
        $fpdf->SetXY(10, 195);
        $fpdf->SetFont("Arial", "I", 7);
        $fpdf->SetTextColor(148, 163, 184);
        $fpdf->Cell(150, 4, "Dicetak dari Sistem SIMKEU pada: " . date("d/m/Y H:i:s") . " WIB", 0, 0, "L");
        $fpdf->Cell(127, 4, "Halaman " . $fpdf->PageNo() . " dari {nb}", 0, 1, "R");

        return response()->streamDownload(function () use ($fpdf) {
            $fpdf->Output("I", "Laporan_Rekap_Total_Web_Absensi.pdf");
        }, "Laporan_Rekap_Total_Web_Absensi.pdf", [
            "Content-Type" => "application/pdf",
        ]);
    }
}
