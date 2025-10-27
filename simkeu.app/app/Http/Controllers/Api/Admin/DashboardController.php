<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\ThAkademik;
use Illuminate\Http\Request;
use App\Models\KeuanganSaldo;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\KeuanganSaldoPemasukan;
use App\Models\KeuanganSaldoPengeluaran;

class DashboardController extends Controller
{
    public function widget()
    {
        $saldo = KeuanganSaldo::sum('saldo');
        $jumlahSaldo = KeuanganSaldo::count();
        $pemasukan = KeuanganSaldoPemasukan::sum('jumlah');
        $pengeluaran = KeuanganSaldoPengeluaran::sum('jumlah');
        $jumlahPemasukan = KeuanganSaldoPemasukan::count();
        $jumlahPengeluaran = KeuanganSaldoPengeluaran::count();
        $jumlahUser = User::count();

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil diambil',
            'data' => [
                'saldo' => $saldo,
                'jumlahSaldo' => $jumlahSaldo,
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'jumlahPemasukan' => $jumlahPemasukan,
                'jumlahPengeluaran' => $jumlahPengeluaran,
                'jumlahUser' => $jumlahUser,
            ],
        ]);
    }

    public function financeOverview()
    {
        try {
            //code...
            $spp = KeuanganPembayaran::join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->where('keuangan_tagihan.nama', 'LIKE', '%SPP%')
                ->sum('keuangan_pembayaran.jumlah');

            $registrasi = KeuanganPembayaran::join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->where('keuangan_tagihan.nama', 'LIKE', '%regis%')
                ->orWhere('keuangan_tagihan.nama', 'LIKE', '%daftar%')
                ->sum('keuangan_pembayaran.jumlah');

            // $lainnya = KeuanganSaldoPemasukan::selectRaw('
            //             keuangan_saldo.nama as nama_saldo,
            //             SUM(keuangan_saldo_pemasukan.jumlah) as total
            //         ')
            //     ->join('keuangan_saldo', 'keuangan_saldo.id', '=', 'keuangan_saldo_pemasukan.saldo_id')
            //     ->groupBy('keuangan_saldo.nama')
            //     ->get();
            // foreach ($lainnya as $key => $value) {
            //     $finance[$value->nama_saldo] = $value->total;
            // }
            $lainnya = KeuanganSaldoPemasukan::sum('jumlah');

            $finance = [
                'spp' => $spp,
                'registrasi' => $registrasi,
                'lainnya' => $lainnya,
            ];


            $total = count($finance) > 0 ? array_sum($finance) : 0;

            $data = [];
            foreach ($finance as $key => $value) {
                $data[] = [
                    'name' => $key,
                    'amount' => $value,
                    'percent' => number_format($value / $total * 100, 2),
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Data berhasil diambil',
                'data' => $data,
                'total' => $total
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function statistic()
    {
        try {

            $startDate = Carbon::now()->subDays(9)->startOfDay(); // 10 hari terakhir (termasuk hari ini)
            $endDate   = Carbon::now()->endOfDay();

            /**
             * Ambil agregat harian dari 3 sumber:
             * - keuangan_pembayaran (penerimaan)
             * - keuangan_saldo_pemasukan (penerimaan)
             * - keuangan_saldo_pengeluaran (pengeluaran)
             */
            $rows = DB::table('keuangan_pembayaran')
                ->selectRaw("DATE(keuangan_pembayaran.tanggal) AS tanggal, SUM(keuangan_pembayaran.jumlah) AS nominal, 'in' AS tipe")
                ->whereBetween('keuangan_pembayaran.tanggal', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(keuangan_pembayaran.tanggal)'))

                ->unionAll(
                    DB::table('keuangan_saldo_pemasukan')
                        ->selectRaw("DATE(keuangan_saldo_pemasukan.tanggal) AS tanggal, SUM(keuangan_saldo_pemasukan.jumlah) AS nominal, 'in' AS tipe")
                        ->whereBetween('keuangan_saldo_pemasukan.tanggal', [$startDate, $endDate])
                        ->groupBy(DB::raw('DATE(keuangan_saldo_pemasukan.tanggal)'))
                )

                ->unionAll(
                    DB::table('keuangan_saldo_pengeluaran')
                        ->selectRaw("DATE(keuangan_saldo_pengeluaran.tanggal) AS tanggal, SUM(keuangan_saldo_pengeluaran.jumlah) AS nominal, 'out' AS tipe")
                        ->whereBetween('keuangan_saldo_pengeluaran.tanggal', [$startDate, $endDate])
                        ->groupBy(DB::raw('DATE(keuangan_saldo_pengeluaran.tanggal)'))
                )
                ->get();

            /**
             * Kelompokkan per tanggal
             */
            $grouped = $rows->groupBy('tanggal')->map(function ($items, $date) {
                $in  = $items->where('tipe', 'in')->sum('nominal');
                $out = $items->where('tipe', 'out')->sum('nominal');

                return [
                    'tanggal'     => $date,
                    'penerimaan'  => (float) $in,
                    'pengeluaran' => (float) $out,
                ];
            })->values();

            /**
             * Pastikan setiap tanggal dalam 10 hari terakhir ada di hasil
             */
            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());
            $categories = [];
            $penerimaan = [];
            $pengeluaran = [];

            foreach ($period as $day) {
                $key = $day->format('Y-m-d');
                $item = $grouped->firstWhere('tanggal', $key);

                $categories[] = $day->format('d M'); // contoh: 27 Okt
                $penerimaan[] = $item['penerimaan']  ?? 0;
                $pengeluaran[] = $item['pengeluaran'] ?? 0;
            }

            return response()->json([
                'status'      => true,
                'message'     => 'Data berhasil diambil',
                'categories'  => $categories,
                'penerimaan'  => $penerimaan,
                'pengeluaran' => $pengeluaran,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }
}
