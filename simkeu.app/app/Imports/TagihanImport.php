<?php

namespace App\Imports;

use App\Models\KeuanganTagihan;
use App\Models\ThAkademik;
use App\Models\Prodi;
use App\Models\FormSchadule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Support\Facades\Auth;

class TagihanImport implements ToModel, WithHeadingRow, SkipsOnFailure, WithEvents
{
    use SkipsFailures, RegistersEventListeners, RemembersRowNumber;

    protected $userId;
    protected $successCount = 0;
    protected $updateCount = 0;
    protected $skipCount = 0;
    protected $skipReasons = [];
    protected $updateExisting = false;
    protected $currentSemester = null;
    protected $currentSheetName = null;
    protected $sheetNames = [];

    public function __construct(bool $updateExisting = false)
    {
        $this->userId = Auth::id();
        $this->updateExisting = $updateExisting;
    }

    public function beforeSheet(BeforeSheet $event): void
    {
        $this->currentSheetName = $event->getSheet()->getDelegate()->getTitle();
        $this->sheetNames[] = $this->currentSheetName;
        $this->currentSemester = null;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if ($this->isEmptyRow($row)) {
            return null;
        }

        $tahunAngkatan = trim((string) $this->value($row, ['tahun', 'tahun_angkatan', 'tahunangkatan']));
        $aliasProdiInput = trim((string) $this->value($row, ['aliasprodi', 'alias_prodi', 'alias']));
        $namaTagihan = trim((string) $this->value($row, ['namatagihan', 'nama_tagihan', 'nama']));
        $semesterRaw = $this->value($row, ['smt', 'semester']);
        $semesterInput = $semesterRaw === null ? null : trim((string) $semesterRaw);
        $jumlahRaw = $this->value($row, ['jumlahtagihan', 'jumlah_tagihan', 'jumlah_rp_tagihan', 'jumlah']);

        $missingColumns = [];
        foreach ([
            'aliasprodi' => $aliasProdiInput,
            'tahun' => $tahunAngkatan,
            'namatagihan' => $namaTagihan,
            'smt' => $semesterInput,
            'jumlahtagihan' => $jumlahRaw,
        ] as $column => $value) {
            if ($this->isBlank($value)) {
                $missingColumns[] = $column;
            }
        }

        if ($missingColumns) {
            return $this->skipRow('Kolom ' . implode(', ', $missingColumns) . ' wajib diisi');
        }

        $jumlah = $this->normalizeAmount($jumlahRaw);
        if ($jumlah === null) {
            return $this->skipRow("Jumlah tagihan untuk '$namaTagihan' tidak valid");
        }

        if (!preg_match('/^\d{4}$/', $tahunAngkatan)) {
            return $this->skipRow("Tahun angkatan '$tahunAngkatan' harus 4 digit, contoh 2020");
        }

        $thAngkatanKode = $tahunAngkatan . '1';
        $thAngkatan = $this->findOrCreateThAkademik($thAngkatanKode);
        if (!$thAngkatan) {
            return $this->skipRow("Tahun angkatan '$tahunAngkatan' gagal dibuat sebagai kode '$thAngkatanKode'");
        }

        $doubleDegree = Str::contains(Str::lower($aliasProdiInput), '(dd)') ? 1 : 0;
        $aliasProdi = trim(preg_replace('/\s*\(dd\)\s*/i', '', $aliasProdiInput));
        $prodi = Prodi::whereRaw('LOWER(alias) = ?', [Str::lower($aliasProdi)])->first();
        if (!$prodi) {
            return $this->skipRow("Alias prodi '$aliasProdi' tidak ditemukan");
        }

        $semester = $this->resolveSemester($namaTagihan, $semesterInput);
        if (!$semester) {
            return $this->skipRow("Kolom smt untuk tagihan '$namaTagihan' harus angka semester");
        }

        $thAkademikKode = $this->resolveThAkademikKode((int) $tahunAngkatan, $semester);
        $thAkademik = $this->findOrCreateThAkademik($thAkademikKode);
        if (!$thAkademik) {
            return $this->skipRow("Tahun akademik '$thAkademikKode' untuk '$namaTagihan' gagal dibuat");
        }

        $formSchaduleKode = $this->resolveFormSchaduleKode($namaTagihan, $semester);
        $formSchadule = FormSchadule::where('kode', $formSchaduleKode)->first();
        if (!$formSchadule) {
            return $this->skipRow("Form schadule kode '$formSchaduleKode' untuk '$namaTagihan' tidak ditemukan");
        }

        $kelasId = 6;
        $kode = $thAkademik->id . $thAngkatan->id . $prodi->id . $kelasId . $formSchadule->id;

        $tagihanData = [
            'th_akademik_id'   => $thAkademik->id,
            'th_angkatan_id'   => $thAngkatan->id,
            'prodi_id'         => $prodi->id,
            'double_degree'    => $doubleDegree,
            'kelas_id'         => $kelasId,
            'form_schadule_id' => $formSchadule->id,
            'kode'             => $kode,
            'nama'             => $namaTagihan,
            'jumlah'           => $jumlah,
            'x_sks'            => 'Y',
            'user_id'          => $this->userId,
        ];

        $existingQuery = KeuanganTagihan::where([
            'th_angkatan_id' => $thAngkatan->id,
            'prodi_id'       => $prodi->id,
            'nama'           => $namaTagihan,
        ]);

        $existing = $existingQuery->first();

        if ($existing && !$this->updateExisting) {
            return $this->skipRow("Data '$namaTagihan' untuk angkatan $tahunAngkatan dan prodi '$aliasProdiInput' sudah ada");
        }

        if ($existing && $this->updateExisting) {
            $existing->fill($tagihanData);
            $existing->save();
            $this->updateCount++;
            return null;
        }

        $this->successCount++;

        return new KeuanganTagihan($tagihanData);
    }

