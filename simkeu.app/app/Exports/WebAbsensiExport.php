<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class WebAbsensiExport extends DefaultValueBinder implements
    FromCollection,
    ShouldAutoSize,
    WithHeadings,
    WithStyles,
    WithCustomValueBinder,
    WithColumnFormatting,
    IValueBinder
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return collect($this->data)->map(function ($item, $index) use ($daysIndo) {
            $tgl = $item['tgl_absen'] ?? null;
            $hari = '-';
            $tglFormatted = '-';
            if ($tgl && $tgl !== '-') {
                $time = strtotime($tgl);
                if ($time !== false) {
                    $hari = $daysIndo[date('w', $time)] ?? '-';
                    $tglFormatted = date('d/m/Y', $time);
                }
            }

            $kode = $item['kode_user'] ?? ($item['user']['kode'] ?? '-');
            if ($kode && $kode !== '-') {
                $kode = preg_replace('/^KD-/i', '', (string) $kode);
            }

            $totalJam = '-';
            if (!empty($item['durasi_jam']) && (float) $item['durasi_jam'] > 0) {
                $totalJam = $item['durasi_jam'] . ' Jam';
            } elseif (!empty($item['selisih_menit']) && (int) $item['selisih_menit'] > 0) {
                $totalJam = round($item['selisih_menit'] / 60, 2) . ' Jam';
            }

            $perincian = !empty($item['durasi_teks']) && $item['durasi_teks'] !== '-'
                ? $item['durasi_teks']
                : ($item['keterangan'] ?? '-');

            $barokah = ($item['perolehan_dana'] ?? 0) > 0 ? (int) $item['perolehan_dana'] : 0;

            return [
                'no' => $index + 1,
                'kode' => $kode,
                'nama' => $item['user']['name'] ?? '-',
                'departemen' => $item['user']['departemen'] ?? '-',
                'tanggal_absen' => $tglFormatted,
                'hari' => $hari,
                'jam_datang' => $item['pagi'] ?? '-',
                'jam_pulang' => $item['sore'] ?? '-',
                'total_jam' => $totalJam,
                'perincian_jam' => $perincian,
                'barokah' => $barokah,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'NO',
            'KODE',
            'NAMA',
            'DEPARTEMEN',
            'TANGGAL ABSEN',
            'HARI',
            'JAM DATANG',
            'JAM PULANG',
            'TOTAL JAM',
            'PERINCIAN JAM',
            'BAROKAH',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                    'endColor'   => ['rgb' => '1E3A8A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT,
            'K' => '_-"Rp"* #,##0_-;-"Rp"* #,##0_-;_-"Rp"* "-"_-;_-@_-',
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
