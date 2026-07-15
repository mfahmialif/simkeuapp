<?php

namespace App\Exports\pdf;

use App\Exports\pdf\CustomFpdf;

class WebAbsensiSlipPdf
{
    private static function formatRupiah($angka)
    {
        if (!$angka || $angka <= 0) {
            return "-";
        }
        return "Rp " . number_format($angka, 0, ',', '.');
    }

    private static function header($fpdf, $rekapItem, $periode)
    {
        // Kop Surat Image banner
        $path = public_path('img/kop uiidalwa mantap.png');
        if (!is_file($path)) {
            $path = base_path('../public_html/img/kop uiidalwa mantap.png');
        }
        if (!is_file($path)) {
            $path = base_path('public_html/img/kop uiidalwa mantap.png');
        }

        if (is_file($path)) {
            $fpdf->Image($path, 10, 8, 190);
            $fpdf->SetY(54);
        } else {
            $fpdf->SetFont("Arial", "B", 14);
            $fpdf->Cell(190, 7, "UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, "C");
            $fpdf->SetFont("Arial", "", 9);
            $fpdf->Cell(190, 5, "Jl. Raya Raci No.51 Bangil Pasuruan Jawa Timur Indonesia, website : uiidalwa.ac.id", 0, 1, "C");
            $fpdf->Line(10, 24, 200, 24);
            $fpdf->Line(10, 25, 200, 25);
            $fpdf->SetY(32);
        }

        // Title
        $fpdf->SetFont("Arial", "BU", 13);
        $fpdf->Cell(190, 8, "REKAP KEHADIRAN", 0, 1, "C");
        $fpdf->Ln(3);

        // Profile Summary info
        $nama = $rekapItem['user']['name'] ?? $rekapItem['user']['nama'] ?? '-';
        $dept = $rekapItem['user']['departemen'] ?? '-';
        $totalJam = (float) ($rekapItem['total_jam_keseluruhan']['total_jam'] ?? 0);
        
        $totalHari = $rekapItem['_total_hari_calculated'] ?? 0;
        if (!$totalHari && !empty($rekapItem['rekap_per_kategori']) && is_array($rekapItem['rekap_per_kategori'])) {
            $totalHari = array_reduce($rekapItem['rekap_per_kategori'], fn($acc, $kat) => $acc + (int)($kat['jumlah'] ?? 0), 0);
        }

        $totalBarokah = (float) ($rekapItem['total_perolehan_dana'] ?? 0);

        $periodeInfo = '-';
        if (!empty($periode['mode']) && $periode['mode'] === 'bulan_tahun') {
            $months = [
                1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
                7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
            ];
            $b = (int) ($periode['bulan'] ?? date('n'));
            $t = $periode['tahun'] ?? date('Y');
            $periodeInfo = ($months[$b] ?? $b) . " " . $t;
        } elseif (!empty($periode['start_date']) && !empty($periode['end_date'])) {
            $periodeInfo = strtoupper(date('d/m/Y', strtotime($periode['start_date'])) . " s/d " . date('d/m/Y', strtotime($periode['end_date'])));
        }

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "NAMA", 0, 0, "L");
        $fpdf->Cell(152, 5.5, ": " . $nama, 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "DEPARTEMEN", 0, 0, "L");
        $fpdf->SetFont("Arial", "", 9.5);
        $fpdf->Cell(152, 5.5, ": " . $dept, 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "TOTAL JAM", 0, 0, "L");
        $fpdf->SetFont("Arial", "", 9.5);
        $fpdf->Cell(152, 5.5, ": " . str_replace('.', ',', (string)$totalJam), 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "TOTAL HARI", 0, 0, "L");
        $fpdf->SetFont("Arial", "", 9.5);
        $fpdf->Cell(152, 5.5, ": " . $totalHari, 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "TOTAL BAROKAH", 0, 0, "L");
        $fpdf->Cell(152, 5.5, ": Rp " . number_format($totalBarokah, 0, ',', '.'), 0, 1, "L");

        $fpdf->SetFont("Arial", "B", 9.5);
        $fpdf->Cell(38, 5.5, "PERIODE", 0, 0, "L");
        $fpdf->SetFont("Arial", "", 9.5);
        $fpdf->Cell(152, 5.5, ": " . $periodeInfo, 0, 1, "L");

        $fpdf->Ln(4);
    }

