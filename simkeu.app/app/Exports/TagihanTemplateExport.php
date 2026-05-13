<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TagihanTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'aliasprodi',
            'tahun',
            'namatagihan',
            'smt',
            'jumlahtagihan',
        ];
    }

    public function array(): array
    {
        return [
            ['AS-HK', '2020', 'HERREGISTRASI SEMESTER 1', '1', '300000'],
            ['AS-HK', '2020', 'SPP SEMESTER 1 BULAN 1', '1', '150000'],
            ['AS-HK(DD)', '2020', 'UAS SEMESTER 1', '1', '300000'],
        ];
    }
}
