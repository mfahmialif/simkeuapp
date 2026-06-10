<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RabController extends Controller
{
    private const SOURCES = [
        'tatap_muka' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'module_name' => 'Dosen Tatap Muka',
            'detail_path' => '/admin/pengeluaran/dosen-tatapmuka/rekap/',
            'pegawai_tipe' => null,
        ],
        'kegiatan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'module_name' => 'Pegawai Kegiatan',
            'detail_path' => '/admin/pengeluaran/dosen-kegiatan/rekap/',
            'pegawai_tipe' => null,
        ],
        'dosen_bulanan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'module_name' => 'Dosen Bulanan',
            'detail_path' => '/admin/pengeluaran/dosen-bulanan/rekap/',
            'pegawai_tipe' => 'dosen',
        ],
        'staff_bulanan' => [
            'rekap_table' => 'keuangan_pengeluaran_staff_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'module_name' => 'Staff Bulanan',
            'detail_path' => '/admin/pengeluaran/staff-bulanan/rekap/',
            'pegawai_tipe' => 'staff',
        ],
    ];

    public function index(Request $request)
    {
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

    private function filteredRekapQuery(Request $request): Builder
    {
        $query = DB::query()->fromSub($this->rekapUnionQuery(), 'rab');
        $this->applyFilters($query, $request);

        return $query;
    }

    private function rekapUnionQuery(): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $queries[] = $this->rekapSourceQuery($moduleKey, $source);
        }

        return $this->unionAll($queries);
    }

    private function rekapSourceQuery(string $moduleKey, array $source): Builder
    {
        $summary = $this->detailSummaryQuery($source);
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

    private function detailSummaryQuery(array $source): Builder
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

        return $query;
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
                'rekap.keterangan',
                'rekap.created_at',
            ]);

        $this->applyPeriodFilter($query, $request, 'rekap.bulan_tahun');

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

    private function moduleOptions(): array
    {
        return [
            ['title' => 'Dosen Tatap Muka', 'value' => 'tatap_muka'],
            ['title' => 'Pegawai Kegiatan', 'value' => 'kegiatan'],
            ['title' => 'Dosen Bulanan', 'value' => 'dosen_bulanan'],
            ['title' => 'Staff Bulanan', 'value' => 'staff_bulanan'],
        ];
    }
}
