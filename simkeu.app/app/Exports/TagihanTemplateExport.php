<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TagihanTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'tahun_angkatan',
            'alias_prodi',
            'nama_tagihan',
            'smt',
            'jumlah_rp_tagihan',
        ];
    }

    public function array(): array
    {
        return [
            ['2020', 'AS-HK', 'HERREGISTRASI SEMESTER 1', '1', '300000'],
            ['2020', 'AS-HK', 'SPP SEMESTER 1 BULAN 1', '1', '150000'],
            ['2020', 'AS-HK(DD)', 'UAS SEMESTER 1', '1', '300000'],
        ];
    }
}
