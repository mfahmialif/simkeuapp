<?php

namespace App\Exports;

use App\Services\Helper;
use App\Models\KeuanganPembayaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PembayaranTahunanExport implements FromView
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $tahun;
    public $kategori;
    public $prodi;
    public $tahunAkademik;
    public $jenisPembayaran;

    public function __construct($tahun, $kategori, $prodi, $tahunAkademik, $jenisPembayaran)
    {
        $this->prodi = $prodi;
        $this->tahunAkademik = $tahunAkademik;
        $this->jenisPembayaran = $jenisPembayaran;
        $this->tahun = $tahun;
        $this->kategori = $kategori;
    }
    public function view(): View
    {
        $pilihTahun = $this->tahun;
        $kategori = $this->kategori;
        $prodi = $this->prodi;
        $tahunAkademik = $this->tahunAkademik;
        $jenisPembayaran = $this->jenisPembayaran;

        $dataPembayaran = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
            ->join('mst_mhs as mhs', 'mhs.nim', '=', 'keuangan_pembayaran.nim')
            ->leftJoin('keuangan_jenis_pembayaran_detail as kjpd', 'kjpd.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran as kjp', 'kjp.id', '=', 'kjpd.jenis_pembayaran_id')
            ->select('*', 'kjp.nama as kjp_nama', 'kt.nama as kt', 'keuangan_pembayaran.jumlah as dibayar', 'kt.jumlah as jumlah_tagihan');
        if ($pilihTahun != '') {
            $dataPembayaran->whereYear('tanggal', $pilihTahun);
        }
        if ($jenisPembayaran != '') {
            $dataPembayaran->where('kjpd.jenis_pembayaran_id', $jenisPembayaran);
        } elseif ($jenisPembayaran == "kosong") {
            $dataPembayaran->where('kjpd.jenis_pembayaran_id', null);
        }
        if ($prodi != '') {
            $dataPembayaran->where('kt.prodi_id', $prodi);
        }
        if ($tahunAkademik != '') {
            $dataPembayaran->where('kt.th_akademik_id', $tahunAkademik);
        }

        $jp = Helper::getJenisKelaminUser();
        $dataPembayaran = $dataPembayaran
            ->where('mhs.jk_id', 'LIKE', "%$jp->id%")
            ->orderBy('kt.prodi_id', 'asc')->orderBy('kjpd.jenis_pembayaran_id', 'asc')->get();

        return view('admin.mhs-laporan.excel-tahunan', compact('dataPembayaran', 'prodi', 'tahunAkademik', 'jenisPembayaran', 'pilihTahun', 'kategori', 'jp'));
    }
}
