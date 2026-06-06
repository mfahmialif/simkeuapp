<?php

namespace App\Exports\pdf;

use App\Services\MataUangFormatter;

class LaporanHarianDetailPdf
{
    private static function tanggal($value)
    {
        return $value ? date("d/m/Y", strtotime($value)) : "-";
    }

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

    private static function header($fpdf, $data)
    {
        $fpdf->SetFillColor(16, 42, 67);
        $fpdf->Rect(10, 8, 277, 22, "F");

        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont("Arial", "B", 13);
        $fpdf->SetXY(14, 11);
        $fpdf->Cell(150, 6, "LAPORAN HARIAN", 0, 1, "L");

        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, "L");
        $fpdf->SetX(14);
        $fpdf->Cell(160, 4, "Raci Bangil Pasuruan Jawa Timur - Jl. Raya Raci No.51 PO.Box.8 Bangil", 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 8);
        $fpdf->SetXY(218, 12);
        $fpdf->Cell(64, 5, "Tanggal Pelayanan", 0, 1, "R");
        $fpdf->SetFont("Arial", "", 11);
        $fpdf->SetX(218);
        $fpdf->Cell(64, 7, date("d-m-Y", strtotime($data["tanggal"])), 0, 1, "R");

        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->Rect(10, 33, 277, 13, "F");
        $fpdf->SetDrawColor(226, 232, 240);
        $fpdf->Rect(10, 33, 277, 13);

        $fpdf->SetFont("Arial", "B", 7.5);
        $fpdf->SetXY(14, 36);
        $fpdf->Cell(18, 4, "Kategori", 0, 0, "L");
        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->MultiCell(245, 4, ": " . $data["kategori"], 0, "L");
        $fpdf->SetY(50);
    }

    private static function tableHeader($fpdf, $widths)
    {
        $headers = [
            "No",
            "Tanggal",
            "Tgl.Trans",
            "Kwitansi",
            "NIM/NoDaftar",
            "Nama",
            "L/P",
            "Prodi",
            "Pembayaran",
            "Nominal",
            "Metode",
            "Petugas",
        ];

        $fpdf->SetFillColor(30, 64, 175);
        $fpdf->SetDrawColor(30, 64, 175);
        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont("Arial", "B", 6.5);
        foreach ($headers as $index => $header) {
            $fpdf->Cell($widths[$index], 7, $header, 1, 0, "C", true);
        }
        $fpdf->Ln();
        $fpdf->SetTextColor(15, 23, 42);
        $fpdf->SetDrawColor(226, 232, 240);
    }

    public static function pdf($data)
    {
        $fpdf = new CustomFpdf("L", "mm", "A4");
        $fpdf->SetMargins(10, 8, 10);
        $fpdf->SetAutoPageBreak(false);
        $fpdf->AddPage();

        self::header($fpdf, $data);

        $widths = [7, 18, 18, 21, 23, 34, 7, 43, 36, 23, 17, 30];
        self::tableHeader($fpdf, $widths);

        $fpdf->SetFont("Arial", "", 6.3);
        foreach ($data["rows"] as $row) {
            if ($fpdf->GetY() > 184) {
                $fpdf->AddPage();
                self::header($fpdf, $data);
                self::tableHeader($fpdf, $widths);
                $fpdf->SetFont("Arial", "", 6.3);
            }

            $values = [
                $row["no"],
                self::tanggal($row["tanggal_input"]),
                self::tanggal($row["tanggal_transaksi"]),
                $row["kwitansi"],
                $row["nim"],
                $row["nama"],
                $row["jenis_kelamin"],
                $row["prodi"],
                $row["pembayaran"],
                MataUangFormatter::amount(
                    $row["nominal"],
                    $row["mata_uang"] ?? MataUangFormatter::defaultCurrency(),
                ),
                $row["metode"],
                $row["petugas"],
            ];

            $fill = ((int) $row["no"] % 2) === 0;
            if ($fill) {
                $fpdf->SetFillColor(248, 250, 252);
            } else {
                $fpdf->SetFillColor(255, 255, 255);
            }

            foreach ($values as $index => $value) {
                $align = in_array($index, [0, 1, 2, 3, 4, 6, 10])
                    ? "C"
                    : ($index === 9
                        ? "R"
                        : "L");

                if ($index === 9) {
                    $fpdf->SetTextColor(22, 101, 52);
                    $fpdf->SetFont("Arial", "B", 6.3);
                } else {
                    $fpdf->SetTextColor(15, 23, 42);
                    $fpdf->SetFont("Arial", "", 6.3);
                }

                $fpdf->Cell(
                    $widths[$index],
                    6,
                    self::fit($fpdf, $value, $widths[$index]),
                    "B",
                    0,
                    $align,
                    true,
                );
            }
            $fpdf->Ln();
        }

        $fpdf->Ln(2);
        $fpdf->SetFillColor(22, 101, 52);
        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetDrawColor(22, 101, 52);
        $fpdf->SetFont("Arial", "B", 8);
        $fpdf->Cell(array_sum(array_slice($widths, 0, 9)), 8, "TOTAL PEMASUKAN", 1, 0, "R", true);
        $fpdf->Cell(
            $widths[9] + $widths[10] + $widths[11],
            8,
            self::fit(
                $fpdf,
                MataUangFormatter::formatTotals(
                    $data["total_by_currency"] ?? [],
                ),
                $widths[9] + $widths[10] + $widths[11],
            ),
            1,
            1,
            "R",
            true,
        );

        $binary = $fpdf->Output("S");

        return response($binary, 200)
            ->header("Content-Type", "application/pdf")
            ->header(
                "Content-Disposition",
                'inline; filename="Laporan Harian ' . $data["tanggal"] . '.pdf"',
            );
    }
}
