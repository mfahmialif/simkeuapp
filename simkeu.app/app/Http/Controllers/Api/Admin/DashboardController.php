<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\KeuanganSaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Models\KeuanganSaldoPemasukan;
use App\Models\KeuanganSaldoPengeluaran;
use App\Services\Helper;

class DashboardController extends Controller
{
    public function widget()
    {
        $jkUser = Helper::getJenisKelaminUser();
        $jkIdStr = (string)$jkUser->id; // 8, 9, or '%'

        $data = Cache::remember('dashboard_widget_v7_' . md5($jkIdStr), 30, function () use ($jkUser) {
            $today = Carbon::today()->format('Y-m-d');
            $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
            $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');

            // 1. Pembayaran Mahasiswa (Matrix Waktu x Gender)
            $pembayaranQuery = DB::table('keuangan_pembayaran');
            
            // Filter menggunakan Helper sesuai request
            $pembayaranQuery->where('jk_id', 'LIKE', "%" . $jkUser->id . "%");

            $selectRawPembayaran = "
                -- Semua
                COALESCE(SUM(CASE WHEN jk_id = 8 THEN jumlah ELSE 0 END), 0) as semua_laki,
                SUM(CASE WHEN jk_id = 8 THEN 1 ELSE 0 END) as count_semua_laki,
                COALESCE(SUM(CASE WHEN jk_id = 9 THEN jumlah ELSE 0 END), 0) as semua_perempuan,
                SUM(CASE WHEN jk_id = 9 THEN 1 ELSE 0 END) as count_semua_perempuan,
                -- Harian
                COALESCE(SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as harian_laki,
                SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_harian_laki,
                COALESCE(SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as harian_perempuan,
                SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_harian_perempuan,
                -- Mingguan
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as mingguan_laki,
                SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_mingguan_laki,
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as mingguan_perempuan,
                SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_mingguan_perempuan,
                -- Bulanan
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as bulanan_laki,
                SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_bulanan_laki,
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as bulanan_perempuan,
                SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_bulanan_perempuan
            ";
            
            $bindingsPembayaran = [
                $today, $today, $today, $today,
                $startOfWeek, $startOfWeek, $startOfWeek, $startOfWeek,
                $startOfMonth, $startOfMonth, $startOfMonth, $startOfMonth
            ];

            $pmb = $pembayaranQuery->selectRaw($selectRawPembayaran, $bindingsPembayaran)->first();

            // 1b. Pembayaran Semester Pendek (tambahan pemasukan)
            $spQuery = DB::table('keuangan_pembayaran_semester_pendek');
            $spQuery->where('jk_id', 'LIKE', "%" . $jkUser->id . "%");

            $sp = $spQuery->selectRaw($selectRawPembayaran, $bindingsPembayaran)->first();

            // 2. Keuangan Saldo Umum (Hanya jika user '*')
            $umum = (object)[
                'semua' => 0, 'count_semua' => 0,
                'harian' => 0, 'count_harian' => 0,
                'mingguan' => 0, 'count_mingguan' => 0,
                'bulanan' => 0, 'count_bulanan' => 0,
            ];

            if ($jkUser->id === '%') {
                $selectRawUmum = "
                    COALESCE(SUM(jumlah), 0) as semua,
                    COUNT(*) as count_semua,
                    COALESCE(SUM(CASE WHEN DATE(tanggal) = ? THEN jumlah ELSE 0 END), 0) as harian,
                    SUM(CASE WHEN DATE(tanggal) = ? THEN 1 ELSE 0 END) as count_harian,
                    COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? THEN jumlah ELSE 0 END), 0) as mingguan,
                    SUM(CASE WHEN DATE(tanggal) >= ? THEN 1 ELSE 0 END) as count_mingguan,
                    COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? THEN jumlah ELSE 0 END), 0) as bulanan,
                    SUM(CASE WHEN DATE(tanggal) >= ? THEN 1 ELSE 0 END) as count_bulanan
                ";
                $bindingsUmum = [$today, $today, $startOfWeek, $startOfWeek, $startOfMonth, $startOfMonth];
                $umum = KeuanganSaldoPemasukan::selectRaw($selectRawUmum, $bindingsUmum)->first();
            }

            // Helpers untuk menyusun response
            $buildPeriod = function($laki, $countLaki, $perempuan, $countPerempuan, $umumTotal, $countUmum, $spLaki = 0, $countSpLaki = 0, $spPerempuan = 0, $countSpPerempuan = 0) use ($jkUser) {
                $result = [];
                $keseluruhan = 0;
                $countKeseluruhan = 0;

                // Jika user login difilter laki-laki atau semua
                if ($jkUser->id == 8 || $jkUser->id === '%') {
                    $totalLaki = (float)$laki + (float)$spLaki;
                    $totalCountLaki = (int)$countLaki + (int)$countSpLaki;
                    $result['Laki-laki'] = ['value' => $totalLaki, 'change' => $totalCountLaki];
                    $keseluruhan += $totalLaki;
                    $countKeseluruhan += $totalCountLaki;
                }
                
                // Jika user login difilter perempuan atau semua
                if ($jkUser->id == 9 || $jkUser->id === '%') {
                    $totalPerempuan = (float)$perempuan + (float)$spPerempuan;
                    $totalCountPerempuan = (int)$countPerempuan + (int)$countSpPerempuan;
                    $result['Perempuan'] = ['value' => $totalPerempuan, 'change' => $totalCountPerempuan];
                    $keseluruhan += $totalPerempuan;
                    $countKeseluruhan += $totalCountPerempuan;
                }

                // Umum hanya untuk semua
                if ($jkUser->id === '%') {
                    $result['Umum'] = ['value' => (float)$umumTotal, 'change' => (int)$countUmum, 'hideIfZero' => true];
                    $keseluruhan += (float)$umumTotal;
                    $countKeseluruhan += (int)$countUmum;
                }

                $result['Keseluruhan'] = ['value' => $keseluruhan, 'change' => $countKeseluruhan];

                return $result;
            };

            $pemasukanBreakdown = [
                'Harian' => $buildPeriod($pmb->harian_laki, $pmb->count_harian_laki, $pmb->harian_perempuan, $pmb->count_harian_perempuan, $umum->harian, $umum->count_harian, $sp->harian_laki, $sp->count_harian_laki, $sp->harian_perempuan, $sp->count_harian_perempuan),
                'Mingguan' => $buildPeriod($pmb->mingguan_laki, $pmb->count_mingguan_laki, $pmb->mingguan_perempuan, $pmb->count_mingguan_perempuan, $umum->mingguan, $umum->count_mingguan, $sp->mingguan_laki, $sp->count_mingguan_laki, $sp->mingguan_perempuan, $sp->count_mingguan_perempuan),
                'Bulanan' => $buildPeriod($pmb->bulanan_laki, $pmb->count_bulanan_laki, $pmb->bulanan_perempuan, $pmb->count_bulanan_perempuan, $umum->bulanan, $umum->count_bulanan, $sp->bulanan_laki, $sp->count_bulanan_laki, $sp->bulanan_perempuan, $sp->count_bulanan_perempuan),
                'Semua' => $buildPeriod($pmb->semua_laki, $pmb->count_semua_laki, $pmb->semua_perempuan, $pmb->count_semua_perempuan, $umum->semua, $umum->count_semua, $sp->semua_laki, $sp->count_semua_laki, $sp->semua_perempuan, $sp->count_semua_perempuan),
            ];

            // 3. Saldo
            $saldoData = KeuanganSaldo::selectRaw('COALESCE(SUM(saldo), 0) as total_saldo, COUNT(*) as jumlah')->first();

            // 4. Pengeluaran
            $pengeluaranUmumData = KeuanganSaldoPengeluaran::selectRaw('COALESCE(SUM(jumlah), 0) as total, COUNT(*) as jumlah')->first();
            $pengeluaranDosenData = DB::table('keuangan_pengeluaran_dosen')->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as jumlah')->first();
            
            $jumlahUser = User::count();

            return [
                'saldo' => (float) $saldoData->total_saldo,
                'jumlahSaldo' => (int) $saldoData->jumlah,
                
                // Pemasukan default (Harian) untuk kartu
                'pemasukanHarian' => $pemasukanBreakdown['Harian']['Keseluruhan']['value'] ?? 0,
                'jumlahPemasukanHarian' => $pemasukanBreakdown['Harian']['Keseluruhan']['change'] ?? 0,
                
                'pemasukanBreakdown' => $pemasukanBreakdown,

                'pengeluaran' => (float) $pengeluaranUmumData->total + (float) $pengeluaranDosenData->total,
                'jumlahPengeluaran' => (int) $pengeluaranUmumData->jumlah + (int) $pengeluaranDosenData->jumlah,
                'jumlahUser' => $jumlahUser,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil diambil',
            'data' => $data,
        ]);
    }

    public function financeOverview(Request $request)
    {
        try {
            $cacheKey = 'dashboard_finance_overview_v2_' . md5(json_encode($request->only(['th_akademik_id', 'prodi_id', 'jk_id'])));

            $result = Cache::remember($cacheKey, 600, function () use ($request) {
                // Bundle grouping via CASE expression
                $caseExpr = "CASE
                    WHEN keuangan_tagihan.nama LIKE '%SPP%' THEN 'SPP'
                    WHEN keuangan_tagihan.nama LIKE '%regis%' OR keuangan_tagihan.nama LIKE '%daftar%' THEN 'Registrasi'
                    WHEN keuangan_tagihan.nama LIKE '%SEMESTER PENDEK%' THEN 'Semester Pendek'
                    WHEN keuangan_tagihan.nama LIKE '%UTS%' THEN 'UTS'
                    WHEN keuangan_tagihan.nama LIKE '%UAS%' THEN 'UAS'
                    ELSE keuangan_tagihan.nama
                END";

                // Subquery: hitung category name per row + jumlah pembayaran
                $subquery = DB::table('keuangan_tagihan')
                    ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->selectRaw("{$caseExpr} as category_name")
                    ->selectRaw("COALESCE(keuangan_pembayaran.jumlah, 0) as jumlah")
                    ->selectRaw("keuangan_pembayaran.jk_id");

                // Filter opsional pada subquery
                if ($request->th_akademik_id) {
                    $subquery->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
                }
                if ($request->prodi_id) {
                    $subquery->where('keuangan_tagihan.prodi_id', $request->prodi_id);
                }
                if ($request->jk_id) {
                    $subquery->where('keuangan_pembayaran.jk_id', $request->jk_id);
                }

                // Query utama: GROUP BY alias dari subquery (aman untuk strict mode)
                $data = DB::query()->fromSub($subquery, 'sub')
                    ->selectRaw("
                        sub.category_name as name,
                        COALESCE(SUM(sub.jumlah), 0) as amount,
                        COALESCE(SUM(CASE WHEN sub.jk_id = 8 THEN sub.jumlah ELSE 0 END), 0) as laki_laki,
                        COALESCE(SUM(CASE WHEN sub.jk_id = 9 THEN sub.jumlah ELSE 0 END), 0) as perempuan
                    ")
                    ->groupBy('sub.category_name')
                    ->orderByDesc('amount')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name'       => $item->name,
                            'amount'     => (float) $item->amount,
                            'laki_laki'  => (float) $item->laki_laki,
                            'perempuan'  => (float) $item->perempuan,
                        ];
                    });

                $total = $data->sum('amount');
                $totalLaki = $data->sum('laki_laki');
                $totalPerempuan = $data->sum('perempuan');

                $data = $data->map(function ($item) use ($total) {
                    $item['percent'] = $total > 0 ? number_format($item['amount'] / $total * 100, 2) : 0;
                    return $item;
                })->values();

                return [
                    'data'            => $data,
                    'total'           => $total,
                    'total_laki_laki' => $totalLaki,
                    'total_perempuan' => $totalPerempuan,
                ];
            });

            return response()->json([
                'status'          => true,
                'message'         => 'Data berhasil diambil',
                'data'            => $result['data'],
                'total'           => $result['total'],
                'total_laki_laki' => $result['total_laki_laki'],
                'total_perempuan' => $result['total_perempuan'],
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
            $cacheKey = 'dashboard_finance_detail_v2_' . md5(json_encode($request->only(['category', 'group_by', 'th_akademik_id', 'prodi_id', 'jk_id'])));

            $result = Cache::remember($cacheKey, 600, function () use ($request) {
                $category = $request->category;
                $groupBy  = $request->group_by ?? 'semester';

                $query = DB::table('keuangan_tagihan')
                    ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
                    ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
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
                    case 'Semester Pendek':
                        $query->where('keuangan_tagihan.nama', 'LIKE', '%SEMESTER PENDEK%');
                        break;
                    case 'UTS':
                        $query->where('keuangan_tagihan.nama', 'LIKE', '%UTS%');
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
                $groupByColumns = [];
                $groupByRaw = null;
                switch ($groupBy) {
                    case 'semester':
                        $labelExpr = "CONCAT(COALESCE(th_akademik.nama, ''), ' - ', COALESCE(th_akademik.semester, ''))";
                        $groupByColumns = ['th_akademik.id', 'th_akademik.nama', 'th_akademik.semester'];
                        break;

                    case 'prodi':
                        $labelExpr = "COALESCE(prodi.nama, 'Tanpa Prodi')";
                        $groupByColumns = ['prodi.id', 'prodi.nama'];
                        break;

                    case 'bulan':
                        $labelExpr = "DATE_FORMAT(keuangan_pembayaran.tanggal, '%Y-%m')";
                        $groupByRaw = "DATE_FORMAT(keuangan_pembayaran.tanggal, '%Y-%m')";
                        break;

                    case 'tahun':
                        $labelExpr = "YEAR(keuangan_pembayaran.tanggal)";
                        $groupByRaw = "YEAR(keuangan_pembayaran.tanggal)";
                        break;

                    case 'detail':
                        // Tanpa bundle — tampilkan per nama tagihan asli
                        $labelExpr = "keuangan_tagihan.nama";
                        $groupByColumns = ['keuangan_tagihan.nama'];
                        break;

                    default:
                        $labelExpr = "keuangan_tagihan.nama";
                        $groupByColumns = ['keuangan_tagihan.nama'];
                        break;
                }

                $query->selectRaw("{$labelExpr} as label")
                    ->selectRaw("COALESCE(keuangan_jenis_pembayaran.nama, 'Lainnya') as jenis_pembayaran")
                    ->selectRaw("COALESCE(keuangan_jenis_pembayaran.kategori, 'Semua') as jenis_kelamin")
                    ->selectRaw("COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as amount")
                    ->selectRaw("COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as laki_laki")
                    ->selectRaw("COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as perempuan");

                foreach ($groupByColumns as $column) {
                    $query->groupBy($column);
                }

                if ($groupByRaw) {
                    $query->groupByRaw($groupByRaw);
                }

                $query->groupBy(
                    'keuangan_jenis_pembayaran.id',
                    'keuangan_jenis_pembayaran.nama',
                    'keuangan_jenis_pembayaran.kategori'
                );

                $rows = $query->get();

                $data = $rows->groupBy(fn($item) => $item->label ?? '-')
                    ->map(function ($items, $label) {
                        $amount = $items->sum(fn($item) => (float) $item->amount);
                        $lakiLaki = $items->sum(fn($item) => (float) $item->laki_laki);
                        $perempuan = $items->sum(fn($item) => (float) $item->perempuan);

                        $paymentMethods = $items
                            ->filter(fn($item) => (float) $item->amount > 0)
                            ->sortByDesc(fn($item) => (float) $item->amount)
                            ->values()
                            ->map(function ($item) {
                                return [
                                    'nama'          => $item->jenis_pembayaran,
                                    'jenis_kelamin' => $item->jenis_kelamin,
                                    'amount'        => (float) $item->amount,
                                    'laki_laki'     => (float) $item->laki_laki,
                                    'perempuan'     => (float) $item->perempuan,
                                ];
                            })
                            ->values();

                        return [
                            'label'            => $label ?: '-',
                            'amount'           => $amount,
                            'laki_laki'        => $lakiLaki,
                            'perempuan'        => $perempuan,
                            'payment_methods'  => $paymentMethods,
                        ];
                    })
                    ->values();

                if (in_array($groupBy, ['detail', 'prodi'])) {
                    $data = $data->sortByDesc('amount')->values();
                } else {
                    $data = $data->sortByDesc('label')->values();
                }

                $total = $data->sum('amount');
                $totalLaki = $data->sum('laki_laki');
                $totalPerempuan = $data->sum('perempuan');

                $data = $data->map(function ($item) use ($total) {
                    $item['percent'] = $total > 0 ? number_format($item['amount'] / $total * 100, 2) : 0;
                    return [
                        'label'           => $item['label'],
                        'amount'          => (float) $item['amount'],
                        'laki_laki'       => (float) $item['laki_laki'],
                        'perempuan'       => (float) $item['perempuan'],
                        'payment_methods' => $item['payment_methods'],
                        'percent'         => $item['percent'],
                    ];
                })->values();

                return [
                    'category'        => $category,
                    'group_by'        => $groupBy,
                    'data'            => $data,
                    'total'           => $total,
                    'total_laki_laki' => $totalLaki,
                    'total_perempuan' => $totalPerempuan,
                ];
            });

            return response()->json([
                'status'          => true,
                'message'         => 'Data berhasil diambil',
                'category'        => $result['category'],
                'group_by'        => $result['group_by'],
                'data'            => $result['data'],
                'total'           => $result['total'],
                'total_laki_laki' => $result['total_laki_laki'],
                'total_perempuan' => $result['total_perempuan'],
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
            $result = Cache::remember('dashboard_statistic', 300, function () {
                $startDate = Carbon::now()->subDays(9)->startOfDay();
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
                        DB::table('keuangan_pembayaran_semester_pendek')
                            ->selectRaw("DATE(tanggal) AS tanggal, SUM(jumlah) AS nominal, 'in' AS tipe")
                            ->whereBetween('tanggal', [$startDate, $endDate])
                            ->groupBy(DB::raw('DATE(tanggal)'))
                    )

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

                    $categories[] = $day->format('d M');
                    $penerimaan[] = $item['penerimaan']  ?? 0;
                    $pengeluaran[] = $item['pengeluaran'] ?? 0;
                }

                return [
                    'categories'  => $categories,
                    'penerimaan'  => $penerimaan,
                    'pengeluaran' => $pengeluaran,
                ];
            });

            return response()->json([
                'status'      => true,
                'message'     => 'Data berhasil diambil',
                'categories'  => $result['categories'],
                'penerimaan'  => $result['penerimaan'],
                'pengeluaran' => $result['pengeluaran'],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function barokahSummary(Request $request)
    {
        try {
            $roleName = auth()->user()?->role?->name;
            $sources = $this->barokahSourcesForRole($roleName);

            $result = Cache::remember(
                'dashboard_barokah_summary_v1_' . md5(($roleName ?? 'none') . Carbon::now()->format('Y-m-d')),
                300,
                function () use ($sources, $roleName) {
                    $now = Carbon::now();
                    $today = $now->copy()->toDateString();
                    $startOfMonth = $now->copy()->startOfMonth();
                    $endOfMonth = $now->copy()->endOfMonth();
                    $year = (int) $now->year;
                    $month = (int) $now->month;
                    $startYear = $year - 4;

                    $stats = [
                        'hari_ini' => ['total' => 0, 'jumlah' => 0],
                        'bulan_ini' => ['total' => 0, 'jumlah' => 0],
                        'tahun_ini' => ['total' => 0, 'jumlah' => 0],
                        'keseluruhan' => ['total' => 0, 'jumlah' => 0],
                    ];

                    $dailyTotals = [];
                    for ($day = 1; $day <= $now->daysInMonth; $day++) {
                        $key = Carbon::create($year, $month, $day)->format('Y-m-d');
                        $dailyTotals[$key] = 0;
                    }

                    $monthlyTotals = array_fill(1, 12, 0);
                    $yearlyTotals = [];
                    for ($currentYear = $startYear; $currentYear <= $year; $currentYear++) {
                        $yearlyTotals[$currentYear] = 0;
                    }

                    $modules = [];
                    $topPegawai = [];

                    foreach ($sources as $source) {
                        $table = $source['table'];
                        $dateColumn = "{$table}.tanggal";
                        $totalColumn = "{$table}.total";
                        $baseQuery = $this->barokahSourceQuery($source);

                        $moduleTotal = (clone $baseQuery)
                            ->selectRaw("COALESCE(SUM({$totalColumn}), 0) as total, COUNT({$table}.id) as jumlah")
                            ->first();

                        $modules[] = [
                            'key' => $source['key'],
                            'label' => $source['label'],
                            'path' => $source['path'],
                            'icon' => $source['icon'],
                            'color' => $source['color'],
                            'total' => (float) ($moduleTotal->total ?? 0),
                            'jumlah' => (int) ($moduleTotal->jumlah ?? 0),
                        ];

                        $this->addBarokahStat(
                            $stats['hari_ini'],
                            (clone $baseQuery)->whereDate($dateColumn, $today),
                            $table,
                            $totalColumn
                        );

                        $this->addBarokahStat(
                            $stats['bulan_ini'],
                            $this->applyBarokahMonthScope((clone $baseQuery), $source, $year, $month),
                            $table,
                            $totalColumn
                        );

                        $this->addBarokahStat(
                            $stats['tahun_ini'],
                            $this->applyBarokahYearScope((clone $baseQuery), $source, $year),
                            $table,
                            $totalColumn
                        );

                        $this->addBarokahStat(
                            $stats['keseluruhan'],
                            (clone $baseQuery),
                            $table,
                            $totalColumn
                        );

                        $dailyRows = (clone $baseQuery)
                            ->whereBetween($dateColumn, [$startOfMonth, $endOfMonth])
                            ->selectRaw("DATE({$dateColumn}) as period, COALESCE(SUM({$totalColumn}), 0) as total")
                            ->groupBy(DB::raw("DATE({$dateColumn})"))
                            ->get();

                        foreach ($dailyRows as $row) {
                            if (array_key_exists($row->period, $dailyTotals)) {
                                $dailyTotals[$row->period] += (float) $row->total;
                            }
                        }

                        $monthlyRows = $this->barokahMonthlyRows((clone $baseQuery), $source, $year);
                        foreach ($monthlyRows as $row) {
                            $period = (int) $row->period;
                            if (isset($monthlyTotals[$period])) {
                                $monthlyTotals[$period] += (float) $row->total;
                            }
                        }

                        $yearlyRows = $this->barokahYearlyRows((clone $baseQuery), $source, $startYear, $year);
                        foreach ($yearlyRows as $row) {
                            $period = (int) $row->period;
                            if (isset($yearlyTotals[$period])) {
                                $yearlyTotals[$period] += (float) $row->total;
                            }
                        }

                        $pegawaiRows = (clone $baseQuery)
                            ->selectRaw("
                                COALESCE(pegawai.id, 0) as pegawai_id,
                                COALESCE(pegawai.kode, '-') as kode,
                                COALESCE(pegawai.nama, 'Tanpa Pegawai') as nama,
                                COALESCE(pegawai.tipe, '-') as tipe,
                                COALESCE(SUM({$totalColumn}), 0) as total,
                                COUNT({$table}.id) as jumlah
                            ")
                            ->groupBy('pegawai.id', 'pegawai.kode', 'pegawai.nama', 'pegawai.tipe')
                            ->orderByDesc('total')
                            ->limit(10)
                            ->get();

                        foreach ($pegawaiRows as $row) {
                            $key = (string) ($row->pegawai_id ?: 'unknown');
                            if (! isset($topPegawai[$key])) {
                                $topPegawai[$key] = [
                                    'pegawai_id' => (int) $row->pegawai_id,
                                    'kode' => $row->kode,
                                    'nama' => $row->nama,
                                    'tipe' => $row->tipe,
                                    'total' => 0,
                                    'jumlah' => 0,
                                    'modules' => [],
                                ];
                            }

                            $topPegawai[$key]['total'] += (float) $row->total;
                            $topPegawai[$key]['jumlah'] += (int) $row->jumlah;
                            $topPegawai[$key]['modules'][] = $source['label'];
                        }
                    }

                    $topPegawai = collect($topPegawai)
                        ->map(function ($item) {
                            $item['modules'] = array_values(array_unique($item['modules']));
                            return $item;
                        })
                        ->sortByDesc('total')
                        ->take(10)
                        ->values()
                        ->all();

                    $dailyCategories = array_map(
                        fn ($date) => Carbon::parse($date)->format('d M'),
                        array_keys($dailyTotals)
                    );

                    return [
                        'role' => $roleName,
                        'modules' => $modules,
                        'stats' => $stats,
                        'charts' => [
                            'harian' => [
                                'categories' => $dailyCategories,
                                'series' => [
                                    ['name' => 'Pengeluaran', 'data' => array_values($dailyTotals)],
                                ],
                            ],
                            'bulanan' => [
                                'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                                'series' => [
                                    ['name' => 'Pengeluaran', 'data' => array_values($monthlyTotals)],
                                ],
                            ],
                            'tahunan' => [
                                'categories' => array_map('strval', array_keys($yearlyTotals)),
                                'series' => [
                                    ['name' => 'Pengeluaran', 'data' => array_values($yearlyTotals)],
                                ],
                            ],
                        ],
                        'top_pegawai' => $topPegawai,
                    ];
                }
            );

            return response()->json([
                'status' => true,
                'message' => 'Data berhasil diambil',
                'data' => $result,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * KRS Report - proxy ke external API krs/getDataInfo
     */
    public function krsReport(Request $request)
    {
        try {
            $apiKey = config('simkeu.simkeu_api_key');
            $url = config('simkeu.simkeu_url') . "krs/getDataInfo";

            $post = array_filter($request->only(['th_akademik_id', 'prodi_id', 'kelas_id', 'th_angkatan_id']), function ($v) {
                return $v !== null && $v !== '';
            });

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $apiKey",
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            return response()->json([
                'status' => true,
                'data'   => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * KRS Report Detail - proxy ke external API krs/getData
     */
    public function krsReportDetail(Request $request)
    {
        try {
            $apiKey = config('simkeu.simkeu_api_key');
            $url = config('simkeu.simkeu_url') . "krs/getData";

            $post = array_filter($request->all(), function ($v) {
                return $v !== null && $v !== '';
            });

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: $apiKey",
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            return response()->json($data);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * KRS Report Local - query dari database lokal keuangan
     */
    public function krsReportLocal(Request $request)
    {
        try {
            $thAkademikId = $request->th_akademik_id;
            $prodiId      = $request->prodi_id;
            $jkId         = $request->jk_id;

            $tagihanQuery = DB::table('keuangan_tagihan')
                ->where(function ($q) {
                    $q->where('keuangan_tagihan.nama', 'LIKE', '%registrasi%')
                      ->orWhere('keuangan_tagihan.nama', 'LIKE', '%daftar ulang%');
                });

            if ($thAkademikId) {
                $tagihanQuery->where('keuangan_tagihan.th_akademik_id', $thAkademikId);
            }
            if ($prodiId) {
                $tagihanQuery->where('keuangan_tagihan.prodi_id', $prodiId);
            }

            $tagihanIds = $tagihanQuery->pluck('id');

            if ($tagihanIds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data'   => [
                        'total_mahasiswa_bayar' => 0,
                        'total_amount'          => 0,
                        'total_lunas'           => 0,
                        'total_belum_lunas'     => 0,
                    ],
                ]);
            }

            $studentPayments = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_pembayaran.tagihan_id,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    SUM(keuangan_pembayaran.jumlah) as total_paid
                ')
                ->groupBy('keuangan_pembayaran.nim', 'keuangan_pembayaran.tagihan_id', 'keuangan_tagihan.jumlah')
                ->get();

            $totalMahasiswaBayar = $studentPayments->unique('nim')->count();
            $totalAmount         = $studentPayments->sum('total_paid');
            $lunasCount          = $studentPayments->filter(fn($s) => $s->total_paid >= $s->tagihan_amount)->unique('nim')->count();
            $belumLunasCount     = $totalMahasiswaBayar - $lunasCount;

            return response()->json([
                'status' => true,
                'data'   => [
                    'total_mahasiswa_bayar' => $totalMahasiswaBayar,
                    'total_amount'          => (float) $totalAmount,
                    'total_lunas'           => $lunasCount,
                    'total_belum_lunas'     => $belumLunasCount,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * KRS Report Detail Local - paginated list dari database lokal
     */
    public function krsReportDetailLocal(Request $request)
    {
        try {
            $thAkademikId = $request->th_akademik_id;
            $prodiId      = $request->prodi_id;
            $jkId         = $request->jk_id;
            $search       = $request->search;
            $perPage      = $request->input('per_page', 15);

            $tagihanQuery = DB::table('keuangan_tagihan')
                ->where(function ($q) {
                    $q->where('keuangan_tagihan.nama', 'LIKE', '%registrasi%')
                      ->orWhere('keuangan_tagihan.nama', 'LIKE', '%daftar ulang%');
                });

            if ($thAkademikId) {
                $tagihanQuery->where('keuangan_tagihan.th_akademik_id', $thAkademikId);
            }
            if ($prodiId) {
                $tagihanQuery->where('keuangan_tagihan.prodi_id', $prodiId);
            }

            $tagihanIds = $tagihanQuery->pluck('id');

            if ($tagihanIds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data'   => [
                        'data'         => [],
                        'current_page' => 1,
                        'last_page'    => 1,
                        'total'        => 0,
                    ],
                ]);
            }

            $query = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->leftJoin('th_akademik', 'keuangan_pembayaran.th_akademik_id', '=', 'th_akademik.id')
                ->leftJoin('prodi', 'keuangan_tagihan.prodi_id', '=', 'prodi.id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->when($search, fn($q) => $q->where(function ($sq) use ($search) {
                    $sq->where('keuangan_pembayaran.nim', 'LIKE', "%{$search}%");
                }))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_tagihan.nama as tagihan_nama,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    SUM(keuangan_pembayaran.jumlah) as total_paid,
                    MAX(keuangan_pembayaran.tanggal) as last_payment_date,
                    COALESCE(prodi.nama, "-") as prodi_nama,
                    CONCAT(COALESCE(th_akademik.nama, ""), " - ", COALESCE(th_akademik.semester, "")) as th_akademik_nama
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_tagihan.nama',
                    'keuangan_tagihan.jumlah',
                    'prodi.nama',
                    'th_akademik.nama',
                    'th_akademik.semester'
                )
                ->orderByDesc('last_payment_date');

            $paginated = $query->paginate($perPage);

            $items = collect($paginated->items())->map(function ($item) {
                $item->sisa     = max(0, $item->tagihan_amount - $item->total_paid);
                $item->is_lunas = $item->total_paid >= $item->tagihan_amount;
                return $item;
            });

            return response()->json([
                'status' => true,
                'data'   => [
                    'data'         => $items,
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * UAS Report - summary dari database lokal keuangan
     */
    public function uasReport(Request $request)
    {
        try {
            $thAkademikId = $request->th_akademik_id;
            $prodiId      = $request->prodi_id;
            $jkId         = $request->jk_id;

            $tagihanQuery = DB::table('keuangan_tagihan')
                ->where('keuangan_tagihan.nama', 'LIKE', '%UAS%');

            if ($thAkademikId) {
                $tagihanQuery->where('keuangan_tagihan.th_akademik_id', $thAkademikId);
            }
            if ($prodiId) {
                $tagihanQuery->where('keuangan_tagihan.prodi_id', $prodiId);
            }

            $tagihanIds = $tagihanQuery->pluck('id');

            if ($tagihanIds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data'   => [
                        'total_mahasiswa_bayar' => 0,
                        'total_amount'          => 0,
                        'total_lunas'           => 0,
                        'total_belum_lunas'     => 0,
                    ],
                ]);
            }

            $studentPayments = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_pembayaran.tagihan_id,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    SUM(keuangan_pembayaran.jumlah) as total_paid
                ')
                ->groupBy('keuangan_pembayaran.nim', 'keuangan_pembayaran.tagihan_id', 'keuangan_tagihan.jumlah')
                ->get();

            $totalMahasiswaBayar = $studentPayments->unique('nim')->count();
            $totalAmount         = $studentPayments->sum('total_paid');
            $lunasCount          = $studentPayments->filter(fn($s) => $s->total_paid >= $s->tagihan_amount)->unique('nim')->count();
            $belumLunasCount     = $totalMahasiswaBayar - $lunasCount;

            return response()->json([
                'status' => true,
                'data'   => [
                    'total_mahasiswa_bayar' => $totalMahasiswaBayar,
                    'total_amount'          => (float) $totalAmount,
                    'total_lunas'           => $lunasCount,
                    'total_belum_lunas'     => $belumLunasCount,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * UAS Report Detail - paginated list dari database lokal
     */
    public function uasReportDetail(Request $request)
    {
        try {
            $thAkademikId = $request->th_akademik_id;
            $prodiId      = $request->prodi_id;
            $jkId         = $request->jk_id;
            $search       = $request->search;
            $perPage      = $request->input('per_page', 15);

            $tagihanQuery = DB::table('keuangan_tagihan')
                ->where('keuangan_tagihan.nama', 'LIKE', '%UAS%');

            if ($thAkademikId) {
                $tagihanQuery->where('keuangan_tagihan.th_akademik_id', $thAkademikId);
            }
            if ($prodiId) {
                $tagihanQuery->where('keuangan_tagihan.prodi_id', $prodiId);
            }

            $tagihanIds = $tagihanQuery->pluck('id');

            if ($tagihanIds->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data'   => [
                        'data'         => [],
                        'current_page' => 1,
                        'last_page'    => 1,
                        'total'        => 0,
                    ],
                ]);
            }

            $query = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->leftJoin('th_akademik', 'keuangan_pembayaran.th_akademik_id', '=', 'th_akademik.id')
                ->leftJoin('prodi', 'keuangan_tagihan.prodi_id', '=', 'prodi.id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->when($search, fn($q) => $q->where(function ($sq) use ($search) {
                    $sq->where('keuangan_pembayaran.nim', 'LIKE', "%{$search}%");
                }))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_tagihan.nama as tagihan_nama,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    SUM(keuangan_pembayaran.jumlah) as total_paid,
                    MAX(keuangan_pembayaran.tanggal) as last_payment_date,
                    COALESCE(prodi.nama, "-") as prodi_nama,
                    CONCAT(COALESCE(th_akademik.nama, ""), " - ", COALESCE(th_akademik.semester, "")) as th_akademik_nama
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_tagihan.nama',
                    'keuangan_tagihan.jumlah',
                    'prodi.nama',
                    'th_akademik.nama',
                    'th_akademik.semester'
                )
                ->orderByDesc('last_payment_date');

            $paginated = $query->paginate($perPage);

            $items = collect($paginated->items())->map(function ($item) {
                $item->sisa     = max(0, $item->tagihan_amount - $item->total_paid);
                $item->is_lunas = $item->total_paid >= $item->tagihan_amount;
                return $item;
            });

            return response()->json([
                'status' => true,
                'data'   => [
                    'data'         => $items,
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    private function barokahSourcesForRole(?string $roleName): array
    {
        $allSources = [
            'tatapmuka' => [
                'key' => 'tatapmuka',
                'label' => 'Barokah Tatapmuka',
                'table' => 'keuangan_pengeluaran_dosen',
                'path' => '/admin/pengeluaran/dosen-tatapmuka',
                'icon' => 'ri-user-voice-line',
                'color' => 'primary',
            ],
            'kegiatan' => [
                'key' => 'kegiatan',
                'label' => 'Barokah Kegiatan',
                'table' => 'keuangan_pengeluaran_dosen_kegiatan',
                'path' => '/admin/pengeluaran/dosen-kegiatan',
                'icon' => 'ri-calendar-event-line',
                'color' => 'info',
            ],
            'staff_bulanan' => [
                'key' => 'staff_bulanan',
                'label' => 'Staff Bulanan',
                'table' => 'keuangan_pengeluaran_pegawai_bulanan',
                'path' => '/admin/pengeluaran/staff-bulanan',
                'icon' => 'ri-team-line',
                'color' => 'success',
                'pegawai_tipe' => 'staff',
                'uses_periode' => true,
            ],
            'dosen_bulanan' => [
                'key' => 'dosen_bulanan',
                'label' => 'Dosen Bulanan',
                'table' => 'keuangan_pengeluaran_pegawai_bulanan',
                'path' => '/admin/pengeluaran/dosen-bulanan',
                'icon' => 'ri-calendar-check-line',
                'color' => 'warning',
                'pegawai_tipe' => 'dosen',
                'uses_periode' => true,
            ],
        ];

        if (in_array($roleName, ['admin', 'pimpinan', 'keuangan', 'kabag'], true)) {
            return array_values($allSources);
        }

        return match ($roleName) {
            'barokahdosen_tatapmuka' => [$allSources['tatapmuka']],
            'barokahdosen_kegiatan' => [$allSources['kegiatan'], $allSources['staff_bulanan']],
            'barokahdosen_bulanan' => [$allSources['dosen_bulanan']],
            default => [],
        };
    }

    private function barokahSourceQuery(array $source)
    {
        $table = $source['table'];

        $query = DB::table($table)
            ->leftJoin('pegawai', 'pegawai.id', '=', "{$table}.pegawai_id");

        if (! empty($source['pegawai_tipe'])) {
            $query->where('pegawai.tipe', $source['pegawai_tipe']);
        }

        return $query;
    }

    private function addBarokahStat(array &$stat, $query, string $table, string $totalColumn): void
    {
        $row = $query
            ->selectRaw("COALESCE(SUM({$totalColumn}), 0) as total, COUNT({$table}.id) as jumlah")
            ->first();

        $stat['total'] += (float) ($row->total ?? 0);
        $stat['jumlah'] += (int) ($row->jumlah ?? 0);
    }

    private function applyBarokahMonthScope($query, array $source, int $year, int $month)
    {
        $table = $source['table'];

        if (! empty($source['uses_periode'])) {
            return $query->where(function ($q) use ($table, $year, $month) {
                $q->where(function ($sq) use ($table, $year, $month) {
                    $sq->where("{$table}.tahun", $year)
                        ->where("{$table}.bulan", $month);
                })->orWhere(function ($sq) use ($table, $year, $month) {
                    $sq->whereNull("{$table}.tahun")
                        ->whereYear("{$table}.tanggal", $year)
                        ->whereMonth("{$table}.tanggal", $month);
                });
            });
        }

        return $query
            ->whereYear("{$table}.tanggal", $year)
            ->whereMonth("{$table}.tanggal", $month);
    }

    private function applyBarokahYearScope($query, array $source, int $year)
    {
        $table = $source['table'];

        if (! empty($source['uses_periode'])) {
            return $query->where(function ($q) use ($table, $year) {
                $q->where("{$table}.tahun", $year)
                    ->orWhere(function ($sq) use ($table, $year) {
                        $sq->whereNull("{$table}.tahun")
                            ->whereYear("{$table}.tanggal", $year);
                    });
            });
        }

        return $query->whereYear("{$table}.tanggal", $year);
    }

    private function barokahMonthlyRows($query, array $source, int $year)
    {
        $table = $source['table'];
        $totalColumn = "{$table}.total";

        if (! empty($source['uses_periode'])) {
            $periodExpr = "COALESCE({$table}.bulan, MONTH({$table}.tanggal))";

            return $query
                ->where(function ($q) use ($table, $year) {
                    $q->where("{$table}.tahun", $year)
                        ->orWhere(function ($sq) use ($table, $year) {
                            $sq->whereNull("{$table}.tahun")
                                ->whereYear("{$table}.tanggal", $year);
                        });
                })
                ->selectRaw("{$periodExpr} as period, COALESCE(SUM({$totalColumn}), 0) as total")
                ->groupBy(DB::raw($periodExpr))
                ->get();
        }

        return $query
            ->whereYear("{$table}.tanggal", $year)
            ->selectRaw("MONTH({$table}.tanggal) as period, COALESCE(SUM({$totalColumn}), 0) as total")
            ->groupBy(DB::raw("MONTH({$table}.tanggal)"))
            ->get();
    }

    private function barokahYearlyRows($query, array $source, int $startYear, int $endYear)
    {
        $table = $source['table'];
        $totalColumn = "{$table}.total";

        if (! empty($source['uses_periode'])) {
            $periodExpr = "COALESCE({$table}.tahun, YEAR({$table}.tanggal))";

            return $query
                ->where(function ($q) use ($table, $startYear, $endYear) {
                    $q->whereBetween("{$table}.tahun", [$startYear, $endYear])
                        ->orWhere(function ($sq) use ($table, $startYear, $endYear) {
                            $sq->whereNull("{$table}.tahun")
                                ->whereBetween(DB::raw("YEAR({$table}.tanggal)"), [$startYear, $endYear]);
                        });
                })
                ->selectRaw("{$periodExpr} as period, COALESCE(SUM({$totalColumn}), 0) as total")
                ->groupBy(DB::raw($periodExpr))
                ->get();
        }

        return $query
            ->whereBetween(DB::raw("YEAR({$table}.tanggal)"), [$startYear, $endYear])
            ->selectRaw("YEAR({$table}.tanggal) as period, COALESCE(SUM({$totalColumn}), 0) as total")
            ->groupBy(DB::raw("YEAR({$table}.tanggal)"))
            ->get();
    }
}
