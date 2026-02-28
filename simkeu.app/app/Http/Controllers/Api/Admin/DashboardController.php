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

    public function financeOverview(Request $request)
    {
        try {
            $query = DB::table('keuangan_tagihan')
                ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id');

            // Filter opsional
            if ($request->th_akademik_id) {
                $query->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
            }
            if ($request->prodi_id) {
                $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
            }
            if ($request->jk_id) {
                $query->where('keuangan_pembayaran.jk_id', $request->jk_id);
            }

            // Bundle grouping: SPP, Registrasi, UAS — sisanya tetap nama asli
            $caseExpr = "CASE
                WHEN keuangan_tagihan.nama LIKE '%SPP%' THEN 'SPP'
                WHEN keuangan_tagihan.nama LIKE '%regis%' OR keuangan_tagihan.nama LIKE '%daftar%' THEN 'Registrasi'
                WHEN keuangan_tagihan.nama LIKE '%UAS%' THEN 'UAS'
                ELSE keuangan_tagihan.nama
            END";

            $data = $query
                ->selectRaw("{$caseExpr} as name, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                ->groupByRaw($caseExpr)
                ->orderByDesc('amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'amount' => (float) $item->amount,
                    ];
                });

            $total = $data->sum('amount');

            $data = $data->map(function ($item) use ($total) {
                $item['percent'] = $total > 0 ? number_format($item['amount'] / $total * 100, 2) : 0;
                return $item;
            })->values();

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

    /**
     * Detail breakdown per kategori tagihan
     * Query params:
     *   - category: SPP | Registrasi | UAS | nama tagihan lainnya
     *   - group_by: semester | prodi | bulan | tahun
     *   - th_akademik_id, prodi_id, jk_id (filter opsional)
     */
    public function financeOverviewDetail(Request $request)
    {
        try {
            $category = $request->category;
            $groupBy  = $request->group_by ?? 'semester';

            $query = DB::table('keuangan_tagihan')
                ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->leftJoin('th_akademik', 'keuangan_tagihan.th_akademik_id', '=', 'th_akademik.id')
                ->leftJoin('prodi', 'keuangan_tagihan.prodi_id', '=', 'prodi.id');

            // Filter berdasarkan kategori bundle
            switch ($category) {
                case 'SPP':
                    $query->where('keuangan_tagihan.nama', 'LIKE', '%SPP%');
                    break;
                case 'Registrasi':
                    $query->where(function ($q) {
                        $q->where('keuangan_tagihan.nama', 'LIKE', '%regis%')
                          ->orWhere('keuangan_tagihan.nama', 'LIKE', '%daftar%');
                    });
                    break;
                case 'UAS':
                    $query->where('keuangan_tagihan.nama', 'LIKE', '%UAS%');
                    break;
                default:
                    $query->where('keuangan_tagihan.nama', $category);
                    break;
            }

            // Filter opsional
            if ($request->th_akademik_id) {
                $query->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
            }
            if ($request->prodi_id) {
                $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
            }
            if ($request->jk_id) {
                $query->where('keuangan_pembayaran.jk_id', $request->jk_id);
            }

            // Group by pilihan
            switch ($groupBy) {
                case 'semester':
                    $query->selectRaw("CONCAT(th_akademik.nama, ' - ', th_akademik.semester) as label, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                          ->groupBy('th_akademik.id', 'th_akademik.nama', 'th_akademik.semester')
                          ->orderBy('th_akademik.nama', 'desc');
                    break;

                case 'prodi':
                    $query->selectRaw("COALESCE(prodi.nama, 'Tanpa Prodi') as label, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                          ->groupBy('prodi.id', 'prodi.nama')
                          ->orderByDesc('amount');
                    break;

                case 'bulan':
                    $query->selectRaw("DATE_FORMAT(keuangan_pembayaran.tanggal, '%Y-%m') as label, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                          ->groupByRaw("DATE_FORMAT(keuangan_pembayaran.tanggal, '%Y-%m')")
                          ->orderBy('label', 'desc');
                    break;

                case 'tahun':
                    $query->selectRaw("YEAR(keuangan_pembayaran.tanggal) as label, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                          ->groupByRaw("YEAR(keuangan_pembayaran.tanggal)")
                          ->orderBy('label', 'desc');
                    break;

                case 'detail':
                    // Tanpa bundle — tampilkan per nama tagihan asli
                    $query->selectRaw("keuangan_tagihan.nama as label, COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                          ->groupBy('keuangan_tagihan.nama')
                          ->orderByDesc('amount');
                    break;
            }

            $data = $query->get()->map(function ($item) {
                return [
                    'label'  => $item->label ?? '-',
                    'amount' => (float) $item->amount,
                ];
            });

            $total = $data->sum('amount');

            $data = $data->map(function ($item) use ($total) {
                $item['percent'] = $total > 0 ? number_format($item['amount'] / $total * 100, 2) : 0;
                return $item;
            })->values();

            return response()->json([
                'status'   => true,
                'message'  => 'Data berhasil diambil',
                'category' => $category,
                'group_by' => $groupBy,
                'data'     => $data,
                'total'    => $total,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
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

