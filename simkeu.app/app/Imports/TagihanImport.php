<?php

namespace App\Imports;

use App\Models\KeuanganTagihan;
use App\Models\ThAkademik;
use App\Models\Prodi;
use App\Models\FormSchadule;
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
    protected $skipCount = 0;
    protected $skipReasons = [];

    public function __construct()
    {
        $this->userId = Auth::id();
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Cast kode values to string (Excel may return as integer)
        $thAkademikKode = (string) $row['th_akademik_kode'];
        $thAngkatanKode = (string) $row['th_angkatan_kode'];
        $prodiKode = (string) $row['prodi_kode'];
        $formSchaduleKode = (string) $row['form_schadule_kode'];

        // Lookup th_akademik by kode
        $thAkademik = ThAkademik::where('kode', $thAkademikKode)->first();
        if (!$thAkademik) {
            $this->skipCount++;
            $this->skipReasons[] = "th_akademik_kode '$thAkademikKode' tidak ditemukan";
            return null;
        }

        // Lookup th_angkatan by kode
        $thAngkatan = ThAkademik::where('kode', $thAngkatanKode)->first();
        if (!$thAngkatan) {
            $this->skipCount++;
            $this->skipReasons[] = "th_angkatan_kode '$thAngkatanKode' tidak ditemukan";
            return null;
        }

        // Lookup prodi by kode
        $prodi = Prodi::where('kode', $prodiKode)->first();
        if (!$prodi) {
            $this->skipCount++;
            $this->skipReasons[] = "prodi_kode '$prodiKode' tidak ditemukan";
            return null;
        }

        // Lookup form_schadule by kode
        $formSchadule = FormSchadule::where('kode', $formSchaduleKode)->first();
        if (!$formSchadule) {
            $this->skipCount++;
            $this->skipReasons[] = "form_schadule_kode '$formSchaduleKode' tidak ditemukan";
            return null;
        }

        // Fixed values as per requirements
        $kelasId = 6;
        $xSks = 'Y';

        // Generate kode
        $kode = $thAkademik->id . $thAngkatan->id . $prodi->id . $kelasId . $formSchadule->id;

        // Check for existing record
        $exists = KeuanganTagihan::where([
            'th_akademik_id'   => $thAkademik->id,
            'th_angkatan_id'   => $thAngkatan->id,
            'prodi_id'         => $prodi->id,
            'kelas_id'         => $kelasId,
            'form_schadule_id' => $formSchadule->id,
            'nama'             => $row['nama'],
        ])->exists();

        if ($exists) {
            $this->skipCount++;
            $this->skipReasons[] = "Data '{$row['nama']}' sudah ada (duplikat)";
            return null;
        }

        $this->successCount++;

        return new KeuanganTagihan([
            'th_akademik_id'   => $thAkademik->id,
            'th_angkatan_id'   => $thAngkatan->id,
            'prodi_id'         => $prodi->id,
            'double_degree'    => $row['double_degree'] ?? null,
            'kelas_id'         => $kelasId,
            'form_schadule_id' => $formSchadule->id,
            'kode'             => $kode,
            'nama'             => $row['nama'],
            'jumlah'           => $row['jumlah'],
            'x_sks'            => $xSks,
            'user_id'          => $this->userId,
        ]);
    }

    /**
     * Validation rules for each row
     */
    public function rules(): array
    {
        return [
            'th_akademik_kode'   => 'required',
            'th_angkatan_kode'   => 'required',
            'prodi_kode'         => 'required',
            'form_schadule_kode' => 'required',
            'nama'               => 'required|string|max:255',
            'jumlah'             => 'required|numeric',
            'double_degree'      => 'nullable|integer',
        ];
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
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
