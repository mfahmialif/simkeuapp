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
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Auth;

class TagihanImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $userId;
    protected $successCount = 0;
    protected $updateCount = 0;
    protected $skipCount = 0;
    protected $skipReasons = [];
    protected $updateExisting = false;
    protected $currentSemester = null;

    public function __construct(bool $updateExisting = false)
    {
        $this->userId = Auth::id();
        $this->updateExisting = $updateExisting;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $tahunAngkatan = trim((string) $row['tahun_angkatan']);
        $aliasProdiInput = trim((string) $row['alias_prodi']);
        $namaTagihan = trim((string) $row['nama_tagihan']);
        $semesterInput = trim((string) $row['smt']);
        $jumlah = $this->normalizeAmount($row['jumlah_rp_tagihan']);

        if (!preg_match('/^\d{4}$/', $tahunAngkatan)) {
            $this->skipCount++;
            $this->skipReasons[] = "Tahun angkatan '$tahunAngkatan' harus 4 digit, contoh 2020";
            return null;
        }

        $thAngkatanKode = $tahunAngkatan . '1';
        $thAngkatan = $this->findOrCreateThAkademik($thAngkatanKode);
        if (!$thAngkatan) {
            $this->skipCount++;
            $this->skipReasons[] = "Tahun angkatan '$tahunAngkatan' gagal dibuat sebagai kode '$thAngkatanKode'";
            return null;
        }

        $doubleDegree = Str::contains(Str::lower($aliasProdiInput), '(dd)') ? 1 : 0;
        $aliasProdi = trim(preg_replace('/\s*\(dd\)\s*/i', '', $aliasProdiInput));
        $prodi = Prodi::whereRaw('LOWER(alias) = ?', [Str::lower($aliasProdi)])->first();
        if (!$prodi) {
            $this->skipCount++;
            $this->skipReasons[] = "Alias prodi '$aliasProdi' tidak ditemukan";
            return null;
        }

        $semester = $this->resolveSemester($namaTagihan, $semesterInput);
        if (!$semester) {
            $this->skipCount++;
            $this->skipReasons[] = "Kolom smt untuk tagihan '$namaTagihan' harus angka semester";
            return null;
        }

        $thAkademikKode = $this->resolveThAkademikKode((int) $tahunAngkatan, $semester);
        $thAkademik = $this->findOrCreateThAkademik($thAkademikKode);
        if (!$thAkademik) {
            $this->skipCount++;
            $this->skipReasons[] = "Tahun akademik '$thAkademikKode' untuk '$namaTagihan' gagal dibuat";
            return null;
        }

        $formSchaduleKode = $this->resolveFormSchaduleKode($namaTagihan, $semester);
        $formSchadule = FormSchadule::where('kode', $formSchaduleKode)->first();
        if (!$formSchadule) {
            $this->skipCount++;
            $this->skipReasons[] = "Form schadule kode '$formSchaduleKode' untuk '$namaTagihan' tidak ditemukan";
            return null;
        }

        $kelasId = 6;
        $kode = $thAkademik->id . $thAngkatan->id . $prodi->id . $kelasId . $formSchadule->id;

        $existingQuery = KeuanganTagihan::where([
            'th_akademik_id'   => $thAkademik->id,
            'th_angkatan_id'   => $thAngkatan->id,
            'prodi_id'         => $prodi->id,
            'kelas_id'         => $kelasId,
            'form_schadule_id' => $formSchadule->id,
            'nama'             => $namaTagihan,
        ]);

        if ($doubleDegree === 1) {
            $existingQuery->where('double_degree', 1);
        } else {
            $existingQuery->where(function ($query) {
                $query->where('double_degree', 0)->orWhereNull('double_degree');
            });
        }

        $existing = $existingQuery->first();

        if ($existing && !$this->updateExisting) {
            $this->skipCount++;
            $this->skipReasons[] = "Data '$namaTagihan' sudah ada (duplikat)";
            return null;
        }

        if ($existing && $this->updateExisting) {
            $existing->fill([
                'kode'          => $kode,
                'jumlah'        => $jumlah,
                'double_degree' => $doubleDegree,
                'x_sks'         => 'Y',
                'user_id'       => $this->userId,
            ]);
            $existing->save();
            $this->updateCount++;
            return null;
        }

        $this->successCount++;

        return new KeuanganTagihan([
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
        ]);
    }

    protected function resolveSemester(string $namaTagihan, ?string $semesterInput = null): ?int
    {
        if ($semesterInput !== null && preg_match('/^\d+$/', $semesterInput)) {
            $this->currentSemester = (int) $semesterInput;
            return $this->currentSemester;
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

    protected function normalizeAmount($amount): float
    {
        $normalized = preg_replace('/[^\d.,-]/', '', (string) $amount);

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
     * Validation rules for each row
     */
    public function rules(): array
    {
        return [
            'tahun_angkatan'    => 'required',
            'alias_prodi'       => 'required|string|max:255',
            'nama_tagihan'      => 'required|string|max:255',
            'smt'               => 'required|integer|min:1',
            'jumlah_rp_tagihan' => 'required',
        ];
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
