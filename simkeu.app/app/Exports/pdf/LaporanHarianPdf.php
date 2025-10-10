<?php

namespace App\Exports\pdf;

use App\Services\Helper;
use App\Exports\pdf\CustomFpdf;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;

class LaporanHarianPdf
{
    /**
     * Save PDF File
     * @param mixed $data from request input ($request->all())
     */
    public static function pdf($data)
    {

        $fpdf = new CustomFpdf("P", "mm", "A4");
        $fpdf->AddPage();

        $prodi = isset($data['prodi']) ? $data['prodi'] : "";
        if ($prodi == "Semua") {
            $prodi = "";
        }
        $tahunAkademik = isset($data['tahun_akademik']) ? $data['tahun_akademik'] : "";
        if ($tahunAkademik == "Semua") {
            $tahunAkademik = "";
        }
        $jenisPembayaran = isset($data['jenis_pembayaran']) ? $data['jenis_pembayaran'] : "";
        if ($jenisPembayaran == "Semua") {
            $jenisPembayaran = "";
        }
        $data['tambahan'] = [
            "prodi" => $prodi,
            "tahunAkademik" => $tahunAkademik,
            "jenisPembayaran" => $jenisPembayaran,
        ];

        $dataPembayaran = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
            ->leftJoin('keuangan_jenis_pembayaran_detail as kjpd', 'kjpd.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran as kjp', 'kjp.id', '=', 'kjpd.jenis_pembayaran_id')
            ->select('*', 'kjp.nama as kjp_nama', 'kt.nama as kt', 'keuangan_pembayaran.jumlah as dibayar', 'kt.jumlah as jumlah_tagihan');
        if ($data['tanggal'] != '') {
            $dataPembayaran->whereDate('tanggal', $data['tanggal']);
        }
        if ($jenisPembayaran != '') {
            $dataPembayaran->where('kjpd.jenis_pembayaran_id', $jenisPembayaran);
        } elseif ($jenisPembayaran == "kosong") {
            $dataPembayaran->where('kjpd.jenis_pembayaran_id', null);
        }
        if ($prodi != '') {
            $dataPembayaran->where('kt.prodi_id', $prodi);
        }
        if ($tahunAkademik != '') {
            $dataPembayaran->where('kt.th_akademik_id', $tahunAkademik);
        }

        $jp = Helper::getJenisKelaminUser();
        $dataPembayaran = $dataPembayaran
            // ->where('mhs.jk_id', 'LIKE', "%$jp->id%")
            ->orderBy('kt.prodi_id', 'asc')->orderBy('kjpd.jenis_pembayaran_id', 'asc')->get();

        self::header($data, $fpdf);

        $totalPemasukan = 0;
        $totalPemasukan += self::body($data, $dataPembayaran, $fpdf);

        self::totalPemasukan($totalPemasukan, $fpdf);

        // Table Untuk Pengeluaran
        $totalPengeluaran = 0;
        $setoran = KeuanganSetoran::where([
            ['tanggal', $data['tanggal']],
            ['status', 'setuju'],
            ['kategori', 'LIKE', "%$jp->kategori%"],
        ])->get();

        foreach ($setoran as $key => $s) {
            $totalPengeluaran += $s->jumlah;
        }

        self::pengeluaran($setoran, $fpdf);
        self::totalPengeluaran($totalPengeluaran, $fpdf);

        $totalKeseluruhan = 0;
        $totalKeseluruhan += $totalPemasukan - $totalPengeluaran;

        self::totalKeseluruhan($totalKeseluruhan, $fpdf);

        // Save File PDF
        // $fpdf->Output('I', 'Laporan Harian.pdf');
        // exit;

        $binary = $fpdf->Output('S'); 

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Laporan Harian ' . $data['tanggal'] . '.pdf"');
    }