    protected function resolveSemester(string $namaTagihan, ?string $semesterInput = null): ?int
    {
        if ($semesterInput !== null && trim($semesterInput) !== '') {
            if (preg_match('/^\d+$/', $semesterInput)) {
                $this->currentSemester = (int) $semesterInput;
                return $this->currentSemester;
            }

            return null;
        }

        if (preg_match('/\bsemester\s+(\d+)\b/i', $namaTagihan, $matches)) {
            $this->currentSemester = (int) $matches[1];
            return $this->currentSemester;
        }

        if ($this->currentSemester) {
            return $this->currentSemester;
        }
        return null;
    }

    protected function resolveThAkademikKode(int $tahunAngkatan, int $semester): string
    {
        $tahunAkademik = $tahunAngkatan + intdiv($semester - 1, 2);
        $semesterSuffix = $semester % 2 === 1 ? '1' : '2';

        return (string) $tahunAkademik . $semesterSuffix;
    }

    protected function findOrCreateThAkademik(string $kode): ?ThAkademik
    {
        $thAkademik = ThAkademik::where('kode', $kode)->first();
        if ($thAkademik) {
            return $thAkademik;
        }

        if (!preg_match('/^(\d{4})([12])$/', $kode, $matches)) {
            return null;
        }

        $tahun = (int) $matches[1];

        return ThAkademik::create([
            'kode' => $kode,
            'nama' => $tahun . '/' . ($tahun + 1),
            'semester' => $matches[2] === '1' ? 'Ganjil' : 'Genap',
            'aktif' => 'T',
            'user_id' => $this->resolveUserId(),
        ]);
    }

    protected function resolveUserId(): ?int
    {
        return $this->userId ?? Auth::id() ?? DB::table('users')->min('id');
    }

    protected function resolveFormSchaduleKode(string $namaTagihan, int $semester): string
    {
        if (Str::contains(Str::lower($namaTagihan), 'wisuda')) {
            return 'WIS';
        }

        return $semester % 2 === 1 ? 'KRS-1' : 'KRS-2';
    }

    protected function skipRow(string $reason)
    {
        $this->skipCount++;

        $context = [];
        if ($this->currentSheetName) {
            $context[] = "sheet '$this->currentSheetName'";
        }

        if ($this->getRowNumber()) {
            $context[] = 'baris ' . $this->getRowNumber();
        }

        $this->skipReasons[] = $context
            ? ucfirst(implode(', ', $context)) . ': ' . $reason
            : $reason;

        return null;
    }

    protected function value(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && !$this->isBlank($row[$key])) {
                return $row[$key];
            }
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    protected function isBlank($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!$this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeAmount($amount): ?float
    {
        $normalized = preg_replace('/[^\d.,-]/', '', (string) $amount);

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, '.') && str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/,\d{3}$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getUpdateCount(): int
    {
        return $this->updateCount;
    }

    public function getSheetCount(): int
    {
        return count(array_unique($this->sheetNames));
    }

    public function getSheetNames(): array
    {
        return array_values(array_unique($this->sheetNames));
    }

    /**
     * Get skip count
     */
    public function getSkipCount(): int
    {
        return $this->skipCount;
    }

    /**
     * Get skip reasons
     */
    public function getSkipReasons(): array
    {
        return $this->skipReasons;
    }
}
