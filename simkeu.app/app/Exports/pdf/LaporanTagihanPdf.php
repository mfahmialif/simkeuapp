<?php

namespace App\Exports\pdf;

use App\Services\Helper;
use App\Exports\pdf\CustomFpdf;
use App\Http\Controllers\Operasi\MhsJenisTagihanController;
use App\Services\TagihanMahasiswa;

class LaporanTagihanPdf
{
    /**
     * Save PDF File
     * @param mixed $data from request input ($request->all())
     */
    private static $total = 0;

    public static function pdf($data)
    {
        $fpdf = new CustomFpdf("P", "mm", "A4");
        $fpdf->addPage();

        $tagihan = TagihanMahasiswa::tagihan($data['nim']);

        self::header($data, $fpdf);
        self::body($data, $fpdf, $tagihan);
        self::footer($data, $fpdf);

        // // Save File PDF
        // $nim = $data['nim'];
        // $fpdf->Output('I', "Laporan Tagihan - $nim.pdf");
        // exit;

        $binary = $fpdf->Output('S');

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Laporan Tagihan ' . $data['nim'] . '.pdf"');
    }

    public static function header($data, $fpdf)
    {
        // Header
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->SetFontSpacing(3);
        $fpdf->Cell(170, 5, "", 0, 1, 'C');
        $fpdf->Cell(170, 5, "", 0, 1, 'C');
        $fpdf->Cell(160, 5, "", 0, 1, 'C');
        $fpdf->Image(asset('/img/full-logo-uii.png'), 40, 0, -210);

        $fpdf->SetFontSpacing(0);
        $fpdf->Line(10, 26, 210 - 10, 26);
        $fpdf->Cell(0, 7, "Jl. Raya Raci No.51, Telp. 087754452667", 0, 1, 'C');
        $fpdf->Line(10, 32, 210 - 10, 32);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->SetFontSpacing(0);
        $tanggal = date('d-m-Y');
        $fpdf->Cell(180, 7, "Tgl : $tanggal", 0, 1, 'R');
        $fpdf->SetFont('Arial', '', 11);
        $fpdf->SetFontSpacing(3);

        $fpdf->SetFont('Arial', 'B', 10);
        $fpdf->Cell(180, 5, "LAPORAN TUNGGAKAN", 0, 1, 'C');
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->SetFontSpacing(0);

        // NIM
        $fpdf->Cell(40, 5, "", 0, 1, 'L');
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(40, 5, "NIM", 0, 0, 'L');
        $nim = $data['nim'];
        $nama = $data['nama'];
        $fpdf->Cell(0, 5, ": $nim ( $nama )", 0, 1, 'L');
        // Prodi
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(40, 5, "Prodi", 0, 0, 'L');
        $prodi = $data['prodi'];
        $fpdf->Cell(0, 5, ": $prodi", 0, 1, 'L');
        // Tahun Akademik
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(40, 5, "Tahun Angkatan", 0, 0, 'L');
        $tahunAkademik = $data['tahun_akademik'];
        $fpdf->Cell(0, 5, ": $tahunAkademik", 0, 1, 'L');
        // Deposit
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(40, 5, "Deposit", 0, 0, 'L');
        $deposit = $data['deposit'];
        $fpdf->Cell(0, 5, ": Rp. $deposit", 0, 1, 'L');
    }

