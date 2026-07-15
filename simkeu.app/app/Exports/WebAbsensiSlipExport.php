<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class WebAbsensiSlipExport implements FromArray, WithColumnWidths, WithDrawings, WithEvents
{
    private array $dataRows;
    private array $rekapItem;
    private array $periode;

    public function __construct(array $dataRows, array $rekapItem, array $periode)
    {
        $this->dataRows = $dataRows;
        $this->rekapItem = $rekapItem;
        $this->periode = $periode;
    }

    public function array(): array
    {
        $rows = [];

        // Rows 1-5: reserved for Kop drawing
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = ['', '', '', '', '', '', '', ''];
        }

        // Row 6: spacer
        $rows[] = ['', '', '', '', '', '', '', ''];

        // Row 7: Title
        $rows[] = ['REKAP KEHADIRAN', '', '', '', '', '', '', ''];

        // Row 8: spacer
        $rows[] = ['', '', '', '', '', '', '', ''];

        $nama = $this->rekapItem['user']['name'] ?? $this->rekapItem['user']['nama'] ?? '-';
        $dept = $this->rekapItem['user']['departemen'] ?? '-';
        $totalJam = (float) ($this->rekapItem['total_jam_keseluruhan']['total_jam'] ?? 0);
        
        $totalHari = $this->rekapItem['_total_hari_calculated'] ?? 0;
        if (!$totalHari && !empty($this->rekapItem['rekap_per_kategori']) && is_array($this->rekapItem['rekap_per_kategori'])) {
            $totalHari = array_reduce($this->rekapItem['rekap_per_kategori'], fn($acc, $kat) => $acc + (int)($kat['jumlah'] ?? 0), 0);
        }
        if (!$totalHari && !empty($this->dataRows)) {
            $totalHari = count($this->dataRows);
        }

        $totalBarokah = (float) ($this->rekapItem['total_perolehan_dana'] ?? 0);

        $periodeInfo = '-';
        if (!empty($this->periode['mode']) && $this->periode['mode'] === 'bulan_tahun') {
            $months = [
                1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
                7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
            ];
            $b = (int) ($this->periode['bulan'] ?? date('n'));
            $t = $this->periode['tahun'] ?? date('Y');
            $periodeInfo = ($months[$b] ?? $b) . " " . $t;
        } elseif (!empty($this->periode['start_date']) && !empty($this->periode['end_date'])) {
            $periodeInfo = strtoupper(date('d/m/Y', strtotime($this->periode['start_date'])) . " s/d " . date('d/m/Y', strtotime($this->periode['end_date'])));
        }

        // Row 9-14: Profile summary
        $rows[] = ['NAMA', ': ' . $nama, '', '', '', '', '', ''];
        $rows[] = ['DEPARTEMEN', ': ' . $dept, '', '', '', '', '', ''];
        $rows[] = ['TOTAL JAM', ': ' . str_replace('.', ',', (string)$totalJam), '', '', '', '', '', ''];
        $rows[] = ['TOTAL HARI', ': ' . $totalHari, '', '', '', '', '', ''];
        $rows[] = ['TOTAL BAROKAH', ': Rp ' . number_format($totalBarokah, 0, ',', '.'), '', '', '', '', '', ''];
        $rows[] = ['PERIODE', ': ' . $periodeInfo, '', '', '', '', '', ''];

        // Row 15: spacer
        $rows[] = ['', '', '', '', '', '', '', ''];

        // Row 16: Table Headers
        $rows[] = [
            'NO',
            'TANGGAL',
            'JAM DATANG',
            'JAM PULANG',
            'TOTAL JAM',
            'PERINCIAN JAM',
            'BAROKAH',
            'KET.'
        ];

        // Row 17+: Daily Data
        $no = 1;
        foreach ($this->dataRows as $item) {
            $tgl = $item['tgl_absen'] ?? '';
            $tglFormatted = $tgl ? date('d-m-y', strtotime($tgl)) : '-';
            $pagi = $item['pagi'] ?? '-';
            $sore = $item['sore'] ?? '-';
            $durasiJam = isset($item['durasi_jam']) ? str_replace('.', ',', (string)$item['durasi_jam']) : '-';
            $durasiTeks = $item['durasi_teks'] ?? ($item['kategori']['nama'] ?? '-');
            $barokah = isset($item['perolehan_dana']) && $item['perolehan_dana'] > 0
                ? 'Rp ' . number_format((float)$item['perolehan_dana'], 0, ',', '.')
                : '-';
            $keterangan = $item['keterangan'] ?? '-';

            $rows[] = [
                $no++,
                $tglFormatted,
                $pagi,
                $sore,
                $durasiJam,
                $durasiTeks,
                $barokah,
                $keterangan
            ];
        }

        if (empty($this->dataRows)) {
            $rows[] = ['', 'Tidak ada data kehadiran untuk pegawai ini pada periode yang dipilih.', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 16,
            'D' => 16,
            'E' => 15,
            'F' => 30,
            'G' => 18,
            'H' => 20,
        ];
    }

    public function drawings()
    {
        $path = public_path('img/kop uiidalwa mantap.png');
        if (!is_file($path)) {
            $path = base_path('../public_html/img/kop uiidalwa mantap.png');
        }
        if (!is_file($path)) {
            $path = base_path('public_html/img/kop uiidalwa mantap.png');
        }
        if (!is_file($path)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('A1');
        $drawing->setEditAs(BaseDrawing::EDIT_AS_ONECELL);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(720);
        $drawing->setHeight(130);

        return [$drawing];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(17, count($this->dataRows) + 16);
                if (empty($this->dataRows)) {
                    $lastRow = 17;
                }

                // Merge and style Title
                $sheet->mergeCells('A7:H7');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A7')->getFont()->setUnderline(true);
                $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Style profile summary labels
                for ($r = 9; $r <= 14; $r++) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("B{$r}:H{$r}")->getFont()->setBold($r === 9 || $r === 13); // Bold Name and Barokah
                }

                // Table headers styling
                $sheet->getStyle('A16:H16')->getFont()->setBold(true);
                $sheet->getStyle('A16:H16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A16:H16')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(16)->setRowHeight(24);

                // Borders for table
                $sheet->getStyle("A16:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Alignments inside table
                if (!empty($this->dataRows)) {
                    $sheet->getStyle("A17:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F17:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G17:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("H17:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                } else {
                    $sheet->mergeCells('B17:H17');
                    $sheet->getStyle('B17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