    private static function tableHeader($fpdf, $widths)
    {
        $headers = [
            "NO", "TANGGAL", "JAM DATANG", "JAM PULANG", "TOTAL JAM", "PERINCIAN JAM", "BAROKAH", "KET."
        ];

        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->SetDrawColor(148, 163, 184);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->SetFont("Arial", "B", 7.5);

        $fpdf->SetX(10);
        foreach ($headers as $index => $header) {
            $fpdf->Cell($widths[$index], 7, $header, 1, 0, "C", true);
        }
        $fpdf->Ln();
    }

    public static function pdf(array $dataRows, array $rekapItem, array $periode)
    {
        $fpdf = new CustomFpdf("P", "mm", "A4");
        $fpdf->SetAutoPageBreak(false);
        $fpdf->AddPage();

        self::header($fpdf, $rekapItem, $periode);

        $widths = [10, 22, 22, 22, 20, 46, 26, 22];
        self::tableHeader($fpdf, $widths);

        $fpdf->SetFont("Arial", "", 7.5);
        $fpdf->SetDrawColor(203, 213, 225);

        $no = 1;
        foreach ($dataRows as $row) {
            if ($fpdf->GetY() > 272) {
                $fpdf->AddPage();
                $fpdf->SetY(15);
                self::tableHeader($fpdf, $widths);
                $fpdf->SetFont("Arial", "", 7.5);
                $fpdf->SetDrawColor(203, 213, 225);
            }

            $tgl = $row['tgl_absen'] ?? '';
            $tglFormatted = $tgl ? date('d-m-y', strtotime($tgl)) : '-';
            $pagi = $row['pagi'] ?? '-';
            $sore = $row['sore'] ?? '-';
            $durasiJam = isset($row['durasi_jam']) ? str_replace('.', ',', (string)$row['durasi_jam']) : '-';
            $durasiTeks = $row['durasi_teks'] ?? ($row['kategori']['nama'] ?? '-');
            $barokah = isset($row['perolehan_dana']) && $row['perolehan_dana'] > 0
                ? self::formatRupiah($row['perolehan_dana'])
                : '-';
            $ket = $row['keterangan'] ?? '-';

            $fpdf->SetX(10);
            $fpdf->Cell($widths[0], 6.5, $no++, 1, 0, "C");
            $fpdf->Cell($widths[1], 6.5, $tglFormatted, 1, 0, "C");
            $fpdf->Cell($widths[2], 6.5, $pagi, 1, 0, "C");
            $fpdf->Cell($widths[3], 6.5, $sore, 1, 0, "C");
            $fpdf->Cell($widths[4], 6.5, $durasiJam, 1, 0, "C");
            $fpdf->Cell($widths[5], 6.5, $durasiTeks, 1, 0, "C");
            $fpdf->Cell($widths[6], 6.5, $barokah, 1, 0, "R");
            $fpdf->Cell($widths[7], 6.5, $ket, 1, 1, "L");
        }

        if (empty($dataRows)) {
            $fpdf->SetX(10);
            $fpdf->Cell(array_sum($widths), 8, "Tidak ada data kehadiran untuk pegawai ini pada periode yang dipilih.", 1, 1, "C");
        }

        $namaClean = preg_replace('/[^A-Za-z0-9_-]/', '_', $rekapItem['user']['name'] ?? $rekapItem['user']['nama'] ?? 'Pegawai');
        $filename = "Slip_Rekap_Kehadiran_" . $namaClean . ".pdf";

        return response()->streamDownload(function () use ($fpdf, $filename) {
            $fpdf->Output("I", $filename);
        }, $filename, [
            "Content-Type" => "application/pdf",
        ]);
    }
}
