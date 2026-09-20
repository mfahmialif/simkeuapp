<?php

namespace App\Exports\pdf;

use App\Services\Helper;
use Carbon\Carbon;
use Codedge\Fpdf\Fpdf\Fpdf;
use Illuminate\Support\Facades\Storage;

class PemasukanUmumPdf
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
                $tempJpg = tempnam(sys_get_temp_dir(), 'pu_img_') . '.jpg';
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

    public static function generate($item)
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

        // 2. Judul Dokumen
        $fpdf->SetFont('Arial', 'B', 12);
        $fpdf->SetTextColor(30, 41, 59); // Slate dark
        $fpdf->Cell(190, 6, 'BUKTI PENERIMAAN KAS / PEMASUKAN UMUM', 0, 1, 'C');

        $fpdf->SetFont('Arial', 'B', 9.5);
        $fpdf->SetTextColor(71, 85, 105); // Slate
        $fpdf->Cell(190, 5, 'Nomor: ' . $item->no_transaksi, 0, 1, 'C');
        $fpdf->SetTextColor(0, 0, 0);

        // Garis pemisah judul
        $fpdf->SetDrawColor(203, 213, 225); // Slate 300
        $fpdf->SetLineWidth(0.4);
        $fpdf->Line(10, $fpdf->GetY() + 2, 200, $fpdf->GetY() + 2);
        $fpdf->Ln(5);

        // 3. Info Kotak Detail Transaksi (Desain Rapi & Elegan)
        $tglCarbon = $item->tanggal ? Carbon::parse($item->tanggal)->locale('id') : null;
        $tglStr = $tglCarbon ? $tglCarbon->translatedFormat('l, d F Y - H:i') . ' WIB' : '-';
        $petugasNama = $item->petugas->name ?? $item->petugas->username ?? 'Petugas';
        $metodePembayaran = $item->jenisPembayaran->nama ?? 'Tunai';
        if ($item->jenisPembayaran && !empty($item->jenisPembayaran->kategori)) {
            $metodePembayaran .= ' (' . $item->jenisPembayaran->kategori . ')';
        }
        $keteranganText = $item->keterangan ?: '-';
        $lampiranList = $item->lampiran_list ?? [];
        $totalLampiran = count($lampiranList);
        $lampiranStr = $totalLampiran > 0 ? $totalLampiran . ' Berkas Dokumen Terlampir' : 'Tidak ada berkas lampiran';

        $tableStartY = $fpdf->GetY();

        // Header Card Informasi
        $fpdf->SetFillColor(30, 58, 138); // Navy Blue
        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(190, 6.5, '   INFORMASI RINCIAN TRANSAKSI', 0, 1, 'L', true);
        $fpdf->SetTextColor(0, 0, 0);

        // Helper untuk baris tabel
        $renderRow = function (string $label, string $value, bool $isMulti = false) use ($fpdf) {
            $startX = 10;
            $startY = $fpdf->GetY();
            $labelW = 42;
            $sepW = 4;
            $valW = 144;
            $fpdf->SetDrawColor(226, 232, 240); // Slate 200

            if ($isMulti) {
                $fpdf->SetFont('Arial', '', 8.5);
                $cleanText = str_replace(["\r\n", "\r"], "\n", $value);
                $lines = explode("\n", $cleanText);
                $totalLines = 0;
                foreach ($lines as $l) {
                    $w = $fpdf->GetStringWidth($l);
                    $totalLines += max(1, (int) ceil($w / ($valW - 6)));
                }
                $rowH = max(6.5, $totalLines * 5 + 2);

                // Background label kolom kiri
                $fpdf->SetFillColor(248, 250, 252);
                $fpdf->Rect($startX, $startY, $labelW + $sepW, $rowH, 'F');

                // Tulis label & separator
                $fpdf->SetXY($startX, $startY + ($rowH > 7 ? 1.5 : 0.5));
                $fpdf->SetFont('Arial', 'B', 8.5);
                $fpdf->SetTextColor(51, 65, 85);
                $fpdf->Cell($labelW, 5.5, '  ' . $label, 0, 0, 'L');
                $fpdf->Cell($sepW, 5.5, ':', 0, 0, 'C');

                // Tulis teks value sekali saja
                $fpdf->SetXY($startX + $labelW + $sepW, $startY + 1);
                $fpdf->SetFont('Arial', '', 8.5);
                $fpdf->SetTextColor(15, 23, 42);
                $fpdf->MultiCell($valW, 5, $cleanText, 0, 'L');

                $fpdf->SetY($startY + $rowH);
                $fpdf->Line($startX, $startY + $rowH, $startX + 190, $startY + $rowH);
            } else {
                $rowH = 6.5;
                $fpdf->SetFillColor(248, 250, 252);
                $fpdf->SetFont('Arial', 'B', 8.5);
                $fpdf->SetTextColor(51, 65, 85);
                $fpdf->Cell($labelW, $rowH, '  ' . $label, 'B', 0, 'L', true);
                $fpdf->Cell($sepW, $rowH, ':', 'B', 0, 'C', true);

                $fpdf->SetFont('Arial', '', 8.5);
                $fpdf->SetTextColor(15, 23, 42);
                $fpdf->Cell($valW, $rowH, $value, 'B', 1, 'L', false);
            }
        };

        // Render Baris-Baris Informasi
        $renderRow('No. Bukti Transaksi', $item->no_transaksi);
        $renderRow('Tanggal & Waktu', $tglStr);
        $renderRow('Metode Pembayaran', $metodePembayaran);
        $renderRow('Petugas Penerima', $petugasNama);
        $renderRow('Keterangan / Keperluan', $keteranganText, true);
        $renderRow('Dokumen Lampiran', $lampiranStr);

        // Bingkai luar tabel transaksi
        $tableTotalH = $fpdf->GetY() - $tableStartY;
        $fpdf->SetDrawColor(203, 213, 225);
        $fpdf->SetLineWidth(0.35);
        $fpdf->Rect(10, $tableStartY, 190, $tableTotalH, 'D');

        $fpdf->Ln(3);

        // 4. Highlight Box Nominal & Terbilang (Desain Modern Finansial)
        $boxY = $fpdf->GetY();
        $boxH = 18;

        // Background soft green
        $fpdf->SetFillColor(240, 253, 244); // Green 50
        $fpdf->SetDrawColor(187, 247, 208); // Green 200
        $fpdf->SetLineWidth(0.3);
        $fpdf->Rect(10, $boxY, 190, $boxH, 'DF');

        // Left accent strip (Dark green)
        $fpdf->SetFillColor(5, 150, 105); // Green 600
        $fpdf->Rect(10, $boxY, 3.5, $boxH, 'F');

        // Label Total Nominal
        $fpdf->SetXY(16, $boxY + 2.5);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->SetTextColor(4, 120, 87); // Green 700
        $fpdf->Cell(85, 4.5, 'TOTAL NOMINAL DITERIMA', 0, 0, 'L');

        // Nilai Nominal Besar
        $fpdf->SetFont('Arial', 'B', 13);
        $fpdf->SetTextColor(6, 95, 70); // Green 800
        $fpdf->Cell(95, 5, 'Rp ' . number_format($item->nominal, 0, ',', '.'), 0, 1, 'R');

        // Terbilang
        $terbilangText = ucfirst(trim(Helper::terbilang((float) $item->nominal)));
        if (empty($terbilangText)) {
            $terbilangText = 'Nol';
        }

        $fpdf->SetXY(16, $boxY + 8.5);
        $fpdf->SetFont('Arial', 'I', 8.5);
        $fpdf->SetTextColor(51, 65, 85);
        $fpdf->MultiCell(180, 4.5, 'Terbilang: "' . $terbilangText . ' rupiah"', 0, 'L');

        $fpdf->SetY($boxY + $boxH + 4);

        // 5. Tanda Tangan (2 Kolom Rapi)
        $tglCetak = Carbon::now()->locale('id')->translatedFormat('d F Y');
        $fpdf->SetFont('Arial', '', 8.5);
        $fpdf->SetTextColor(30, 41, 59);

        $fpdf->Cell(95, 4.5, 'Diterima oleh,', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, 'Bangil, ' . $tglCetak, 0, 1, 'C');

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(95, 4.5, 'Petugas Penerima', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, 'Bagian Keuangan / Kasir', 0, 1, 'C');

        $fpdf->Ln(16);

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(95, 4.5, '( ' . $petugasNama . ' )', 0, 0, 'C');
        $fpdf->Cell(95, 4.5, '( .................................................... )', 0, 1, 'C');

        // 6. Lampiran Foto / Berkas Minimalis
        // Mengumpulkan berkas gambar valid (termasuk WebP yang dikonversi)
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
            // Cek apakah muat di halaman 1 (butuh minimal 65mm untuk 1 baris)
            $availableSpace = 280 - $fpdf->GetY();
            if ($availableSpace < 65) {
                $fpdf->AddPage();
            } else {
                $fpdf->Ln(4);
            }

            // Header Bagian Lampiran Minimalis
            $fpdf->SetFillColor(241, 245, 249); // Slate 100
            $fpdf->SetDrawColor(203, 213, 225); // Slate 300
            $fpdf->SetTextColor(30, 41, 59);
            $fpdf->SetFont('Arial', 'B', 8.5);
            $fpdf->Cell(190, 6, '   DOKUMEN LAMPIRAN BUKTI TRANSAKSI (' . count($validImages) . ' Berkas Gambar)', 1, 1, 'L', true);
            $fpdf->Ln(2.5);

            // Render dalam grid 2 kolom minimalis
            $colWidth = 92;
            $boxHeight = 50;
            $colSpacing = 6;
            $startX = 10;

            for ($i = 0; $i < count($validImages); $i += 2) {
                // Cek apakah muat untuk 1 baris foto (boxHeight + caption + margin = ~58mm)
                if ($fpdf->GetY() + $boxHeight + 8 > 280) {
                    $fpdf->AddPage();
                    $fpdf->SetFont('Arial', 'B', 8);
                    $fpdf->SetTextColor(71, 85, 105);
                    $fpdf->Cell(190, 5, 'Lanjutan Lampiran - No. Bukti: ' . $item->no_transaksi, 0, 1, 'L');
                    $fpdf->Ln(2);
                }

                $rowY = $fpdf->GetY();

                // Kolom 1
                $img1 = $validImages[$i];
                self::renderMinimalistImageCell($fpdf, $img1, $startX, $rowY, $colWidth, $boxHeight);

                // Kolom 2 (jika ada)
                if (isset($validImages[$i + 1])) {
                    $img2 = $validImages[$i + 1];
                    self::renderMinimalistImageCell($fpdf, $img2, $startX + $colWidth + $colSpacing, $rowY, $colWidth, $boxHeight);
                }

                $fpdf->SetY($rowY + $boxHeight + 7);
            }
        }

        $binary = $fpdf->Output('S');

        // Bersihkan file sementara hasil konversi
        foreach ($tempFiles as $tf) {
            if (is_file($tf)) {
                @unlink($tf);
            }
        }

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Bukti Pemasukan ' . str_replace('/', '-', $item->no_transaksi) . '.pdf"');
    }

    /**
     * Render 1 sel foto minimalis dengan aspect ratio terjaga dan caption rapi
     */
    private static function renderMinimalistImageCell(CustomFpdf $fpdf, array $imgData, float $x, float $y, float $w, float $h): void
    {
        // Kotak wadah foto
        $fpdf->SetFillColor(248, 250, 252); // Slate 50
        $fpdf->SetDrawColor(226, 232, 240); // Slate 200
        $fpdf->SetLineWidth(0.25);
        $fpdf->Rect($x, $y, $w, $h, 'DF');

        // Hitung proporsi foto agar tidak terdistorsi (fit inside box dengan margin 3mm)
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

        // Caption label di bawah gambar
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
