<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\PemasukanTunaiHarianBulananExport;
use App\Exports\PemasukanTunaiHarianTahunanExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class LaporanHarianController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mode' => ['nullable', Rule::in(['bulanan', 'tahunan'])],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'jenis_pembayaran' => ['nullable', 'string', 'max:255'],
            'petugas_id' => ['nullable', 'integer'],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'action' => ['nullable', Rule::in(['json', 'excel'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $mode = $request->input('mode', 'bulanan');
        $action = $request->input('action', 'json');
        $year = $mode === 'tahunan'
            ? (int) $request->input('tahun', now()->year)
            : (int) substr($request->input('bulan', now()->format('Y-m')), 0, 4);
        $month = $mode === 'bulanan'
            ? (int) substr($request->input('bulan', now()->format('Y-m')), 5, 2)
            : null;
        $start = $mode === 'tahunan'
            ? Carbon::create($year, 1, 1)->startOfDay()
            : Carbon::create($year, $month, 1)->startOfDay();
        $end = $mode === 'tahunan'
            ? Carbon::create($year, 12, 31)->endOfDay()
            : $start->copy()->endOfMonth();
        $modules = $this->modules();
        $columns = collect($modules)
            ->map(fn (array $module) => [
                'key' => $module['key'],
                'label' => $module['label'],
            ])
            ->values()
            ->all();
        $dailyTotals = $this->dailyTotals($modules, $start, $end, $request);

        if ($mode === 'tahunan') {
            $allData = [];

            for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
                $allData[$monthNumber] = $this->monthData(
                    $year,
                    $monthNumber,
                    $modules,
                    $dailyTotals
                );
            }

            if ($action === 'excel') {
                return Excel::download(
                    new PemasukanTunaiHarianTahunanExport($columns, $allData, $year),
                    "Pengeluaran_Harian_Tahun_{$year}.xlsx"
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Laporan pengeluaran tahunan berhasil diambil.',
                'title' => "PENGELUARAN HARIAN TAHUN {$year}",
                'columns' => $columns,
                'all_data' => $allData,
                'stats' => $this->yearStats($allData, count($columns)),
                'filter_options' => $this->filterOptions($modules, $request),
            ]);
        }

        $monthData = $this->monthData($year, $month, $modules, $dailyTotals);

        if ($action === 'excel') {
            return Excel::download(
                new PemasukanTunaiHarianBulananExport(
                    $columns,
                    $monthData['data'],
                    $monthData['totals'],
                    $monthData['title']
                ),
                sprintf('Pengeluaran_Harian_%04d-%02d.xlsx', $year, $month)
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Laporan pengeluaran bulanan berhasil diambil.',
            'title' => $monthData['title'],
            'columns' => $columns,
            'data' => $monthData['data'],
            'totals' => $monthData['totals'],
            'stats' => $monthData['stats'],
            'filter_options' => $this->filterOptions($modules, $request),
        ]);
    }

    private function modules(): array
    {
        return [
            [
                'key' => 'tatap_muka',
                'label' => 'Tatap Muka',
                'table' => 'keuangan_pengeluaran_dosen',
            ],
            [
                'key' => 'kegiatan',
                'label' => 'Kegiatan',
                'table' => 'keuangan_pengeluaran_dosen_kegiatan',
            ],
            [
                'key' => 'bulanan',
                'label' => 'Bulanan',
                'table' => 'keuangan_pengeluaran_pegawai_bulanan',
            ],
            [
                'key' => 'rumah_tangga',
                'label' => 'Rumah Tangga',
                'table' => 'keuangan_pengeluaran_rumah_tangga',
            ],
            [
                'key' => 'sarana_prasarana',
                'label' => 'Sarana Prasarana',
                'table' => 'keuangan_pengeluaran_sarana_prasarana',
            ],
            [
                'key' => 'transportasi',
                'label' => 'Transportasi',
                'table' => 'keuangan_pengeluaran_transportasi',
            ],
            [
                'key' => 'umum',
                'label' => 'Pengeluaran Umum',
                'table' => 'keuangan_pengeluaran_umum',
            ],
        ];
    }

    private function dailyTotals(
        array $modules,
        Carbon $start,
        Carbon $end,
        Request $request
    ): array {
        $dailyTotals = [];

        foreach ($modules as $module) {
            if (! $this->usableTable($module['table'])) {
                continue;
            }

            $table = $module['table'];
            $query = DB::table($table)
                ->whereBetween("{$table}.tanggal", [
                    $start->toDateString(),
                    $end->toDateString(),
                ]);

            if ($request->filled('jenis_pembayaran')) {
                $query->where(
                    "{$table}.jenis_pembayaran",
                    $request->input('jenis_pembayaran')
                );
            }

            $this->applyPetugasFilters($query, $table, $request);

            $rows = $query
                ->selectRaw("DATE({$table}.tanggal) as tanggal")
                ->selectRaw("COALESCE(SUM({$table}.total), 0) as total")
                ->groupByRaw("DATE({$table}.tanggal)")
                ->get();

            foreach ($rows as $row) {
                $date = (string) $row->tanggal;
                $dailyTotals[$date] ??= [];
                $dailyTotals[$date][$module['key']] = (int) $row->total;
            }
        }

        return $dailyTotals;
    }

    private function monthData(
        int $year,
        int $month,
        array $modules,
        array $dailyTotals
    ): array {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $rows = [];
        $totals = ['jumlah' => 0, 'jumlah_by_currency' => []];
        $activeDays = 0;

        foreach ($modules as $module) {
            $totals[$module['key']] = 0;
            $totals[$module['key'].'_by_currency'] = [];
        }

        $cursor = $start->copy();
        $number = 1;

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $row = [
                'no' => $number,
                'tanggal' => $date,
                'jumlah' => 0,
                'jumlah_by_currency' => [],
            ];

            foreach ($modules as $module) {
                $key = $module['key'];
                $amount = (int) data_get($dailyTotals, "{$date}.{$key}", 0);
                $row[$key] = $amount;
                $row[$key.'_by_currency'] = $this->idrTotals($amount);
                $row['jumlah'] += $amount;
                $totals[$key] += $amount;
            }

            $row['jumlah_by_currency'] = $this->idrTotals($row['jumlah']);
            $totals['jumlah'] += $row['jumlah'];

            if ($row['jumlah'] > 0) {
                $activeDays++;
            }

            $rows[] = $row;
            $cursor->addDay();
            $number++;
        }

        foreach ($modules as $module) {
            $key = $module['key'];
            $totals[$key.'_by_currency'] = $this->idrTotals($totals[$key]);
        }

        $totals['jumlah_by_currency'] = $this->idrTotals($totals['jumlah']);

        return [
            'title' => 'PENGELUARAN HARIAN BULAN '.$this->monthName($month).' '.$year,
            'bulan_name' => $this->monthName($month),
            'data' => $rows,
            'totals' => $totals,
            'stats' => [
                'total' => $totals['jumlah'],
                'hari_transaksi' => $activeDays,
                'kategori' => count($modules),
                'rata_rata_harian' => $activeDays > 0
                    ? (int) round($totals['jumlah'] / $activeDays)
                    : 0,
            ],
        ];
    }

    private function yearStats(array $allData, int $categoryCount): array
    {
        $total = 0;
        $activeDays = 0;

        foreach ($allData as $monthData) {
            $total += (int) data_get($monthData, 'totals.jumlah', 0);
            $activeDays += (int) data_get($monthData, 'stats.hari_transaksi', 0);
        }

        return [
            'total' => $total,
            'hari_transaksi' => $activeDays,
            'kategori' => $categoryCount,
            'rata_rata_harian' => $activeDays > 0
                ? (int) round($total / $activeDays)
                : 0,
        ];
    }

    private function filterOptions(array $modules, Request $request): array
    {
        $paymentTypes = collect();

        foreach ($modules as $module) {
            $table = $module['table'];

            if (! $this->usableTable($table)) {
                continue;
            }

            $query = DB::table($table)
                ->whereNotNull('jenis_pembayaran')
                ->where('jenis_pembayaran', '<>', '');

            $this->applyPetugasFilters($query, $table, $request);

            $paymentTypes = $paymentTypes->merge(
                $query->distinct()->pluck('jenis_pembayaran')
            );
        }

        return [
            'jenis_pembayaran' => $paymentTypes
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique(fn ($value) => strtolower($value))
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
        ];
    }

    private function applyPetugasFilters($query, string $table, Request $request): void
    {
        if ($request->filled('petugas_id')) {
            $query->where("{$table}.petugas_id", $request->integer('petugas_id'));
        }

        if ($request->filled('jenis_kelamin')) {
            $gender = $request->input('jenis_kelamin');

            $query->whereExists(function ($userQuery) use ($table, $gender) {
                $userQuery
                    ->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', "{$table}.petugas_id")
                    ->where('users.jenis_kelamin', $gender);
            });
        }
    }

    private function usableTable(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'tanggal')
            && Schema::hasColumn($table, 'total')
            && Schema::hasColumn($table, 'jenis_pembayaran')
            && Schema::hasColumn($table, 'petugas_id');
    }

    private function idrTotals(int $amount): array
    {
        if ($amount === 0) {
            return [];
        }

        return [[
            'key' => 'kode:IDR',
            'mata_uang' => [
                'id' => null,
                'kode' => 'IDR',
                'nama' => 'Rupiah',
                'simbol' => 'Rp',
            ],
            'total' => $amount,
        ]];
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'JANUARI',
            2 => 'FEBRUARI',
            3 => 'MARET',
            4 => 'APRIL',
            5 => 'MEI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AGUSTUS',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DESEMBER',
        ][$month] ?? '';
    }
}
