<?php

namespace App\Exports\pdf;

use App\Models\KeuanganPembayaran;
use Carbon\Carbon;

class SlipGajiPdf
{
    /**
     * Save PDF File
     * @param mixed $data from request input ($request->all())
     */

    public static function pdf($data)
    {

        $fpdf = new CustomKopFpdf("L", "mm", "A5");
        $fpdf->AddPage();
        $fpdf->setDataSign([
            'nama' => @\Auth::user()->name ?? "TTD",
        ]);

        self::body($data, $fpdf);

        // // Save File PDF
        // $fpdf->Output('I', "Kwitansi Pembayaran $nomor.pdf");
        // exit;

        $binary = $fpdf->Output('S');  // <- ini kuncinya

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            // inline => ditampilkan di browser (tanpa forced download)
            ->header('Content-Disposition', 'inline; filename="slip' . '.pdf"');
    }

    public static function body($data, $fpdf)
    {
        // ==== SECTION: TITLE ====
        $fpdf->SetFont('Helvetica', 'B', 12);
        $fpdf->Cell(0, 8, "", 0, 1, 'C');
        $fpdf->Cell(0, 8, "SLIP DOSEN", 0, 1, 'C');
        $fpdf->Ln(3);


        // ==== SECTION: DATA PEGAWAI ====
        $fpdf->SetFont('Helvetica', '', 9);

        $leftWidth  = 35;
        $colonWidth = 3;
        $rightWidth = 60;
        // Baris 1
        $fpdf->Cell($leftWidth, 5, "Nama", 0, 0);
        $fpdf->Cell($colonWidth, 5, ":", 0, 0);
        $fpdf->Cell($rightWidth, 5, @$data->dosen->nama, 0, 0);

        $fpdf->Cell($leftWidth, 5, "Tanggal", 0, 0);
        $fpdf->Cell($colonWidth, 5, ":", 0, 0);
        $fpdf->Cell($rightWidth, 5, $fpdf->formatDate($data->tanggal), 0, 1);

        // Baris 2
        $fpdf->Cell($leftWidth, 5, "NIY", 0, 0);
        $fpdf->Cell($colonWidth, 5, ":", 0, 0);
        $fpdf->Cell($rightWidth, 5, @$data->dosen->kode, 0, 0);

        $fpdf->Cell($leftWidth, 5, "Prodi", 0, 0);
        $fpdf->Cell($colonWidth, 5, ":", 0, 0);
        $fpdf->Cell($rightWidth, 5, @$data->dosen->prodi->alias, 0, 1);

        $fpdf->Ln(4);



        // ==== SECTION: RINCIAN BAROKAH ====
        $fpdf->SetFont('Helvetica', 'B', 10);
        $fpdf->Cell(0, 6, "Rincian Barokah", 0, 1);

        $fpdf->SetFont('Helvetica', '', 9);

        // Jam & hari
        self::rowSalary($fpdf, "Jam", $data->jam ?? 0);
        self::rowSalary($fpdf, "Hari", $data->hari ?? 0);

        $fpdf->Cell(105, 1, "", "B", 1);
        $fpdf->Ln(2);

        // Barokah & Transport
        self::rowSalaryRp($fpdf, "Barokah", $data->barokah ?? 0);
        self::rowSalaryRp($fpdf, "Transport", $data->transport ?? 0);

        // Hitung total
        $penerimaan = $data->total;

        // ==== TOTAL DITERIMA ====
        $fpdf->SetFont('Helvetica', 'B', 9);
        self::rowSalaryRp($fpdf, "Total Diterima", $penerimaan);


        // ==== PENERIMAAN BAROKAH ====
        $fpdf->Ln(4);
        $fpdf->SetFont('Helvetica', 'B', 12);
        $fpdf->Cell(0, 6, "Penerimaan Barokah: Rp. " . number_format($penerimaan, 0, ',', '.'), 1, 1, 'C');


        // ==== TERBILANG ====
        $fpdf->SetFont('Helvetica', 'I', 10);
        $fpdf->Cell(0, 5, "Terbilang: " . ucfirst(self::terbilang($penerimaan)) . " rupiah", 1, 1, 'C');

        $fpdf->Ln(8); // space sebelum footer/signature
    }

    private static function terbilang($angka)
    {
        $angka = abs($angka);
        $baca = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];

        if ($angka < 12)
            return $baca[$angka];

        if ($angka < 20)
            return self::terbilang($angka - 10) . " belas";

        if ($angka < 100)
            return self::terbilang(intval($angka / 10)) . " puluh " . self::terbilang($angka % 10);

        if ($angka < 200)
            return "seratus " . self::terbilang($angka - 100);

        if ($angka < 1000)
            return self::terbilang(intval($angka / 100)) . " ratus " . self::terbilang($angka % 100);

        if ($angka < 2000)
            return "seribu " . self::terbilang($angka - 1000);

        if ($angka < 1000000)
            return self::terbilang(intval($angka / 1000)) . " ribu " . self::terbilang($angka % 1000);

        if ($angka < 1000000000)
            return self::terbilang(intval($angka / 1000000)) . " juta " . self::terbilang($angka % 1000000);

        return $angka;
    }


    private static function rowSalary($fpdf, $label, $value)
    {
        $fpdf->Cell(60, 6, $label, 0, 0);
        $fpdf->Cell(5, 6, ":", 0, 0);
        $fpdf->Cell(40, 6, number_format($value, 0, ',', '.'), 0, 1, 'R');
    }

    private static function rowSalaryRp($fpdf, $label, $value)
    {
        $fpdf->Cell(60, 6, $label, 0, 0);
        $fpdf->Cell(5, 6, ":", 0, 0);

        $formatted = "Rp. " . number_format($value, 0, ',', '.');

        $fpdf->Cell(40, 6, $formatted, 0, 1, 'R');
    }
}
