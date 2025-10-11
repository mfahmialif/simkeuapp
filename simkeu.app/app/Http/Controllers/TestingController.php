<?php

namespace App\Http\Controllers;

use App\Services\Mahasiswa;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;
use App\Services\Helper;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    public function index()
    {
        dd(Mahasiswa::updateStatusMahasiswa('202485010002', 20));
        // $getSemester = Mahasiswa::getSemester(24, 6, 8);
        // $getMahasiswaBySemester = Mahasiswa::getMahasiswaBySemester(24, 6, 8, 1)->data;
        // $mahasiswa = collect($getMahasiswaBySemester->mahasiswa)->pluck('nim')->values();
        // dd($mahasiswa);

        // $jkId = [8, 9];
        // foreach ($jkId as $item) {
        //     $nim = collect(Mahasiswa::all(null, null, null, null, null, [
        //         ['mst_mhs.jk_id', '=', $item]
        //     ]))
        //         ->pluck('nim')        // pastikan jadi list NIM saja
        //         ->filter()            // buang null/kosong
        //         ->unique()
        //         ->values();

        //     foreach ($nim->chunk(1000) as $chunk) {
        //         KeuanganPembayaran::whereIn('nim', $chunk->all())
        //             ->update(['jk_id' => $item]);
        //     }
        // }
        // return response()->json(['ok' => true, 'message' => 'Sinkron jk_id selesai (fast join).']);
        // $pembayaranTanpaJenisPembayaran = KeuanganPembayaran::leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.pembayaran_id')
        //     // ->whereNull('keuangan_jenis_pembayaran_detail.id')
        //     // ->select('keuangan_pembayaran.*', 'keuangan_jenis_pembayaran_detail.id as jenis_pembayaran_id')
        //     ->get();
        // dd($pembayaranTanpaJenisPembayaran);

        // dd($pembayaranTanpaJenisPembayaran);
        // $nota = Helper::generateNota('2025-10-09', 8);
        // dd($nota);
        // Ambil data mahasiswa dari API

        // $mahasiswa = Mahasiswa::nim('["202085030001","202085030001","202085030001","202085030001","202085030001","202485030019","202485030019","202485200131","202485200112","202385200137"]', true);
        // dd($mahasiswa);
        // $mahasiswa = Mahasiswa::nim('202485030019');
        // $mahasiswa = Mahasiswa::all(null, null, null, null, null, [
        //     ['mst_mhs.jk_id', '=', 9]
        // ], ['nim']);

        // dd($mahasiswa);
        // $mahasiswaApi = Mahasiswa::all();

        // $nimList = collect($mahasiswaApi)
        //     ->filter(fn($m) => str_contains((string) $m->jk_id, '8'))
        //     ->pluck('nim')
        //     ->values();

        // $pemasukan = 0;
        // $nimList->chunk(1000)->each(function ($chunk) use (&$pemasukan) {
        //     $batch = KeuanganPembayaran::with('tagihan')
        //         ->whereIn('nim', $chunk)
        //         ->get();

        //     foreach ($batch as $t) {
        //         if ($t->jumlah == $t->nim) {
        //             $t->jumlah = optional($t->tagihan)->jumlah ?? 0;
        //         }
        //         $pemasukan += (float) $t->jumlah;
        //     }
        // });

        // $setoran     = KeuanganSetoran::where('kategori', 'LIKE', "%{$jk->kategori}%")->get();
        // $pengeluaran = 0;
        // $pending     = 0;
        // foreach ($setoran as $s) {
        //     $status = strtolower((string) $s->status);
        //     if ($status === 'setuju') {
        //         $pengeluaran += (float) $s->jumlah;
        //     }

        //     if ($status === 'pending') {
        //         $pending += (float) $s->jumlah;
        //     }

        // }

        // return [
        //     'pemasukan' => $pemasukan,
        //     // 'pengeluaran' => $pengeluaran,
        //     // 'pending'     => $pending,
        // ];

    }
}
