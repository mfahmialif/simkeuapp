<?php

namespace App\Exports;

use App\Models\Prodi;
use App\Services\Mahasiswa;
use App\Models\KeuanganUasSusulan;
use Illuminate\Contracts\View\View;
use App\Models\KeuanganUasSusulanMk;
use App\Services\Jadwal;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class UasSusulanExport implements FromView, WithColumnWidths
{
    /**
     * @return \Illuminate\Support\Collection
     */

    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function view(): View
    {
        $data = $this->data;
        $request = $data['data'];
        $tanggal = $request['tanggal_print'];
        $prodiId = $request['prodi_id_print'];

        $prodi = Prodi::when($prodiId != '*', function ($q) use ($prodiId) {
            $q->where('id', $prodiId);
        })
            ->whereNotIn('id', [1, 14])
            ->where('jenjang', '=', 'S1')
            ->get();

        $mahasiswa = KeuanganUasSusulan::join('keuangan_uas_susulan_mk', 'keuangan_uas_susulan_mk.uas_susulan_id', '=', 'keuangan_uas_susulan.id')
            ->where('tanggal', $tanggal)
            ->select(
                'keuangan_uas_susulan.*',
                'keuangan_uas_susulan_mk.jadwal_kuliah_id',
            )
            ->get();
        $nimList = $mahasiswa->pluck('nim')->unique()->values()->toArray();
        $getDetailMahasiswa = collect(Mahasiswa::nim(
            json_encode($nimList),
            true
        ));
        $jadwalKuliahList = $mahasiswa->pluck('jadwal_kuliah_id')->unique()->values()->toArray();
        $getDetailJadwalKuliah = collect(Jadwal::find(
            json_encode($jadwalKuliahList),
            true
        ));

        // $mahasiswa = KeuanganUasSusulan::join('keuangan_uas_susulan_mk', 'keuangan_uas_susulan_mk.uas_susulan_id', '=', 'keuangan_uas_susulan.id')
        //     ->join('trans_jadwal_kuliah', 'trans_jadwal_kuliah.id', '=', 'keuangan_uas_susulan_mk.jadwal_kuliah_id')
        //     ->join('trans_kurikulum_matakuliah', 'trans_kurikulum_matakuliah.id', '=', 'trans_jadwal_kuliah.kurikulum_matakuliah_id')
        //     ->join('mst_matakuliah', 'mst_matakuliah.id', '=', 'trans_kurikulum_matakuliah.matakuliah_id')
        //     ->join('ref as kelompok', 'kelompok.id', '=', 'trans_jadwal_kuliah.kelompok_id')
        //     ->join('mst_mhs', 'keuangan_uas_susulan.nim', '=', 'mst_mhs.nim')
        //     ->whereDate('keuangan_uas_susulan.tanggal', $tanggal)
        //     ->select(
        //         'keuangan_uas_susulan.*',
        //         'trans_jadwal_kuliah.prodi_id',
        //         'keuangan_uas_susulan_mk.jadwal_kuliah_id',
        //         'mst_matakuliah.nama as mk_nama',
        //         'kelompok.kode as kelompok_kode',
        //         'mst_mhs.nama as mhs_nama',
        //         'mst_mhs.jk_id as mhs_jk_id'
        //     )
        //     ->get();

        $dataExcel = [];
        foreach ($mahasiswa as $key => $value) {
            $mahasiswaDetail = $getDetailMahasiswa->where('nim', $value->nim)->first();
            $jadwalKuliahDetail = $getDetailJadwalKuliah->where('id', $value->jadwal_kuliah_id)->first();
            $dataExcel[$mahasiswaDetail->prodi_id][$value->jadwal_kuliah_id][] = [
                'nim' => $value->nim,
                'mhs_nama' => $mahasiswaDetail->nama,
                'jk_id' => $mahasiswaDetail->jk_id,
                'mk_nama' => $jadwalKuliahDetail->kurikulum_matakuliah->matakuliah->nama,
                'kelompok_kode' => $jadwalKuliahDetail->kelompok->kode,
            ];
        }

        return view('admin.mhs-uas-susulan.excel', compact('data', 'tanggal', 'dataExcel', 'prodi'));
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 30,
            'C' => 30,
            'D' => 5,
            'E' => 30,
            'F' => 30,
        ];
    }
}
