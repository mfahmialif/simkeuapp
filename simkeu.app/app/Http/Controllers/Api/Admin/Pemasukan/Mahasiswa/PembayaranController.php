<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Models\KeuanganTagihan;
use App\Exports\pdf\KwitansiPdf;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaran;
use App\Services\SemesterPendek;
use App\Services\TagihanMahasiswa;
use App\Services\Wisuda;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exports\pdf\KwitansiPreviewPdf;
use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganDispensasiTagihan;
use Illuminate\Support\Facades\Validator;
use App\Models\KeuanganJenisPembayaranDetail;

class PembayaranController extends Controller
{
    public function tahunWisuda()
    {
        try {
            $response = Wisuda::tahun();

            if ($response === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tidak ada response dari API tahun wisuda.',
                    'code' => 502,
                ], 502);
            }

            if (is_object($response) && isset($response->status) && $response->status === false) {
                return response()->json([
                    'status' => false,
                    'message' => $response->message ?? 'Gagal mengambil data tahun wisuda.',
                    'code' => 502,
                    'wisuda_response' => $response,
                ], 502);
            }

            return response()->json([
                'status' => true,
                'data' => $response->data ?? $response->tahun ?? $response,
                'message' => 'Berhasil mengambil data tahun wisuda.',
                'code' => 200,
                'wisuda_response' => $response,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    public function statistic(Request $request)
    {
        $jkUser = Helper::getJenisKelaminUser();
        $jkIdStr = (string)$jkUser->id;
        $thAkademikId = $request->th_akademik_id ?? 'all';
        $prodiFilter = $request->prodi_id ?? 'all';
        $jenisPembayaranFilter = $request->jenis_pembayaran_id ?? 'all';
        $userIdFilter = $request->user_id ?? 'all';
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        $data = \Illuminate\Support\Facades\Cache::remember('pembayaran_mahasiswa_widget_v5_' . md5($jkIdStr . '_' . $thAkademikId . '_' . $prodiFilter . '_' . $jenisPembayaranFilter . '_' . $userIdFilter . '_' . $tanggalMulai . '_' . $tanggalAkhir), 30, function () use ($jkUser, $thAkademikId, $prodiFilter, $jenisPembayaranFilter, $userIdFilter, $tanggalMulai, $tanggalAkhir) {
            $startOfWeek = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
            $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');

            $pembayaranQuery = DB::table('keuangan_pembayaran');
            $pembayaranQuery->where('keuangan_pembayaran.jk_id', 'LIKE', "%" . $jkUser->id . "%");

            if ($thAkademikId !== 'all') {
                $pembayaranQuery->where('keuangan_pembayaran.th_akademik_id', $thAkademikId);
            }

            // Filter by prodi
            if ($prodiFilter !== 'all') {
                $pembayaranQuery->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id');
                if ($prodiFilter === 'sarjana') {
                    $pembayaranQuery->join('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id');
                    $pembayaranQuery->where('prodi.jenjang', 'S1');
                } elseif ($prodiFilter === 'pasca') {
                    $pembayaranQuery->join('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id');
                    $pembayaranQuery->where('prodi.jenjang', '!=', 'S1');
                } else {
                    $pembayaranQuery->where('keuangan_tagihan.prodi_id', $prodiFilter);
                }
            }

            // Filter by jenis pembayaran
            if ($jenisPembayaranFilter !== 'all') {
                $pembayaranQuery->join('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id');
                $pembayaranQuery->where('keuangan_jenis_pembayaran_detail.jenis_pembayaran_id', $jenisPembayaranFilter);
            }

            // Filter by user_id
            if ($userIdFilter !== 'all') {
                $pembayaranQuery->where('keuangan_pembayaran.user_id', $userIdFilter);
            }

            // Harian: pakai tanggal_mulai jika ada, otherwise today
            $today = $tanggalMulai ? $tanggalMulai : \Carbon\Carbon::today()->format('Y-m-d');

            // Build Semua (Keseluruhan) dengan kondisi tanggal jika ada
            $semuaCondLaki = "keuangan_pembayaran.jk_id = 8";
            $semuaCondPerempuan = "keuangan_pembayaran.jk_id = 9";
            $semuaBindingsSingle = [];

            if ($tanggalMulai) {
                $semuaCondLaki .= " AND DATE(keuangan_pembayaran.tanggal) >= ?";
                $semuaCondPerempuan .= " AND DATE(keuangan_pembayaran.tanggal) >= ?";
                $semuaBindingsSingle[] = $tanggalMulai;
            }
            if ($tanggalAkhir) {
                $semuaCondLaki .= " AND DATE(keuangan_pembayaran.tanggal) <= ?";
                $semuaCondPerempuan .= " AND DATE(keuangan_pembayaran.tanggal) <= ?";
                $semuaBindingsSingle[] = $tanggalAkhir;
            }

            // Clone base query BEFORE it gets modified by first() or selectRaw()
            $harianDetailQuery = clone $pembayaranQuery;

            $selectRawPembayaran = "
                -- Semua (Keseluruhan)
                COALESCE(SUM(CASE WHEN {$semuaCondLaki} THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as semua_laki,
                SUM(CASE WHEN {$semuaCondLaki} THEN 1 ELSE 0 END) as count_semua_laki,
                COALESCE(SUM(CASE WHEN {$semuaCondPerempuan} THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as semua_perempuan,
                SUM(CASE WHEN {$semuaCondPerempuan} THEN 1 ELSE 0 END) as count_semua_perempuan,
                -- Harian
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) = ? AND keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as harian_laki,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) = ? AND keuangan_pembayaran.jk_id = 8 THEN 1 ELSE 0 END) as count_harian_laki,
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) = ? AND keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as harian_perempuan,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) = ? AND keuangan_pembayaran.jk_id = 9 THEN 1 ELSE 0 END) as count_harian_perempuan,
                -- Mingguan
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as mingguan_laki,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 8 THEN 1 ELSE 0 END) as count_mingguan_laki,
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as mingguan_perempuan,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 9 THEN 1 ELSE 0 END) as count_mingguan_perempuan,
                -- Bulanan
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as bulanan_laki,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 8 THEN 1 ELSE 0 END) as count_bulanan_laki,
                COALESCE(SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as bulanan_perempuan,
                SUM(CASE WHEN DATE(keuangan_pembayaran.tanggal) >= ? AND keuangan_pembayaran.jk_id = 9 THEN 1 ELSE 0 END) as count_bulanan_perempuan
            ";

            // SQL order: semua_laki(value) -> count_semua_laki -> semua_perempuan(value) -> count_semua_perempuan -> harian -> mingguan -> bulanan
            $bindingsPembayaran = array_merge(
                $semuaBindingsSingle, // semua_laki value
                $semuaBindingsSingle, // count_semua_laki
                $semuaBindingsSingle, // semua_perempuan value
                $semuaBindingsSingle, // count_semua_perempuan
                [$today, $today, $today, $today],
                [$startOfWeek, $startOfWeek, $startOfWeek, $startOfWeek],
                [$startOfMonth, $startOfMonth, $startOfMonth, $startOfMonth]
            );

            $pmb = $pembayaranQuery->selectRaw($selectRawPembayaran, $bindingsPembayaran)->first();

            $buildPeriod = function($laki, $countLaki, $perempuan, $countPerempuan) use ($jkUser) {
                $result = [];
                $keseluruhan = 0;
                $countKeseluruhan = 0;

                if ($jkUser->id == 8 || $jkUser->id === '%') {
                    $result['Laki-laki'] = ['value' => (float)$laki, 'change' => (int)$countLaki];
                    $keseluruhan += (float)$laki;
                    $countKeseluruhan += (int)$countLaki;
                }
                if ($jkUser->id == 9 || $jkUser->id === '%') {
                    $result['Perempuan'] = ['value' => (float)$perempuan, 'change' => (int)$countPerempuan];
                    $keseluruhan += (float)$perempuan;
                    $countKeseluruhan += (int)$countPerempuan;
                }
                $result['Keseluruhan'] = ['value' => $keseluruhan, 'change' => $countKeseluruhan];

                return $result;
            };

            if ($prodiFilter === 'all') {
                $harianDetailQuery->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id');
            }
            if ($jenisPembayaranFilter === 'all') {
                $harianDetailQuery->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id');
                $harianDetailQuery->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id');
            } else {
                $harianDetailQuery->join('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id');
            }

            $caseExpr = "CASE
                WHEN keuangan_tagihan.nama LIKE '%SPP%' THEN 'SPP'
                WHEN keuangan_tagihan.nama LIKE '%regis%' OR keuangan_tagihan.nama LIKE '%daftar%' THEN 'Registrasi'
                WHEN keuangan_tagihan.nama LIKE '%SEMESTER PENDEK%' THEN 'Semester Pendek'
                WHEN keuangan_tagihan.nama LIKE '%UTS%' THEN 'UTS'
                WHEN keuangan_tagihan.nama LIKE '%UAS%' THEN 'UAS'
                WHEN keuangan_tagihan.nama LIKE '%KKN%' OR keuangan_tagihan.nama LIKE '%PPL%' OR keuangan_tagihan.nama LIKE '%PKL%' THEN 'KKN / PPL / PKL'
                ELSE keuangan_tagihan.nama
            END";

            $semuaDetailQuery = clone $harianDetailQuery;
            $harianDetailQuery->whereDate('keuangan_pembayaran.tanggal', $today);

            $buildDetail = function($query) use ($caseExpr) {
                return $query->select(
                    DB::raw("{$caseExpr} as category_name"),
                    DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA') as payment_method"),
                    DB::raw('COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as laki_laki'),
                    DB::raw('COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as perempuan'),
                    DB::raw('COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as keseluruhan')
                )
                ->groupBy(DB::raw("category_name"), DB::raw("payment_method"))
                ->orderBy(DB::raw("category_name"))
                ->orderBy(DB::raw("payment_method"))
                ->get()
                ->map(function ($item) {
                    return [
                        'jp_nama' => $item->payment_method, // Jenis Pembayaran e.g. CASH
                        'tagihan_nama' => $item->category_name, // Tagihan e.g. Registrasi
                        'laki_laki' => $item->laki_laki,
                        'perempuan' => $item->perempuan,
                        'keseluruhan' => $item->keseluruhan,
                    ];
                });
            };

            $harianDetail = $buildDetail($harianDetailQuery);
            $semuaDetail = $buildDetail($semuaDetailQuery);

            return [
                'Harian' => $buildPeriod($pmb->harian_laki, $pmb->count_harian_laki, $pmb->harian_perempuan, $pmb->count_harian_perempuan),
                'Mingguan' => $buildPeriod($pmb->mingguan_laki, $pmb->count_mingguan_laki, $pmb->mingguan_perempuan, $pmb->count_mingguan_perempuan),
                'Bulanan' => $buildPeriod($pmb->bulanan_laki, $pmb->count_bulanan_laki, $pmb->bulanan_perempuan, $pmb->count_bulanan_perempuan),
                'Semua' => $buildPeriod($pmb->semua_laki, $pmb->count_semua_laki, $pmb->semua_perempuan, $pmb->count_semua_perempuan),
                'Harian_Detail' => $harianDetail,
                'Semua_Detail' => $semuaDetail,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Data statistik berhasil diambil',
            'data' => $data,
        ]);
    }

    public function statisticDetailProdi(Request $request)
    {
        $jkUser = Helper::getJenisKelaminUser();
        $thAkademikId = $request->th_akademik_id ?? 'all';
        $prodiFilter = $request->prodi_id ?? 'all';
        $jenisPembayaranFilter = $request->jenis_pembayaran_id ?? 'all';
        $paymentMethodFilter = $request->payment_method ?? null;
        $userIdFilter = $request->user_id ?? 'all';
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;
        $category = $request->category ?? null;

        if (!$category) {
            return response()->json(['status' => false, 'message' => 'Category is required', 'data' => []], 400);
        }

        $pembayaranQuery = DB::table('keuangan_pembayaran');
        $pembayaranQuery->where('keuangan_pembayaran.jk_id', 'LIKE', "%" . $jkUser->id . "%");

        if ($thAkademikId !== 'all') {
            $pembayaranQuery->where('keuangan_pembayaran.th_akademik_id', $thAkademikId);
        }

        // Filter by prodi
        $pembayaranQuery->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id');
        $pembayaranQuery->leftJoin('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id');

        if ($prodiFilter !== 'all') {
            if ($prodiFilter === 'sarjana') {
                $pembayaranQuery->where('prodi.jenjang', 'S1');
            } elseif ($prodiFilter === 'pasca') {
                $pembayaranQuery->where('prodi.jenjang', '!=', 'S1');
            } else {
                $pembayaranQuery->where('keuangan_tagihan.prodi_id', $prodiFilter);
            }
        }

        // Filter by jenis pembayaran
        if ($jenisPembayaranFilter !== 'all') {
            $pembayaranQuery->join('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id');
            $pembayaranQuery->where('keuangan_jenis_pembayaran_detail.jenis_pembayaran_id', $jenisPembayaranFilter);
        } elseif ($paymentMethodFilter) {
            $pembayaranQuery->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id');
            $pembayaranQuery->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id');

            if (strtoupper(trim((string) $paymentMethodFilter)) === 'LAINNYA') {
                $pembayaranQuery->whereNull('keuangan_jenis_pembayaran.id');
            } else {
                $pembayaranQuery->whereRaw('UPPER(TRIM(keuangan_jenis_pembayaran.nama)) = ?', [
                    strtoupper(trim((string) $paymentMethodFilter)),
                ]);
            }
        }

        // Filter by user_id
        if ($userIdFilter !== 'all') {
            $pembayaranQuery->where('keuangan_pembayaran.user_id', $userIdFilter);
        }

        // Apply Category Filter using CASE expression logic
        if ($category === 'SPP') {
            $pembayaranQuery->where('keuangan_tagihan.nama', 'LIKE', '%SPP%');
        } elseif ($category === 'Registrasi') {
            $pembayaranQuery->where(function ($q) {
                $q->where('keuangan_tagihan.nama', 'LIKE', '%regis%')
                  ->orWhere('keuangan_tagihan.nama', 'LIKE', '%daftar%');
            });
        } elseif ($category === 'Semester Pendek') {
            $pembayaranQuery->where('keuangan_tagihan.nama', 'LIKE', '%SEMESTER PENDEK%');
        } elseif ($category === 'UTS') {
            $pembayaranQuery->where('keuangan_tagihan.nama', 'LIKE', '%UTS%');
        } elseif ($category === 'UAS') {
            $pembayaranQuery->where('keuangan_tagihan.nama', 'LIKE', '%UAS%');
        } elseif ($category === 'KKN / PPL / PKL') {
            $pembayaranQuery->where(function ($q) {
                $q->where('keuangan_tagihan.nama', 'LIKE', '%KKN%')
                  ->orWhere('keuangan_tagihan.nama', 'LIKE', '%PPL%')
                  ->orWhere('keuangan_tagihan.nama', 'LIKE', '%PKL%');
            });
        } else {
            $pembayaranQuery->where('keuangan_tagihan.nama', $category);
        }

        // Apply Date Filters based on period
        $period = $request->period ?? 'Harian';

        if ($period === 'Harian') {
            $today = $tanggalMulai ? $tanggalMulai : \Carbon\Carbon::today()->format('Y-m-d');
            $pembayaranQuery->whereDate('keuangan_pembayaran.tanggal', $today);
        } else {
            if ($tanggalMulai) {
                $pembayaranQuery->whereDate('keuangan_pembayaran.tanggal', '>=', $tanggalMulai);
            }
            if ($tanggalAkhir) {
                $pembayaranQuery->whereDate('keuangan_pembayaran.tanggal', '<=', $tanggalAkhir);
            }
        }

        $detail = $pembayaranQuery->select(
            DB::raw("COALESCE(prodi.nama, 'Tanpa Prodi') as prodi_nama"),
            DB::raw('COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 8 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as laki_laki'),
            DB::raw('COALESCE(SUM(CASE WHEN keuangan_pembayaran.jk_id = 9 THEN keuangan_pembayaran.jumlah ELSE 0 END), 0) as perempuan'),
            DB::raw('COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as keseluruhan')
        )
        ->groupBy('prodi.id', 'prodi.nama')
        ->orderByDesc('keseluruhan');

        \Illuminate\Support\Facades\Log::info('SQL Query:', [
            'sql' => $detail->toSql(),
            'bindings' => $detail->getBindings()
        ]);

        $detail = $detail->get();

        return response()->json([
            'status' => true,
            'message' => 'Data statistik detail prodi berhasil diambil',
            'data' => $detail,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganPembayaran::join('th_akademik', 'th_akademik.id', '=', 'keuangan_pembayaran.th_akademik_id')
            ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
            ->leftJoin('keuangan_nota', 'keuangan_nota.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
            ->leftJoin('users', 'users.id', '=', 'keuangan_pembayaran.user_id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('th_akademik.kode', 'LIKE', "%$request->search%")
                    ->orWhere('th_akademik.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.nomor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.nim', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.jumlah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_nota.nota', 'LIKE', "%$request->search%");
            });
        }

        $query->where('keuangan_pembayaran.jk_id', 'LIKE', "%" . Helper::getJenisKelaminUser()->id . "%");

        if ($request->filled('th_akademik_id')) {
            $query->where('keuangan_pembayaran.th_akademik_id', $request->th_akademik_id);
        }

        // Filter by prodi
        if ($request->filled('prodi_id')) {
            $prodiFilter = $request->prodi_id;
            if ($prodiFilter === 'sarjana') {
                $query->join('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id');
                $query->where('prodi.jenjang', 'S1');
            } elseif ($prodiFilter === 'pasca') {
                $query->join('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id');
                $query->where('prodi.jenjang', '!=', 'S1');
            } else {
                $query->where('keuangan_tagihan.prodi_id', $prodiFilter);
            }
        }

        // Filter by jenis pembayaran
        if ($request->filled('jenis_pembayaran_id')) {
            $query->where('keuangan_jenis_pembayaran_detail.jenis_pembayaran_id', $request->jenis_pembayaran_id);
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('keuangan_pembayaran.user_id', $request->user_id);
        }

        // Filter by tanggal
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('keuangan_pembayaran.tanggal', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('keuangan_pembayaran.tanggal', '<=', $request->tanggal_akhir);
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query
            ->select('keuangan_pembayaran.*', 'th_akademik.kode as th_akademik_kode', 'keuangan_tagihan.nama as keuangan_tagihan_nama', 'keuangan_jenis_pembayaran.nama as keuangan_jenis_pembayaran_nama', 'keuangan_tagihan.double_degree as keuangan_tagihan_double_degree', 'users.name as petugas_nama')
            ->addSelect(DB::raw(
                "COALESCE(keuangan_nota.nota, keuangan_pembayaran.nomor) AS nota"
            ));

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Tagihan retrieved successfully',
            'jk' => Helper::getJenisKelaminUser()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                'tanggal'             => 'required',
                'tahun_akademik'      => 'required',
                'nim'                 => 'required',
                'semester'            => 'nullable',
                'list_tagihan_id'     => 'required',
                'list_tagihan'        => 'required',
                'list_dibayar'        => 'required',
                'list_deposit'        => 'required',
                'list_keringanan_jenis'  => 'nullable|array',
                'list_keringanan_jumlah' => 'nullable|array',
                'list_keringanan_batas'  => 'nullable|array',
                'list_keringanan_jenis.*'  => 'nullable|in:samahah,dhomin',
                'list_keringanan_jumlah.*' => 'nullable|numeric|min:0',
                'list_keringanan_batas.*'  => 'nullable|date',
                'jenis_pembayaran'    => 'required',
                'dipakai_deposit_mhs' => 'nullable',
                'kamar_id'            => 'nullable',
                'wisuda'              => 'nullable',
                'jk_id'               => 'required'
            ]);

            $wisudaPayload = $this->resolveWisudaPayload(
                $request->input('wisuda'),
                $dataValidated['nim'],
                $dataValidated['list_tagihan']
            );

            $nim = strtoupper($dataValidated['nim']);
            $this->ensureTagihanCanBePaid($nim, $dataValidated['list_tagihan_id']);

            DB::beginTransaction();

            $jk = Helper::getJenisKelaminUser();

            $nota = Helper::generateNota($dataValidated['tanggal'], $request->jk_id);
            $pembayaran = null;
            $totalDepositDipakai = 0;

            for ($i = 0; $i < count($dataValidated['list_tagihan']); $i++) {
                $tagihanId = $dataValidated['list_tagihan_id'][$i];
                $dibayar = $this->normalizeNumber($dataValidated['list_dibayar'][$i] ?? 0);
                $depositDibayar = $this->normalizeNumber($dataValidated['list_deposit'][$i] ?? 0);
                $keringananJenis = $this->normalizeKeringananJenis($dataValidated['list_keringanan_jenis'][$i] ?? null);

                if ($keringananJenis) {
                    $jumlahKeringanan = $this->resolveKeringananJumlah(
                        $keringananJenis,
                        $tagihanId,
                        $nim,
                        $dataValidated['list_keringanan_jumlah'][$i] ?? 0,
                        $dibayar,
                        $depositDibayar,
                        $i
                    );

                    $batasKeringanan = $keringananJenis === 'dhomin'
                        ? '9999-12-31'
                        : (($dataValidated['list_keringanan_batas'][$i] ?? null) ?: '9999-12-31');

                    $this->upsertKeringananTagihan(
                        $dataValidated['tahun_akademik'],
                        $tagihanId,
                        $nim,
                        $jumlahKeringanan,
                        $batasKeringanan,
                        $keringananJenis
                    );
                }

                if ($dibayar <= 0 && $depositDibayar <= 0) {
                    continue;
                }

                $nomor = Helper::generateNumber();
                $data  = KeuanganPembayaran::where('nomor', $request->nomor)->first();

                if (! $data) {

                    if ($dibayar != 0) {
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $tagihanId,
                            "nim"            => $nim,
                            "jumlah"         => $dibayar,
                            "smt"            => $dataValidated['semester'],
                            "jml_sks"        => 1,
                            "jk_id"          => $request->jk_id,
                            "user_id"        => Auth::user()->id,
                        ]);

                        KeuanganJenisPembayaranDetail::create([
                            'jenis_pembayaran_id' => $dataValidated['jenis_pembayaran'],
                            'pembayaran_id'       => $pembayaran->id,
                        ]);

                        KeuanganNota::create([
                            'nota'          => $nota,
                            'pembayaran_id' => $pembayaran->id,
                        ]);

                        if (
                            stripos(strtolower($pembayaran->tagihan->nama), 'daftar ulang') !== false ||
                            stripos(strtolower($pembayaran->tagihan->nama), 'regist') !== false
                        ) {
                            // update mahasiswa jadi aktif

                            Mahasiswa::updateStatusMahasiswa($dataValidated['nim'], 18);
                        }
                    }

                    // insert data deposit jika bukan 0, auto jenis pembayaran deposit
                    if ($depositDibayar != 0) {
                        $jenisPembayaran = KeuanganJenisPembayaran::where([
                            ['nama', 'LIKE', "%deposit%"],
                            ['kategori', 'LIKE', "%$jk->kategori%"],
                        ])->first();
                        $nomor      = Helper::generateNumber();
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $tagihanId,
                            "nim"            => $nim,
                            "jumlah"         => $depositDibayar,
                            "smt"            => $dataValidated['semester'],
                            "jml_sks"        => 1,
                            "jk_id"          => $request->jk_id,
                            "user_id"        => Auth::user()->id,
                        ]);

                        KeuanganJenisPembayaranDetail::create([
                            'jenis_pembayaran_id' => $jenisPembayaran->id,
                            'pembayaran_id'       => $pembayaran->id,
                        ]);

                        KeuanganNota::create([
                            'nota'          => $nota,
                            'pembayaran_id' => $pembayaran->id,
                        ]);

                        if (
                            stripos(strtolower($pembayaran->tagihan->nama), 'daftar ulang') !== false ||
                            stripos(strtolower($pembayaran->tagihan->nama), 'regist') !== false
                        ) {
                            Mahasiswa::updateStatusMahasiswa($dataValidated['nim'], 18);
                        }

                        $totalDepositDipakai += $depositDibayar;
                    }
                }
            }

            // catatan-deposit
            if ($totalDepositDipakai > 0) {
                $deposit = KeuanganDeposit::where('nim', $dataValidated['nim'])->first();
                if ($deposit != null) {
                    $jumlahDepositBaru = $deposit->jumlah - $totalDepositDipakai;
                    $deposit->update([
                        'jumlah' => $jumlahDepositBaru,
                    ]);
                }
            }

            // Banat Tambah Kamar
            if ($request->has('kamar_id')) {
                if ($dataValidated['kamar_id'] != null) {
                    if ($jk->kategori == '%' || $jk->kategori == 'Putri') {
                        $kamarMhs = KeuanganKamarMhs::where('nim', $dataValidated['nim'])->first();
                        if (! $kamarMhs) {
                            $kamarMhs = new KeuanganKamarMhs();
                        }
                        $kamarMhs->nim      = $dataValidated['nim'];
                        $kamarMhs->kamar_id = $dataValidated['kamar_id'];
                        $kamarMhs->save();
                    }
                }
            }
            // End Banat Tambah Kamar

            SemesterPendek::syncTagihanIds($dataValidated['list_tagihan_id'], $dataValidated['nim']);

            DB::commit();

            $wisudaResponse = null;
            $wisudaError = null;

            if ($wisudaPayload) {
                try {
                    $wisudaResponse = Wisuda::registrasi($wisudaPayload);
                    if ($wisudaResponse === null) {
                        $wisudaError = 'Tidak ada response dari API wisuda.';
                    } elseif (is_object($wisudaResponse) && isset($wisudaResponse->status) && $wisudaResponse->status === false) {
                        $wisudaError = $wisudaResponse->message ?? 'Registrasi wisuda gagal.';
                    }
                } catch (\Throwable $th) {
                    $wisudaError = $th->getMessage();
                }
            }

            $data = [
                "status" => true,
                "code" => 200,
                "id"      => $pembayaran ? $pembayaran->id : null,
                "message" => $wisudaError
                    ? "Berhasil menyimpan data, tetapi registrasi wisuda gagal: $wisudaError"
                    : "Berhasil menyimpan data",
                'req' => $request->all(),
                'wisuda_response' => $wisudaResponse,
            ];
            return $data;
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
                'code'  => 422,
                'req'     => $request->all(),
            ]);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $data = [
                "status" => false,
                "code" => 500,
                "message"    => $th->getMessage(),
            ];
            return $data;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id)
    {
        $data = KeuanganPembayaran::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak ditemukan.',
                'code'     => 404,
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'tagihan', 'keuanganNota', 'jenisPembayaranDetail', 'user'),
            'message' => 'Berhasil mengambil data.',
            'code'     => 200,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        try {
            $v = Validator::make($request->all(), [
                'tanggal'      => 'required|date',
                'th_akademik_id' => 'required|integer',
                'jumlah'       => 'required|numeric',
                'jenis_pembayaran' => 'required'
            ]);

            if ($v->fails()) {
                return response()->json(['status' => false, 'message' => $v->errors()], 422);
            }

            $pembayaran = KeuanganPembayaran::find($id);

            if (! $pembayaran) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan.',
                    'code'     => 404,
                ], 404);
            }

            KeuanganJenisPembayaranDetail::where('pembayaran_id', $pembayaran->id)->update([
                'jenis_pembayaran_id' => $request->input('jenis_pembayaran'),
            ]);

            $pembayaran->update([
                'tanggal'        => $request->input('tanggal'),
                'th_akademik_id' => $request->input('th_akademik_id'),
                'jumlah'         => $request->input('jumlah'),
                // 'user_id'        => Auth::user()->id,
            ]);

            SemesterPendek::syncFromPembayaran($pembayaran);

            return response()->json([
                'status'  => true,
                'message' => 'Berhasil mengupdate data.',
                'code'     => 200,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $pembayaran = KeuanganPembayaran::with('tagihan')->find($id);

            if (! $pembayaran) {
                return [
                    "status" => false,
                    "code" => 404,
                    "message" => "Data tidak ditemukan",
                ];
            }

            $pembayaran->delete();
            SemesterPendek::syncFromPembayaran($pembayaran);

            $data = [
                "status" => true,
                "code" => 200,
                "message" => "Berhasil menghapus data",
            ];
            return $data;
        } catch (\Throwable $th) {
            $data = [
                "status" => false,
                "code" => 500,
                "message" => "Gagal mengahapus data",
            ];
            return $data;
        }
    }

    private function resolveWisudaPayload($rawWisuda, string $nim, $listTagihan): ?array
    {
        if (! $this->hasWisudaTagihan($listTagihan)) {
            return null;
        }

        $payload = null;

        if (is_array($rawWisuda)) {
            $payload = $rawWisuda;
        } elseif (is_string($rawWisuda) && trim($rawWisuda) !== '') {
            $decoded = json_decode($rawWisuda, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (! $payload) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'wisuda' => 'Detail input wisuda wajib diisi saat memilih tagihan wisuda.',
            ]);
        }

        $payload['nim'] = strtoupper(trim((string) ($payload['nim'] ?? $nim)));
        $payload['tahun_id'] = (int) ($payload['tahun_id'] ?? 0);
        $payload['nama_ayah'] = $payload['nama_ayah'] ?? '-';
        $payload['is_bayar'] = Wisuda::TANPA_BAYAR;
        unset($payload['jenis_pembayaran'], $payload['jumlah'], $payload['keterangan']);

        foreach (['nim', 'nama', 'nama_ayah', 'tahun_id', 'jenis_kelamin', 'prodi', 'ukuran_baju'] as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '' || $payload[$field] === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "wisuda.$field" => "Field $field pada detail wisuda wajib diisi.",
                ]);
            }
        }

        return $payload;
    }

    private function hasWisudaTagihan($listTagihan): bool
    {
        foreach ((array) $listTagihan as $namaTagihan) {
            if (stripos((string) $namaTagihan, 'wisuda') !== false) {
                return true;
            }
        }

        return false;
    }

    private function ensureTagihanCanBePaid(string $nim, $tagihanIds): void
    {
        $ids = array_values(array_filter((array) $tagihanIds, function ($id) {
            return $id !== null && $id !== '';
        }));

        if (empty($ids)) {
            return;
        }

        $tagihan = KeuanganTagihan::whereIn('id', $ids)->get(['id', 'nama']);
        $tagihan = TagihanMahasiswa::markPaymentEligibility($tagihan, $nim);
        $blocked = collect($tagihan)
            ->filter(fn ($item) => ! empty($item->tidak_bisa_dibayar))
            ->pluck('nama')
            ->values()
            ->all();

        if (! empty($blocked)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'list_tagihan_id' => 'Tagihan ' . implode(', ', $blocked) . ' belum memenuhi syarat dan tidak bisa dibayar.',
            ]);
        }
    }

    private function normalizeKeringananJenis($jenis): ?string
    {
        $jenis = strtolower(trim((string) $jenis));

        return in_array($jenis, ['samahah', 'dhomin'], true) ? $jenis : null;
    }

    private function normalizeNumber($value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }

    private function resolveKeringananJumlah($jenis, $tagihanId, $nim, $jumlahInput, $dibayar, $deposit, $index): float
    {
        if (! KeuanganTagihan::whereKey($tagihanId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "list_tagihan_id.$index" => 'Tagihan tidak ditemukan.',
            ]);
        }

        $jumlah = $this->normalizeNumber($jumlahInput);
        $sisaTagihan = max(0, (float) TagihanMahasiswa::getSisaTagihan($nim, $tagihanId));

        $maksimalKeringanan = max(0, $sisaTagihan - $dibayar - $deposit);

        if ($jenis === 'dhomin') {
            return $jumlah > 0 ? min($jumlah, $maksimalKeringanan) : $maksimalKeringanan;
        }

        if ($jumlah <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "list_keringanan_jumlah.$index" => 'Jumlah keringanan Samahah wajib lebih dari 0.',
            ]);
        }

        if ($jumlah > $maksimalKeringanan) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "list_keringanan_jumlah.$index" => 'Jumlah keringanan tidak boleh melebihi sisa tagihan setelah pembayaran.',
            ]);
        }

        return $jumlah;
    }

    private function upsertKeringananTagihan($tahunAkademikId, $tagihanId, $nim, $jumlah, $batas, $jenisKeringanan): void
    {
        KeuanganDispensasiTagihan::updateOrCreate(
            [
                'nim' => $nim,
                'jenis_tagihan_id' => $tagihanId,
            ],
            [
                'th_akademik_id' => $tahunAkademikId,
                'jenis' => 'Beasiswa',
                'jumlah' => $jumlah,
                'batas' => $batas,
                'keterangan' => ucfirst($jenisKeringanan),
                'user_id' => Auth::user()->id,
            ]
        );
    }

    public function kwitansi($id)
    {
        $keuanganPembayaran = KeuanganPembayaran::findOrFail($id);
        return KwitansiPdf::pdf($keuanganPembayaran);
    }

    public function kwitansiPreview($id)
    {
        $keuanganPembayaran = KeuanganPembayaran::findOrFail($id);
        return KwitansiPreviewPdf::pdf($keuanganPembayaran);
    }
}
