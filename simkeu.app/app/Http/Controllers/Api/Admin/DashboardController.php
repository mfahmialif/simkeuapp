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

class DashboardController extends Controller
{
    public function widget()
    {
        $data = Cache::remember('dashboard_widget_v4', 300, function () {
            $today = Carbon::today()->format('Y-m-d');
            $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
            $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');

            $selectRawPemasukan = "
                COALESCE(SUM(jumlah), 0) as total_semua, 
                COUNT(*) as jumlah_semua,
                COALESCE(SUM(CASE WHEN DATE(tanggal) = ? THEN jumlah ELSE 0 END), 0) as total_harian,
                SUM(CASE WHEN DATE(tanggal) = ? THEN 1 ELSE 0 END) as jumlah_harian,
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? THEN jumlah ELSE 0 END), 0) as total_mingguan,
                SUM(CASE WHEN DATE(tanggal) >= ? THEN 1 ELSE 0 END) as jumlah_mingguan,
                COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? THEN jumlah ELSE 0 END), 0) as total_bulanan,
                SUM(CASE WHEN DATE(tanggal) >= ? THEN 1 ELSE 0 END) as jumlah_bulanan
            ";
            $bindings = [$today, $today, $startOfWeek, $startOfWeek, $startOfMonth, $startOfMonth];

            $saldoData = KeuanganSaldo::selectRaw('COALESCE(SUM(saldo), 0) as total_saldo, COUNT(*) as jumlah')->first();
            $pengeluaranUmumData = KeuanganSaldoPengeluaran::selectRaw('COALESCE(SUM(jumlah), 0) as total, COUNT(*) as jumlah')->first();
            $pengeluaranDosenData = DB::table('keuangan_pengeluaran_dosen')->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as jumlah')->first();
            $jumlahUser = User::count();

            $pemasukanData = KeuanganSaldoPemasukan::selectRaw($selectRawPemasukan, $bindings)->first();
            $pembayaranData = DB::table('keuangan_pembayaran')->selectRaw($selectRawPemasukan, $bindings)->first();

            $totalPengeluaran = (float) $pengeluaranUmumData->total + (float) $pengeluaranDosenData->total;
            $jumlahDataPengeluaran = (int) $pengeluaranUmumData->jumlah + (int) $pengeluaranDosenData->jumlah;

            return [
                'saldo' => (float) $saldoData->total_saldo,
                'jumlahSaldo' => (int) $saldoData->jumlah,
                
                // Semua
                'pemasukan' => (float) $pemasukanData->total_semua + (float) $pembayaranData->total_semua,
                'jumlahPemasukan' => (int) $pemasukanData->jumlah_semua + (int) $pembayaranData->jumlah_semua,
                
                // Harian
                'pemasukanHarian' => (float) $pemasukanData->total_harian + (float) $pembayaranData->total_harian,
                'jumlahPemasukanHarian' => (int) $pemasukanData->jumlah_harian + (int) $pembayaranData->jumlah_harian,
                
                // Mingguan
                'pemasukanMingguan' => (float) $pemasukanData->total_mingguan + (float) $pembayaranData->total_mingguan,
                'jumlahPemasukanMingguan' => (int) $pemasukanData->jumlah_mingguan + (int) $pembayaranData->jumlah_mingguan,
                
                // Bulanan
                'pemasukanBulanan' => (float) $pemasukanData->total_bulanan + (float) $pembayaranData->total_bulanan,
                'jumlahPemasukanBulanan' => (int) $pemasukanData->jumlah_bulanan + (int) $pembayaranData->jumlah_bulanan,

                'pengeluaran' => $totalPengeluaran,
                'jumlahPengeluaran' => $jumlahDataPengeluaran,
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
            $cacheKey = 'dashboard_finance_overview_' . md5(json_encode($request->only(['th_akademik_id', 'prodi_id', 'jk_id'])));

            $result = Cache::remember($cacheKey, 600, function () use ($request) {
                // Bundle grouping via CASE expression
                $caseExpr = "CASE
                    WHEN keuangan_tagihan.nama LIKE '%SPP%' THEN 'SPP'
                    WHEN keuangan_tagihan.nama LIKE '%regis%' OR keuangan_tagihan.nama LIKE '%daftar%' THEN 'Registrasi'
                    WHEN keuangan_tagihan.nama LIKE '%UAS%' THEN 'UAS'
                    ELSE keuangan_tagihan.nama
                END";

                // Subquery: hitung category name per row + jumlah pembayaran
                $subquery = DB::table('keuangan_tagihan')
                    ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->selectRaw("{$caseExpr} as category_name, keuangan_pembayaran.jumlah");

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
                    ->selectRaw("sub.category_name as name, COALESCE(SUM(sub.jumlah), 0) as amount")
                    ->groupBy('sub.category_name')
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

                return ['data' => $data, 'total' => $total];
            });

            return response()->json([
                'status' => true,
                'message' => 'Data berhasil diambil',
                'data' => $result['data'],
                'total' => $result['total']
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
            $cacheKey = 'dashboard_finance_detail_' . md5(json_encode($request->only(['category', 'group_by', 'th_akademik_id', 'prodi_id', 'jk_id'])));

            $result = Cache::remember($cacheKey, 600, function () use ($request) {
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

                return [
                    'category' => $category,
                    'group_by' => $groupBy,
                    'data'     => $data,
                    'total'    => $total,
                ];
            });

            return response()->json([
                'status'   => true,
                'message'  => 'Data berhasil diambil',
                'category' => $result['category'],
                'group_by' => $result['group_by'],
                'data'     => $result['data'],
                'total'    => $result['total'],
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
}
