<?php

namespace App\Exports\pdf;

use Carbon\Carbon;
use Codedge\Fpdf\Fpdf\Fpdf;
use Illuminate\Support\Facades\Storage;

class PemasukanUmumBundlingPdf
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
                $tempJpg = tempnam(sys_get_temp_dir(), 'pu_bdl_') . '.jpg';
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
        $fpdf->Cell(190, 6, 'REKAP LAPORAN PEMASUKAN UMUM', 0, 1, 'C');

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
            $fpdf->Cell(46, 7, 'Keterangan', 1, 0, 'C', true);
            $fpdf->Cell(24, 7, 'Metode', 1, 0, 'C', true);
            $fpdf->Cell(24, 7, 'Petugas', 1, 0, 'C', true);
            $fpdf->Cell(28, 7, 'Nominal (Rp)', 1, 1, 'C', true);
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
            if (strlen($keterangan) > 35) {
                $keterangan = substr($keterangan, 0, 32) . '...';
            }

            $metode = $item->jenisPembayaran->nama ?? 'Tunai';
            if (strlen($metode) > 14) {
                $metode = substr($metode, 0, 12) . '...';
            }

            $petugas = $item->petugas->name ?? $item->petugas->username ?? '-';
            if (strlen($petugas) > 15) {
                $petugas = substr($petugas, 0, 13) . '...';
            }

            $fpdf->SetFillColor($no % 2 === 0 ? 248 : 255, $no % 2 === 0 ? 250 : 255, $no % 2 === 0 ? 252 : 255);
            $fpdf->Cell(8, 6, (string) $no, 1, 0, 'C', true);
            $fpdf->Cell(32, 6, $item->no_transaksi, 1, 0, 'C', true);
            $fpdf->Cell(28, 6, $tglStr, 1, 0, 'C', true);
            $fpdf->Cell(46, 6, ' ' . $keterangan, 1, 0, 'L', true);
            $fpdf->Cell(24, 6, ' ' . $metode, 1, 0, 'C', true);
            $fpdf->Cell(24, 6, ' ' . $petugas, 1, 0, 'L', true);
            $fpdf->Cell(28, 6, number_format($item->nominal, 0, ',', '.') . ' ', 1, 1, 'R', true);

            $no++;
        }

        if ($totalTransaksi === 0) {
            $fpdf->Cell(190, 8, 'Tidak ada data pemasukan umum yang sesuai dengan filter.', 1, 1, 'C');
        }

        // 5. Total Row
        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->SetFillColor(240, 253, 244); // Green 50
        $fpdf->SetTextColor(6, 95, 70);
        $fpdf->Cell(162, 7, 'TOTAL DITERIMA (' . $totalTransaksi . ' Transaksi)  ', 1, 0, 'R', true);
        $fpdf->Cell(28, 7, 'Rp ' . number_format($totalNominal, 0, ',', '.') . ' ', 1, 1, 'R', true);
        $fpdf->SetTextColor(0, 0, 0);

        // 6. Tanda Tangan
        if ($fpdf->GetY() > 230) {
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
            $lampiranList = $item->lampiran_list ?? [];
            $validImages = [];
            foreach ($lampiranList as $idx => $att) {
                $rawPath = Storage::disk('public')->path($att['path']);
                $prepared = self::prepareImageForFpdf($rawPath, $tempFiles);
                if ($prepared) {
                    $validImages[] = [
                        'index' => $idx + 1,
                        'name'  => $att['name'] ?? basename($att['path']),
                        'path'  => $prepared,
                    ];
                }
            }

            if (count($validImages) > 0) {
                $transactionsWithAttachments[] = [
                    'item'   => $item,
                    'images' => $validImages,
                ];
            }
        }

        if (count($transactionsWithAttachments) > 0) {
            $fpdf->AddPage();

            // Header Utama Lampiran Bundling
            $fpdf->SetFont('Arial', 'B', 12);
            $fpdf->SetTextColor(30, 41, 59);
            $fpdf->Cell(190, 6, 'LAMPIRAN BUKTI TRANSAKSI PEMASUKAN UMUM', 0, 1, 'C');
            $fpdf->SetFont('Arial', '', 8.5);
            $fpdf->SetTextColor(71, 85, 105);
            $fpdf->Cell(190, 5, 'Daftar berkas foto dan bukti fisik transaksi pemasukan umum yang terlampir', 0, 1, 'C');
            $fpdf->SetDrawColor(203, 213, 225);
            $fpdf->Line(10, $fpdf->GetY() + 2, 200, $fpdf->GetY() + 2);
            $fpdf->Ln(4);

            $colWidth = 92;
            $boxHeight = 48;
            $colSpacing = 6;
            $startX = 10;

            foreach ($transactionsWithAttachments as $tItem) {
                $item = $tItem['item'];
                $images = $tItem['images'];

                // Cek apakah muat header transaksi + 1 baris gambar (~64mm)
                if ($fpdf->GetY() + 64 > 280) {
                    $fpdf->AddPage();
                }

                // Subheader Transaksi
                $fpdf->SetFillColor(241, 245, 249);
                $fpdf->SetDrawColor(203, 213, 225);
                $fpdf->SetFont('Arial', 'B', 8.5);
                $fpdf->SetTextColor(30, 58, 138);

                $tglDetail = $item->tanggal ? Carbon::parse($item->tanggal)->translatedFormat('d/m/Y H:i') : '-';
                $nomText = 'Rp ' . number_format($item->nominal, 0, ',', '.');
                $subTitle = ' No. Bukti: ' . $item->no_transaksi . '  |  Tgl: ' . $tglDetail . '  |  Nominal: ' . $nomText;
                if (!empty($item->keterangan)) {
                    $subTitle .= '  |  ' . (strlen($item->keterangan) > 30 ? substr($item->keterangan, 0, 27) . '...' : $item->keterangan);
                }

                $fpdf->Cell(190, 6, $subTitle, 1, 1, 'L', true);
                $fpdf->Ln(2);

                // Render gambar transaksi ini dalam grid 2 kolom
                for ($i = 0; $i < count($images); $i += 2) {
                    if ($fpdf->GetY() + $boxHeight + 6 > 280) {
                        $fpdf->AddPage();
                        $fpdf->SetFont('Arial', 'B', 8);
                        $fpdf->SetTextColor(71, 85, 105);
                        $fpdf->Cell(190, 5, 'Lanjutan Lampiran - No. Bukti: ' . $item->no_transaksi, 0, 1, 'L');
                        $fpdf->Ln(2);
                    }

                    $rowY = $fpdf->GetY();

                    $img1 = $images[$i];
                    self::renderMinimalistImageCell($fpdf, $img1, $startX, $rowY, $colWidth, $boxHeight);

                    if (isset($images[$i + 1])) {
                        $img2 = $images[$i + 1];
                        self::renderMinimalistImageCell($fpdf, $img2, $startX + $colWidth + $colSpacing, $rowY, $colWidth, $boxHeight);
                    }

                    $fpdf->SetY($rowY + $boxHeight + 5);
                }

                $fpdf->Ln(2);
            }
        }

        $binary = $fpdf->Output('S');

        // Hapus file sementara
        foreach ($tempFiles as $tf) {
            if (is_file($tf)) {
                @unlink($tf);
            }
        }

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Rekap Pemasukan Umum ' . date('Ymd_His') . '.pdf"');
    }

    private static function renderMinimalistImageCell(CustomFpdf $fpdf, array $imgData, float $x, float $y, float $w, float $h): void
    {
        $fpdf->SetFillColor(248, 250, 252);
        $fpdf->SetDrawColor(226, 232, 240);
        $fpdf->SetLineWidth(0.25);
        $fpdf->Rect($x, $y, $w, $h, 'DF');

        $maxW = $w - 6;
        $maxH = $h - 9;

        $imgInfo = @getimagesize($imgData['path']);
        $origW = $imgInfo[0] ?? 100;
        $origH = $imgInfo[1] ?? 100;

        $ratio = min($maxW / $origW, $maxH / $origH);
        $renderW = $origW * $ratio;
        $renderH = $origH * $ratio;

        $imgX = $x + ($w - $renderW) / 2;
        $imgY = $y + 3 + ($maxH - $renderH) / 2;

        try {
            $fpdf->Image($imgData['path'], $imgX, $imgY, $renderW, $renderH);
        } catch (\Throwable $e) {
            $fpdf->SetXY($x, $y + ($h / 2) - 3);
            $fpdf->SetFont('Arial', 'I', 7.5);
            $fpdf->SetTextColor(150, 150, 150);
            $fpdf->Cell($w, 6, 'Gagal memuat gambar', 0, 0, 'C');
        }

        $fpdf->SetXY($x, $y + $h - 5.5);
        $fpdf->SetFont('Arial', '', 7.5);
        $fpdf->SetTextColor(71, 85, 105);

        $name = $imgData['name'];
        if (strlen($name) > 34) {
            $name = substr($name, 0, 31) . '...';
        }
        $caption = '[' . $imgData['index'] . '] ' . $name;
        $fpdf->Cell($w, 4.5, $caption, 0, 0, 'C');
    }
}
