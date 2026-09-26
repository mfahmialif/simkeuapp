<?php

namespace App\Exports\pdf;

use App\Services\Helper;
use Carbon\Carbon;
use Codedge\Fpdf\Fpdf\Fpdf;
use Illuminate\Support\Facades\Storage;

class PengembalianDanaPdf
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
                $tempJpg = tempnam(sys_get_temp_dir(), 'pd_img_') . '.jpg';
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
        $fpdf->Cell(190, 6, 'BUKTI PENGELUARAN KAS / PENGEMBALIAN DANA', 0, 1, 'C');

        $fpdf->SetFont('Arial', 'B', 9.5);
        $fpdf->SetTextColor(71, 85, 105); // Slate
        $fpdf->Cell(190, 5, 'Nomor: ' . ($item->no_transaksi ?: sprintf('%04d/PD/%s/%s', $item->id, Carbon::now()->format('m'), Carbon::now()->format('Y'))), 0, 1, 'C');
        $fpdf->SetTextColor(0, 0, 0);

        // Garis pemisah judul
        $fpdf->SetDrawColor(203, 213, 225); // Slate 300
        $fpdf->SetLineWidth(0.4);
        $fpdf->Line(10, $fpdf->GetY() + 2, 200, $fpdf->GetY() + 2);
        $fpdf->Ln(5);

        // 3. Info Kotak Detail Transaksi
        $tglCarbon = $item->tanggal ? Carbon::parse($item->tanggal)->locale('id') : null;
        $tglStr = $tglCarbon ? $tglCarbon->translatedFormat('l, d F Y - H:i') . ' WIB' : '-';
        $petugasNama = $item->petugas->name ?? $item->petugas->username ?? 'Petugas Keuangan';
        $keteranganText = $item->keterangan ?: '-';

        $statusBuktiMasuk = $item->file_bukti_masuk ? 'Tersedia (' . basename($item->file_bukti_masuk) . ')' : 'Tidak Ada';
        $statusBuktiKeluar = $item->file_bukti_keluar ? 'Tersedia (' . basename($item->file_bukti_keluar) . ')' : 'Belum Ada';

        $tableStartY = $fpdf->GetY();

        // Header Card Informasi
        $fpdf->SetFillColor(30, 58, 138); // Navy Blue
        $fpdf->SetTextColor(255, 255, 255);
        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(190, 6.5, '   INFORMASI RINCIAN PENGEMBALIAN DANA', 0, 1, 'L', true);
        $fpdf->SetTextColor(0, 0, 0);

        // Helper untuk baris tabel
        $renderRow = function (string $label, string $value, bool $isMulti = false) use ($fpdf) {
            $startX = 10;
            $startY = $fpdf->GetY();
            $labelW = 44;
            $sepW = 4;
            $valW = 142;
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

                // Tulis teks value
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

        $metodeNama = $item->jenisPembayaran->nama ?? 'Tunai';

        // Render Baris-Baris Informasi
        $renderRow('No. Bukti Transaksi', $item->no_transaksi ?: '-');
        $renderRow('Tanggal & Waktu', $tglStr);
        $renderRow('Petugas Pencatat', $petugasNama);
        $renderRow('Metode Pengembalian', $metodeNama);
        if (!empty($item->nama_bank) || !empty($item->no_rek_tujuan)) {
            $rekStr = trim(($item->nama_bank ? $item->nama_bank . ' - ' : '') . ($item->no_rek_tujuan ?: ''));
            $renderRow('Rekening Tujuan', $rekStr);
        }
        $renderRow('Keterangan / Alasan', $keteranganText, true);
        $renderRow('Bukti Dana Masuk', $statusBuktiMasuk);
        $renderRow('Bukti Dana Dikeluarkan', $statusBuktiKeluar);

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
        $fpdf->Cell(85, 4.5, 'TOTAL NOMINAL PENGEMBALIAN DANA', 0, 0, 'L');

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

        // 5. Tanda Tangan (Sebelah Kanan)
        $tglCetak = Carbon::now()->locale('id')->translatedFormat('d F Y');
        $fpdf->SetFont('Arial', '', 8.5);
        $fpdf->SetTextColor(30, 41, 59);

        $fpdf->Cell(120, 4.5, '', 0, 0, 'C');
        $fpdf->Cell(70, 4.5, 'Bangil, ' . $tglCetak, 0, 1, 'C');

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(120, 4.5, '', 0, 0, 'C');
        $fpdf->Cell(70, 4.5, 'Petugas Keuangan / Kasir,', 0, 1, 'C');

        $fpdf->Ln(16);

        $fpdf->SetFont('Arial', 'B', 8.5);
        $fpdf->Cell(120, 4.5, '', 0, 0, 'C');
        $fpdf->Cell(70, 4.5, '( ' . $petugasNama . ' )', 0, 1, 'C');

        // 6. Lampiran Foto / Berkas Minimalis (Bukti Dana Masuk & Keluar)
        $attachmentEntries = [];

        // Bukti Dana Masuk
        if (!empty($item->file_bukti_masuk)) {
            $rawPath = Storage::disk('public')->path($item->file_bukti_masuk);
            $ext = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            $preparedImg = $isImg ? self::prepareImageForFpdf($rawPath, $tempFiles) : null;

            $attachmentEntries[] = [
                'type'      => 'masuk',
                'title'     => '1. Bukti Dana Masuk',
                'filename'  => basename($item->file_bukti_masuk),
                'is_image'  => !empty($preparedImg),
                'image_path'=> $preparedImg,
                'is_pdf'    => $ext === 'pdf',
            ];
        }

        // Bukti Dana Keluar
        if (!empty($item->file_bukti_keluar)) {
            $rawPath = Storage::disk('public')->path($item->file_bukti_keluar);
            $ext = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            $preparedImg = $isImg ? self::prepareImageForFpdf($rawPath, $tempFiles) : null;

            $attachmentEntries[] = [
                'type'      => 'keluar',
                'title'     => '2. Bukti Dana Keluar',
                'filename'  => basename($item->file_bukti_keluar),
                'is_image'  => !empty($preparedImg),
                'image_path'=> $preparedImg,
                'is_pdf'    => $ext === 'pdf',
            ];
        }

        if (count($attachmentEntries) > 0) {
            // Cek sisa ruang halaman (butuh ~65mm untuk baris lampiran)
            $availableSpace = 280 - $fpdf->GetY();
            if ($availableSpace < 65) {
                $fpdf->AddPage();
            } else {
                $fpdf->Ln(4);
            }

            // Header Lampiran
            $fpdf->SetFillColor(241, 245, 249); // Slate 100
            $fpdf->SetDrawColor(203, 213, 225); // Slate 300
            $fpdf->SetTextColor(30, 41, 59);
            $fpdf->SetFont('Arial', 'B', 8.5);
            $fpdf->Cell(190, 6, '   BERKAS LAMPIRAN BUKTI TRANSAKSI (' . count($attachmentEntries) . ' Berkas Terlampir)', 1, 1, 'L', true);
            $fpdf->Ln(2.5);

            $colWidth = 92;
            $boxHeight = 52;
            $colSpacing = 6;
            $startX = 10;
            $rowY = $fpdf->GetY();

            foreach ($attachmentEntries as $idx => $entry) {
                $cellX = $idx === 0 ? $startX : ($startX + $colWidth + $colSpacing);
                self::renderAttachmentCell($fpdf, $entry, $cellX, $rowY, $colWidth, $boxHeight);
            }

            $fpdf->SetY($rowY + $boxHeight + 7);
        }

        $binary = $fpdf->Output('S');

        // Bersihkan file sementara hasil konversi
        foreach ($tempFiles as $tf) {
            if (is_file($tf)) {
                @unlink($tf);
            }
        }

        $noDoc = $item->no_transaksi ?: ('PD-' . $item->id);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Bukti Pengembalian Dana ' . str_replace('/', '-', $noDoc) . '.pdf"');
    }

    /**
     * Render sel lampiran (gambar atau dokumen PDF)
     */
    private static function renderAttachmentCell(CustomFpdf $fpdf, array $entry, float $x, float $y, float $w, float $h): void
    {
        // Kotak wadah
        $fpdf->SetFillColor(248, 250, 252); // Slate 50
        $fpdf->SetDrawColor(226, 232, 240); // Slate 200
        $fpdf->SetLineWidth(0.25);
        $fpdf->Rect($x, $y, $w, $h, 'DF');

        // Header kecil judul lampiran di dalam sel
        $fpdf->SetFillColor(241, 245, 249);
        $fpdf->Rect($x, $y, $w, 5.5, 'F');
        $fpdf->SetXY($x + 2, $y + 0.8);
        $fpdf->SetFont('Arial', 'B', 7.5);
        $fpdf->SetTextColor(30, 41, 59);
        $fpdf->Cell($w - 4, 4, $entry['title'], 0, 0, 'L');

        $contentY = $y + 6;
        $contentH = $h - 11.5;

        if ($entry['is_image'] && !empty($entry['image_path'])) {
            // Hitung proporsi gambar
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
            // Box informasi dokumen PDF
            $fpdf->SetXY($x + 4, $contentY + 8);
            $fpdf->SetFont('Arial', 'B', 9);
            $fpdf->SetTextColor(220, 38, 38); // Red
            $fpdf->Cell($w - 8, 5, '[ DOKUMEN DIGITAL PDF ]', 0, 1, 'C');

            $fpdf->SetXY($x + 4, $contentY + 14);
            $fpdf->SetFont('Arial', '', 7.5);
            $fpdf->SetTextColor(100, 116, 139);
            $fpdf->MultiCell($w - 8, 3.8, "Berkas berupa dokumen PDF digital resmi tersimpan di sistem SIMKEU.", 0, 'C');
        } else {
            // Format berkas lainnya
            $fpdf->SetXY($x + 4, $contentY + 12);
            $fpdf->SetFont('Arial', 'I', 8);
            $fpdf->SetTextColor(148, 163, 184);
            $fpdf->Cell($w - 8, 5, 'Berkas Digital Terlampir', 0, 1, 'C');
        }

        // Caption nama file di bagian bawah sel
        $fpdf->SetXY($x, $y + $h - 5.5);
        $fpdf->SetFont('Arial', '', 7.5);
        $fpdf->SetTextColor(71, 85, 105);

        $name = $entry['filename'];
        if (strlen($name) > 34) {
            $name = substr($name, 0, 31) . '...';
        }
        $fpdf->Cell($w, 4.5, $name, 0, 0, 'C');
    }
}