    public static function header($data, $fpdf)
    {
        // Header
        $fpdf->SetFont('Courier', '', 11);
        $fpdf->SetFontSpacing(3);
        $fpdf->Cell(170, 7, "INSTITUT AGAMA ISLAM", 0, 1, 'C');
        $fpdf->Cell(170, 7, "DARULLUGHAH WADDA 'WAH", 0, 1, 'C');
        $fpdf->Cell(160, 7, "RACI BANGIL PASURUAN JAWA TIMUR", 0, 1, 'C');
        $fpdf->SetFontSpacing(0);
        $fpdf->Line(10, 31, 210 - 10, 31);
        $fpdf->Cell(0, 10, "Jl. Raya Raci No.51 PO.Box.8 Bangil, Telp. (0343) 745317", 0, 1, 'C');
        $fpdf->Line(10, 40, 210 - 10, 40);
        $fpdf->SetFontSpacing(3);
        $fpdf->Cell(180, 10, "LAPORAN HARIAN", 0, 1, 'C');
        $fpdf->SetFontSpacing(0);

        // Tanggal
        $fpdf->SetFont('Courier', '', 9.5);
        $fpdf->Cell(40, 7, "Tanggal", 0, 0, 'L');
        $tanggal = date('d-m-Y', strtotime($data['tanggal']));
        $fpdf->Cell(0, 7, ': ' . $tanggal, 0, 1, 'L');

        // Kategori
        $fpdf->SetFont('Courier', '', 9.5);
        $fpdf->Cell(40, 7, "Kategori", 0, 0, 'L');

        $ta = $data['tambahan']['tahunAkademik'] == "" ? "Semua" : $data['tambahan']['tahunAkademik'];
        $kat = $data['kategori'];
        $prodi = $data['tambahan']['prodi'] == "" ? "Semua" : $data['tambahan']['prodi'];
        $jp = $data['tambahan']['jenisPembayaran'] == "" ? "Semua" : $data['tambahan']['jenisPembayaran'];

        $kategori = "$kat ( Jurusan : $prodi, TA : $ta )";
        $fpdf->MultiCell(140, 7, ': ' . $kategori, 0, 'L', 0);
    }

    public static function body($data, $dataPembayaran, $fpdf)
    {
        // Table
        $fpdf->SetFont('Courier', '', 9.5);
        $fpdf->Cell(0, 7, "", 0, 1, 'L');
        $fpdf->Cell(10, 7, "No.", 'T,B', 0, 'C');
        $fpdf->Cell(30, 7, "Nota", 'T,B', 0, 'C');
        $fpdf->Cell(40, 7, "NIM / No.Pendaftaran", 'T,B', 0, 'C');
        $fpdf->Cell(45, 7, "Prodi", 'T,B', 0, 'C');
        $fpdf->Cell(30, 7, "Jenis Pembayaran", 'T,B', 0, 'C');
        $fpdf->Cell(35, 7, "Sub Total(Rp)", 'T,B', 1, 'C');

        $i = 1;
        $total = 0;
        foreach ($dataPembayaran as $t) {

            $offset = 0;
            $posY = $fpdf->getY();
            $posX = $fpdf->getX();

            // set how much line space and get higher (max) line space
            $offset = [
                'no' => floor(strlen($i) / 4),
                'nota' => floor(strlen($t->nomor) / 13),
                'nim' => floor(strlen($t->nim) / 18),
                'prodi' => floor(strlen($t->tagihan->prodi->nama) / 21),
                'jenis' => floor(strlen($t->kjp_nama) / 13),
                'subTotal' => floor(strlen($t->dibayar) / 16),
            ];
            $max = max($offset);

            // add line space (\n)
            $offset = [
                'no' => str_repeat("\n", $max - $offset['no'] + 1),
                'nota' => str_repeat("\n", $max - $offset['nota'] + 1),
                'nim' => str_repeat("\n", $max - $offset['nim'] + 1),
                'no' => str_repeat("\n", $max - $offset['no'] + 1),
                'prodi' => str_repeat("\n", $max - $offset['prodi'] + 1),
                'jenis' => str_repeat("\n", $max - $offset['jenis'] + 1),
                'subTotal' => str_repeat("\n", $max - $offset['subTotal'] + 1),
            ];

            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(10, 7, "$i" . $offset['no'], 'T,B', 'C', 0);
            $posX += 10;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(30, 7, $t->nomor . $offset['nota'], 'T,B', 'C', 0);
            $posX += 30;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(40, 7, $t->nim . $offset['nim'], 'T,B', 'C', 0);
            $posX += 40;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(45, 7, $t->tagihan->prodi->nama . $offset['prodi'], 'T,B', 'C', 0);
            $posX += 45;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(30, 7, ucwords($t->kjp_nama) . $offset['jenis'], 'T,B', 'C', 0);
            $posX += 30;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(35, 7, number_format($t->dibayar, 0, ',', '.') . $offset['subTotal'], 'T,B', 'R', 0);

            $i++;
            if ($t->dibayar == $t->nim) {
                $t->dibayar = $t->jumlah_tagihan;
            }
            $total += $t->dibayar;

            if ($posY > 230) {
                $fpdf->AddPage();
            }
        }
        // Total
        $fpdf->SetFont('Courier', 'B', 9.5);
        $fpdf->Cell(155, 7, "Total : Rp.", 'T,B', 0, 'R');
        $fpdf->Cell(35, 7, number_format($total, 0, ',', '.'), 'T,B', 1, 'R');

        return $total;
    }

