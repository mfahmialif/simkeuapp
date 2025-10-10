<?php

namespace App\Services;

use App\Models\ThAkademik;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use App\Models\KeuanganSetting;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\KeuanganPembayaranTambahan;

class Helper
{
    public static function penyebut($nilai)
    {
        $nilai = abs($nilai);
        $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        $temp = "";
        if ($nilai < 12) {
            $temp = " " . $huruf[$nilai];
        } else if ($nilai < 20) {
            $temp = Helper::penyebut($nilai - 10) . " belas";
        } else if ($nilai < 100) {
            $temp = Helper::penyebut($nilai / 10) . " puluh" . Helper::penyebut($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " seratus" . Helper::penyebut($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = Helper::penyebut($nilai / 100) . " ratus" . Helper::penyebut($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " seribu" . Helper::penyebut($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = Helper::penyebut($nilai / 1000) . "ribu" . Helper::penyebut($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = Helper::penyebut($nilai / 1000000) . " juta" . Helper::penyebut($nilai % 1000000);
        } else if ($nilai < 1000000000000) {
            $temp = Helper::penyebut($nilai / 1000000000) . " milyar" . Helper::penyebut(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = Helper::penyebut($nilai / 1000000000000) . " trilyun" . Helper::penyebut(fmod($nilai, 1000000000000));
        }
        return $temp;
    }

    public static function terbilang($nilai)
    {
        if ($nilai < 0) {
            $hasil = "minus " . trim(Helper::penyebut($nilai));
        } else {
            $hasil = trim(Helper::penyebut($nilai));
        }
        return $hasil;
    }

    public static function formatNumber($angka)
    {
        return number_format($angka, 0, ",", ".");
    }

    public static function semester($th_masuk, $npm = null)
    {
        // $npm = null;
        $thAktif = ThAkademik::Aktif()->first();
        $smt = $thAktif->semester;
        $cuti = 0; //App\kemahasiswaan\CutiMahasiswa::where('npm',$npm)->count();

        $thA = substr($thAktif->kode, 0, 4);
        $thM = substr($th_masuk, 0, 4);

        $smtM = substr($thAktif->kode, 4, 1);

        if ((int) $thA >= $thM) {
            $th = $thA - $thM;
            $hasil = ($th * 2) + $smtM;
        } else {
            $hasil = 0; //$thAktif->kode.'-'.$th_masuk;
        }

        return $hasil;
    }

    public static function generateNumber()
    {
        $th = date('Y');
        $th .= uniqid();
        // $row = KeuanganPembayaran::select(DB::raw('right(nomor,7) as nomor_akhir'))
        //     ->whereYear('tanggal', $th)->orderBy('nomor', 'DESC')->limit(1)->first();

        //     $uniqid = uniqid();
        // if (isset($row)) {
        //     $akhir = (int) $row->nomor_akhir + 1;
        //     $return = $th . sprintf("%07s$uniqid", $akhir);
        // } else {
        //     $return = $th . '0000001';
        // }
        return $th;
    }

    public static function generateNotaBanat($tanggal)
    {
        $notaStart = KeuanganSetting::where('slug', 'nota')->first();
        $number = sprintf("%05d", $notaStart->value);
        $notaBaru = $notaStart->value + 1;
        $notaStart->value = $notaBaru;
        $notaStart->save();

        $cek = KeuanganNota::where('nota', "$tanggal-$number")->first();
        if ($cek) {
            return [
                'status' => true,
                'number' => $number
            ];
        } else {
            return [
                'status' => false,
                'number' => $number
            ];
        }
    }

    public static function generateNota($tanggal, $jkId)
    {
        $d = date('Y-m-d', strtotime($tanggal));
        $tanggalNota = date('dmy', strtotime($tanggal));
        $nim = Mahasiswa::all(null, null, null, null, null, [
            ['mst_mhs.jk_id', '=', $jkId]
        ], ['nim']);

        $nimList = $nim; // hasil API: array of NIM (sudah di-pluck & terfilter jk)

        $query = KeuanganPembayaran::query()
            ->leftJoin('keuangan_nota as kn', 'kn.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->whereDate('keuangan_pembayaran.tanggal', $d);

        $query->where(function ($q) use ($nimList) {
            foreach (array_chunk($nimList, 1000) as $chunk) {
                $q->orWhereIn('keuangan_pembayaran.nim', $chunk);
            }
        });

        $transaksiTagihan = $query->orderByDesc('kn.nota')->first();

        $jk = 0;
        if ($jkId == 9) {
            // $notaBanat = Helper::generateNotaBanat($tanggalNota);
            // while ($notaBanat['status']) {
            //     $notaBanat = Helper::generateNotaBanat($tanggalNota);
            // }
            // $number = $notaBanat['number'];
            $jk = "P";
        }

        if ($jkId == 8) {
            $jk = "L";
        }
        $number = sprintf("%05d", 1);
        if ($transaksiTagihan != null) {
            $nomer = $transaksiTagihan->nota;
            if ($nomer) {
                $len = 5;
                $n = substr($nomer, 7, $len) + 1;
                $number = sprintf("%05d", $n);
            }
        }

        $uniq = Helper::randomNumber();
        return "$tanggalNota-$number-$jk-$uniq";
    }
    public static function generateNumberTambahan()
    {
        $th = date('Y');
        $th .= uniqid();
        // $row = KeuanganPembayaran::select(DB::raw('right(nomor,7) as nomor_akhir'))
        //     ->whereYear('tanggal', $th)->orderBy('nomor', 'DESC')->limit(1)->first();

        //     $uniqid = uniqid();
        // if (isset($row)) {
        //     $akhir = (int) $row->nomor_akhir + 1;
        //     $return = $th . sprintf("%07s$uniqid", $akhir);
        // } else {
        //     $return = $th . '0000001';
        // }
        return $th;
    }

    public static function generateNotaTambahan($tanggal)
    {
        $d = date('Y-m-d', strtotime($tanggal));
        $tanggalNota = date('dmy', strtotime($tanggal));
        $transaksiTagihan = KeuanganPembayaranTambahan::whereDate('tanggal', $d)
            ->orderBy('nota', 'desc')
            ->first();

        $number = sprintf("%05d", 1);
        if ($transaksiTagihan != null) {
            $nomer = $transaksiTagihan->nota;
            if ($nomer) {
                $len = 5;
                $n = substr($nomer, 7, $len) + 1;
                $number = sprintf("%05d", $n);
            }
        }

        $uniq = Helper::randomNumber();
        return "$tanggalNota-$number-$uniq";
    }

    public static function randomNumber()
    {
        // Generate a unique 4-digit value
        $uniqueValue = substr(uniqid(), -4);

        // Ensure it's exactly 4 digits
        $uniqueValue = str_pad($uniqueValue, 4, '0', STR_PAD_LEFT);
        $uniqueValue = strtoupper($uniqueValue);
        return $uniqueValue;
    }
    /**
     * return id jenis_kelamin dari tabel ref
     */
    public static function getJenisKelaminUser()
    {
        $putra = (object) [
            "id" => 8,
            "nama" => "Laki-Laki",
            "kategori" => "Putra",
            "kode" => "L",
        ];
        $putri = (object) [
            "id" => 9,
            "nama" => "Perempuan",
            "kategori" => "Putri",
            "kode" => "P",
        ];
        $semua = (object) [
            "id" => "%",
            "nama" => "%",
            "kategori" => "%",
            "kode" => "%",
        ];

        $jenisKelamin = auth()->user()->jk_id != null ? auth()->user()->jk_id : 8;

        if ($jenisKelamin == 9) {
            return $putri;
        } else {
            if (auth()->user()->level_id == 1) { // jika admin
                if (strtolower(Session::get('kategori_sistem')) == "putri") {
                    return $putri;
                }
                if (strtolower(Session::get('kategori_sistem')) == "putra") {
                    return $putra;
                }
                return $semua;
            }

            return $putra;
        }
    }

    public static function isAdmin()
    {
        if (auth()->user()->level_id == 1) {
            return true;
        } else {
            return false;
        }
    }
}
