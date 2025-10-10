<?php

namespace App\Exports\pdf;

use App\Services\Helper;
use Codedge\Fpdf\Fpdf\Fpdf;
use App\Models\KeuanganPembayaranTambahan;

class CustomKwitansiMhsTambahanFpdf extends Fpdf
{
    protected $FontSpacingPt; // current font spacing in points
    protected $FontSpacing; // current font spacing in user units
    protected $data;

    public function setData($data)
    {
        $this->data = $data;
    }

    public function SetFontSpacing($size)
    {
        if ($this->FontSpacingPt == $size) {
            return;
        }

        $this->FontSpacingPt = $size;
        $this->FontSpacing = $size / $this->k;
        if ($this->page > 0) {
            $this->_out(sprintf('BT %.3f Tc ET', $size));
        }

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
        $this->Cell(170, 5, "", 0, 1, 'C');
        $this->Cell(170, 5, "", 0, 1, 'C');
        $this->Cell(160, 5, "", 0, 1, 'C');
        $this->Image( asset('/img/full-logo-uii.png') , 40, 0, -210);

        $this->SetFontSpacing(0);
        $this->Line(10, 26, 210 - 10, 26);
        $this->Cell(0, 7, "Jl. Raya Raci No.51, Telp. 087754452667", 0, 1, 'C');
        $this->Line(10, 32, 210 - 10, 32);

        $data = $this->data;
        //    Data Biodata Mahasiswa
        $this->SetFont('Courier', '', 9);
        $this->Cell(0, 1, "", 0, 1, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(40, 5, "Nota", 0, 0, 'L');

        $this->SetFontSpacing(0);
        $nomor = $data->nomor;
        if ($data->nota) {
            $nomor = $data->nota;
        }
        $this->Cell(50, 5, ": $nomor", 0, 0, 'L');

        $this->SetFontSpacing(0);
        $tahunAkademik = $data->th_akademik;
        $this->Cell(100, 5, "Tahun Akademik : $tahunAkademik", 0, 1, 'R');
        $this->SetFontSpacing(0);
        $this->Cell(190, 5, "Sudah diterima dari ", 0, 1, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(40, 5, "NIM", 0, 0, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(150, 5, ": $data->nim (" . $data->nama . ")", 0, 1, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(40, 5, "Program Studi", 0, 0, 'L');

        $this->SetFontSpacing(0);
        $prodi = $data->prodi;
        $this->Cell(150, 5, ": $prodi", 0, 1, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(40, 5, "Untuk Pembayaran", 0, 0, 'L');

        $this->SetFontSpacing(0);
        $this->Cell(150, 5, ": ", 0, 1, 'L');

        $this->SetFont('Courier', '', 9);
        $this->Cell(10, 5, "No.", 'B', 0, 'C');
        $this->Cell(75, 5, "Nama Tagihan", 'B', 0, 'C');
        $this->Cell(70, 5, "Jenis pembayaran", 'B', 0, 'C');
        $this->Cell(35, 5, "Sub Total(Rp)", 'B', 1, 'R');

    }

    public function footer()
    {
        $data = $this->data;

        $transaksi = KeuanganPembayaranTambahan::where('nota', $data['nota'])->get();
       
        $total = 0; 
        foreach ($transaksi as $t) {
            $total += $t->jumlah;
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

        $posY = $this->getY();
        $posX = $this->getX();
        $this->setXY($posX, $posY);

        $posX += 0;
        $this->setXY($posX, $posY);
        $this->MultiCell(150, 4, "Terbilang : $terbilang rupiah",  0, 'L');
        $this->Ln(0);
        $this->MultiCell(150, 4, "Terimakasih Atas Pembayaran Anda",  0,  'L', );
        $posX += 150;
        $this->setXY($posX, $posY);
        $this->MultiCell(40, 4, '(' . $transaksi[0]->user->name . ')', 0,  'C');
        
        // $this->Cell(0, 4, "", 0, 1, 'L');
        // $this->Cell(155, 4, "Terimakasih Atas Pembayaran Anda", 0, 0, 'L');
    }

}
