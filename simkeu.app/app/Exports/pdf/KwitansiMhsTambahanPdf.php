<?php

namespace App\Exports\pdf;

use App\Models\KeuanganPembayaranTambahan;
use App\Exports\pdf\CustomKwitansiMhsTambahanFpdf;

class KwitansiMhsTambahanPdf
{
    /**
     * Save PDF File
     * @param mixed $data from request input ($request->all())
     */

    public static function pdf($data)
    {

        $fpdf = new CustomKwitansiMhsTambahanFpdf("L", "mm", "A5");
        $fpdf->setData($data);

        $fpdf->AddPage();
        $transaksi = KeuanganPembayaranTambahan::where('nota', $data['nota'])->get();
        $nota = $data['nota'];
        
        self::body($transaksi, $fpdf);

        // // Save File PDF
        // $fpdf->Output('I', "Kwitansi Pembayaran $nota.pdf");
        // exit;

        $binary = $fpdf->Output('S');  // <- ini kuncinya

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            // inline => ditampilkan di browser (tanpa forced download)
            ->header('Content-Disposition', 'inline; filename="kwitansi-' . $nota . '.pdf"');
    }

    public static function body($transaksi, $fpdf)
    {
        $i = 1;
        $total = 0;
        foreach ($transaksi as $key => $t) {
            $offset = 0;
            $posY = $fpdf->getY();
            $posX = $fpdf->getX();

            // set how much line space and get higher (max) line space
            $offset = [
                'no' => floor(strlen($i) / 3),
                'nama tagihan' => floor(strlen($t->tagihan) / 35),
                'jenis pembayaran' => floor(strlen($t->jenis_pembayaran) / 30),
                'Sub Total(Rp)' => floor(strlen($t->jumlah) / 16),
            ];
            $max = max($offset);

            // add line space (\n)
            $offset = [
                'no' => str_repeat("\n", $max - $offset['no'] + 1),
                'nama tagihan' => str_repeat("\n", $max - $offset['nama tagihan'] + 1),
                'jenis pembayaran' => str_repeat("\n", $max - $offset['jenis pembayaran'] + 1),
                'Sub Total(Rp)' => str_repeat("\n", $max - $offset['Sub Total(Rp)'] + 1),
            ];

            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(10, 4, "$i" . $offset['no'], 'B', 'C', 0);
            $posX += 10;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(75, 4, $t->tagihan . $offset['nama tagihan'], 'B', 'C', 0);
            $posX += 75;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(70, 4, $t->jenis_pembayaran . $offset['jenis pembayaran'], 'B', 'C', 0);
            $posX += 70;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(35, 4, number_format($t->jumlah, 0, ',', '.') . $offset['Sub Total(Rp)'], 'B', 'R', 0);

            $i++;
            $total += $t->jumlah;

            if ($posY > 100) {
                $fpdf->AddPage();
            }
        }

        // Total
        $fpdf->SetFont('Courier', 'B', 9);
        // $fpdf->Cell(0, 1, "", 0, 1, 'L');
        $fpdf->Cell(160, 4, "Total : Rp.", '', 0, 'R');
        $fpdf->Cell(30, 4, number_format($total, 0, ',', '.'), '', 1, 'R');

        return $total;
    }

}
