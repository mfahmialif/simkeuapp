<?php

namespace App\Services;

use App\Services\Mahasiswa;
use App\Models\KeuanganTagihan;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Models\KeuanganPembayaranIDN;
use App\Models\KeuanganDispensasiTagihan;

class TagihanMahasiswa
{
    public static function getSemesterTagihan($tagihan, $angkatanKode)
    {
        $kodeTahunAkademik = (string) (
            data_get($tagihan, 'th_akademik_kode')
            ?: data_get($tagihan, 'th_akademik.kode')
            ?: ''
        );
        $tahunAngkatan = (int) substr((string) $angkatanKode, 0, 4);

        if (! $tahunAngkatan || ! preg_match('/^\d{5}$/', $kodeTahunAkademik)) {
            return null;
        }

        $tahunTagihan = (int) substr($kodeTahunAkademik, 0, 4);
        $semesterKode = (int) substr($kodeTahunAkademik, 4, 1);

        if (! in_array($semesterKode, [1, 2], true)) {
            return null;
        }

        return (($tahunTagihan - $tahunAngkatan) * 2) + $semesterKode;
    }

    public static function splitTagihanBySemester($listTagihan, $semesterMahasiswa = null, $angkatanKode = null)
    {
        $semesterMahasiswa = (int) $semesterMahasiswa;
        $groups = [
            'semester_ini' => [],
            'semester_depan' => [],
        ];

        foreach ($listTagihan ?: [] as $tagihan) {
            $semesterTagihan = self::getSemesterTagihan($tagihan, $angkatanKode);

            if (is_array($tagihan)) {
                $tagihan['semester_tagihan'] = $semesterTagihan;
            } else {
                $tagihan->semester_tagihan = $semesterTagihan;
            }

            if ($semesterMahasiswa > 0 && $semesterTagihan && $semesterTagihan > $semesterMahasiswa) {
                $groups['semester_depan'][] = $tagihan;
            } else {
                $groups['semester_ini'][] = $tagihan;
            }
        }

        return $groups;
    }

    public static function sumTagihan($listTagihan)
    {
        return array_reduce($listTagihan ?: [], function ($total, $tagihan) {
            return $total + (float) data_get($tagihan, 'sisa', 0);
        }, 0);
    }

    public static function getTagihanGroupsForScope($listTagihan, $scope = 'semua', $semesterMahasiswa = null, $angkatanKode = null)
    {
        $groups = self::splitTagihanBySemester($listTagihan, $semesterMahasiswa, $angkatanKode);
        $semesterMahasiswa = (int) $semesterMahasiswa;
        $labels = [
            'semester_ini' => $semesterMahasiswa > 0
                ? "TAGIHAN SEMESTER INI (S/D SEMESTER {$semesterMahasiswa})"
                : 'TAGIHAN SEMESTER INI',
            'semester_depan' => $semesterMahasiswa > 0
                ? "TAGIHAN SEMESTER DEPAN (> SEMESTER {$semesterMahasiswa})"
                : 'TAGIHAN SEMESTER DEPAN',
        ];

        $keys = in_array($scope, ['semester_ini', 'semester_depan'], true)
            ? [$scope]
            : ['semester_ini', 'semester_depan'];

        return array_map(function ($key) use ($groups, $labels) {
            return [
                'key' => $key,
                'title' => $labels[$key],
                'items' => $groups[$key],
                'total' => self::sumTagihan($groups[$key]),
            ];
        }, $keys);
    }

    public static function isSkripsiTagihan($tagihan)
    {
        return stripos((string) data_get($tagihan, 'nama', ''), 'skripsi') !== false;
    }

