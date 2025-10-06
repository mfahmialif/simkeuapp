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
        if ($mhs) {
            $tagihan = KeuanganTagihan::where('th_angkatan_id', $mhs->th_akademik_id)
                ->where('kelas_id', $mhs->kelas_id)
                ->when($mhs->prodi_double_degree_id == null, function ($query) use ($mhs) {
                    $query->where('prodi_id', $mhs->prodi_id);
                    $query->where(function($query){
                        $query->where('double_degree', '=', 0);
                        $query->orWhereNull('double_degree');
                    });
                })
                ->when($mhs->prodi_double_degree_id, function ($query) use ($mhs) {
                    $query->where(function ($query) {
                        $query->where('double_degree', '=', 1);
                    });
                    $query->where('prodi_id', $mhs->prodi_double_degree_id);
                })
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
                    $row->dibayar        = $row->jumlah - $sisa;
                    $row->sisa           = $sisaAkhir;
                    $row->tahun_akademik = $row->th_akademik->nama;
                    $row->semester       = $row->th_akademik->semester;

                    $row->status_dispensasi = $statusDispensasi;
                    $row->batas_dispensasi  = $batasDispensasi;
                    $row->jumlah_dispensasi = $jumlahDispensasi;
                    $row->jenis_dispensasi  = @$dispensasiTagihan->jenis;

                    $listTagihan[] = $row;
                }
            }
            $return = [
                'nama_mhs'     => $mhs->nama,
                'nama_prodi'   => @$mhs->prodi->nama,
                'nama_kelas'   => @$mhs->kelas->nama,
                'list_tagihan' => $listTagihan,
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
        // dd($listTagihan);

        return $return;
    }

    public static function tagihanKKN($nim)
    {
        $nim = strtoupper($nim);
        $mhs = Mahasiswa::nim($nim);
        if ($mhs) {
            $tagihan = KeuanganTagihan::where('th_angkatan_id', $mhs->th_akademik_id)
                ->where('prodi_id', $mhs->prodi_id)
                ->where('kelas_id', $mhs->kelas_id)
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
                $tagihan->dibayar        = $tagihan->jumlah - $sisa;
                $tagihan->sisa           = $sisaAkhir;
                $tagihan->tahun_akademik = $tagihan->th_akademik->nama;
                $tagihan->semester       = $tagihan->th_akademik->semester;

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
