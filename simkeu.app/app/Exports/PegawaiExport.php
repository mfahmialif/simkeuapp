<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiExport extends DefaultValueBinder implements
    FromArray,
    ShouldAutoSize,
    WithCustomValueBinder,
    WithHeadings,
    WithStyles,
    IValueBinder
{
    private const PEGAWAI_HEADINGS = [
        'id',
        'tipe',
        'kode',
        'nama',
        'jenis_kelamin',
        'status',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'email',
        'hp',
        'nomer_rekening',
        'nama_pemilik_rekening',
        'bank',
    ];

    private const DOSEN_HEADINGS = [
        'dosen_kode',
        'dosen_nidn',
        'dosen_gelar_depan',
        'dosen_gelar_belakang',
        'dosen_prodi_id',
        'dosen_prodi_kode',
        'dosen_prodi_nama',
    ];

    private const STAFF_HEADINGS = [
        'staff_jabatan',
    ];

    private const STRING_HEADINGS = [
        'id',
        'kode',
        'hp',
        'nomer_rekening',
        'dosen_kode',
        'dosen_nidn',
    ];

    private Collection $rows;
    private ?string $tipe;
    private array $headings;

    public function __construct($rows, ?string $tipe = null)
    {
        $this->rows = collect($rows)->values();
        $this->tipe = in_array($tipe, ['dosen', 'staff'], true) ? $tipe : null;
        $this->headings = $this->resolveHeadings();
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows
            ->map(fn ($pegawai) => array_map(
                fn ($heading) => $this->valueForHeading($pegawai, $heading),
                $this->headings
            ))
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                ],
            ],
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($this->headingForCell($cell), self::STRING_HEADINGS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function resolveHeadings(): array
    {
        return match ($this->tipe) {
            'dosen' => array_merge(self::PEGAWAI_HEADINGS, self::DOSEN_HEADINGS),
            'staff' => array_merge(self::PEGAWAI_HEADINGS, self::STAFF_HEADINGS),
            default => array_merge(self::PEGAWAI_HEADINGS, self::DOSEN_HEADINGS, self::STAFF_HEADINGS),
        };
    }

    private function valueForHeading($pegawai, string $heading)
    {
        return match ($heading) {
            'id' => $pegawai->id,
            'tipe' => $pegawai->tipe,
            'kode' => $pegawai->kode,
            'nama' => $pegawai->nama,
            'jenis_kelamin' => $pegawai->jenis_kelamin,
            'status' => $pegawai->status,
            'tempat_lahir' => $pegawai->tempat_lahir,
            'tanggal_lahir' => optional($pegawai->tanggal_lahir)->format('Y-m-d') ?: $pegawai->tanggal_lahir,
            'alamat' => $pegawai->alamat,
            'email' => $pegawai->email,
            'hp' => $pegawai->hp,
            'nomer_rekening' => $pegawai->nomer_rekening,
            'nama_pemilik_rekening' => $pegawai->nama_pemilik_rekening,
            'bank' => $pegawai->bank,
            'dosen_kode' => $pegawai->dosen?->kode,
            'dosen_nidn' => $pegawai->dosen?->nidn,
            'dosen_gelar_depan' => $pegawai->dosen?->gelar_depan,
            'dosen_gelar_belakang' => $pegawai->dosen?->gelar_belakang,
            'dosen_prodi_id' => $pegawai->dosen?->prodi_id,
            'dosen_prodi_kode' => $pegawai->dosen?->prodi?->kode,
            'dosen_prodi_nama' => $pegawai->dosen?->prodi?->nama,
            'staff_jabatan' => $pegawai->staff?->jabatan,
            default => null,
        };
    }

    private function headingForCell(Cell $cell): ?string
    {
        $index = Coordinate::columnIndexFromString($cell->getColumn()) - 1;

        return $this->headings[$index] ?? null;
    }
}
