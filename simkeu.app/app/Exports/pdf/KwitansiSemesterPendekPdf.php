<?php

namespace App\Exports\pdf;

use App\Models\KeuanganPembayaranSemesterPendek;
use App\Services\Helper;
use App\Services\SemesterPendek;
use Codedge\Fpdf\Fpdf\Fpdf;

class KwitansiSemesterPendekPdf
{
    public static function pdf($pembayaran)
    {
        // Fetch KRS data from SIAKAD
        $krsData = null;
        try {
            $krsData = SemesterPendek::krsDetail($pembayaran->krs_id);
        } catch (\Exception $e) {
            // If SIAKAD unreachable, continue with limited data
        }

        $fpdf = new Fpdf("L", "mm", "A5");
        $fpdf->AddPage();

        // ─── Header ─────────────────────────────────────────
        $fpdf->SetFont('Courier', '', 10);
        $fpdf->Cell(170, 5, "", 0, 1, 'C');
        $fpdf->Cell(170, 5, "", 0, 1, 'C');
        $fpdf->Cell(160, 5, "", 0, 1, 'C');
        $fpdf->Image(asset('/img/full-logo-uii.png'), 40, 0, -210);

        $fpdf->Line(10, 26, 210 - 10, 26);
        $fpdf->Cell(0, 7, "Jl. Raya Raci No.51, Telp. 087754452667", 0, 1, 'C');
        $fpdf->Line(10, 32, 210 - 10, 32);

        // ─── Biodata ────────────────────────────────────────
        $fpdf->SetFont('Courier', '', 9);
        $fpdf->Cell(0, 1, "", 0, 1, 'L');

        // Nota
        $fpdf->Cell(40, 5, "Nota", 0, 0, 'L');
        $fpdf->Cell(95, 5, ": " . $pembayaran->nomor, 0, 0, 'L');

        // Periode
        $periode = $krsData->periode_semester_pendek->periode ?? '-';
        $fpdf->Cell(0, 5, "Periode : $periode", 0, 1, 'L');

        // Sudah diterima dari
        $fpdf->Cell(135, 5, "Sudah diterima dari ", 0, 0, 'L');
        $fpdf->Cell(0, 5, "", 0, 1, 'L');

        // NIM
        $nim = $krsData->nim ?? '-';
        $nama = $krsData->mhs_nama ?? '-';
        $fpdf->Cell(40, 5, "NIM", 0, 0, 'L');
        $fpdf->Cell(150, 5, ": $nim ($nama)", 0, 1, 'L');

        // Program Studi
        $prodi = $krsData->prodi_nama ?? '-';
        $fpdf->Cell(40, 5, "Program Studi", 0, 0, 'L');
        $fpdf->Cell(150, 5, ": $prodi", 0, 1, 'L');

        // Untuk Pembayaran
        $fpdf->Cell(40, 5, "Untuk Pembayaran", 0, 0, 'L');
        $fpdf->Cell(150, 5, ": Semester Pendek", 0, 1, 'L');

        // ─── Table Header ───────────────────────────────────
        $fpdf->SetFont('Courier', '', 9);
        $fpdf->Cell(10, 5, "", 0, 1, 'C');
        $fpdf->Cell(10, 5, "No.", 'B', 0, 'C');
        $fpdf->Cell(75, 5, "Keterangan", 'B', 0, 'C');
        $fpdf->Cell(70, 5, "Jenis Pembayaran", 'B', 0, 'C');
        $fpdf->Cell(35, 5, "Sub Total(Rp)", 'B', 1, 'R');

        // ─── Table Body (semua record dengan nomor yang sama) ─────
        $transaksi = KeuanganPembayaranSemesterPendek::with('jenisPembayaran')
            ->where('nomor', $pembayaran->nomor)
            ->get();

        $i = 1;
        $total = 0;
        foreach ($transaksi as $t) {
            $jp = $t->jenisPembayaran ? $t->jenisPembayaran->nama : '-';
            $fpdf->Cell(10, 5, "$i", 'B', 0, 'C');
            $fpdf->Cell(75, 5, "Semester Pendek", 'B', 0, 'C');
            $fpdf->Cell(70, 5, $jp, 'B', 0, 'C');
            $fpdf->Cell(35, 5, number_format($t->jumlah, 0, ',', '.'), 'B', 1, 'R');
            $total += $t->jumlah;
            $i++;
        }

        // ─── Total ──────────────────────────────────────────
        $fpdf->SetFont('Courier', 'B', 9);
        $fpdf->Cell(155, 5, "Total : Rp.", '', 0, 'R');
        $fpdf->Cell(35, 5, number_format($total, 0, ',', '.'), '', 1, 'R');

        // ─── Footer ─────────────────────────────────────────
        $fpdf->SetY(-65);
        $fpdf->SetFont('Courier', '', 9);
        $fpdf->Cell(0, 4, "", 0, 1, 'L');
        $fpdf->Cell(155, 4, "", 0, 'L');
        $fpdf->Cell(30, 4, "Bangil,", 0, 1, 'C');
        $fpdf->Cell(155, 4, "", 0, 'L');
        $fpdf->Cell(30, 4, date('d-m-Y', strtotime($pembayaran->tanggal)), 0, 1, 'C');

        $fpdf->Cell(0, 8, "", 0, 1, 'L');
        $terbilang = Helper::terbilang($total);
        if ($terbilang == "") {
            $terbilang = "nol";
        }

        $posY = $fpdf->getY();
        $posX = $fpdf->getX();
        $fpdf->setXY($posX, $posY);

        $fpdf->MultiCell(150, 4, "Terbilang : $terbilang rupiah", 0, 'L');
        $fpdf->Ln(0);
        $fpdf->MultiCell(150, 4, "Terimakasih Atas Pembayaran Anda", 0, 'L');
        $posX += 150;
        $fpdf->setXY($posX, $posY);
        $userName = $pembayaran->user ? $pembayaran->user->name : '-';
        $fpdf->MultiCell(40, 4, '(' . $userName . ')', 0, 'C');

        // ─── Output ─────────────────────────────────────────
        $binary = $fpdf->Output('S');

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Kwitansi SP ' . $pembayaran->nomor . '.pdf"');
    }
}
