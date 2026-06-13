<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Services\Helper;
use App\Services\MataUangFormatter;

class DashboardController extends Controller
{
    public function widget()
    {
        $jkUser = Helper::getJenisKelaminUser();
        $jkIdStr = (string)$jkUser->id; // 8, 9, or '%'

        $data = Cache::remember('dashboard_widget_v8_' . md5($jkIdStr), 30, function () use ($jkUser) {
            $today = Carbon::today()->format('Y-m-d');
            $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
            $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');

            $selectRawPembayaran = "
                COALESCE(SUM(CASE WHEN kp.jk_id = 8 THEN kp.jumlah ELSE 0 END), 0) as semua_laki,
                SUM(CASE WHEN kp.jk_id = 8 THEN 1 ELSE 0 END) as count_semua_laki,
                COALESCE(SUM(CASE WHEN kp.jk_id = 9 THEN kp.jumlah ELSE 0 END), 0) as semua_perempuan,
                SUM(CASE WHEN kp.jk_id = 9 THEN 1 ELSE 0 END) as count_semua_perempuan,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) = ? AND kp.jk_id = 8 THEN kp.jumlah ELSE 0 END), 0) as harian_laki,
                SUM(CASE WHEN DATE(kp.tanggal) = ? AND kp.jk_id = 8 THEN 1 ELSE 0 END) as count_harian_laki,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) = ? AND kp.jk_id = 9 THEN kp.jumlah ELSE 0 END), 0) as harian_perempuan,
                SUM(CASE WHEN DATE(kp.tanggal) = ? AND kp.jk_id = 9 THEN 1 ELSE 0 END) as count_harian_perempuan,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 8 THEN kp.jumlah ELSE 0 END), 0) as mingguan_laki,
                SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 8 THEN 1 ELSE 0 END) as count_mingguan_laki,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 9 THEN kp.jumlah ELSE 0 END), 0) as mingguan_perempuan,
                SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 9 THEN 1 ELSE 0 END) as count_mingguan_perempuan,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 8 THEN kp.jumlah ELSE 0 END), 0) as bulanan_laki,
                SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 8 THEN 1 ELSE 0 END) as count_bulanan_laki,
                COALESCE(SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 9 THEN kp.jumlah ELSE 0 END), 0) as bulanan_perempuan,
                SUM(CASE WHEN DATE(kp.tanggal) >= ? AND kp.jk_id = 9 THEN 1 ELSE 0 END) as count_bulanan_perempuan
            ";

            $bindingsPembayaran = [
                $today, $today, $today, $today,
                $startOfWeek, $startOfWeek, $startOfWeek, $startOfWeek,
                $startOfMonth, $startOfMonth, $startOfMonth, $startOfMonth
            ];

            $pembayaranRows = DB::table('keuangan_pembayaran as kp')
                ->join('keuangan_tagihan as kt', 'kt.id', '=', 'kp.tagihan_id')
                ->leftJoin('mata_uang as mu', 'mu.id', '=', 'kt.mata_uang_id')
                ->where('kp.jk_id', 'LIKE', "%" . $jkUser->id . "%")
                ->selectRaw("
                    COALESCE(mu.id, 0) as mata_uang_id,
                    COALESCE(mu.kode, 'IDR') as mata_uang_kode,
                    COALESCE(mu.nama, 'Rupiah') as mata_uang_nama,
                    COALESCE(mu.simbol, 'Rp') as mata_uang_simbol,
                    {$selectRawPembayaran}
                ", $bindingsPembayaran)
                ->groupBy('mu.id', 'mu.kode', 'mu.nama', 'mu.simbol')
                ->get();

            $selectRawSemesterPendek = str_replace(
                ['kp.jk_id', 'kp.jumlah', 'kp.tanggal'],
                ['jk_id', 'jumlah', 'tanggal'],
                $selectRawPembayaran
            );

            $sp = DB::table('keuangan_pembayaran_semester_pendek')
                ->where('jk_id', 'LIKE', "%" . $jkUser->id . "%")
                ->selectRaw($selectRawSemesterPendek, $bindingsPembayaran)
                ->first();

            $umum = (object)[
                'semua' => 0, 'count_semua' => 0,
                'harian' => 0, 'count_harian' => 0,
                'mingguan' => 0, 'count_mingguan' => 0,
                'bulanan' => 0, 'count_bulanan' => 0,
            ];

            // KeuanganSaldoPemasukan removed — will be remade
            // $umum stays as zero-initialized object above

            $defaultCurrency = MataUangFormatter::defaultCurrency();
            $legacyIdrTotal = function (array $totals): float {
                foreach ($totals as $row) {
                    if (strtoupper((string) data_get($row, 'mata_uang.kode')) === 'IDR') {
                        return (float) data_get($row, 'total', 0);
                    }
                }

                return 0;
            };

            $buildGender = function (string $period, string $gender, float $semesterPendek = 0) use ($pembayaranRows, $defaultCurrency, $legacyIdrTotal) {
                $totals = [];
                $count = 0;
                $amountKey = "{$period}_{$gender}";
                $countKey = "count_{$period}_{$gender}";

                foreach ($pembayaranRows as $row) {
                    $amount = (float) ($row->{$amountKey} ?? 0);
                    $count += (int) ($row->{$countKey} ?? 0);

                    if ($amount != 0.0) {
                        MataUangFormatter::addToTotals(
                            $totals,
                            $amount,
                            MataUangFormatter::fromColumns($row)
                        );
                    }
                }

                if ($semesterPendek != 0.0) {
                    MataUangFormatter::addToTotals($totals, $semesterPendek, $defaultCurrency);
                }

                $normalized = MataUangFormatter::normalizeTotals($totals);

                return [
                    'value' => $legacyIdrTotal($normalized),
                    'change' => $count,
                    'by_currency' => $normalized,
                ];
            };

            $buildPeriod = function (string $period) use ($jkUser, $sp, $umum, $buildGender, $defaultCurrency, $legacyIdrTotal) {
                $result = [];
                $countKeseluruhan = 0;
                $currencyGroups = [];

                if ($jkUser->id == 8 || $jkUser->id === '%') {
                    $result['Laki-laki'] = $buildGender(
                        $period,
                        'laki',
                        (float) ($sp->{$period . '_laki'} ?? 0)
                    );
                    $result['Laki-laki']['change'] += (int) ($sp->{'count_' . $period . '_laki'} ?? 0);
                    $countKeseluruhan += $result['Laki-laki']['change'];
                    $currencyGroups[] = $result['Laki-laki']['by_currency'];
                }

                if ($jkUser->id == 9 || $jkUser->id === '%') {
                    $result['Perempuan'] = $buildGender(
                        $period,
                        'perempuan',
                        (float) ($sp->{$period . '_perempuan'} ?? 0)
                    );
                    $result['Perempuan']['change'] += (int) ($sp->{'count_' . $period . '_perempuan'} ?? 0);
                    $countKeseluruhan += $result['Perempuan']['change'];
                    $currencyGroups[] = $result['Perempuan']['by_currency'];
                }

                if ($jkUser->id === '%') {
                    $umumTotal = (float) ($umum->{$period} ?? 0);
                    $umumCurrency = [];
                    if ($umumTotal != 0.0) {
                        MataUangFormatter::addToTotals($umumCurrency, $umumTotal, $defaultCurrency);
                    }
                    $umumCurrency = MataUangFormatter::normalizeTotals($umumCurrency);
                    $result['Umum'] = [
                        'value' => $umumTotal,
                        'change' => (int) ($umum->{'count_' . $period} ?? 0),
                        'hideIfZero' => true,
                        'by_currency' => $umumCurrency,
                    ];
                    $countKeseluruhan += $result['Umum']['change'];
                    $currencyGroups[] = $umumCurrency;
                }

                $byCurrency = MataUangFormatter::mergeTotals(...$currencyGroups);
                $result['Keseluruhan'] = [
                    'value' => $legacyIdrTotal($byCurrency),
                    'change' => $countKeseluruhan,
                    'by_currency' => $byCurrency,
                ];

                return $result;
            };

            $pemasukanBreakdown = [
                'Harian' => $buildPeriod('harian'),
                'Mingguan' => $buildPeriod('mingguan'),
                'Bulanan' => $buildPeriod('bulanan'),
                'Semua' => $buildPeriod('semua'),
            ];

            // KeuanganSaldo & KeuanganSaldoPengeluaran removed — will be remade
            $saldoData = (object) ['total_saldo' => 0, 'jumlah' => 0];
            $pengeluaranUmumData = (object) ['total' => 0, 'jumlah' => 0];
            $pengeluaranDosenData = DB::table('keuangan_pengeluaran_dosen')->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as jumlah')->first();
            $jumlahUser = User::count();
            $saldo = (float) $saldoData->total_saldo;
            $pengeluaran = (float) $pengeluaranUmumData->total + (float) $pengeluaranDosenData->total;
            $singleIdrTotal = fn (float $total) => [[
                'key' => 'kode:IDR',
                'mata_uang' => $defaultCurrency,
                'total' => $total,
            ]];

            return [
                'saldo' => $saldo,
                'saldoByCurrency' => $singleIdrTotal($saldo),
                'jumlahSaldo' => (int) $saldoData->jumlah,
                'pemasukanHarian' => $pemasukanBreakdown['Harian']['Keseluruhan']['value'] ?? 0,
                'pemasukanHarianByCurrency' => $pemasukanBreakdown['Harian']['Keseluruhan']['by_currency'] ?? [],
                'jumlahPemasukanHarian' => $pemasukanBreakdown['Harian']['Keseluruhan']['change'] ?? 0,
                'pemasukanBreakdown' => $pemasukanBreakdown,
                'pengeluaran' => $pengeluaran,
                'pengeluaranByCurrency' => $singleIdrTotal($pengeluaran),
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
            $cacheKey = 'dashboard_finance_overview_v3_' . md5(json_encode($request->only(['th_akademik_id', 'prodi_id', 'jk_id'])));

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
                    ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
                    ->selectRaw("{$caseExpr} as category_name")
                    ->selectRaw("COALESCE(keuangan_pembayaran.jumlah, 0) as jumlah")
                    ->selectRaw("keuangan_pembayaran.jk_id")
                    ->selectRaw("
                        COALESCE(mata_uang.id, 0) as mata_uang_id,
                        COALESCE(mata_uang.kode, 'IDR') as mata_uang_kode,
                        COALESCE(mata_uang.nama, 'Rupiah') as mata_uang_nama,
                        COALESCE(mata_uang.simbol, 'Rp') as mata_uang_simbol
                    ");

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
                        sub.mata_uang_id,
                        sub.mata_uang_kode,
                        sub.mata_uang_nama,
                        sub.mata_uang_simbol,
                        COALESCE(SUM(sub.jumlah), 0) as amount,
                        COALESCE(SUM(CASE WHEN sub.jk_id = 8 THEN sub.jumlah ELSE 0 END), 0) as laki_laki,
                        COALESCE(SUM(CASE WHEN sub.jk_id = 9 THEN sub.jumlah ELSE 0 END), 0) as perempuan
                    ")
                    ->groupBy(
                        'sub.category_name',
                        'sub.mata_uang_id',
                        'sub.mata_uang_kode',
                        'sub.mata_uang_nama',
                        'sub.mata_uang_simbol'
                    )
                    ->orderBy('sub.category_name')
                    ->orderBy('sub.mata_uang_kode')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name'       => $item->name,
                            'amount'     => (float) $item->amount,
                            'laki_laki'  => (float) $item->laki_laki,
                            'perempuan'  => (float) $item->perempuan,
                            'mata_uang'  => MataUangFormatter::fromColumns($item),
                        ];
                    });

                $totalsByCurrency = [];
                foreach ($data as $item) {
                    $kode = strtoupper((string) data_get($item, 'mata_uang.kode', 'IDR'));
                    $key = 'kode:' . $kode;

                    if (! isset($totalsByCurrency[$key])) {
                        $totalsByCurrency[$key] = [
                            'key' => $key,
                            'mata_uang' => $item['mata_uang'],
                            'total' => 0,
                            'laki_laki' => 0,
                            'perempuan' => 0,
                        ];
                    }

                    $totalsByCurrency[$key]['total'] += $item['amount'];
                    $totalsByCurrency[$key]['laki_laki'] += $item['laki_laki'];
                    $totalsByCurrency[$key]['perempuan'] += $item['perempuan'];
                }

                $totalsByCurrency = $this->sortCurrencyRows(array_values($totalsByCurrency));
                $currencyTotals = collect($totalsByCurrency)->keyBy(
                    fn ($item) => strtoupper((string) data_get($item, 'mata_uang.kode', 'IDR'))
                );

                $data = $data
                    ->groupBy('name')
                    ->map(function ($items, $name) use ($currencyTotals) {
                        $byCurrency = $items->map(function ($item) use ($currencyTotals) {
                            $kode = strtoupper((string) data_get($item, 'mata_uang.kode', 'IDR'));
                            $currencyTotal = (float) data_get($currencyTotals->get($kode), 'total', 0);

                            return [
                                'key' => 'kode:' . $kode,
                                'mata_uang' => $item['mata_uang'],
                                'amount' => $item['amount'],
                                'laki_laki' => $item['laki_laki'],
                                'perempuan' => $item['perempuan'],
                                'percent' => $currencyTotal > 0
                                    ? round($item['amount'] / $currencyTotal * 100, 2)
                                    : 0,
                            ];
                        })->all();

                        return [
                            'name' => $name,
                            'by_currency' => $this->sortCurrencyRows($byCurrency),
                        ];
                    })
                    ->sortByDesc(function ($item) {
                        $idr = collect($item['by_currency'])
                            ->first(fn ($row) => strtoupper((string) data_get($row, 'mata_uang.kode')) === 'IDR');

                        return (float) data_get($idr, 'amount', 0);
                    })
                    ->values();

                $legacyIdr = collect($totalsByCurrency)->first(
                    fn ($item) => strtoupper((string) data_get($item, 'mata_uang.kode')) === 'IDR'
                ) ?? [];

                return [
                    'data'            => $data,
                    'totals_by_currency' => $totalsByCurrency,
                    'total'           => (float) data_get($legacyIdr, 'total', 0),
                    'total_laki_laki' => (float) data_get($legacyIdr, 'laki_laki', 0),
                    'total_perempuan' => (float) data_get($legacyIdr, 'perempuan', 0),
                ];
            });

            return response()->json([
                'status'          => true,
                'message'         => 'Data berhasil diambil',
                'data'            => $result['data'],
                'totals_by_currency' => $result['totals_by_currency'],
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
            $cacheKey = 'dashboard_finance_detail_v3_' . md5(json_encode($request->only([
                'category',
                'group_by',
                'th_akademik_id',
                'prodi_id',
                'jk_id',
                'mata_uang_kode',
            ])));

            $result = Cache::remember($cacheKey, 600, function () use ($request) {
                $category = $request->category;
                $groupBy  = $request->group_by ?? 'semester';
                $mataUangKode = strtoupper((string) ($request->mata_uang_kode ?: 'IDR'));

                $query = DB::table('keuangan_tagihan')
                    ->leftJoin('keuangan_pembayaran', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                    ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
                    ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
                    ->leftJoin('th_akademik', 'keuangan_tagihan.th_akademik_id', '=', 'th_akademik.id')
                    ->leftJoin('prodi', 'keuangan_tagihan.prodi_id', '=', 'prodi.id')
                    ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
                    ->whereRaw("COALESCE(mata_uang.kode, 'IDR') = ?", [$mataUangKode]);

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
                    ->selectRaw("
                        COALESCE(mata_uang.id, 0) as mata_uang_id,
                        COALESCE(mata_uang.kode, 'IDR') as mata_uang_kode,
                        COALESCE(mata_uang.nama, 'Rupiah') as mata_uang_nama,
                        COALESCE(mata_uang.simbol, 'Rp') as mata_uang_simbol
                    ")
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
                    'keuangan_jenis_pembayaran.kategori',
                    'mata_uang.id',
                    'mata_uang.kode',
                    'mata_uang.nama',
                    'mata_uang.simbol'
                );

                $rows = $query->get();
                $mataUang = $rows->isNotEmpty()
                    ? MataUangFormatter::fromColumns($rows->first())
                    : [
                        ...MataUangFormatter::defaultCurrency(),
                        'kode' => $mataUangKode,
                        'nama' => $mataUangKode,
                        'simbol' => $mataUangKode === 'IDR' ? 'Rp' : $mataUangKode,
                    ];

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
                    'mata_uang'       => $mataUang,
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
                'mata_uang'       => $result['mata_uang'],
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
            $result = Cache::remember('dashboard_statistic_v2', 300, function () {
                $startDate = Carbon::now()->subDays(9)->startOfDay();
                $endDate   = Carbon::now()->endOfDay();

                $rows = DB::table('keuangan_pembayaran as kp')
                    ->join('keuangan_tagihan as kt', 'kt.id', '=', 'kp.tagihan_id')
                    ->leftJoin('mata_uang as mu', 'mu.id', '=', 'kt.mata_uang_id')
                    ->selectRaw("
                        DATE(kp.tanggal) AS tanggal,
                        SUM(kp.jumlah) AS nominal,
                        'in' AS tipe,
                        COALESCE(mu.id, 0) as mata_uang_id,
                        COALESCE(mu.kode, 'IDR') as mata_uang_kode,
                        COALESCE(mu.nama, 'Rupiah') as mata_uang_nama,
                        COALESCE(mu.simbol, 'Rp') as mata_uang_simbol
                    ")
                    ->whereBetween('kp.tanggal', [$startDate, $endDate])
                    ->groupBy(
                        DB::raw('DATE(kp.tanggal)'),
                        'mu.id',
                        'mu.kode',
                        'mu.nama',
                        'mu.simbol'
                    )

                    ->unionAll(
                        DB::table('keuangan_pembayaran_semester_pendek')
                            ->selectRaw("
                                DATE(tanggal) AS tanggal,
                                SUM(jumlah) AS nominal,
                                'in' AS tipe,
                                0 as mata_uang_id,
                                'IDR' as mata_uang_kode,
                                'Rupiah' as mata_uang_nama,
                                'Rp' as mata_uang_simbol
                            ")
                            ->whereBetween('tanggal', [$startDate, $endDate])
                            ->groupBy(DB::raw('DATE(tanggal)'))
                    )
                    // keuangan_saldo_pemasukan & keuangan_saldo_pengeluaran unions removed — tables will be remade
                    ->get();

                $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());
                $categories = [];
                $dates = [];

                foreach ($period as $day) {
                    $dates[] = $day->format('Y-m-d');
                    $categories[] = $day->format('d M');
                }

                $seriesByCurrency = $rows
                    ->groupBy(fn ($row) => strtoupper((string) ($row->mata_uang_kode ?: 'IDR')))
                    ->map(function ($currencyRows, $kode) use ($dates) {
                        $mataUang = MataUangFormatter::fromColumns($currencyRows->first());
                        $byDate = $currencyRows->groupBy('tanggal');
                        $penerimaan = [];
                        $pengeluaran = [];

                        foreach ($dates as $date) {
                            $dateRows = $byDate->get($date, collect());
                            $penerimaan[] = (float) $dateRows->where('tipe', 'in')->sum('nominal');
                            $pengeluaran[] = (float) $dateRows->where('tipe', 'out')->sum('nominal');
                        }

                        return [
                            'key' => 'kode:' . $kode,
                            'mata_uang' => $mataUang,
                            'penerimaan' => $penerimaan,
                            'pengeluaran' => $pengeluaran,
                        ];
                    })
                    ->values()
                    ->all();

                $seriesByCurrency = $this->sortCurrencyRows($seriesByCurrency);
                $legacyIdr = collect($seriesByCurrency)->first(
                    fn ($row) => strtoupper((string) data_get($row, 'mata_uang.kode')) === 'IDR'
                ) ?? ['penerimaan' => array_fill(0, count($dates), 0), 'pengeluaran' => array_fill(0, count($dates), 0)];

                return [
                    'categories'  => $categories,
                    'series_by_currency' => $seriesByCurrency,
                    'penerimaan'  => $legacyIdr['penerimaan'],
                    'pengeluaran' => $legacyIdr['pengeluaran'],
                ];
            });

            return response()->json([
                'status'      => true,
                'message'     => 'Data berhasil diambil',
                'categories'  => $result['categories'],
                'series_by_currency' => $result['series_by_currency'],
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
                        'total_amount_by_currency' => [],
                        'total_lunas'           => 0,
                        'total_belum_lunas'     => 0,
                    ],
                ]);
            }

            $studentPayments = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_pembayaran.tagihan_id,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    COALESCE(mata_uang.id, 0) as mata_uang_id,
                    COALESCE(mata_uang.kode, "IDR") as mata_uang_kode,
                    COALESCE(mata_uang.nama, "Rupiah") as mata_uang_nama,
                    COALESCE(mata_uang.simbol, "Rp") as mata_uang_simbol,
                    SUM(keuangan_pembayaran.jumlah) as total_paid
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_pembayaran.tagihan_id',
                    'keuangan_tagihan.jumlah',
                    'mata_uang.id',
                    'mata_uang.kode',
                    'mata_uang.nama',
                    'mata_uang.simbol'
                )
                ->get();

            $totalMahasiswaBayar = $studentPayments->unique('nim')->count();
            $totalsByCurrency    = $this->currencyTotalsFromRows($studentPayments, 'total_paid');
            $totalAmount         = $this->currencyTotal($totalsByCurrency, 'IDR');
            $lunasCount          = $studentPayments->filter(fn($s) => $s->total_paid >= $s->tagihan_amount)->unique('nim')->count();
            $belumLunasCount     = $totalMahasiswaBayar - $lunasCount;

            return response()->json([
                'status' => true,
                'data'   => [
                    'total_mahasiswa_bayar' => $totalMahasiswaBayar,
                    'total_amount'          => (float) $totalAmount,
                    'total_amount_by_currency' => $totalsByCurrency,
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
                ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
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
                    COALESCE(mata_uang.id, 0) as mata_uang_id,
                    COALESCE(mata_uang.kode, "IDR") as mata_uang_kode,
                    COALESCE(mata_uang.nama, "Rupiah") as mata_uang_nama,
                    COALESCE(mata_uang.simbol, "Rp") as mata_uang_simbol,
                    SUM(keuangan_pembayaran.jumlah) as total_paid,
                    MAX(keuangan_pembayaran.tanggal) as last_payment_date,
                    COALESCE(prodi.nama, "-") as prodi_nama,
                    CONCAT(COALESCE(th_akademik.nama, ""), " - ", COALESCE(th_akademik.semester, "")) as th_akademik_nama
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_tagihan.nama',
                    'keuangan_tagihan.jumlah',
                    'mata_uang.id',
                    'mata_uang.kode',
                    'mata_uang.nama',
                    'mata_uang.simbol',
                    'prodi.nama',
                    'th_akademik.nama',
                    'th_akademik.semester'
                )
                ->orderByDesc('last_payment_date');

            $paginated = $query->paginate($perPage);

            $items = collect($paginated->items())->map(function ($item) {
                $item->sisa     = max(0, $item->tagihan_amount - $item->total_paid);
                $item->is_lunas = $item->total_paid >= $item->tagihan_amount;
                $item->mata_uang = MataUangFormatter::fromColumns($item);
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
                        'total_amount_by_currency' => [],
                        'total_lunas'           => 0,
                        'total_belum_lunas'     => 0,
                    ],
                ]);
            }

            $studentPayments = DB::table('keuangan_pembayaran')
                ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
                ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
                ->whereIn('keuangan_pembayaran.tagihan_id', $tagihanIds)
                ->when($thAkademikId, fn($q) => $q->where('keuangan_pembayaran.th_akademik_id', $thAkademikId))
                ->when($jkId, fn($q) => $q->where('keuangan_pembayaran.jk_id', $jkId))
                ->selectRaw('
                    keuangan_pembayaran.nim,
                    keuangan_pembayaran.tagihan_id,
                    keuangan_tagihan.jumlah as tagihan_amount,
                    COALESCE(mata_uang.id, 0) as mata_uang_id,
                    COALESCE(mata_uang.kode, "IDR") as mata_uang_kode,
                    COALESCE(mata_uang.nama, "Rupiah") as mata_uang_nama,
                    COALESCE(mata_uang.simbol, "Rp") as mata_uang_simbol,
                    SUM(keuangan_pembayaran.jumlah) as total_paid
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_pembayaran.tagihan_id',
                    'keuangan_tagihan.jumlah',
                    'mata_uang.id',
                    'mata_uang.kode',
                    'mata_uang.nama',
                    'mata_uang.simbol'
                )
                ->get();

            $totalMahasiswaBayar = $studentPayments->unique('nim')->count();
            $totalsByCurrency    = $this->currencyTotalsFromRows($studentPayments, 'total_paid');
            $totalAmount         = $this->currencyTotal($totalsByCurrency, 'IDR');
            $lunasCount          = $studentPayments->filter(fn($s) => $s->total_paid >= $s->tagihan_amount)->unique('nim')->count();
            $belumLunasCount     = $totalMahasiswaBayar - $lunasCount;

            return response()->json([
                'status' => true,
                'data'   => [
                    'total_mahasiswa_bayar' => $totalMahasiswaBayar,
                    'total_amount'          => (float) $totalAmount,
                    'total_amount_by_currency' => $totalsByCurrency,
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
                ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
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
                    COALESCE(mata_uang.id, 0) as mata_uang_id,
                    COALESCE(mata_uang.kode, "IDR") as mata_uang_kode,
                    COALESCE(mata_uang.nama, "Rupiah") as mata_uang_nama,
                    COALESCE(mata_uang.simbol, "Rp") as mata_uang_simbol,
                    SUM(keuangan_pembayaran.jumlah) as total_paid,
                    MAX(keuangan_pembayaran.tanggal) as last_payment_date,
                    COALESCE(prodi.nama, "-") as prodi_nama,
                    CONCAT(COALESCE(th_akademik.nama, ""), " - ", COALESCE(th_akademik.semester, "")) as th_akademik_nama
                ')
                ->groupBy(
                    'keuangan_pembayaran.nim',
                    'keuangan_tagihan.nama',
                    'keuangan_tagihan.jumlah',
                    'mata_uang.id',
                    'mata_uang.kode',
                    'mata_uang.nama',
                    'mata_uang.simbol',
                    'prodi.nama',
                    'th_akademik.nama',
                    'th_akademik.semester'
                )
                ->orderByDesc('last_payment_date');

            $paginated = $query->paginate($perPage);

            $items = collect($paginated->items())->map(function ($item) {
                $item->sisa     = max(0, $item->tagihan_amount - $item->total_paid);
                $item->is_lunas = $item->total_paid >= $item->tagihan_amount;
                $item->mata_uang = MataUangFormatter::fromColumns($item);
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

    private function currencyTotalsFromRows($rows, string $amountField): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $amount = (float) data_get($row, $amountField, 0);
            if ($amount == 0.0) {
                continue;
            }

            MataUangFormatter::addToTotals(
                $totals,
                $amount,
                MataUangFormatter::fromColumns($row)
            );
        }

        return MataUangFormatter::normalizeTotals($totals);
    }

    private function currencyTotal(array $totals, string $kode): float
    {
        $target = strtoupper($kode);

        foreach ($totals as $row) {
            if (strtoupper((string) data_get($row, 'mata_uang.kode')) === $target) {
                return (float) data_get($row, 'total', 0);
            }
        }

        return 0;
    }

    private function sortCurrencyRows(array $rows): array
    {
        usort($rows, function ($left, $right) {
            $leftCode = strtoupper((string) data_get($left, 'mata_uang.kode', 'IDR'));
            $rightCode = strtoupper((string) data_get($right, 'mata_uang.kode', 'IDR'));

            if ($leftCode === $rightCode) {
                return 0;
            }
            if ($leftCode === 'IDR') {
                return -1;
            }
            if ($rightCode === 'IDR') {
                return 1;
            }

            return strcmp($leftCode, $rightCode);
        });

        return array_values($rows);
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
            'barokahdosen_kegiatan' => [$allSources['kegiatan']],
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
