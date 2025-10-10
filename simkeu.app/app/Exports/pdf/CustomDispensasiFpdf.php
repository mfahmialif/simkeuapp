<?php

namespace App\Http\Services;

use Codedge\Fpdf\Fpdf\Fpdf;

class CustomDispensasiFpdf extends Fpdf
{
    protected $FontSpacingPt;      // current font spacing in points
    protected $FontSpacing;        // current font spacing in user units
    protected $data;               

    public function setData($data)
    {
        $this->data = $data;
    }

    function SetFontSpacing($size)
    {
        if ($this->FontSpacingPt == $size)
            return;
        $this->FontSpacingPt = $size;
        $this->FontSpacing = $size / $this->k;
        if ($this->page > 0)
            $this->_out(sprintf('BT %.3f Tc ET', $size));
    }

    protected function _dounderline($x, $y, $txt)
    {
        // Underline text
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ') + (strlen($txt) - 1) * $this->FontSpacing;
        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
    }

    public function header()
    {

       // Header
       $this->SetFont('Courier', '', 10);
       $this->SetFontSpacing(3);
       $this->Cell(170, 5, "INSTITUT AGAMA ISLAM", 0, 1, 'C');
       $this->Cell(170, 5, "DARULLUGHAH WADDA 'WAH", 0, 1, 'C');
       $this->Cell(160, 5, "RACI BANGIL PASURUAN JAWA TIMUR", 0, 1, 'C');

       $this->SetFontSpacing(0);
       $this->Line(10, 26, 210 - 10, 26);
       $this->Cell(0, 7, "Jl. Raya Raci No.51 PO.Box.8 Bangil, Telp. (0343) 745317", 0, 1, 'C');
       $this->Line(10, 32, 210 - 10, 32);

       $data = $this->data;
       $nim = $data['nim'];
       $nama = $data['nama'];
       $semester = $data['semester'];
       $prodi = $data['prodi'];
       $angkatan = $data['angkatan'];
       //    Data Biodata Mahasiswa
       $this->SetFont('Courier', '', 9);
       $this->Cell(0, 1, "", 0, 1, 'L');
       $this->SetFontSpacing(0);
       $this->Cell(190, 5, "Tahun Angkatan : $angkatan", 0, 1, 'R');
       $this->SetFontSpacing(0);

       $this->Cell(45, 5, "NIM", 0, 0, 'L');
       $this->SetFontSpacing(0);
       $this->Cell(145, 5, ": $nim ($nama)", 0, 1, 'L');
       $this->SetFontSpacing(0);

       $this->Cell(45, 5, "Progam Studi", 0, 0, 'L');
       $this->SetFontSpacing(0);
       $this->Cell(145, 5, ": $prodi", 0, 1, 'L');
       $this->SetFontSpacing(0);

       $this->Cell(45, 5, "Semester", 0, 0, 'L');
       $this->SetFontSpacing(0);
       $this->Cell(145, 5, ": $semester", 0, 1, 'L');
       $this->SetFontSpacing(0);

       $this->Cell(45, 5, "Memperoleh Dispensasi", 0, 0, 'L');
       $this->SetFontSpacing(0);
       $this->Cell(145, 5, ":", 0, 1, 'L');
       $this->SetFontSpacing(0);

       $this->SetFont('Courier', '', 9);
       $this->Cell(10, 5, "No.", 'B', 0, 'C');
       $this->Cell(145, 5, "Nama Dispensasi", 'B', 0, 'C');
       $this->Cell(35, 5, "Sub Total(Rp)", 'B', 1, 'R');
    
    }

    public function footer()
    {

        $data = $this->data;

        // $transaksi = MhsTransaksiTagihan::where('nomor', $data['nomor'])->get();
        $total = 0;
        foreach ($data['list_jumlah_dispensasi'] as $ljd) {
            $total += $ljd;
        }

        $this->setY(-45);
        $this->SetFont('Courier', '', 9);
        $this->Cell(0, 4, "", 0, 1, 'L');
        $this->Cell(155, 4, "", 0, 'L');
        $this->Cell(30, 4, "Bangil,", 0, 1, 'C');
        $this->Cell(155, 4, "", 0, 'L');
        $this->Cell(30, 4, date('Y-m-d'), 0, 1, 'C');

        $this->Cell(0, 8, "", 0, 1, 'L');
        $terbilang = Helper::terbilang($total);

        $this->Cell(155, 4, "Terbilang : $terbilang rupiah", 0, 'L');
        $this->Cell(30, 4, '(' . auth()->user()->nama . ')', 0, 1, 'C');
        $this->Cell(155, 4, "Universitas Islam Internasional Darullughah Wadda'wah", 0, 'L');
    }

}
