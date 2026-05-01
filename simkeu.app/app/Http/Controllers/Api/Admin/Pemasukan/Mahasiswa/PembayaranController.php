<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Exports\pdf\KwitansiPdf;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exports\pdf\KwitansiPreviewPdf;
use App\Models\KeuanganJenisPembayaran;
use Illuminate\Support\Facades\Validator;
use App\Models\KeuanganJenisPembayaranDetail;

class PembayaranController extends Controller
{
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
                ->groupBy(DB::raw($caseExpr), DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA')"))
                ->orderBy(DB::raw($caseExpr))
                ->orderBy(DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA')"))
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
                'jenis_pembayaran'    => 'required',
                'dipakai_deposit_mhs' => 'nullable',
                'kamar_id'            => 'nullable',
                'jk_id'               => 'required'
            ]);

            DB::beginTransaction();

            $jk = Helper::getJenisKelaminUser();

            $nota = Helper::generateNota($dataValidated['tanggal'], $request->jk_id);

            for ($i = 0; $i < count($dataValidated['list_tagihan']); $i++) {
                $nomor = Helper::generateNumber();
                $data  = KeuanganPembayaran::where('nomor', $request->nomor)->first();

                if (! $data) {

                    if ($dataValidated['list_dibayar'][$i] != 0) {
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $dataValidated['list_tagihan_id'][$i],
                            "nim"            => strtoupper($dataValidated['nim']),
                            "jumlah"         => $dataValidated['list_dibayar'][$i],
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
                    if ($dataValidated['list_deposit'][$i] != 0) {
                        $jenisPembayaran = KeuanganJenisPembayaran::where([
                            ['nama', 'LIKE', "%deposit%"],
                            ['kategori', 'LIKE', "%$jk->kategori%"],
                        ])->first();
                        $nomor      = Helper::generateNumber();
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $dataValidated['list_tagihan_id'][$i],
                            "nim"            => strtoupper($dataValidated['nim']),
                            "jumlah"         => $dataValidated['list_deposit'][$i],
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
                    }
                }
            }

            // catatan-deposit
            if (isset($dataValidated['dipakai_deposit_mhs']) && $dataValidated['dipakai_deposit_mhs'] > 0) {
                $deposit = KeuanganDeposit::where('nim', $dataValidated['nim'])->first();
                if ($deposit != null) {
                    $jumlahDepositBaru = $deposit->jumlah - $dataValidated['dipakai_deposit_mhs'];
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

            DB::commit();

            $data = [
                "status" => true,
                "code" => 200,
                "id"      => $pembayaran->id,
                "message" => "Berhasil menyimpan data",
                'req' => $request->all()
            ];
            return $data;
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
                'code'  => 422,
                'req'     => $request->all(),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
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
            KeuanganPembayaran::destroy($id);

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