    public static function body($data, $fpdf, $tagihan)
    {
        // Table
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(0, 7, "", 0, 1, 'L');
        $fpdf->Cell(10, 7, "No.", 1, 0, 'C');
        $fpdf->Cell(50, 7, "Jenis Pembayaran", 1, 0, 'C');
        $fpdf->Cell(90, 7, "Keterangan", 1, 0, 'C');
        $fpdf->Cell(40, 7, "Sub Total(Rp)", 1, 1, 'C');

        $data = $tagihan['list_tagihan'];

        $total = 0;
        if ($data) {
            $i = 1;
            foreach ($data as $key => $t) {
                $dibayar = $t->dibayar > 0 ? " (dibayar Rp. $t->dibayar)" : '';
                $dispensasi = $t->status_dispensasi && $t->jenis_dispensasi != "Beasiswa" ? " (dispensasi ($t->jenis_dispensasi) Rp. $t->jumlah_dispensasi)" : '';
                $status = $t->sisa > 0 ? 'BELUM LUNAS' : 'LUNAS';
                $keterangan = $status . $dibayar . $dispensasi;
                $subTotal = $t->sisa;

                $offset = 0;
                $posY = $fpdf->getY();
                $posX = $fpdf->getX();

                // set how much line space and get higher (max) line space
                $offset = [
                    'no' => floor(strlen($i) / 3),
                    'jenisPembayaran' => floor(strlen($t->nama) / 24),
                    'keterangan' => floor(strlen($keterangan) / 42),
                    'subTotal' => floor(strlen($subTotal) / 18),
                ];
                $max = max($offset);
                // add line space (\n) depend on $max - offset
                $offset = [
                    'no' => str_repeat("\n", $max - $offset['no'] + 1),
                    'jenisPembayaran' => str_repeat("\n", $max - $offset['jenisPembayaran'] + 1),
                    'keterangan' => str_repeat("\n", $max - $offset['keterangan'] + 1),
                    'subTotal' => str_repeat("\n", $max - $offset['subTotal'] + 1),
                ];

                $fpdf->setXY($posX, $posY);
                $fpdf->MultiCell(10, 7, $i . $offset['no'], 1, 'C', 0);
                $posX += 10;
                $fpdf->setXY($posX, $posY);
                $fpdf->MultiCell(50, 7, $t->nama . $offset['jenisPembayaran'], 1, 'L', 0);
                $posX += 50;
                $fpdf->setXY($posX, $posY);
                $fpdf->MultiCell(90, 7, $keterangan . $offset['keterangan'], 1, 'L', 0);
                $posX += 90;
                $fpdf->setXY($posX, $posY);
                $fpdf->MultiCell(40, 7, $subTotal . $offset['subTotal'], 1, 'R', 0);

                $i++;

                $total += $subTotal;

                if ($posY > 230) {
                    $fpdf->AddPage();
                }
            }
        }
        // Total
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(150, 7, "Total :", 1, 0, 'R');
        $fpdf->Cell(40, 7, "Rp. " . number_format($total, 0, ',', '.'), 1, 1, 'R');
        $terbilang = Helper::terbilang($total);
        if ($terbilang == "") {
            $terbilang = "nol";
        }
        // $fpdf->SetFillColor(0, 0, 0); // Light gray
        $fpdf->SetFont('Arial', 'IB', 9.5);
        $fpdf->Cell(190, 7, "Terbilang : $terbilang rupiah", 1, 1, 'L');

        $fpdf->SetFont('Arial', 'B', 9.5);
        $fpdf->Cell(190, 7, "REKENING UNIVERSITAS ISLAM INTERNASIONAL DARULLUGHAH WADDA'WAH", 0, 1, 'L');
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(190, 4, "Bank BSI", 0, 1, 'L');
        $fpdf->Cell(190, 4, "Kode bank : 451", 0, 1, 'L');
        $fpdf->Cell(190, 4, "An. UII DALWA", 0, 1, 'L');
        $fpdf->Cell(13, 4, "No Rek. ", 0, 0, 'L');
        $fpdf->SetFont('Arial', 'B', 9.5);
        $fpdf->Cell(177, 4, "7751999997", 0, 1, 'L');
        $fpdf->SetFont('Arial', '', 9.5);
        $fpdf->Cell(190, 4, "Konfirmasi transfer ke no berikut :", 0, 1, 'L');
        $fpdf->SetFont('Arial', 'B', 9.5);
        $fpdf->Cell(190, 4, "(087754452667)", 0, 1, 'L');
        $fpdf->SetFont('Arial', '', 9.5);
        self::$total = $total;
        return $total;
    }

    public static function footer($data, $fpdf)
    {
        $fpdf->setY(-70);
        $fpdf->SetFont('Arial', '', 9);
        $fpdf->Cell(0, 4, "", 0, 1, 'L');
        $fpdf->Cell(155, 4, "", 0, 'L');
        $fpdf->Cell(30, 4, "Bangil,", 0, 1, 'C');
        $fpdf->Cell(155, 4, "", 0, 'L');
        $fpdf->Cell(30, 4, date('Y-m-d'), 0, 1, 'C');

        $fpdf->Cell(0, 8, "", 0, 1, 'L');
        $terbilang = Helper::terbilang(self::$total);
        if ($terbilang == "") {
            $terbilang = "nol";
        }

        $posY = $fpdf->getY();
        $posX = $fpdf->getX();
        $fpdf->setXY($posX, $posY);

        $posX += 0;
        $fpdf->setXY($posX, $posY);
        // $fpdf->MultiCell(150, 4, "Terbilang : $terbilang rupiah", 0, 'L');
        $fpdf->MultiCell(150, 4, "", 0, 'L');
        $fpdf->Ln(0);
        $fpdf->MultiCell(150, 4, "Terimakasih Atas Pembayaran Anda", 0, 'L',);
        $posX += 150;
        $fpdf->setXY($posX, $posY);
        $fpdf->MultiCell(40, 4, '(' . @\Auth::user()->name . ')', 0, 'C');
    }
}
