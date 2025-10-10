<?php

namespace App\Exports\pdf;

use App\Models\KeuanganPembayaran;
use App\Exports\pdf\CustomKwitansiFpdf;

class KwitansiPreviewPdf
{
    /**
     * Save PDF File
     * @param mixed $data from request input ($request->all())
     */

    public static function pdf($data)
    {

        $fpdf = new CustomKwitansiFpdf("L", "mm", "A5");
        $fpdf->setData($data);
        $fpdf->AddPage();
        $transaksi = KeuanganPembayaran::where('nomor', $data['nomor'])->get();
        $nomor = $data['nomor'];
        if ($data->keuanganNota) {
            $transaksi = KeuanganPembayaran::leftJoin('keuangan_nota as kn', 'kn.pembayaran_id', 'keuangan_pembayaran.id')
                ->where('nota', $data->keuanganNota->nota)
                ->select('*', 'pembayaran_id as id')
                ->get();
            $nomor = $data->keuanganNota->nota;
        }
        self::body($transaksi, $fpdf);

        // // Save File PDF
        // $fpdf->Output('I', "Kwitansi Pembayaran $nomor.pdf");
        // exit;

        $binary = $fpdf->Output('S');  // <- ini kuncinya

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            // inline => ditampilkan di browser (tanpa forced download)
            ->header('Content-Disposition', 'inline; filename="kwitansi-' . $nomor . '.pdf"');
    }

    public static function body($transaksi, $fpdf)
    {
        $i = 1;
        $total = 0;
        foreach ($transaksi as $key => $t) {
            $offset = 0;
            $posY = $fpdf->getY();
            $posX = $fpdf->getX();

            $jenisPembayaran = ($t->jenisPembayaranDetail) ? $t->jenisPembayaranDetail->jenisPembayaran->nama : null;

            // set how much line space and get higher (max) line space
            $offset = [
                'no' => floor(strlen($i) / 3),
                'nama' => floor(strlen($t->tagihan->nama) / 35),
                'jenis pembayaran' => floor(strlen($jenisPembayaran) / 30),
                'Sub Total(Rp)' => floor(strlen($t->jumlah) / 16),
            ];
            $max = max($offset);

            // add line space (\n)
            $offset = [
                'no' => str_repeat("\n", $max - $offset['no'] + 1),
                'nama' => str_repeat("\n", $max - $offset['nama'] + 1),
                'jenis pembayaran' => str_repeat("\n", $max - $offset['jenis pembayaran'] + 1),
                'Sub Total(Rp)' => str_repeat("\n", $max - $offset['Sub Total(Rp)'] + 1),
            ];

            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(10, 4, "$i" . $offset['no'], 'B', 'C', 0);
            $posX += 10;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(75, 4, $t->tagihan->nama . $offset['nama'], 'B', 'C', 0);
            $posX += 75;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(70, 4, $jenisPembayaran . $offset['jenis pembayaran'], 'B', 'C', 0);
            $posX += 70;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(35, 4, number_format($t->jumlah, 0, ',', '.') . $offset['Sub Total(Rp)'], 'B', 'R', 0);

            $i++;
            if (!str_contains(strtolower($jenisPembayaran), 'deposit')) {
                $total += $t->jumlah;
            }

            if ($posY > 100) {
                $fpdf->AddPage();
            }
        }

        // Total
        $fpdf->SetFont('Courier', 'B', 9);
        // $fpdf->Cell(0, 1, "", 0, 1, 'L');
        $fpdf->Cell(155, 4, "Total : Rp.", '', 0, 'R');
        $fpdf->Cell(35, 4, number_format($total, 0, ',', '.'), '', 1, 'R');

        return $total;
    }
}