    public static function hasSkripsiTagihan($listTagihan)
    {
        foreach ($listTagihan ?: [] as $tagihan) {
            if (self::isSkripsiTagihan($tagihan)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeCekNilai($cekNilai)
    {
        if ($cekNilai === null) {
            return null;
        }

        if (is_bool($cekNilai)) {
            return $cekNilai;
        }

        $normalized = filter_var($cekNilai, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? (bool) $cekNilai;
    }

    public static function resolveCekNilai($nim, $listTagihan, $cekNilai = null)
    {
        $normalized = self::normalizeCekNilai($cekNilai);

        if ($normalized !== null) {
            return $normalized;
        }

        if (! self::hasSkripsiTagihan($listTagihan)) {
            return true;
        }

        $cekNilai = Mahasiswa::cekNilai($nim);

        return (bool) data_get($cekNilai, 'status', false);
    }

    public static function markPaymentEligibility($listTagihan, $nim, $cekNilai = null)
    {
        $listTagihan = $listTagihan ?: [];
        $nilai = self::resolveCekNilai($nim, $listTagihan, $cekNilai);

        foreach ($listTagihan as &$tagihan) {
            $tidakBisaDibayar = ! $nilai && self::isSkripsiTagihan($tagihan);
            $keterangan = $tidakBisaDibayar ? 'SKRIPSI TIDAK BISA DIBAYAR' : null;

            if (is_array($tagihan)) {
                $tagihan['tidak_bisa_dibayar'] = $tidakBisaDibayar;
                $tagihan['keterangan_pembayaran'] = $keterangan;
            } else {
                $tagihan->tidak_bisa_dibayar = $tidakBisaDibayar;
                $tagihan->keterangan_pembayaran = $keterangan;
            }
        }
        unset($tagihan);

        return $listTagihan;
    }

    public static function filterTagihanByScope($listTagihan, $scope = 'semua', $semesterMahasiswa = null, $angkatanKode = null)
    {
        $listTagihan = $listTagihan ?: [];

        if (! in_array($scope, ['semester_ini', 'semester_depan'], true)) {
            return $listTagihan;
        }

        $groups = self::splitTagihanBySemester($listTagihan, $semesterMahasiswa, $angkatanKode);

        return $groups[$scope];
    }

    public static function getSisaTagihan($nim, $tagihan_id)
    {
        $tagihan     = KeuanganTagihan::select('jumlah')->where('id', $tagihan_id)->first();
        $jml_tagihan = $tagihan->jumlah;

        $pembayaran = KeuanganPembayaranIDN::select(DB::raw('SUM(total_bill_amount) as total_bayar'))
            ->where('tagihan_id', $tagihan_id)->where('bill_key', $nim)->first();
        $jml_bayar_idn = $pembayaran->total_bayar;

        $pembayaran = KeuanganPembayaran::select(DB::raw('SUM(jumlah) as total_bayar'))
            ->where('tagihan_id', $tagihan_id)->where('nim', $nim)->first();
        $jml_bayar_pdw = $pembayaran->total_bayar;

        $sisa = $jml_tagihan - ($jml_bayar_idn + $jml_bayar_pdw);
        return $sisa;
    }

    public static function tagihan($nim)
    {
        $nim = strtoupper($nim);
        $mhs = Mahasiswa::nim($nim);

        if (! $mhs) {
            return [
                'nama_mhs'     => null,
                'nama_prodi'   => null,
                'nama_kelas'   => null,
                'list_tagihan' => null,
                'smt'          => null,
                'angkatan'     => null,
                'semester'     => null,
            ];
        }

        $angkatan = (int) substr($mhs->th_akademik->kode, 0, 4);
        if ($mhs->prodi_double_degree_id) {
            if ($angkatan <= 2023) {
                $mhs->th_akademik_id = 21;
            }
        }

        $tagihan = KeuanganTagihan::where(function ($query) use ($mhs, $nim) {
            $query->where(function ($query) use ($mhs) {
                $query->whereNull('nim')
                    ->where('th_angkatan_id', $mhs->th_akademik_id)
                    ->where('kelas_id', $mhs->kelas_id)
                    ->when($mhs->prodi_double_degree_id == null, function ($query) use ($mhs) {
                        $query->where('prodi_id', $mhs->prodi_id);
                        $query->where(function ($query) {
                            $query->where('double_degree', '=', 0);
                            $query->orWhereNull('double_degree');
                        });
                    })
                    ->when($mhs->prodi_double_degree_id, function ($query) use ($mhs) {
                        $query->where(function ($query) {
                            $query->where('double_degree', '=', 1);
                        });
                        $query->where('prodi_id', $mhs->prodi_double_degree_id);
                    });
            })->orWhere('nim', $nim);
            })
            ->with(['th_akademik', 'mata_uang'])
            ->get();

        $listTagihan = [];

        foreach ($tagihan as $row) {
            $sisa = TagihanMahasiswa::getSisaTagihan($mhs->nim, $row->id);

            // dispensasi tagihan
            $dispensasiTagihan = KeuanganDispensasiTagihan::where([
                ['jenis_tagihan_id', $row->id],
                ['nim', $nim],
            ])->first();
            $batasDispensasi  = ($dispensasiTagihan) ? $dispensasiTagihan->batas : null;
            $statusDispensasi = false;
            if ($dispensasiTagihan) {
                $tanggalSekarang   = date('Y-m-d');
                $tanggalDispensasi = date('Y-m-d', strtotime($dispensasiTagihan->batas));
                if ($tanggalSekarang <= $tanggalDispensasi) {
                    $statusDispensasi = true;
                }
                // if($dispensasiTagihan->jenis == 'Non Beasiswa'){
                //     $statusDispensasi = false;
                // }
            }
            $jumlahDispensasi = ($dispensasiTagihan) ? $dispensasiTagihan->jumlah : 0;

            $sisaAkhir = ($statusDispensasi) ? $sisa - $jumlahDispensasi : $sisa; // sisa total dari pembayaran dan dispensasi
            if (@$dispensasiTagihan->jenis == 'Non Beasiswa') {
                $sisaAkhir = $sisa;
            }
            if ($sisa > 0) {
                $thAkademik = $row->th_akademik;

                $row->dibayar          = $row->jumlah - $sisa;
                $row->sisa             = $sisaAkhir;
                $row->tahun_akademik   = $thAkademik->nama;
                $row->semester         = $thAkademik->semester;
                $row->th_akademik_kode = $thAkademik->kode;
                $row->semester_tagihan = self::getSemesterTagihan($row, $mhs->th_akademik->kode);
                $row->mata_uang_id     = $row->mata_uang?->id;
                $row->mata_uang_kode   = $row->mata_uang?->kode ?? 'IDR';
                $row->mata_uang_nama   = $row->mata_uang?->nama ?? 'Rupiah';
                $row->mata_uang_simbol = $row->mata_uang?->simbol ?? 'Rp';

                $row->status_dispensasi = $statusDispensasi;
                $row->batas_dispensasi  = $batasDispensasi;
                $row->jumlah_dispensasi = $jumlahDispensasi;
                $row->jenis_dispensasi  = @$dispensasiTagihan->jenis;

                $listTagihan[] = $row;
            }
        }
        $tagihanGroups = self::splitTagihanBySemester($listTagihan, @$mhs->semester, @$mhs->th_akademik->kode);
        $return = [
            'nama_mhs'     => $mhs->nama,
            'nama_prodi'   => $mhs->prodi_double_degree_id ? $mhs->prodi_double_degree->nama . ' - Double Degree' : $mhs->prodi->nama,
            'nama_kelas'   => @$mhs->kelas->nama,
            'list_tagihan' => $listTagihan,
            'list_tagihan_semester_ini' => $tagihanGroups['semester_ini'],
            'list_tagihan_semester_depan' => $tagihanGroups['semester_depan'],
            'angkatan'     => @$mhs->th_akademik->kode,
            'semester'     => @$mhs->semester,
        ];
        // dd($listTagihan);

        return $return;
    }

    public static function tagihanKKN($nim)
    {
        $nim = strtoupper($nim);
        $mhs = Mahasiswa::nim($nim);
        if ($mhs) {
            $tagihan = KeuanganTagihan::where('th_angkatan_id', $mhs->th_akademik_id)
                ->with(['th_akademik', 'mata_uang'])
                ->where('prodi_id', $mhs->prodi_id)
                ->where('kelas_id', $mhs->kelas_id)
                ->whereNull('nim')
                ->where('nama', 'LIKE', '%KKN%')
                ->first();

            if (! $tagihan) {
                return [
                    'nama_mhs'     => $mhs->nama,
                    'nama_prodi'   => @$mhs->prodi->nama,
                    'nama_kelas'   => @$mhs->kelas->nama,
                    'list_tagihan' => null,
                    'angkatan'     => @$mhs->th_akademik->kode,
                ];
            }
            $sisa = TagihanMahasiswa::getSisaTagihan($mhs->nim, $tagihan->id);

            // dispensasi tagihan
            $dispensasiTagihan = KeuanganDispensasiTagihan::where([
                ['jenis_tagihan_id', $tagihan->id],
                ['nim', $nim],
            ])->first();
            $batasDispensasi  = ($dispensasiTagihan) ? $dispensasiTagihan->batas : null;
            $statusDispensasi = false;
            if ($dispensasiTagihan) {
                $tanggalSekarang   = date('Y-m-d');
                $tanggalDispensasi = date('Y-m-d', strtotime($dispensasiTagihan->batas));
                if ($tanggalSekarang <= $tanggalDispensasi) {
                    $statusDispensasi = true;
                }
            }
            $jumlahDispensasi = ($dispensasiTagihan) ? $dispensasiTagihan->jumlah : 0;

            $sisaAkhir = ($statusDispensasi) ? $sisa - $jumlahDispensasi : $sisa; // sisa total dari pembayaran dan dispensasi
            if ($sisa > 0) {
                $thAkademik = $tagihan->th_akademik;

                $tagihan->dibayar          = $tagihan->jumlah - $sisa;
                $tagihan->sisa             = $sisaAkhir;
                $tagihan->tahun_akademik   = $thAkademik->nama;
                $tagihan->semester         = $thAkademik->semester;
                $tagihan->th_akademik_kode = $thAkademik->kode;
                $tagihan->mata_uang_id     = $tagihan->mata_uang?->id;
                $tagihan->mata_uang_kode   = $tagihan->mata_uang?->kode ?? 'IDR';
                $tagihan->mata_uang_nama   = $tagihan->mata_uang?->nama ?? 'Rupiah';
                $tagihan->mata_uang_simbol = $tagihan->mata_uang?->simbol ?? 'Rp';

                $tagihan->status_dispensasi = $statusDispensasi;
                $tagihan->batas_dispensasi  = $batasDispensasi;
                $tagihan->jumlah_dispensasi = $jumlahDispensasi;
            } else {
                $tagihan = null;
            }
            $return = [
                'nama_mhs'     => $mhs->nama,
                'nama_prodi'   => @$mhs->prodi->nama,
                'nama_kelas'   => @$mhs->kelas->nama,
                'list_tagihan' => $tagihan,
                'angkatan'     => @$mhs->th_akademik->kode,
            ];
        } else {
            $return = [
                'nama_mhs'     => null,
                'nama_prodi'   => null,
                'nama_kelas'   => null,
                'list_tagihan' => null,
                'smt'          => null,
                'angkatan'     => null,
            ];
        }
        return $return;
    }
}
