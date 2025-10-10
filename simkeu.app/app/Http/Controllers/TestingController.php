<?php
namespace App\Http\Controllers;

use App\Services\Mahasiswa;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;
use App\Services\Helper;

class TestingController extends Controller
{
    public function index()
    {
        // $nota = Helper::generateNota('2025-10-09', 8);
        // dd($nota);
        $mahasiswa = Mahasiswa::nim('["202085030001","202085030001","202085030001","202085030001","202085030001","202485030019","202485030019","202485200131","202485200112","202385200137"]', true);
        dd($mahasiswa);
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
