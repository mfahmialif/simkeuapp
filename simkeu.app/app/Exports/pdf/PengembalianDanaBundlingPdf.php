<?php

namespace App\Exports\pdf;

use Carbon\Carbon;
use Codedge\Fpdf\Fpdf\Fpdf;
use Illuminate\Support\Facades\Storage;

class PengembalianDanaBundlingPdf
{
    private static function getKopPath(): ?string
    {
        $candidates = [
            public_path('img/kop uiidalwa mantap.png'),
            base_path('../public_html/img/kop uiidalwa mantap.png'),
            base_path('public_html/img/kop uiidalwa mantap.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Konversi file gambar jika diperlukan (khususnya WebP yang tidak didukung langsung oleh FPDF)
     */
    private static function prepareImageForFpdf(string $filePath, array &$tempFiles): ?string
    {
        if (!is_file($filePath)) {
            return null;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Konversi WebP ke JPG sementara menggunakan GD
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($filePath);
            if ($img) {
                $tempJpg = tempnam(sys_get_temp_dir(), 'pd_bdl_') . '.jpg';
                $w = imagesx($img);
                $h = imagesy($img);
                $bg = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefilledrectangle($bg, 0, 0, $w, $h, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
                imagejpeg($bg, $tempJpg, 90);
                imagedestroy($img);
                imagedestroy($bg);

                $tempFiles[] = $tempJpg;
                return $tempJpg;
            }
        }

        // Format yang didukung langsung oleh FPDF
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return $filePath;
        }

        return null;
    }

    public static function generate($items, array $filterInfo = [])
    {
        $tempFiles = [];

        $fpdf = new CustomFpdf('P', 'mm', 'A4');
        $fpdf->SetAutoPageBreak(true, 15);
        $fpdf->AddPage();

        // 1. Kop Surat
        $kopPath = self::getKopPath();
        if ($kopPath) {
            $fpdf->Image($kopPath, 10, 8, 190);
            $fpdf->SetY(50);
        } else {
            $fpdf->SetFont('Arial', 'B', 14);
            $fpdf->Cell(190, 7, "UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, 'C');
            $fpdf->SetFont('Arial', '', 9);
            $fpdf->Cell(190, 5, "Jl. Raya Raci No.51 Bangil Pasuruan Jawa Timur Indonesia", 0, 1, 'C');
            $fpdf->SetDrawColor(30, 41, 59);
            $fpdf->SetLineWidth(0.6);
            $fpdf->Line(10, 26, 200, 26);
            $fpdf->SetLineWidth(0.2);
            $fpdf->Line(10, 27, 200, 27);
            $fpdf->SetY(34);
        }

        // 2. Judul & Periode Laporan
        $fpdf->SetFont('Arial', 'B', 12);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->Cell(190, 6, 'REKAP LAPORAN PENGEMBALIAN DANA (REFUND)', 0, 1, 'C');

        $periodeText = 'Semua Periode Transaksi';
        if (!empty($filterInfo['start_date']) && !empty($filterInfo['end_date'])) {
            $s = Carbon::parse($filterInfo['start_date'])->translatedFormat('d M Y');
            $e = Carbon::parse($filterInfo['end_date'])->translatedFormat('d M Y');
            $periodeText = "Periode: {$s} s/d {$e}";
        } elseif (!empty($filterInfo['start_date'])) {
            $s = Carbon::parse($filterInfo['start_date'])->translatedFormat('d M Y');
            $periodeText = "Mulai Tanggal: {$s}";
        } elseif (!empty($filterInfo['end_date'])) {
            $e = Carbon::parse($filterInfo['end_date'])->translatedFormat('d M Y');
            $periodeText = "Sampai Tanggal: {$e}";
        }

        $fpdf->SetFont('Arial', 'B', 9);
        $fpdf->SetTextColor(71, 85, 105);
        $fpdf->Cell(190, 5, $periodeText, 0, 1, 'C');
        $fpdf->SetTextColor(0, 0, 0);

        $fpdf->SetDrawColor(203, 213, 225);
        $fpdf->SetLineWidth(0.4);
        $fpdf->Line(10, $fpdf->GetY() + 2, 200, $fpdf->GetY() + 2);
        $fpdf->Ln(4);

        // 3. Header Tabel Rekap
        $renderTableHeader = function () use ($fpdf) {
            $fpdf->SetFont('Arial', 'B', 8.5);
            $fpdf->SetFillColor(30, 58, 138); // Navy
            $fpdf->SetTextColor(255, 255, 255);
            $fpdf->SetDrawColor(203, 213, 225);
            $fpdf->Cell(8, 7, 'No', 1, 0, 'C', true);
            $fpdf->Cell(32, 7, 'No. Bukti', 1, 0, 'C', true);
            $fpdf->Cell(28, 7, 'Tanggal & Waktu', 1, 0, 'C', true);
            $fpdf->Cell(22, 7, 'Metode', 1, 0, 'C', true);
            $fpdf->Cell(44, 7, 'Keterangan', 1, 0, 'C', true);
            $fpdf->Cell(26, 7, 'Petugas', 1, 0, 'C', true);
            $fpdf->Cell(30, 7, 'Nominal (Rp)', 1, 1, 'C', true);
            $fpdf->SetTextColor(0, 0, 0);
        };

        $renderTableHeader();

        // 4. Data Baris Tabel
        $fpdf->SetFont('Arial', '', 8);
        $totalNominal = 0;
        $totalTransaksi = count($items);
        $no = 1;

        foreach ($items as $item) {
            if ($fpdf->GetY() > 255) {
                $fpdf->AddPage();
                $renderTableHeader();
                $fpdf->SetFont('Arial', '', 8);
            }

            $totalNominal += (float) $item->nominal;

            $tglStr = $item->tanggal ? Carbon::parse($item->tanggal)->translatedFormat('d/m/Y H:i') : '-';
            $keterangan = $item->keterangan ?: '-';
            if (strlen($keterangan) > 32) {
                $keterangan = substr($keterangan, 0, 29) . '...';
            }

            $metode = $item->jenisPembayaran->nama ?? 'Tunai';
            $infoTujuan = [];
            if (!empty($item->nama_bank)) {
                $infoTujuan[] = $item->nama_bank;
            }
            if (!empty($item->nama_tujuan)) {
                $infoTujuan[] = $item->nama_tujuan;
            }
            if (!empty($infoTujuan)) {
                $metode .= ' (' . implode(' - ', $infoTujuan) . ')';
            }
            if (strlen($metode) > 22) {
                $metode = substr($metode, 0, 20) . '...';
            }

            $petugas = $item->petugas->name ?? $item->petugas->username ?? '-';
            if (strlen($petugas) > 16) {
                $petugas = substr($petugas, 0, 14) . '...';
            }

            $noBukti = $item->no_transaksi ?: ('PD-' . $item->id);

            $fpdf->SetFillColor($no % 2 === 0 ? 248 : 255, $no % 2 === 0 ? 250 : 255, $no % 2 === 0 ? 252 : 255);
            $fpdf->Cell(8, 6, (string) $no, 1, 0, 'C', true);
            $fpdf->Cell(32, 6, $noBukti, 1, 0, 'C', true);
            $fpdf->Cell(28, 6, $tglStr, 1, 0, 'C', true);
            $fpdf->Cell(22, 6, ' ' . $metode, 1, 0, 'L', true);
            $fpdf->Cell(44, 6, ' ' . $keterangan, 1, 0, 'L', true);
            $fpdf->Cell(26, 6, ' ' . $petugas, 1, 0, 'L', true);
            $fpdf->Cell(30, 6, number_format($item->nominal, 0, ',', '.') . ' ', 1, 1, 'R', true);

            $no++;
        }

        if ($totalTransaksi === 0) {
            $fpdf->Cell(190, 8, 'Tidak ada data pengembalian dana yang sesuai dengan filter.', 1, 1, 'C');
        }

        // 5. Total Row
        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->Cell(160, 7, ' TOTAL KESELURUHAN PENGEMBALIAN DANA (' . $totalTransaksi . ' Transaksi)', 1, 0, 'L', true);
        $fpdf->Cell(30, 7, number_format($totalNominal, 0, ',', '.') . ' ', 1, 1, 'R', true);

        // 6. Tanda Tangan Ringkasan
        if ($fpdf->GetY() > 240) {
            $fpdf->AddPage();
        }

        $tglCetak = Carbon::now()->translatedFormat('d F Y');
        $fpdf->Ln(5);
        $fpdf->SetFont('Arial', '', 8.5);
        $fpdf->Cell(95, 4.5, '', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, 'Bangil, ' . $tglCetak, 0, 1, 'C');

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(95, 4.5, 'Mengetahui,', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, 'Petugas Keuangan / Kasir,', 0, 1, 'C');

        $fpdf->Ln(16);

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(95, 4.5, '( Bagian Keuangan )', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, '( .................................................... )', 0, 1, 'C');

        // 7. Lampiran Bundling Minimalis (Grid 2 Kolom per Transaksi)
        $transactionsWithAttachments = [];

        foreach ($items as $item) {
            $entries = [];

            if (!empty($item->file_bukti_masuk)) {
                $rawPath = Storage::disk('public')->path($item->file_bukti_masuk);
                $ext = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $prepared = $isImg ? self::prepareImageForFpdf($rawPath, $tempFiles) : null;

                $entries[] = [
                    'type'       => 'masuk',
                    'title'      => 'Bukti Dana Masuk',
                    'filename'   => basename($item->file_bukti_masuk),
                    'is_image'   => !empty($prepared),
                    'image_path' => $prepared,
                    'is_pdf'     => $ext === 'pdf',
                ];
            }

            if (!empty($item->file_bukti_keluar)) {
                $rawPath = Storage::disk('public')->path($item->file_bukti_keluar);
                $ext = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $prepared = $isImg ? self::prepareImageForFpdf($rawPath, $tempFiles) : null;

                $entries[] = [
                    'type'       => 'keluar',
                    'title'      => 'Bukti Dana Keluar',
                    'filename'   => basename($item->file_bukti_keluar),
                    'is_image'   => !empty($prepared),
                    'image_path' => $prepared,
                    'is_pdf'     => $ext === 'pdf',
                ];
            }

            if (count($entries) > 0) {
                $transactionsWithAttachments[] = [
                    'item'    => $item,
                    'entries' => $entries,
                ];
            }
        }

        if (count($transactionsWithAttachments) > 0) {
            $fpdf->AddPage();

            // Header Utama Lampiran Bundling
            $fpdf->SetFont('Arial', 'B', 12);
            $fpdf->SetTextColor(30, 41, 59);
            $fpdf->Cell(190, 6, 'LAMPIRAN BUKTI TRANSAKSI PENGEMBALIAN DANA', 0, 1, 'C');
            $fpdf->SetFont('Arial', '', 8.5);
            $fpdf->SetTextColor(71, 85, 105);
            $fpdf->Cell(190, 5, 'Daftar berkas foto dan bukti fisik transaksi pengembalian dana yang terlampir', 0, 1, 'C');
            $fpdf->SetDrawColor(203, 213, 225);
            $fpdf->Line(10, $fpdf->GetY() + 2, 200, $fpdf->GetY() + 2);
            $fpdf->Ln(4);

            $colWidth = 92;
            $boxHeight = 48;
            $colSpacing = 6;
            $startX = 10;

            foreach ($transactionsWithAttachments as $tItem) {
                $item = $tItem['item'];
                $entries = $tItem['entries'];

                // Cek apakah muat header transaksi + 1 baris lampiran (~64mm)
                if ($fpdf->GetY() + 64 > 280) {
                    $fpdf->AddPage();
                }

                $fpdf->SetFillColor(241, 245, 249);
                $fpdf->SetTextColor(30, 58, 138);
                $fpdf->SetFont('Arial', 'B', 8);
                $tglStr = $item->tanggal ? Carbon::parse($item->tanggal)->translatedFormat('d/m/Y H:i') : '-';
                $noDoc = $item->no_transaksi ?: ('PD-' . $item->id);
                $nomStr = 'Rp ' . number_format($item->nominal, 0, ',', '.');
                $ketStr = $item->keterangan ? ' - ' . (strlen($item->keterangan) > 40 ? substr($item->keterangan, 0, 37) . '...' : $item->keterangan) : '';

                $fpdf->Cell(190, 5.5, '  [' . $noDoc . '] ' . $tglStr . ' | ' . $nomStr . $ketStr, 1, 1, 'L', true);
                $fpdf->Ln(1.5);

                $rowY = $fpdf->GetY();

                foreach ($entries as $idx => $entry) {
                    $cellX = $idx === 0 ? $startX : ($startX + $colWidth + $colSpacing);
                    self::renderAttachmentCell($fpdf, $entry, $cellX, $rowY, $colWidth, $boxHeight);
                }

                $fpdf->SetY($rowY + $boxHeight + 5);
            }
        }

        $binary = $fpdf->Output('S');

        // Bersihkan file sementara
        foreach ($tempFiles as $tf) {
            if (is_file($tf)) {
                @unlink($tf);
            }
        }

        $filename = 'Rekap Pengembalian Dana ' . Carbon::now()->format('Ymd_His') . '.pdf';

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    private static function renderAttachmentCell(CustomFpdf $fpdf, array $entry, float $x, float $y, float $w, float $h): void
    {
        // Kotak wadah
        $fpdf->SetFillColor(248, 250, 252);
        $fpdf->SetDrawColor(226, 232, 240);
        $fpdf->SetLineWidth(0.25);
        $fpdf->Rect($x, $y, $w, $h, 'DF');

        // Header kecil judul lampiran di dalam sel
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->Rect($x, $y, $w, 5, 'F');
        $fpdf->SetXY($x + 2, $y + 0.5);
        $fpdf->SetFont('Arial', 'B', 7.5);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->Cell($w - 4, 4, $entry['title'], 0, 0, 'L');

        $contentY = $y + 5.5;
        $contentH = $h - 10.5;

        if ($entry['is_image'] && !empty($entry['image_path'])) {
            $maxW = $w - 6;
            $maxH = $contentH;

            $imgInfo = @getimagesize($entry['image_path']);
            $origW = $imgInfo[0] ?? 100;
            $origH = $imgInfo[1] ?? 100;

            $ratio = min($maxW / $origW, $maxH / $origH);
            $renderW = $origW * $ratio;
            $renderH = $origH * $ratio;

            $imgX = $x + ($w - $renderW) / 2;
            $imgY = $contentY + ($maxH - $renderH) / 2;

            try {
                $fpdf->Image($entry['image_path'], $imgX, $imgY, $renderW, $renderH);
            } catch (\Throwable $e) {
                $fpdf->SetXY($x, $contentY + ($contentH / 2) - 3);
                $fpdf->SetFont('Arial', 'I', 7.5);
                $fpdf->SetTextColor(150, 150, 150);
                $fpdf->Cell($w, 6, 'Gagal memuat gambar', 0, 0, 'C');
            }
        } elseif ($entry['is_pdf']) {
            $fpdf->SetXY($x + 4, $contentY + 6);
            $fpdf->SetFont('Arial', 'B', 8.5);
            $fpdf->SetTextColor(220, 38, 38);
            $fpdf->Cell($w - 8, 5, '[ DOKUMEN DIGITAL PDF ]', 0, 1, 'C');

            $fpdf->SetXY($x + 4, $contentY + 11.5);
            $fpdf->SetFont('Arial', '', 7);
            $fpdf->SetTextColor(100, 116, 139);
            $fpdf->MultiCell($w - 8, 3.5, "Berkas dokumen PDF resmi tersimpan secara digital.", 0, 'C');
        } else {
            $fpdf->SetXY($x + 4, $contentY + 9);
            $fpdf->SetFont('Arial', 'I', 7.5);
            $fpdf->SetTextColor(148, 163, 184);
            $fpdf->Cell($w - 8, 5, 'Berkas Digital Terlampir', 0, 1, 'C');
        }

        // Caption nama file di bagian bawah sel
        $fpdf->SetXY($x, $y + $h - 5);
        $fpdf->SetFont('Arial', '', 7);
        $fpdf->SetTextColor(71, 85, 105);

        $name = $entry['filename'];
        if (strlen($name) > 34) {
            $name = substr($name, 0, 31) . '...';
        }
        $fpdf->Cell($w, 4, $name, 0, 0, 'C');
    }
}
