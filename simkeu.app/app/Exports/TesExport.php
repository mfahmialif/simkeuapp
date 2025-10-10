<?php

namespace App\Exports;

use App\Http\Services\Mahasiswa;
use App\Models\KeuanganPembayaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TesExport implements FromView
{

    public function view(): View
    {
        $kkn = KeuanganPembayaran::join('keuangan_tagihan as kt', 'kt.id', '=', 'keuangan_pembayaran.tagihan_id')
        ->where('keuangan_pembayaran.th_akademik_id', 19)
        ->where('kt.nama', 'LIKE', "%kkn%")
        ->select('keuangan_pembayaran.nim','keuangan_pembayaran.created_at', 'kt.nama as nama_tagihan')
        ->addSelect(\DB::raw('SUM(keuangan_pembayaran.jumlah) as jumlah'))
        ->groupBy('keuangan_pembayaran.nim', 'keuangan_pembayaran.created_at', 'kt.nama')
        ->get();
        return view('tes.excel', compact('kkn'));
    }
}