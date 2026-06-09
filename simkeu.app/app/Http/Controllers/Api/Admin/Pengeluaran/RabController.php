<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RabController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $statsQuery = clone $query;
        $stats = [
            'total_rekap' => (int) (clone $statsQuery)->count(),
            'total_data' => (int) (clone $statsQuery)->sum('jumlah_data'),
            'total_anggaran' => (int) (clone $statsQuery)->sum('total_pengeluaran'),
            'total_modul' => (int) (clone $statsQuery)->distinct()->count('module_key'),
        ];

        $sortColumns = [
            'nama' => 'nama',
            'bulan_tahun' => 'bulan_tahun',
            'module_name' => 'module_name',
            'jumlah_data' => 'jumlah_data',
            'total_pengeluaran' => 'total_pengeluaran',
            'created_at' => 'created_at',
        ];
        $sortKey = $request->input('sort_key', 'bulan_tahun');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumns[$sortKey] ?? 'bulan_tahun', $sortOrder);
        $query->orderBy('module_name')->orderBy('nama');

        $data = $query->paginate(max(1, (int) $request->input('limit', 10)));
        $years = $this->baseQuery()
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
            'stats' => $stats,
            'filters' => [
                'years' => $years,
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'RAB retrieved successfully',
        ]);
    }

    private function baseQuery(): Builder
    {
        return DB::query()->fromSub($this->unionQuery(), 'rab');
    }

    private function unionQuery(): Builder
    {
        $queries = [
            $this->sourceQuery(
                'keuangan_pengeluaran_dosen_rekap',
                'tatap_muka',
                'Dosen Tatap Muka',
                '/admin/pengeluaran/dosen-tatapmuka/rekap/',
                $this->simpleSummary('keuangan_pengeluaran_dosen')
            ),
            $this->sourceQuery(
                'keuangan_pengeluaran_dosen_kegiatan_rekap',
                'kegiatan',
                'Pegawai Kegiatan',
                '/admin/pengeluaran/dosen-kegiatan/rekap/',
                $this->simpleSummary('keuangan_pengeluaran_dosen_kegiatan')
            ),
            $this->sourceQuery(
                'keuangan_pengeluaran_dosen_bulanan_rekap',
                'dosen_bulanan',
                'Dosen Bulanan',
                '/admin/pengeluaran/dosen-bulanan/rekap/',
                $this->monthlySummary('dosen')
            ),
            $this->sourceQuery(
                'keuangan_pengeluaran_staff_bulanan_rekap',
                'staff_bulanan',
                'Staff Bulanan',
                '/admin/pengeluaran/staff-bulanan/rekap/',
                $this->monthlySummary('staff')
            ),
        ];

        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function sourceQuery(
        string $rekapTable,
        string $moduleKey,
        string $moduleName,
        string $detailPath,
        Builder $summary
    ): Builder {
        return DB::table("{$rekapTable} as rekap")
            ->leftJoinSub($summary, 'summary', 'summary.rekap_id', '=', 'rekap.id')
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.keterangan',
                'rekap.created_at',
                DB::raw("CONCAT('{$moduleKey}:', rekap.id) as row_key"),
                DB::raw("'{$moduleKey}' as module_key"),
                DB::raw("'{$moduleName}' as module_name"),
                DB::raw("'{$detailPath}' as detail_path"),
                DB::raw('COALESCE(summary.jumlah_data, 0) as jumlah_data'),
                DB::raw('COALESCE(summary.total_pengeluaran, 0) as total_pengeluaran'),
            ]);
    }

    private function simpleSummary(string $detailTable): Builder
    {
        return DB::table($detailTable)
            ->select([
                'rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(total), 0) as total_pengeluaran'),
            ])
            ->whereNotNull('rekap_id')
            ->groupBy('rekap_id');
    }

    private function monthlySummary(string $pegawaiTipe): Builder
    {
        return DB::table('keuangan_pengeluaran_pegawai_bulanan as detail')
            ->join('pegawai', 'pegawai.id', '=', 'detail.pegawai_id')
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_pengeluaran'),
            ])
            ->whereNotNull('detail.rekap_id')
            ->where('pegawai.tipe', $pegawaiTipe)
            ->groupBy('detail.rekap_id');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($tahun && $bulan >= 1 && $bulan <= 12) {
            $start = sprintf('%04d-%02d-01', $tahun, $bulan);
            $end = date('Y-m-d', strtotime("{$start} +1 month"));
            $query->where('bulan_tahun', '>=', $start)
                ->where('bulan_tahun', '<', $end);
        } elseif ($tahun) {
            $query->where('bulan_tahun', '>=', "{$tahun}-01-01")
                ->where('bulan_tahun', '<', ($tahun + 1) . '-01-01');
        } elseif ($bulan >= 1 && $bulan <= 12) {
            $query->whereMonth('bulan_tahun', $bulan);
        }

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
