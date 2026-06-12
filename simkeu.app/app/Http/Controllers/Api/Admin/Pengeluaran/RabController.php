<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RabController extends Controller
{
    private const SOURCES = [
        'tatap_muka' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'lpj_table' => 'keuangan_pengeluaran_dosen_lpj',
            'module_name' => 'Dosen Tatap Muka',
            'detail_path' => '/admin/pengeluaran/dosen-tatapmuka/rekap/',
            'pegawai_tipe' => null,
        ],
        'kegiatan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'lpj_table' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
            'module_name' => 'Pegawai Kegiatan',
            'detail_path' => '/admin/pengeluaran/dosen-kegiatan/rekap/',
            'pegawai_tipe' => null,
        ],
        'rumah_tangga' => [
            'rekap_table' => 'keuangan_pengeluaran_rumah_tangga_rekap',
            'detail_table' => 'keuangan_pengeluaran_rumah_tangga',
            'lpj_table' => 'keuangan_pengeluaran_rumah_tangga_lpj',
            'module_name' => 'Rumah Tangga',
            'detail_path' => '/admin/pengeluaran/rumah-tangga/rekap/',
            'pegawai_tipe' => null,
        ],
        'sarana_prasarana' => [
            'rekap_table' => 'keuangan_pengeluaran_sarana_prasarana_rekap',
            'detail_table' => 'keuangan_pengeluaran_sarana_prasarana',
            'lpj_table' => 'keuangan_pengeluaran_sarana_prasarana_lpj',
            'module_name' => 'Sarana Prasarana',
            'detail_path' => '/admin/pengeluaran/sarana-prasarana/rekap/',
            'pegawai_tipe' => null,
        ],
        'transportasi' => [
            'rekap_table' => 'keuangan_pengeluaran_transportasi_rekap',
            'detail_table' => 'keuangan_pengeluaran_transportasi',
            'lpj_table' => 'keuangan_pengeluaran_transportasi_lpj',
            'module_name' => 'Transportasi',
            'detail_path' => '/admin/pengeluaran/transportasi/rekap/',
            'pegawai_tipe' => null,
        ],
        'dosen_bulanan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'module_name' => 'Dosen Bulanan',
            'detail_path' => '/admin/pengeluaran/dosen-bulanan/rekap/',
            'pegawai_tipe' => 'dosen',
        ],
        'staff_bulanan' => [
            'rekap_table' => 'keuangan_pengeluaran_staff_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'module_name' => 'Staff Bulanan',
            'detail_path' => '/admin/pengeluaran/staff-bulanan/rekap/',
            'pegawai_tipe' => 'staff',
        ],
    ];

    public function index(Request $request)
    {
        $this->forceOwnPetugasForBarokah($request);

        $filteredRekaps = $this->filteredRekapQuery($request);
        $rekapStats = DB::query()
            ->fromSub(clone $filteredRekaps, 'rab')
            ->selectRaw(
                'COUNT(*) as total_rekap,
                COUNT(DISTINCT module_key) as total_modul,
                COALESCE(SUM(jumlah), 0) as total_anggaran'
            )
            ->first();

        $totalRekap = (int) ($rekapStats->total_rekap ?? 0);
        $sortKey = $request->input('sort_key', 'bulan_tahun');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'nama' => 'nama',
            'bulan_tahun' => 'bulan_tahun',
            'tanggal_rekap' => 'tanggal_rekap',
            'jumlah' => 'jumlah',
            'jumlah_data' => 'jumlah_data',
            'total_pengeluaran' => 'total_pengeluaran',
            'module_name' => 'module_name',
            'created_at' => 'created_at',
        ];

        $pageQuery = clone $filteredRekaps;
        $pageQuery
            ->orderBy($sortColumns[$sortKey] ?? 'bulan_tahun', $sortOrder)
            ->orderBy('module_name')
            ->orderBy('nama');

        $data = $this->paginate($pageQuery, $request, $totalRekap);
        $data->getCollection()->each(function ($item) {
            $item->jumlah = (int) $item->jumlah;
            $item->jumlah_sementara = $item->jumlah_sementara === null
                ? null
                : (int) $item->jumlah_sementara;
            $item->jumlah_data = (int) $item->jumlah_data;
            $item->total_pengeluaran = (int) $item->total_pengeluaran;
            $item->is_jumlah_sementara = (bool) $item->is_jumlah_sementara;
            $item->selisih_sementara = (int) $item->selisih_sementara;
        });

        $detailStats = $this->detailStats($request);
        $years = DB::query()
            ->fromSub($this->rekapUnionQuery(), 'rab')
            ->whereNotNull('bulan_tahun')
            ->selectRaw('YEAR(bulan_tahun) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn ($year) => (int) $year)
            ->values();

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => [
                'total_rekap' => $totalRekap,
                'total_data' => $detailStats['total_data'],
                'total_anggaran' => (int) ($rekapStats->total_anggaran ?? 0),
                'total_realisasi' => $detailStats['total_realisasi'],
                'total_modul' => (int) ($rekapStats->total_modul ?? 0),
            ],
            'filters' => [
                'years' => $years,
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'RAB retrieved successfully',
        ]);
    }

    public function kas(Request $request)
    {
        $this->forceOwnPetugasForBarokah($request);

        $rows = collect(self::SOURCES)
            ->map(function (array $source, string $moduleKey) use ($request) {
                if ($request->filled('module_key') && $request->module_key !== $moduleKey) {
                    return null;
                }

                $rabQuery = DB::query()->fromSub($this->rekapSourceQuery($moduleKey, $source, $request), 'rab');
                $this->applyFilters($rabQuery, $request);

                $totalRab = (int) (clone $rabQuery)->sum('jumlah');
                $lpjStats = $this->lpjStatsForRabQuery($rabQuery, $source, $moduleKey, $request);
                $manual = $this->manualKasSummary($request, $moduleKey);

                return [
                    'module_key' => $moduleKey,
                    'module_name' => $source['module_name'],
                    'total_rab' => $totalRab,
                    'total_lpj' => $lpjStats['total_lpj'],
                    'jumlah_lpj' => $lpjStats['jumlah_lpj'],
                    'manual_masuk' => $manual['masuk'],
                    'manual_keluar' => $manual['keluar'],
                    'saldo_kas' => $totalRab - $lpjStats['total_lpj'] + $manual['masuk'] - $manual['keluar'],
                ];
            })
            ->filter()
            ->values();

        $totals = [
            'total_rab' => (int) $rows->sum('total_rab'),
            'total_lpj' => (int) $rows->sum('total_lpj'),
            'manual_masuk' => (int) $rows->sum('manual_masuk'),
            'manual_keluar' => (int) $rows->sum('manual_keluar'),
            'saldo_kas' => (int) $rows->sum('saldo_kas'),
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'summary' => $rows,
                'totals' => $totals,
                'manual' => $this->manualKasRows($request),
                'petugas' => $this->selectedPetugas($request),
            ],
            'filters' => [
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'Kas RAB retrieved successfully',
        ]);
    }

    public function storeKasManual(Request $request)
    {
        $this->forceOwnPetugasForBarokah($request);

        $validator = Validator::make($request->all(), [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tipe' => ['required', Rule::in(['masuk', 'keluar'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = DB::table('keuangan_pengeluaran_saldo')->insertGetId([
            ...$validator->validated(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $data,
            ],
            'message' => 'Kas manual berhasil ditambahkan.',
        ], 201);
    }

    public function updateKasManual(Request $request, $id)
    {
        $this->forceOwnPetugasForBarokah($request);

        $validator = Validator::make($request->all(), [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tipe' => ['required', Rule::in(['masuk', 'keluar'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $query = DB::table('keuangan_pengeluaran_saldo')->where('id', $id);

        if ($this->shouldForceOwnPetugas()) {
            $query->where('petugas_id', auth()->id());
        }

        $updated = $query->update([
            ...$validator->validated(),
            'updated_at' => now(),
        ]);

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Kas manual not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kas manual berhasil diperbarui.',
        ]);
    }

    public function destroyKasManual($id)
    {
        $query = DB::table('keuangan_pengeluaran_saldo')->where('id', $id);

        if ($this->shouldForceOwnPetugas()) {
            $query->where('petugas_id', auth()->id());
        }

        $deleted = $query->delete();

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Kas manual not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kas manual berhasil dihapus.',
        ]);
    }

    private function filteredRekapQuery(Request $request): Builder
    {
        $query = DB::query()->fromSub($this->rekapUnionQuery($request), 'rab');
        $this->applyFilters($query, $request);

        return $query;
    }

    private function rekapUnionQuery(?Request $request = null): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $queries[] = $this->rekapSourceQuery($moduleKey, $source, $request);
        }

        return $this->unionAll($queries);
    }

    private function rekapSourceQuery(string $moduleKey, array $source, ?Request $request = null): Builder
    {
        $summary = $this->detailSummaryQuery($source, $request);
        $effectiveAmount = 'CASE
            WHEN COALESCE(summary.jumlah_data, 0) > 0
                THEN COALESCE(summary.total_pengeluaran, 0)
            ELSE COALESCE(rekap.jumlah_sementara, 0)
        END';
        $temporaryDifference = 'CASE
            WHEN rekap.jumlah_sementara IS NOT NULL
                AND rekap.jumlah_sementara > COALESCE(summary.total_pengeluaran, 0)
                THEN rekap.jumlah_sementara - COALESCE(summary.total_pengeluaran, 0)
            ELSE 0
        END';

        return DB::table("{$source['rekap_table']} as rekap")
            ->leftJoinSub($summary, 'summary', 'summary.rekap_id', '=', 'rekap.id')
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                DB::raw("{$effectiveAmount} as jumlah"),
                'rekap.keterangan',
                'rekap.created_at',
                DB::raw("CONCAT('{$moduleKey}:', rekap.id) as row_key"),
                DB::raw("'{$moduleKey}' as module_key"),
                DB::raw("'{$source['module_name']}' as module_name"),
                DB::raw("'{$source['detail_path']}' as detail_path"),
                DB::raw('COALESCE(summary.jumlah_data, 0) as jumlah_data'),
                DB::raw('COALESCE(summary.total_pengeluaran, 0) as total_pengeluaran'),
                DB::raw('CASE WHEN COALESCE(summary.jumlah_data, 0) = 0 THEN 1 ELSE 0 END as is_jumlah_sementara'),
                DB::raw("{$temporaryDifference} as selisih_sementara"),
            ]);
    }

    private function detailSummaryQuery(array $source, ?Request $request = null): Builder
    {
        $query = DB::table("{$source['detail_table']} as detail")
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_pengeluaran'),
            ])
            ->whereNotNull('detail.rekap_id')
            ->groupBy('detail.rekap_id');

        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        if (
            $request?->filled('petugas_id')
            && Schema::hasColumn($source['detail_table'], 'petugas_id')
        ) {
            $query->where('detail.petugas_id', $request->petugas_id);
        }

        return $query;
    }

    private function lpjSummaryQuery(array $source, ?Request $request = null): Builder
    {
        $query = DB::table("{$source['lpj_table']} as detail")
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_lpj'),
            ])
            ->whereNotNull('detail.rekap_id')
            ->groupBy('detail.rekap_id');

        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        if (
            $request?->filled('petugas_id')
            && Schema::hasColumn($source['lpj_table'], 'petugas_id')
        ) {
            $query->where('detail.petugas_id', $request->petugas_id);
        }

        return $query;
    }

    private function lpjStatsForRabQuery(Builder $rabQuery, array $source, string $moduleKey, Request $request): array
    {
        $sameAsRabAmount = $request->filled('petugas_id')
            ? 'rab_filtered.jumlah'
            : 'COALESCE(NULLIF(lpj_status.total_lpj, 0), rab_filtered.jumlah)';

        $stats = DB::query()
            ->fromSub(clone $rabQuery, 'rab_filtered')
            ->leftJoinSub($this->lpjSummaryQuery($source, $request), 'lpj_summary', 'lpj_summary.rekap_id', '=', 'rab_filtered.id')
            ->leftJoin('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($moduleKey) {
                $join->on('lpj_status.rekap_id', '=', 'rab_filtered.id')
                    ->where('lpj_status.module_key', '=', $moduleKey);
            })
            ->selectRaw(
                "COALESCE(SUM(
                    CASE
                        WHEN COALESCE(lpj_summary.jumlah_data, 0) > 0
                            THEN COALESCE(lpj_summary.total_lpj, 0)
                        WHEN COALESCE(lpj_status.sama_dengan_rab, 0) = 1
                            THEN {$sameAsRabAmount}
                        ELSE 0
                    END
                ), 0) as total_lpj,
                COALESCE(SUM(COALESCE(lpj_summary.jumlah_data, 0)), 0) as jumlah_lpj"
            )
            ->first();

        return [
            'total_lpj' => (int) ($stats->total_lpj ?? 0),
            'jumlah_lpj' => (int) ($stats->jumlah_lpj ?? 0),
        ];
    }

    private function manualKasSummary(Request $request, string $moduleKey): array
    {
        $query = DB::table('keuangan_pengeluaran_saldo')
            ->where('module_key', $moduleKey);

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn('keuangan_pengeluaran_saldo', 'petugas_id')
        ) {
            $query->where('petugas_id', $request->petugas_id);
        }

        $this->applyPeriodFilter($query, $request, 'tanggal');

        $summary = $query
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE 0 END), 0) as masuk,
                COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN nominal ELSE 0 END), 0) as keluar"
            )
            ->first();

        return [
            'masuk' => (int) ($summary->masuk ?? 0),
            'keluar' => (int) ($summary->keluar ?? 0),
        ];
    }

    private function manualKasRows(Request $request)
    {
        $query = DB::table('keuangan_pengeluaran_saldo as kas')
            ->leftJoin('users as petugas', 'petugas.id', '=', 'kas.petugas_id')
            ->select([
                'kas.*',
                'petugas.name as petugas_nama',
            ]);

        if ($request->filled('module_key')) {
            $query->where('kas.module_key', $request->module_key);
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn('keuangan_pengeluaran_saldo', 'petugas_id')
        ) {
            $query->where('kas.petugas_id', $request->petugas_id);
        }

        $this->applyPeriodFilter($query, $request, 'kas.tanggal');

        return $query
            ->orderByDesc('kas.tanggal')
            ->orderByDesc('kas.id')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $item->module_name = self::SOURCES[$item->module_key]['module_name'] ?? $item->module_key;
                $item->id = (int) $item->id;
                $item->petugas_id = $item->petugas_id === null ? null : (int) $item->petugas_id;
                $item->nominal = (int) $item->nominal;

                return $item;
            });
    }

    private function detailStats(Request $request): array
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $filteredSource = $this->filteredSourceRekaps($request, $moduleKey, $source);

            if (! $filteredSource) {
                continue;
            }

            $query = DB::query()
                ->fromSub($filteredSource->select('id'), 'rekap')
                ->leftJoin("{$source['detail_table']} as detail", function ($join) use ($source) {
                    $join->on('detail.rekap_id', '=', 'rekap.id');

                    if ($source['pegawai_tipe']) {
                        $join->where('detail.pegawai_tipe', '=', $source['pegawai_tipe']);
                    }
                });

            if (
                $request->filled('petugas_id')
                && Schema::hasColumn($source['detail_table'], 'petugas_id')
            ) {
                $query->where('detail.petugas_id', $request->petugas_id);
            }

            $queries[] = $query->selectRaw(
                'COUNT(detail.id) as total_data,
                COALESCE(SUM(detail.total), 0) as total_anggaran'
            );
        }

        if ($queries === []) {
            return [
                'total_data' => 0,
                'total_realisasi' => 0,
            ];
        }

        $stats = DB::query()
            ->fromSub($this->unionAll($queries), 'stats')
            ->selectRaw(
                'COALESCE(SUM(total_data), 0) as total_data,
                COALESCE(SUM(total_anggaran), 0) as total_realisasi'
            )
            ->first();

        return [
            'total_data' => (int) ($stats->total_data ?? 0),
            'total_realisasi' => (int) ($stats->total_realisasi ?? 0),
        ];
    }

    private function filteredSourceRekaps(
        Request $request,
        string $moduleKey,
        array $source
    ): ?Builder {
        if ($request->filled('module_key') && $request->module_key !== $moduleKey) {
            return null;
        }

        $query = DB::table("{$source['rekap_table']} as rekap")
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                'rekap.keterangan',
                'rekap.created_at',
            ]);

        $this->applyPeriodFilter($query, $request, 'rekap.bulan_tahun');

        if ($request->filled('petugas_id')) {
            $query->where('rekap.petugas_id', $request->petugas_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            if (stripos($source['module_name'], $search) === false) {
                $query->where(function (Builder $filter) use ($search) {
                    $filter->where('rekap.nama', 'LIKE', "%{$search}%")
                        ->orWhere('rekap.keterangan', 'LIKE', "%{$search}%");
                });
            }
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $this->applyPeriodFilter($query, $request, 'bulan_tahun');

        if ($request->filled('module_key')) {
            $query->where('module_key', $request->module_key);
        }

        if ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->petugas_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('keterangan', 'LIKE', "%{$search}%")
                    ->orWhere('module_name', 'LIKE', "%{$search}%");
            });
        }
    }

    private function applyPeriodFilter(Builder $query, Request $request, string $column): void
    {
        $bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($tahun && $bulan >= 1 && $bulan <= 12) {
            $start = sprintf('%04d-%02d-01', $tahun, $bulan);
            $end = date('Y-m-d', strtotime("{$start} +1 month"));
            $query->where($column, '>=', $start)
                ->where($column, '<', $end);
        } elseif ($tahun) {
            $query->where($column, '>=', "{$tahun}-01-01")
                ->where($column, '<', ($tahun + 1).'-01-01');
        } elseif ($bulan >= 1 && $bulan <= 12) {
            $query->whereMonth($column, $bulan);
        }
    }

    private function paginate(
        Builder $query,
        Request $request,
        int $total
    ): LengthAwarePaginator {
        $perPage = max(1, (int) $request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $items = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function unionAll(array $queries): Builder
    {
        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function forceOwnPetugasForBarokah(Request $request): void
    {
        if ($this->shouldForceOwnPetugas()) {
            $request->merge([
                'petugas_id' => auth()->id(),
            ]);
        }
    }

    private function shouldForceOwnPetugas(): bool
    {
        $roleName = strtolower((string) (auth()->user()?->role?->name ?? ''));

        return in_array($roleName, [
            'barokahdosen_tatapmuka',
            'barokahdosen_kegiatan',
            'barokahdosen_bulanan',
        ], true);
    }

    private function selectedPetugas(Request $request): ?object
    {
        if (! $request->filled('petugas_id')) {
            return null;
        }

        $petugas = DB::table('users')
            ->leftJoin('role', 'role.id', '=', 'users.role_id')
            ->where('users.id', $request->petugas_id)
            ->first([
                'users.id',
                'users.name',
                'role.name as role_name',
            ]);

        if (! $petugas) {
            return null;
        }

        $petugas->id = (int) $petugas->id;

        return $petugas;
    }

    private function moduleOptions(): array
    {
        return [
            ['title' => 'Dosen Tatap Muka', 'value' => 'tatap_muka'],
            ['title' => 'Pegawai Kegiatan', 'value' => 'kegiatan'],
            ['title' => 'Rumah Tangga', 'value' => 'rumah_tangga'],
            ['title' => 'Sarana Prasarana', 'value' => 'sarana_prasarana'],
            ['title' => 'Transportasi', 'value' => 'transportasi'],
            ['title' => 'Dosen Bulanan', 'value' => 'dosen_bulanan'],
            ['title' => 'Staff Bulanan', 'value' => 'staff_bulanan'],
        ];
    }
}
