<?php
namespace App\Http\Controllers;

use App\Services\Mahasiswa;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;

class TestingController extends Controller
{
    public function index()
    {
        $mahasiswaApi = Mahasiswa::all();

        $nimList = collect($mahasiswaApi)
            ->filter(fn($m) => str_contains((string) $m->jk_id, '8'))
            ->pluck('nim')
            ->values();

        $pemasukan = 0;
        $nimList->chunk(1000)->each(function ($chunk) use (&$pemasukan) {
            $batch = KeuanganPembayaran::with('tagihan')
                ->whereIn('nim', $chunk)
                ->get();

            foreach ($batch as $t) {
                if ($t->jumlah == $t->nim) {
                    $t->jumlah = optional($t->tagihan)->jumlah ?? 0;
                }
                $pemasukan += (float) $t->jumlah;
            }
        });

        $setoran     = KeuanganSetoran::where('kategori', 'LIKE', "%{$jk->kategori}%")->get();
        $pengeluaran = 0;
        $pending     = 0;
        foreach ($setoran as $s) {
            $status = strtolower((string) $s->status);
            if ($status === 'setuju') {
                $pengeluaran += (float) $s->jumlah;
            }

            if ($status === 'pending') {
                $pending += (float) $s->jumlah;
            }

        }

        return [
            'pemasukan' => $pemasukan,
            // 'pengeluaran' => $pengeluaran,
            // 'pending'     => $pending,
        ];

    }
}