    public static function totalPemasukan($totalPemasukan, $fpdf)
    {
        // Total Pemasukan
        $fpdf->SetFont('Courier', 'B', 9.5);
        $fpdf->Cell(0, 7, "", 0, 1, 'L');
        $fpdf->Cell(155, 7, "Total Pemasukan: Rp.", 'T,B', 0, 'R');
        $fpdf->Cell(35, 7, number_format($totalPemasukan, 0, ',', '.'), 'T,B', 1, 'R');
    }

    public static function pengeluaran($setoran, $fpdf)
    {

        // Pengeluaran
        $fpdf->SetFont('Courier', '', 9.5);
        $fpdf->Cell(0, 7, "", 0, 1, 'L');
        $fpdf->Cell(10, 7, "No.", 'T,B', 0, 'C');
        $fpdf->Cell(145, 7, "Pengeluaran", 'T,B', 0, 'C');
        $fpdf->Cell(35, 7, "Sub Total(Rp)", 'T,B', 1, 'C');

        $i = 1;
        foreach ($setoran as $key => $s) {
            $offset = 0;
            $posY = $fpdf->getY();
            $posX = $fpdf->getX();

            // set how much line space and get higher (max) line space
            $offset = [
                'no' => floor(strlen($i) / 3),
                'keterangan' => floor(strlen($s->keterangan) / 65),
                'jumlah' => floor(strlen($s->jumlah) / 16),
            ];
            $max = max($offset);

            // add line space (\n)
            $offset = [
                'no' => str_repeat("\n", $max - $offset['no'] + 1),
                'keterangan' => str_repeat("\n", $max - $offset['keterangan'] + 1),
                'jumlah' => str_repeat("\n", $max - $offset['jumlah'] + 1),
            ];

            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(10, 7, "$i" . $offset['no'], 'T,B', 'C', 0);
            $posX += 10;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(145, 7, $s->keterangan . $offset['keterangan'], 'T,B', 'C', 0);
            $posX += 145;
            $fpdf->setXY($posX, $posY);
            $fpdf->MultiCell(35, 7, number_format($s->jumlah, 0, ',', '.') . $offset['jumlah'], 'T,B', 'R', 0);

            $i++;
            if ($posY > 230) {
                $fpdf->AddPage();
            }
        }
    }

    public static function totalPengeluaran($totalPengeluaran, $fpdf)
    {
        // Total Pengeluaran
        $fpdf->SetFont('Courier', 'B', 9.5);
        $fpdf->Cell(155, 7, "Total Pengeluaran: Rp.", 'T,B', 0, 'R');
        $fpdf->Cell(35, 7, number_format($totalPengeluaran, 0, ',', '.'), 'T,B', 1, 'R');
    }

    public static function totalKeseluruhan($totalKeseluruhan, $fpdf)
    {
        // Total Keseluruhan
        $fpdf->SetFont('Courier', 'B', 9.5);
        $fpdf->Cell(0, 7, "", 0, 1, 'L');
        $fpdf->Cell(155, 7, "Total Keseluruhan: Rp.", 'T,B', 0, 'R');
        $fpdf->Cell(35, 7, number_format($totalKeseluruhan, 0, ',', '.'), 'T,B', 1, 'R');
    }
}
