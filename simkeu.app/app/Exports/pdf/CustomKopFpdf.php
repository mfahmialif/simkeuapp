<?php

namespace App\Exports\pdf;

use Carbon\Carbon;
use App\Services\Helper;
use App\Services\Mahasiswa;
use Codedge\Fpdf\Fpdf\Fpdf;
use App\Models\KeuanganDeposit;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaran;

class CustomKopFpdf extends Fpdf
{
    protected $FontSpacingPt; // current font spacing in points
    protected $FontSpacing; // current font spacing in user units
    protected $dataSign;

    public function setDataSign($dataSign)
    {
        $this->dataSign = $dataSign;
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
        $this->SetFont('Helvetica', '', 10);
        $this->SetFontSpacing(3);
        $this->Cell(170, 5, "", 0, 1, 'C');
        $this->Cell(170, 5, "", 0, 1, 'C');
        $this->Cell(160, 5, "", 0, 1, 'C');
        $this->Image(asset('/img/kop-uiidalwa.png'), 42, 0, -210);

        $this->SetFontSpacing(0);
        $this->Line(10, 30, 210 - 10, 30);
        $this->Line(10, 31, 210 - 10, 31);
        // $this->Cell(0, 7, "Jl. Raya Raci No.51, Telp. 087754452667", 0, 1, 'C');
    }

    public function footer()
    {

        $this->setY(-45);
        $this->SetFont('Helvetica', '', 9);

        $this->Cell(0, 15, "", 0, 1);
        // Tanggal - rata kanan
        $this->Cell(0, 4, "Bangil, " . $this->formatDate(Carbon::now()), 0, 1, 'R');

        // Jarak sebelum tanda tangan
        $this->Cell(0, 10, "", 0, 1);

        // Nama penanda tangan - rata kanan
        $this->Cell(0, 4, @$this->dataSign['nama'], 0, 1, 'R');

        // Nomor (NIP/Nomor lain) - rata kanan
        $this->Cell(0, 4, @$this->dataSign['nomer'], 0, 1, 'R');
    }

    public function formatDate($date){
        return Carbon::parse($date)->format('d-m-Y');
    }
}
