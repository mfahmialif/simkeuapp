<?php

namespace App\Exports\pdf;

use App\Exports\pdf\CustomFpdf;

class WebAbsensiPdf
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
        $fpdf->SetFillColor(30, 58, 138); // Biru tua
        $fpdf->Rect(10, 8, 277, 22, "F");

        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont("Arial", "B", 13);
        $fpdf->SetXY(14, 11);
        $fpdf->Cell(150, 6, "DATA & REKAP WEB ABSENSI", 0, 1, "L");

        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, "L");
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "Sistem Informasi Keuangan (SIMKEU) - Laporan Terintegrasi Web Absensi", 0, 1, "L");

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
        $headers = [
            "NO", "KODE", "NAMA", "DEPARTEMEN", "TANGGAL", "HARI",
            "DATANG", "PULANG", "TOTAL", "PERINCIAN JAM", "BAROKAH"
        ];

        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->SetDrawColor(203, 213, 225);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->SetFont("Arial", "B", 7);

        $fpdf->SetX(10);
        foreach ($headers as $index => $header) {
            $fpdf->Cell($widths[$index], 7, $header, 1, 0, "C", true);
        }
        $fpdf->Ln();
    }

    public static function pdf(array $dataRows, array $periode)
    {
        $fpdf = new CustomFpdf("L", "mm", "A4");
        $fpdf->SetAutoPageBreak(false);
        $fpdf->AddPage();

        self::header($fpdf, $periode);

        $widths = [10, 22, 45, 22, 23, 18, 18, 18, 22, 54, 25];
        self::tableHeader($fpdf, $widths);

        $daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        $fpdf->SetFont("Arial", "", 6.8);
        $totalBarokah = 0;

        foreach ($dataRows as $index => $row) {
            if ($fpdf->GetY() > 186) {
                $fpdf->AddPage();
                self::header($fpdf, $periode);
                self::tableHeader($fpdf, $widths);
                $fpdf->SetFont("Arial", "", 6.8);
            }

            $tgl = $row['tgl_absen'] ?? null;
            $hari = '-';
            $tglFormatted = '-';
            if ($tgl && $tgl !== '-') {
                $time = strtotime($tgl);
                if ($time !== false) {
                    $hari = $daysIndo[date('w', $time)] ?? '-';
                    $tglFormatted = date('d/m/Y', $time);
                }
            }

            $kode = $row['kode_user'] ?? ($row['user']['kode'] ?? '-');
            if ($kode && $kode !== '-') {
                $kode = preg_replace('/^KD-/i', '', (string) $kode);
            }

            $totalJam = '-';
            if (!empty($row['durasi_jam']) && (float) $row['durasi_jam'] > 0) {
                $totalJam = $row['durasi_jam'] . ' Jam';
            } elseif (!empty($row['selisih_menit']) && (int) $row['selisih_menit'] > 0) {
                $totalJam = round($row['selisih_menit'] / 60, 2) . ' Jam';
            }

            $perincian = !empty($row['durasi_teks']) && $row['durasi_teks'] !== '-'
                ? $row['durasi_teks']
                : ($row['keterangan'] ?? '-');

            $barokahNum = ($row['perolehan_dana'] ?? 0) > 0 ? (int) $row['perolehan_dana'] : 0;
            $totalBarokah += $barokahNum;

            $values = [
                $index + 1,
                $kode,
                $row['user']['name'] ?? '-',
                $row['user']['departemen'] ?? '-',
                $tglFormatted,
                $hari,
                $row['pagi'] ?? '-',
                $row['sore'] ?? '-',
                $totalJam,
                $perincian,
                self::formatRupiah($barokahNum)
            ];

            $fill = ($index % 2) === 1;
            if ($fill) {
                $fpdf->SetFillColor(248, 250, 252);
            } else {
                $fpdf->SetFillColor(255, 255, 255);
            }

            $fpdf->SetX(10);
            foreach ($values as $colIndex => $value) {
                $align = in_array($colIndex, [0, 1, 4, 5, 6, 7, 8])
                    ? "C"
                    : ($colIndex === 10 ? "R" : "L");

                if ($colIndex === 10 && $barokahNum > 0) {
                    $fpdf->SetTextColor(22, 101, 52);
                    $fpdf->SetFont("Arial", "B", 6.8);
                } else {
                    $fpdf->SetTextColor(15, 23, 42);
                    $fpdf->SetFont("Arial", "", 6.8);
                }

                $fpdf->Cell(
                    $widths[$colIndex],
                    6,
                    self::fit($fpdf, $value, $widths[$colIndex]),
                    "B",
                    0,
                    $align,
                    true
                );
            }
            $fpdf->Ln();
        }

        // Total Baris
        if ($fpdf->GetY() > 186) {
            $fpdf->AddPage();
            self::header($fpdf, $periode);
            self::tableHeader($fpdf, $widths);
        }

        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->SetTextColor(15, 23, 42);
        $fpdf->SetFont("Arial", "B", 7.5);
        $fpdf->SetX(10);
        $totalLabelWidth = array_sum(array_slice($widths, 0, 10));
        $fpdf->Cell($totalLabelWidth, 7, "TOTAL KESELURUHAN BAROKAH", 1, 0, "R", true);
        $fpdf->SetTextColor(22, 101, 52);
        $fpdf->Cell($widths[10], 7, self::formatRupiah($totalBarokah), 1, 1, "R", true);

        $binary = $fpdf->Output("S");

        return response($binary, 200)
            ->header("Content-Type", "application/pdf")
            ->header("Content-Disposition", 'inline; filename="Laporan_Web_Absensi.pdf"');
    }
}
