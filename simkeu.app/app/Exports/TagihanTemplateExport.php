<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TagihanTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'th_akademik_kode',
            'th_angkatan_kode',
            'prodi_kode',
            'form_schadule_kode',
            'nama',
            'jumlah',
            'double_degree',
        ];
    }

    public function array(): array
    {
        // Example row
        return [
            ['20241', '20241', 'TI', 'REG', 'Biaya SPP', '5000000', ''],
        ];
    }
}
