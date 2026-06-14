<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\BarokahPegawaiExport;
use App\Exports\PegawaiExport;
use App\Http\Controllers\Controller;
use App\Models\Dosen as DosenModel;
use App\Models\Pegawai;
use App\Models\Prodi;
use App\Models\Staff as StaffModel;
use App\Services\Absensi;
use App\Services\Dosen as SiakadDosen;
use App\Services\Helper;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $query = Pegawai::with(['dosen.prodi', 'staff']);

        $this->applyFilters($query, $request);

        $sortable = ['id', 'nama', 'kode', 'tipe', 'jenis_kelamin', 'status', 'created_at', 'updated_at'];
        $sortKey = in_array($request->input('sort_key'), $sortable) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        $limit = (int) $request->get('limit', 10);
        if ($limit === 0) {
            $items = $query->get();
            $data = [
                'current_page' => 1,
                'data' => $items,
                'first_page_url' => null,
                'from' => $items->isEmpty() ? null : 1,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $items->count(),
                'prev_page_url' => null,
                'to' => $items->count(),
                'total' => $items->count(),
            ];
        } else {
            $data = $query->paginate($limit);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $this->stats($request),
            'message' => 'Pegawai retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        [$validator, $payload] = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $pegawai = DB::transaction(function () use ($payload) {
            $pegawai = Pegawai::create($this->pegawaiPayload($payload));
            $this->syncDetail($pegawai, $payload);

            return $pegawai->load(['dosen.prodi', 'staff']);
        });

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai created successfully',
        ], 201);
    }

    public function show($id)
    {
        $pegawai = $this->scopedPegawaiQuery(['dosen.prodi', 'staff'])->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai retrieved successfully',
        ]);
    }

    public function barokah(Request $request, $id)
    {
        $pegawai = $this->scopedPegawaiQuery(['dosen.prodi', 'staff'])->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'mode' => ['nullable', Rule::in(['bulan', 'rentang'])],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $period = $this->resolveBarokahPeriod($request);
        $modules = $this->barokahModulesForPegawai($pegawai);
        $periods = $this->barokahChartPeriods($period['start'], $period['end']);
        $periodTotals = collect($periods)->mapWithKeys(fn ($item) => [$item['key'] => 0])->all();
        $moduleSummaries = [];
        $chartSeries = [];
        $recentRows = collect();

        foreach ($modules as $module) {
            $monthlyRows = $this->barokahMonthlyRows(
                $module,
                (int) $pegawai->id,
                $period['start'],
                $period['end']
            )->keyBy('periode');

            $moduleTotal = 0;
            $moduleJumlah = 0;
            $seriesData = [];

            foreach ($periods as $chartPeriod) {
                $row = $monthlyRows->get($chartPeriod['key']);
                $value = (int) ($row->total ?? 0);
                $count = (int) ($row->jumlah ?? 0);

                $seriesData[] = $value;
                $moduleTotal += $value;
                $moduleJumlah += $count;
                $periodTotals[$chartPeriod['key']] += $value;
            }

            $moduleSummaries[] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'short_label' => $module['short_label'],
                'icon' => $module['icon'],
                'color' => $module['color'],
                'path' => $module['path'],
                'total' => $moduleTotal,
                'jumlah' => $moduleJumlah,
                'rata_rata' => $moduleJumlah > 0 ? (int) round($moduleTotal / $moduleJumlah) : 0,
            ];

            $chartSeries[] = [
                'name' => $module['short_label'],
                'data' => $seriesData,
            ];

            $recentRows = $recentRows->merge($this->barokahRecentRows(
                $module,
                (int) $pegawai->id,
                $period['start'],
                $period['end']
            ));
        }

        $total = array_sum(array_column($moduleSummaries, 'total'));
        $jumlah = array_sum(array_column($moduleSummaries, 'jumlah'));
        $topModule = collect($moduleSummaries)->sortByDesc('total')->first();
        $topPeriodKey = collect($periodTotals)->sortDesc()->keys()->first();
        $topPeriod = collect($periods)->firstWhere('key', $topPeriodKey);

        return response()->json([
            'status' => true,
            'data' => [
                'pegawai' => $pegawai,
                'filters' => [
                    'mode' => $period['mode'],
                    'bulan' => $period['bulan'],
                    'tanggal_mulai' => $period['start']->toDateString(),
                    'tanggal_akhir' => $period['end']->toDateString(),
                    'label' => $period['label'],
                ],
                'stats' => [
                    'total' => $total,
                    'jumlah' => $jumlah,
                    'rata_rata' => $jumlah > 0 ? (int) round($total / $jumlah) : 0,
                    'modul_terbesar' => $topModule,
                    'periode_terbesar' => [
                        'label' => $topPeriod['label'] ?? '-',
                        'total' => (int) ($topPeriodKey ? ($periodTotals[$topPeriodKey] ?? 0) : 0),
                    ],
                ],
                'modules' => $moduleSummaries,
                'charts' => [
                    'monthly' => [
                        'categories' => array_column($periods, 'label'),
                        'series' => $chartSeries,
                    ],
                    'distribution' => [
                        'labels' => array_column($moduleSummaries, 'short_label'),
                        'series' => array_map('intval', array_column($moduleSummaries, 'total')),
                    ],
                ],
                'recent' => $recentRows
                    ->sortByDesc('sort_key')
                    ->take(10)
                    ->values()
                    ->map(fn ($row) => Arr::except($row, ['sort_key']))
                    ->all(),
            ],
            'message' => 'Ringkasan barokah pegawai retrieved successfully',
        ]);
    }

    public function barokahReport(Request $request)
    {
        if (is_string($request->input('pegawai_ids'))) {
            $request->merge([
                'pegawai_ids' => collect(explode(',', $request->input('pegawai_ids')))
                    ->map(fn ($id) => trim($id))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'mode' => ['nullable', Rule::in(['bulan', 'rentang'])],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'pegawai_ids' => ['nullable', 'array'],
            'pegawai_ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $period = $this->resolveBarokahPeriod($request);
        $pegawaiQuery = Pegawai::query()->select(['id', 'nama', 'kode', 'tipe']);
        $this->applyFilters($pegawaiQuery, $request);

        $pegawaiCount = (clone $pegawaiQuery)->count();
        $types = (clone $pegawaiQuery)
            ->reorder()
            ->select('tipe')
            ->distinct()
            ->pluck('tipe')
            ->all();

        $requestedTableIds = collect($request->input('pegawai_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $tableIds = $requestedTableIds;

        $modules = $this->barokahReportModulesForTypes($types);
        $periods = $this->barokahChartPeriods($period['start'], $period['end']);
        $totalsByPegawai = [];
        $moduleSummaries = [];
        $chartSeries = [];

        foreach ($modules as $module) {
            if (! $this->barokahTableIsUsable($module['table'])) {
                continue;
            }

            $moduleTotals = ['total' => 0, 'jumlah' => 0];
            $table = $module['table'];

            $byPegawaiRows = $this->barokahReportBaseQuery(
                $module,
                clone $pegawaiQuery,
                $period['start'],
                $period['end']
            )
                ->selectRaw("{$table}.pegawai_id")
                ->selectRaw('filtered_pegawai.nama')
                ->selectRaw('filtered_pegawai.kode')
                ->selectRaw('filtered_pegawai.tipe')
                ->selectRaw('COUNT(*) as jumlah')
                ->selectRaw("COALESCE(SUM({$table}.total), 0) as total")
                ->groupBy("{$table}.pegawai_id", 'filtered_pegawai.nama', 'filtered_pegawai.kode', 'filtered_pegawai.tipe')
                ->get();

            foreach ($byPegawaiRows as $row) {
                $pegawaiId = (int) $row->pegawai_id;
                $rowTotal = (int) ($row->total ?? 0);
                $rowJumlah = (int) ($row->jumlah ?? 0);

                $totalsByPegawai[$pegawaiId] ??= [
                    'pegawai_id' => $pegawaiId,
                    'nama' => $row->nama,
                    'kode' => $row->kode,
                    'tipe' => $row->tipe,
                    'total' => 0,
                    'jumlah' => 0,
                    'modules' => [],
                ];
                $totalsByPegawai[$pegawaiId]['total'] += $rowTotal;
                $totalsByPegawai[$pegawaiId]['jumlah'] += $rowJumlah;
                $totalsByPegawai[$pegawaiId]['modules'][$module['key']] = [
                    'label' => $module['short_label'],
                    'total' => $rowTotal,
                    'jumlah' => $rowJumlah,
                ];

                $moduleTotals['total'] += $rowTotal;
                $moduleTotals['jumlah'] += $rowJumlah;
            }

            $monthlyRows = $this->barokahReportBaseQuery(
                $module,
                clone $pegawaiQuery,
                $period['start'],
                $period['end']
            )
                ->selectRaw("DATE_FORMAT({$table}.tanggal, '%Y-%m') as periode")
                ->selectRaw('COUNT(*) as jumlah')
                ->selectRaw("COALESCE(SUM({$table}.total), 0) as total")
                ->groupBy('periode')
                ->orderBy('periode')
                ->get()
                ->keyBy('periode');

            $chartSeries[] = [
                'name' => $module['short_label'],
                'data' => collect($periods)
                    ->map(fn ($item) => (int) ($monthlyRows->get($item['key'])->total ?? 0))
                    ->all(),
            ];

            $moduleSummaries[] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'short_label' => $module['short_label'],
                'icon' => $module['icon'],
                'color' => $module['color'],
                'path' => $module['path'],
                'total' => $moduleTotals['total'],
                'jumlah' => $moduleTotals['jumlah'],
            ];
        }

        $total = array_sum(array_column($totalsByPegawai, 'total'));
        $jumlah = array_sum(array_column($totalsByPegawai, 'jumlah'));
        $tableTotals = $tableIds
            ->mapWithKeys(function ($pegawaiId) use ($totalsByPegawai) {
                $row = $totalsByPegawai[$pegawaiId] ?? ['total' => 0, 'jumlah' => 0, 'modules' => []];

                return [$pegawaiId => [
                    'total' => (int) $row['total'],
                    'jumlah' => (int) $row['jumlah'],
                    'modules' => array_values($row['modules']),
                ]];
            })
            ->all();

        $pegawaiReportRows = collect($totalsByPegawai)
            ->map(function ($row) {
                return [
                    'pegawai_id' => $row['pegawai_id'],
                    'nama' => $row['nama'] ?? '-',
                    'kode' => $row['kode'] ?? '-',
                    'tipe' => $row['tipe'] ?? '-',
                    'total' => (int) $row['total'],
                    'jumlah' => (int) $row['jumlah'],
                    'modules' => array_values($row['modules']),
                ];
            });
        $topPegawai = $pegawaiReportRows
            ->sortByDesc('total')
            ->take(10)
            ->values()
            ->all();
        $allPegawai = (clone $pegawaiQuery)
            ->reorder()
            ->orderByRaw("CASE WHEN tipe = 'dosen' THEN 1 WHEN tipe = 'staff' THEN 2 ELSE 3 END")
            ->orderBy('nama')
            ->get()
            ->map(function (Pegawai $pegawai) use ($totalsByPegawai) {
                $row = $totalsByPegawai[$pegawai->id] ?? [
                    'total' => 0,
                    'jumlah' => 0,
                    'modules' => [],
                ];

                return [
                    'pegawai_id' => (int) $pegawai->id,
                    'nama' => $pegawai->nama ?: '-',
                    'kode' => $pegawai->kode ?: '-',
                    'tipe' => $pegawai->tipe ?: '-',
                    'total' => (int) $row['total'],
                    'jumlah' => (int) $row['jumlah'],
                    'modules' => array_values($row['modules']),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status' => true,
            'data' => [
                'filters' => [
                    'mode' => $period['mode'],
                    'bulan' => $period['bulan'],
                    'tanggal_mulai' => $period['start']->toDateString(),
                    'tanggal_akhir' => $period['end']->toDateString(),
                    'label' => $period['label'],
                ],
                'stats' => [
                    'total' => $total,
                    'jumlah' => $jumlah,
                    'pegawai' => $pegawaiCount,
                    'pegawai_dengan_barokah' => count($totalsByPegawai),
                    'rata_rata_transaksi' => $jumlah > 0 ? (int) round($total / $jumlah) : 0,
                    'rata_rata_pegawai' => count($totalsByPegawai) > 0 ? (int) round($total / count($totalsByPegawai)) : 0,
                ],
                'table_totals' => $tableTotals,
                'modules' => $moduleSummaries,
                'top_pegawai' => $topPegawai,
                'all_pegawai' => $allPegawai,
                'charts' => [
                    'monthly' => [
                        'categories' => array_column($periods, 'label'),
                        'series' => $chartSeries,
                    ],
                    'distribution' => [
                        'labels' => array_column($moduleSummaries, 'short_label'),
                        'series' => array_map('intval', array_column($moduleSummaries, 'total')),
                    ],
                ],
            ],
            'message' => 'Laporan barokah pegawai retrieved successfully',
        ]);
    }

    public function exportBarokahReportExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mode' => ['nullable', Rule::in(['bulan', 'rentang'])],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $period = $this->resolveBarokahPeriod($request);
        $pegawaiQuery = Pegawai::query()->select(['id', 'nama', 'kode', 'tipe']);
        $this->applyFilters($pegawaiQuery, $request);

        $types = (clone $pegawaiQuery)
            ->reorder()
            ->select('tipe')
            ->distinct()
            ->pluck('tipe')
            ->all();
        $modules = $this->barokahReportModulesForTypes($types);
        $pegawaiTotals = [];
        $moduleTotals = [];
        $sheets = [];

        foreach ($modules as $module) {
            if (! $this->barokahTableIsUsable($module['table'])) {
                continue;
            }

            $table = $module['table'];
            $detailRows = $this->barokahReportBaseQuery(
                $module,
                clone $pegawaiQuery,
                $period['start'],
                $period['end']
            )
                ->select([
                    ...$this->barokahExcelDetailColumns($module),
                    'filtered_pegawai.nama as pegawai_nama',
                    'filtered_pegawai.kode as pegawai_kode',
                    'filtered_pegawai.tipe as pegawai_tipe_filter',
                ])
                ->orderBy("{$table}.tanggal")
                ->orderBy('filtered_pegawai.nama')
                ->orderBy("{$table}.id")
                ->get();

            $moduleTotal = 0;
            $excelRows = [];

            foreach ($detailRows as $index => $row) {
                $rowTotal = (int) data_get($row, 'total', 0);
                $pegawaiId = (int) data_get($row, 'pegawai_id');
                $moduleTotal += $rowTotal;

                $pegawaiTotals[$pegawaiId] ??= [
                    'nama' => data_get($row, 'pegawai_nama', '-'),
                    'kode' => data_get($row, 'pegawai_kode', '-'),
                    'tipe' => data_get($row, 'pegawai_tipe_filter', '-'),
                    'jumlah' => 0,
                    'total' => 0,
                ];
                $pegawaiTotals[$pegawaiId]['jumlah']++;
                $pegawaiTotals[$pegawaiId]['total'] += $rowTotal;

                $excelRows[] = $this->barokahExcelDetailRow($module['key'], $row, $index + 1);
            }

            $moduleTotals[] = [
                'label' => $module['short_label'],
                'jumlah' => count($excelRows),
                'total' => $moduleTotal,
            ];
            $sheets[] = [
                'title' => $module['short_label'],
                'headings' => $this->barokahExcelDetailHeadings($module['key']),
                'rows' => $excelRows,
            ];
        }

        $summaryRows = [
            ['Periode', $period['label'], '-', '-', 0, 0],
            ['Filter', $this->barokahExcelFilterLabel($request), '-', '-', 0, 0],
        ];

        foreach ($moduleTotals as $moduleTotal) {
            $summaryRows[] = [
                'Modul',
                $moduleTotal['label'],
                '-',
                '-',
                $moduleTotal['jumlah'],
                $moduleTotal['total'],
            ];
        }

        foreach (collect($pegawaiTotals)->sortByDesc('total') as $pegawaiTotal) {
            $summaryRows[] = [
                'Pegawai',
                $pegawaiTotal['nama'],
                $pegawaiTotal['kode'],
                ucfirst((string) $pegawaiTotal['tipe']),
                $pegawaiTotal['jumlah'],
                $pegawaiTotal['total'],
            ];
        }

        array_unshift($sheets, [
            'title' => 'Ringkasan',
            'headings' => ['Kategori', 'Nama / Keterangan', 'Kode', 'Tipe', 'Jumlah Data', 'Total Barokah'],
            'rows' => $summaryRows,
        ]);

        return Excel::download(
            new BarokahPegawaiExport($sheets),
            $this->barokahExcelFileName($period)
        );
    }

    public function update(Request $request, $id)
    {
        $pegawai = $this->scopedPegawaiQuery(['dosen', 'staff'])->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        [$validator, $payload] = $this->validator($request, $pegawai);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $pegawai = DB::transaction(function () use ($pegawai, $payload) {
            $pegawai->fill($this->pegawaiPayload($payload));
            $pegawai->save();

            $this->syncDetail($pegawai, $payload);

            return $pegawai->load(['dosen.prodi', 'staff']);
        });

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $pegawai = $this->scopedPegawaiQuery()->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        $pegawai->delete();

        return response()->json([
            'status' => true,
            'message' => 'Pegawai deleted successfully',
        ]);
    }

    private function resolveBarokahPeriod(Request $request): array
    {
        $mode = $request->input('mode');
        if (! $mode) {
            $mode = $request->filled('tanggal_mulai') || $request->filled('tanggal_akhir')
                ? 'rentang'
                : 'bulan';
        }

        if ($mode === 'rentang') {
            $start = $request->filled('tanggal_mulai')
                ? Carbon::parse($request->tanggal_mulai)->startOfDay()
                : now()->startOfMonth();
            $end = $request->filled('tanggal_akhir')
                ? Carbon::parse($request->tanggal_akhir)->endOfDay()
                : now()->endOfDay();
            $bulan = null;
        } else {
            $bulan = $request->input('bulan') ?: now()->format('Y-m');
            $start = Carbon::createFromFormat('Y-m-d', "{$bulan}-01")->startOfDay();
            $end = $start->copy()->endOfMonth();
        }

        return [
            'mode' => $mode,
            'bulan' => $bulan,
            'start' => $start,
            'end' => $end,
            'label' => $mode === 'bulan'
                ? $this->barokahMonthLabel($start)
                : $this->barokahDateLabel($start).' - '.$this->barokahDateLabel($end),
        ];
    }

    private function barokahModulesForPegawai(Pegawai $pegawai): array
    {
        $modules = [];
        $tipe = strtolower((string) $pegawai->tipe);

        if ($tipe === 'dosen') {
            $modules[] = [
                'key' => 'tatapmuka',
                'label' => 'Barokah Dosen Tatapmuka',
                'short_label' => 'Tatap Muka',
                'table' => 'keuangan_pengeluaran_dosen',
                'icon' => 'ri-presentation-line',
                'color' => 'primary',
                'path' => '/admin/pengeluaran/dosen-tatapmuka',
            ];
        }

        if (in_array($tipe, ['dosen', 'staff'], true)) {
            $modules[] = [
                'key' => 'kegiatan',
                'label' => 'Barokah Pegawai Kegiatan',
                'short_label' => 'Kegiatan',
                'table' => 'keuangan_pengeluaran_dosen_kegiatan',
                'icon' => 'ri-calendar-event-line',
                'color' => 'success',
                'path' => '/admin/pengeluaran/dosen-kegiatan',
            ];

            $modules[] = [
                'key' => 'bulanan',
                'label' => 'Barokah Bulanan',
                'short_label' => 'Bulanan',
                'table' => 'keuangan_pengeluaran_pegawai_bulanan',
                'icon' => 'ri-wallet-3-line',
                'color' => 'info',
                'path' => '/admin/pengeluaran/bulanan',
            ];
        }

        return $modules;
    }

    private function barokahReportModulesForTypes(array $types): array
    {
        $types = collect($types)->map(fn ($type) => strtolower((string) $type))->unique();
        $modules = [];

        if ($types->contains('dosen')) {
            $modules[] = [
                'key' => 'tatapmuka',
                'label' => 'Barokah Dosen Tatapmuka',
                'short_label' => 'Tatap Muka',
                'table' => 'keuangan_pengeluaran_dosen',
                'icon' => 'ri-presentation-line',
                'color' => 'primary',
                'path' => '/admin/pengeluaran/dosen-tatapmuka',
            ];
        }

        if ($types->intersect(['dosen', 'staff'])->isNotEmpty()) {
            $modules[] = [
                'key' => 'kegiatan',
                'label' => 'Barokah Pegawai Kegiatan',
                'short_label' => 'Kegiatan',
                'table' => 'keuangan_pengeluaran_dosen_kegiatan',
                'icon' => 'ri-calendar-event-line',
                'color' => 'success',
                'path' => '/admin/pengeluaran/dosen-kegiatan',
            ];

            $modules[] = [
                'key' => 'bulanan',
                'label' => 'Barokah Bulanan',
                'short_label' => 'Bulanan',
                'table' => 'keuangan_pengeluaran_pegawai_bulanan',
                'icon' => 'ri-wallet-3-line',
                'color' => 'info',
                'path' => '/admin/pengeluaran/bulanan',
            ];
        }

        return $modules;
    }

    private function barokahChartPeriods(Carbon $start, Carbon $end): array
    {
        $periods = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $periods[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $this->barokahMonthLabel($cursor),
            ];

            $cursor->addMonth();
        }

        return $periods;
    }

    private function barokahMonthlyRows(array $module, int $pegawaiId, Carbon $start, Carbon $end)
    {
        $table = $module['table'];

        if (! $this->barokahTableIsUsable($table)) {
            return collect();
        }

        return $this->barokahBaseQuery($module, $pegawaiId, $start, $end)
            ->selectRaw("DATE_FORMAT({$table}.tanggal, '%Y-%m') as periode")
            ->selectRaw("COUNT(*) as jumlah")
            ->selectRaw("COALESCE(SUM({$table}.total), 0) as total")
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    }

    private function barokahRecentRows(array $module, int $pegawaiId, Carbon $start, Carbon $end)
    {
        $table = $module['table'];

        if (! $this->barokahTableIsUsable($table)) {
            return collect();
        }

        $columns = $this->barokahRecentColumns($module);
        if (! $columns) {
            return collect();
        }

        return $this->barokahBaseQuery($module, $pegawaiId, $start, $end)
            ->select($columns)
            ->orderByDesc("{$table}.tanggal")
            ->orderByDesc("{$table}.id")
            ->limit(10)
            ->get()
            ->map(fn ($row) => $this->barokahRecentItem($module, $row));
    }

    private function barokahBaseQuery(array $module, int $pegawaiId, Carbon $start, Carbon $end)
    {
        $table = $module['table'];
        $query = DB::table($table)
            ->where("{$table}.pegawai_id", $pegawaiId)
            ->whereBetween("{$table}.tanggal", [
                $start->toDateString(),
                $end->toDateString(),
            ]);
        Helper::applyExpenseGenderScope($query, $table);

        if ($module['key'] === 'kegiatan' && Schema::hasColumn($table, 'kategori_detail')) {
            $query->where("{$table}.kategori_detail", 'pegawai');
        }

        return $query;
    }

    private function barokahReportBaseQuery(array $module, $pegawaiQuery, Carbon $start, Carbon $end)
    {
        $table = $module['table'];

        if (! $this->barokahTableIsUsable($table)) {
            return DB::table($table)->whereRaw('1 = 0');
        }

        $query = DB::table($table)
            ->joinSub($pegawaiQuery, 'filtered_pegawai', function ($join) use ($table) {
                $join->on("{$table}.pegawai_id", '=', 'filtered_pegawai.id');
            })
            ->whereBetween("{$table}.tanggal", [
                $start->toDateString(),
                $end->toDateString(),
            ]);
        Helper::applyExpenseGenderScope($query, $table);

        if ($module['key'] === 'tatapmuka') {
            $query->where('filtered_pegawai.tipe', 'dosen');
        } else {
            $query->whereIn('filtered_pegawai.tipe', ['dosen', 'staff']);
        }

        if ($module['key'] === 'kegiatan' && Schema::hasColumn($table, 'kategori_detail')) {
            $query->where("{$table}.kategori_detail", 'pegawai');
        }

        return $query;
    }

    private function barokahTableIsUsable(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'id')
            && Schema::hasColumn($table, 'pegawai_id')
            && Schema::hasColumn($table, 'tanggal')
            && Schema::hasColumn($table, 'total');
    }

    private function barokahRecentColumns(array $module): array
    {
        $table = $module['table'];
        $columns = [
            'tatapmuka' => [
                'id',
                'tanggal',
                'jam',
                'jam_mengajar_double_degree',
                'barokah_mengajar_biasa',
                'barokah_mengajar_double_degree',
                'barokah_uas',
                'jumlah_mahasiswa_uas',
                'barokah_sempro',
                'jam_sempro',
                'transport',
                'total',
                'jenis_pembayaran',
                'keterangan',
            ],
            'kegiatan' => [
                'id',
                'tanggal',
                'nama_kegiatan',
                'transport',
                'barokah',
                'total',
                'jenis_pembayaran',
                'keterangan',
            ],
            'bulanan' => [
                'id',
                'tanggal',
                'bulan',
                'tahun',
                'barokah_harian',
                'barokah_bulanan',
                'barokah_dosen_tetap',
                'barokah_struktural',
                'total',
                'jenis_pembayaran',
                'keterangan',
            ],
        ][$module['key']] ?? ['id', 'tanggal', 'total'];

        return collect($columns)
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->map(fn ($column) => "{$table}.{$column}")
            ->values()
            ->all();
    }

    private function barokahRecentItem(array $module, $row): array
    {
        $tanggal = (string) data_get($row, 'tanggal');

        return [
            'id' => (int) data_get($row, 'id'),
            'module_key' => $module['key'],
            'module_label' => $module['short_label'],
            'module_color' => $module['color'],
            'module_icon' => $module['icon'],
            'tanggal' => $tanggal,
            'tanggal_label' => $tanggal ? $this->barokahDateLabel(Carbon::parse($tanggal)) : '-',
            'deskripsi' => $this->barokahRecentDescription($module['key'], $row),
            'meta' => $this->barokahRecentMeta($module['key'], $row),
            'total' => (int) data_get($row, 'total', 0),
            'path' => $module['path'],
            'sort_key' => $tanggal.'-'.str_pad((string) data_get($row, 'id', 0), 12, '0', STR_PAD_LEFT),
        ];
    }

    private function barokahRecentDescription(string $moduleKey, $row): string
    {
        if ($moduleKey === 'tatapmuka') {
            $jam = (float) data_get($row, 'jam', 0);
            $jamDouble = (float) data_get($row, 'jam_mengajar_double_degree', 0);

            return trim(($jam + $jamDouble).' jam mengajar');
        }

        if ($moduleKey === 'kegiatan') {
            return (string) (data_get($row, 'nama_kegiatan') ?: 'Kegiatan pegawai');
        }

        $bulan = data_get($row, 'bulan');
        $tahun = data_get($row, 'tahun');

        if ($bulan && $tahun) {
            return 'Barokah bulanan '.$this->barokahMonthName((int) $bulan).' '.$tahun;
        }

        return 'Barokah bulanan';
    }

    private function barokahRecentMeta(string $moduleKey, $row): array
    {
        if ($moduleKey === 'tatapmuka') {
            return array_values(array_filter([
                'Mengajar '.(float) data_get($row, 'jam', 0).' jam',
                (float) data_get($row, 'jam_mengajar_double_degree', 0) > 0
                    ? 'Double degree '.(float) data_get($row, 'jam_mengajar_double_degree', 0).' jam'
                    : null,
                (float) data_get($row, 'jumlah_mahasiswa_uas', 0) > 0
                    ? 'UAS '.(float) data_get($row, 'jumlah_mahasiswa_uas', 0).' mhs'
                    : null,
                (float) data_get($row, 'jam_sempro', 0) > 0
                    ? 'Sempro '.(float) data_get($row, 'jam_sempro', 0).' jam'
                    : null,
            ]));
        }

        if ($moduleKey === 'kegiatan') {
            return array_values(array_filter([
                'Transport '.number_format((int) data_get($row, 'transport', 0), 0, ',', '.'),
                'Barokah '.number_format((int) data_get($row, 'barokah', 0), 0, ',', '.'),
            ]));
        }

        return array_values(array_filter([
            (int) data_get($row, 'barokah_dosen_tetap', 0) > 0
                ? 'Tetap '.number_format((int) data_get($row, 'barokah_dosen_tetap', 0), 0, ',', '.')
                : null,
            (int) data_get($row, 'barokah_struktural', 0) > 0
                ? 'Struktural '.number_format((int) data_get($row, 'barokah_struktural', 0), 0, ',', '.')
                : null,
            (int) data_get($row, 'barokah_bulanan', 0) > 0
                ? 'Bulanan '.number_format((int) data_get($row, 'barokah_bulanan', 0), 0, ',', '.')
                : null,
        ]));
    }

    private function barokahMonthLabel(Carbon $date): string
    {
        return $this->barokahMonthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function barokahDateLabel(Carbon $date): string
    {
        return $date->format('d').' '.$this->barokahMonthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function barokahMonthName(int $month): string
    {
        return [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ][$month] ?? '';
    }

    private function barokahExcelDetailHeadings(string $moduleKey): array
    {
        return [
            'tatapmuka' => [
                'No',
                'Tanggal',
                'Nama Pegawai',
                'Kode',
                'Tipe',
                'Jam Mengajar',
                'Jam Double Degree',
                'Hari',
                'Hari Transport Motor',
                'Hari Transport Mobil',
                'Transport',
                'Transport Motor',
                'Transport Mobil',
                'Transport Mobil Tol',
                'Transport Mobil Tanpa Tol',
                'Barokah Mengajar',
                'Barokah Double Degree',
                'Barokah UAS',
                'Jumlah Mahasiswa UAS',
                'Jam Sempro',
                'Barokah Sempro',
                'Total',
                'Jenis Pembayaran',
                'Keterangan Sempro',
                'Keterangan',
            ],
            'kegiatan' => [
                'No',
                'Tanggal',
                'Nama Pegawai',
                'Kode',
                'Tipe',
                'Nama Kegiatan',
                'Transport',
                'Barokah',
                'Nominal',
                'Total',
                'Jenis Pembayaran',
                'Keterangan',
            ],
            'bulanan' => [
                'No',
                'Tanggal',
                'Periode',
                'Nama Pegawai',
                'Kode',
                'Tipe',
                'Hari',
                'Barokah Harian',
                'Barokah Bulanan',
                'Barokah Dosen Tetap',
                'Barokah Struktural',
                'Total',
                'Jenis Pembayaran',
                'Keterangan',
            ],
        ][$moduleKey] ?? ['No', 'Tanggal', 'Nama Pegawai', 'Kode', 'Tipe', 'Total'];
    }

    private function barokahExcelDetailColumns(array $module): array
    {
        $table = $module['table'];
        $columns = [
            'tatapmuka' => [
                'pegawai_id',
                'tanggal',
                'jam',
                'jam_mengajar_double_degree',
                'hari',
                'hari_transport_motor',
                'hari_transport_mobil',
                'transport',
                'transport_motor',
                'transport_mobil',
                'transport_mobil_tol',
                'transport_mobil_tanpa_tol',
                'barokah_mengajar_biasa',
                'barokah_mengajar_double_degree',
                'barokah_uas',
                'jumlah_mahasiswa_uas',
                'jam_sempro',
                'barokah_sempro',
                'total',
                'jenis_pembayaran',
                'keterangan_sempro',
                'keterangan',
            ],
            'kegiatan' => [
                'pegawai_id',
                'tanggal',
                'nama_kegiatan',
                'transport',
                'barokah',
                'nominal',
                'total',
                'jenis_pembayaran',
                'keterangan',
            ],
            'bulanan' => [
                'pegawai_id',
                'tanggal',
                'bulan',
                'tahun',
                'hari',
                'barokah_harian',
                'barokah_bulanan',
                'barokah_dosen_tetap',
                'barokah_struktural',
                'total',
                'jenis_pembayaran',
                'keterangan',
            ],
        ][$module['key']] ?? ['pegawai_id', 'tanggal', 'total'];

        return collect($columns)
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->map(fn ($column) => "{$table}.{$column}")
            ->values()
            ->all();
    }

    private function barokahExcelDetailRow(string $moduleKey, $row, int $number): array
    {
        $identity = [
            data_get($row, 'pegawai_nama', '-'),
            data_get($row, 'pegawai_kode', '-'),
            ucfirst((string) data_get($row, 'pegawai_tipe_filter', '-')),
        ];

        if ($moduleKey === 'tatapmuka') {
            return [
                $number,
                (string) data_get($row, 'tanggal', ''),
                ...$identity,
                (float) data_get($row, 'jam', 0),
                (float) data_get($row, 'jam_mengajar_double_degree', 0),
                (float) data_get($row, 'hari', 0),
                (float) data_get($row, 'hari_transport_motor', 0),
                (float) data_get($row, 'hari_transport_mobil', 0),
                (int) data_get($row, 'transport', 0),
                (int) data_get($row, 'transport_motor', 0),
                (int) data_get($row, 'transport_mobil', 0),
                (int) data_get($row, 'transport_mobil_tol', 0),
                (int) data_get($row, 'transport_mobil_tanpa_tol', 0),
                (int) data_get($row, 'barokah_mengajar_biasa', 0),
                (int) data_get($row, 'barokah_mengajar_double_degree', 0),
                (int) data_get($row, 'barokah_uas', 0),
                (float) data_get($row, 'jumlah_mahasiswa_uas', 0),
                (float) data_get($row, 'jam_sempro', 0),
                (int) data_get($row, 'barokah_sempro', 0),
                (int) data_get($row, 'total', 0),
                data_get($row, 'jenis_pembayaran', '-'),
                data_get($row, 'keterangan_sempro', '-'),
                data_get($row, 'keterangan', '-'),
            ];
        }

        if ($moduleKey === 'kegiatan') {
            return [
                $number,
                (string) data_get($row, 'tanggal', ''),
                ...$identity,
                data_get($row, 'nama_kegiatan', '-'),
                (int) data_get($row, 'transport', 0),
                (int) data_get($row, 'barokah', 0),
                (int) data_get($row, 'nominal', 0),
                (int) data_get($row, 'total', 0),
                data_get($row, 'jenis_pembayaran', '-'),
                data_get($row, 'keterangan', '-'),
            ];
        }

        $bulan = (int) data_get($row, 'bulan', 0);
        $tahun = (int) data_get($row, 'tahun', 0);
        $periode = $bulan && $tahun ? $this->barokahMonthName($bulan).' '.$tahun : '-';

        return [
            $number,
            (string) data_get($row, 'tanggal', ''),
            $periode,
            ...$identity,
            (float) data_get($row, 'hari', 0),
            (int) data_get($row, 'barokah_harian', 0),
            (int) data_get($row, 'barokah_bulanan', 0),
            (int) data_get($row, 'barokah_dosen_tetap', 0),
            (int) data_get($row, 'barokah_struktural', 0),
            (int) data_get($row, 'total', 0),
            data_get($row, 'jenis_pembayaran', '-'),
            data_get($row, 'keterangan', '-'),
        ];
    }

    private function barokahExcelFilterLabel(Request $request): string
    {
        $prodi = $request->filled('prodi_id')
            ? Prodi::query()->find($request->input('prodi_id'))
            : null;

        return collect([
            $request->filled('search') ? 'Pencarian: '.$request->input('search') : null,
            $request->filled('tipe') ? 'Tipe: '.ucfirst((string) $request->input('tipe')) : null,
            $request->filled('jenis_kelamin') ? 'Jenis kelamin: '.$request->input('jenis_kelamin') : null,
            $request->filled('status') ? 'Status: '.ucfirst((string) $request->input('status')) : null,
            $request->filled('prodi_id') ? 'Prodi: '.($prodi?->nama ?? $request->input('prodi_id')) : null,
        ])->filter()->implode(' | ') ?: 'Semua pegawai';
    }

    private function barokahExcelFileName(array $period): string
    {
        $label = preg_replace('/[^\pL\pN _-]+/u', '-', $period['label']);

        return "Laporan Barokah Pegawai {$label}.xlsx";
    }

    public function exportExcel(Request $request)
    {
        $query = Pegawai::with(['dosen.prodi', 'staff']);
        $this->applyFilters($query, $request);

        $sortable = ['id', 'nama', 'kode', 'tipe', 'jenis_kelamin', 'status', 'created_at', 'updated_at'];
        $sortKey = in_array($request->input('sort_key'), $sortable, true) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $data = $query->orderBy($sortKey, $sortOrder)->get();

        return Excel::download(new PegawaiExport($data, $request->input('tipe')), $this->pegawaiExportFileName($request));
    }

    public function importExcel(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'tipe' => ['nullable', Rule::in(['dosen', 'staff'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $fixedTipe = $request->input('tipe');
        $rows = $this->pegawaiImportRows($request->file('file'));

        if (! $rows) {
            return response()->json([
                'status' => false,
                'message' => 'File import tidak memiliki data.',
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $payload = $this->pegawaiImportPayload($row['data'], $fixedTipe);

            if (! $payload) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: kolom nama, kode, dan tipe wajib diisi.";
                continue;
            }

            if ($fixedTipe && $payload['tipe'] !== $fixedTipe) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: tipe harus {$fixedTipe}.";
                continue;
            }

            $pegawai = $this->findPegawaiForImport($row['data'], $payload['kode']);
            [$rowValidator] = $this->validator(new Request($payload), $pegawai);

            if ($rowValidator->fails()) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: " . collect($rowValidator->errors()->all())->implode('; ');
                continue;
            }

            $payload = $rowValidator->validated();
            $wasUpdate = (bool) $pegawai;

            try {
                DB::transaction(function () use ($pegawai, $payload) {
                    if ($pegawai) {
                        $pegawai->fill($this->pegawaiPayload($payload));
                        $pegawai->save();
                    } else {
                        $pegawai = Pegawai::create($this->pegawaiPayload($payload));
                    }

                    $this->syncDetail($pegawai, $payload);
                });

                if ($wasUpdate) {
                    $updated++;
                } else {
                    $created++;
                }
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: gagal disimpan ({$exception->getMessage()}).";
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 20),
                'error_count' => count($errors),
            ],
            'message' => "Import selesai. {$created} data baru, {$updated} data diperbarui, {$skipped} dilewati.",
        ]);
    }

    public function syncDosenSiakad(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $batchSize = max(50, min((int) $request->input('batch_size', 200), 1000));
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        $dosenSiakad = $this->siakadDosenSources();

        if ($ids->isNotEmpty()) {
            $selected = array_fill_keys($ids->all(), true);
            $dosenSiakad = $dosenSiakad->filter(function ($source) use ($selected) {
                $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);

                return $kode && isset($selected[(string) $kode]);
            })->values();
        }

        if ($dosenSiakad->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Data dosen SIAKAD kosong atau tidak dapat diambil.',
            ], 422);
        }

        $prodiMap = $this->prodiMap();
        $seenKode = [];
        $seenNidn = [];
        $seenEmail = [];
        $existingNidn = DosenModel::whereNotNull('nidn')
            ->where('nidn', '!=', '')
            ->pluck('kode', 'nidn')
            ->all();
        $existingEmail = Pegawai::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['kode', 'email'])
            ->mapWithKeys(fn ($pegawai) => [mb_strtolower($pegawai->email) => $pegawai->kode])
            ->all();

        $rows = [];
        $skipped = 0;

        foreach ($dosenSiakad as $source) {
            $mapped = $this->mapSiakadDosen($source, $prodiMap);

            if (! $mapped) {
                $skipped++;
                continue;
            }

            $kode = $mapped['pegawai']['kode'];
            if (isset($seenKode[$kode])) {
                $skipped++;
                continue;
            }

            $nidn = $mapped['dosen']['nidn'];
            if ($nidn && (isset($seenNidn[$nidn]) || (isset($existingNidn[$nidn]) && $existingNidn[$nidn] !== $kode))) {
                $mapped['dosen']['nidn'] = null;
            }

            if ($mapped['dosen']['nidn']) {
                $seenNidn[$mapped['dosen']['nidn']] = true;
            }

            $emailKey = $mapped['pegawai']['email'] ? mb_strtolower($mapped['pegawai']['email']) : null;
            if ($emailKey && (isset($seenEmail[$emailKey]) || (isset($existingEmail[$emailKey]) && $existingEmail[$emailKey] !== $kode))) {
                $mapped['pegawai']['email'] = null;
            }

            if ($mapped['pegawai']['email']) {
                $seenEmail[mb_strtolower($mapped['pegawai']['email'])] = true;
            }

            $seenKode[$kode] = true;
            $rows[] = $mapped;
        }

        $created = 0;
        $updated = 0;
        $synced = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$created, &$updated, &$synced) {
                $now = now();
                $codes = array_column(array_column($chunk, 'pegawai'), 'kode');
                $existingPegawai = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');

                $pegawaiRows = array_map(function ($row) use ($now) {
                    return array_merge($this->pegawaiPayload($row['pegawai']), [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }, $chunk);

                Pegawai::upsert(
                    $pegawaiRows,
                    ['kode'],
                    $this->pegawaiUpsertUpdateColumns()
                );

                $pegawaiIds = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');
                DB::table('staff')->whereIn('pegawai_id', $pegawaiIds->values())->delete();
                DosenModel::whereIn('kode', $codes)
                    ->whereNotIn('pegawai_id', $pegawaiIds->values())
                    ->delete();

                $dosenRows = [];
                foreach ($chunk as $row) {
                    $pegawaiId = $pegawaiIds[$row['pegawai']['kode']] ?? null;
                    if (! $pegawaiId) {
                        continue;
                    }

                    $dosenRows[] = array_merge($row['dosen'], [
                        'pegawai_id' => $pegawaiId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($dosenRows) {
                    DosenModel::upsert(
                        $dosenRows,
                        ['pegawai_id'],
                        [
                            'kode',
                            'nidn',
                            'gelar_depan',
                            'gelar_belakang',
                            'prodi_id',
                            'updated_at',
                        ]
                    );
                }

                $chunkCreated = collect($codes)->reject(fn ($kode) => $existingPegawai->has($kode))->count();
                $created += $chunkCreated;
                $updated += count($codes) - $chunkCreated;
                $synced += count($codes);
            });
        }

        return response()->json([
            'status' => true,
            'data' => [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'batch_size' => $batchSize,
            ],
            'message' => "Sinkronisasi dosen SIAKAD selesai. {$synced} data diproses.",
        ]);
    }

    public function previewDosenSiakad(Request $request)
    {
        try {
            $page = max((int) $request->input('page', 1), 1);
            $perPage = max(1, min((int) $request->input('per_page', $request->input('limit', 10)), 100));
            $sources = $this->filteredSiakadDosenSources($request);
            $total = $sources->count();
            $items = $sources
                ->forPage($page, $perPage)
                ->values();
            $codes = $items
                ->map(fn ($item) => $this->sourceValue($item, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']))
                ->filter()
                ->map(fn ($kode) => (string) $kode)
                ->unique()
                ->values();
            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->get(['id', 'kode', 'tipe'])
                ->keyBy('kode');

            return response()->json([
                'status' => true,
                'data' => [
                    'current_page' => $page,
                    'data' => $items
                        ->map(fn ($item) => $this->siakadDosenPreviewRow($item, $existing))
                        ->filter()
                        ->values()
                        ->all(),
                    'from' => $total ? (($page - 1) * $perPage) + 1 : null,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'to' => min($page * $perPage, $total),
                    'total' => $total,
                ],
                'message' => 'Data dosen SIAKAD berhasil diambil.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil data dosen SIAKAD.',
            ], 422);
        }
    }

    public function dosenSiakadIds(Request $request)
    {
        try {
            $sources = $this->filteredSiakadDosenSources($request);
            $codes = $sources
                ->map(fn ($item) => $this->sourceValue($item, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']))
                ->filter()
                ->map(fn ($kode) => (string) $kode)
                ->unique()
                ->values();
            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->pluck('tipe', 'kode');
            $skippedConflict = 0;
            $ids = [];

            foreach ($codes as $kode) {
                if ($existing->has($kode) && $existing[$kode] !== 'dosen') {
                    $skippedConflict++;
                    continue;
                }

                $ids[] = $kode;
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'ids' => $ids,
                    'total' => count($ids),
                    'skipped_conflict' => $skippedConflict,
                ],
                'message' => count($ids) . ' data dosen SIAKAD dipilih.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil ID dosen SIAKAD.',
            ], 422);
        }
    }

    public function previewStaffAbsensi(Request $request)
    {
        try {
            $page = max((int) $request->input('page', 1), 1);
            $perPage = max(1, min((int) $request->input('per_page', $request->input('limit', 10)), 100));
            $query = $this->staffAbsensiQuery($request, $page, $perPage);

            $payload = $this->absensiPaginatorPayload(Absensi::users($query), $page, $perPage);
            $codes = collect($payload['data'])
                ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->get(['id', 'kode', 'tipe'])
                ->keyBy('kode');

            $payload['data'] = collect($payload['data'])
                ->map(fn ($item) => $this->absensiPreviewRow($item, $existing))
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'status' => true,
                'data' => $payload,
                'message' => 'Data staff Web Absensi berhasil diambil.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil data staff Web Absensi.',
            ], 422);
        }
    }

    public function staffAbsensiIds(Request $request)
    {
        try {
            $ids = [];
            $page = 1;
            $perPage = 100;
            $skippedConflict = 0;
            $maxPages = 500;
            $seenPageSignatures = [];

            do {
                $payload = $this->absensiPaginatorPayload(
                    Absensi::users($this->staffAbsensiQuery($request, $page, $perPage)),
                    $page,
                    $perPage
                );
                $items = collect($payload['data']);

                if ($items->isEmpty()) {
                    break;
                }

                $signature = $this->absensiItemsSignature($items);
                if ($signature && isset($seenPageSignatures[$signature])) {
                    break;
                }

                if ($signature) {
                    $seenPageSignatures[$signature] = true;
                }

                $codes = $items
                    ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();
                $existing = Pegawai::query()
                    ->whereIn('kode', $codes)
                    ->pluck('tipe', 'kode');

                foreach ($codes as $kode) {
                    if ($existing->has($kode) && $existing[$kode] !== 'staff') {
                        $skippedConflict++;
                        continue;
                    }

                    $ids[$kode] = true;
                }

                $page++;
            } while ($page <= (int) $payload['last_page'] && $page <= $maxPages);

            $ids = array_keys($ids);

            return response()->json([
                'status' => true,
                'data' => [
                    'ids' => $ids,
                    'total' => count($ids),
                    'skipped_conflict' => $skippedConflict,
                ],
                'message' => count($ids) . ' data staff Web Absensi dipilih.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil ID staff Web Absensi.',
            ], 422);
        }
    }

    public function syncStaffAbsensi(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'batch_size' => ['nullable', 'integer', 'min:50', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $batchSize = max(50, min((int) $request->input('batch_size', 200), 1000));
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Tidak ada data staff Web Absensi yang dipilih.',
            ], 422);
        }

        $existingTipe = Pegawai::query()
            ->whereIn('kode', $ids)
            ->pluck('tipe', 'kode')
            ->all();
        $existingEmail = Pegawai::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['kode', 'email'])
            ->mapWithKeys(fn ($pegawai) => [mb_strtolower($pegawai->email) => $pegawai->kode])
            ->all();

        [$sources, $failedFetches] = $this->absensiStaffSourcesForSync($ids->all());
        $rows = [];
        $seenKode = [];
        $seenEmail = [];
        $skipped = $failedFetches;

        foreach ($sources as $source) {
            $departemenId = $this->sourceValue($source, ['departemen_id', 'department_id', 'departemen.id', 'department.id']);
            if ($departemenId && $departemenId !== '2') {
                $skipped++;
                continue;
            }

            $mapped = $this->mapAbsensiStaff($source);
            if (! $mapped) {
                $skipped++;
                continue;
            }

            $kode = $mapped['pegawai']['kode'];
            if (isset($seenKode[$kode])) {
                $skipped++;
                continue;
            }

            if (isset($existingTipe[$kode]) && $existingTipe[$kode] !== 'staff') {
                $skipped++;
                continue;
            }

            $emailKey = $mapped['pegawai']['email'] ? mb_strtolower($mapped['pegawai']['email']) : null;
            if ($emailKey && (isset($seenEmail[$emailKey]) || (isset($existingEmail[$emailKey]) && $existingEmail[$emailKey] !== $kode))) {
                $mapped['pegawai']['email'] = null;
            }

            if ($mapped['pegawai']['email']) {
                $seenEmail[mb_strtolower($mapped['pegawai']['email'])] = true;
            }

            $seenKode[$kode] = true;
            $rows[] = $mapped;
        }

        if (! $rows) {
            return response()->json([
                'status' => false,
                'data' => [
                    'synced' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => $skipped,
                    'failed_fetches' => $failedFetches,
                    'batch_size' => $batchSize,
                ],
                'message' => 'Tidak ada data staff Web Absensi yang dapat disinkronkan.',
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $synced = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$created, &$updated, &$synced) {
                $now = now();
                $codes = array_column(array_column($chunk, 'pegawai'), 'kode');
                $existingPegawai = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');

                $pegawaiRows = array_map(function ($row) use ($now) {
                    return array_merge($this->pegawaiPayload($row['pegawai']), [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }, $chunk);

                Pegawai::upsert(
                    $pegawaiRows,
                    ['kode'],
                    $this->pegawaiUpsertUpdateColumns()
                );

                $pegawaiIds = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');
                DosenModel::whereIn('pegawai_id', $pegawaiIds->values())->delete();

                $staffRows = [];
                foreach ($chunk as $row) {
                    $pegawaiId = $pegawaiIds[$row['pegawai']['kode']] ?? null;
                    if (! $pegawaiId) {
                        continue;
                    }

                    $staffRows[] = array_merge($row['staff'], [
                        'pegawai_id' => $pegawaiId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($staffRows) {
                    StaffModel::upsert(
                        $staffRows,
                        ['pegawai_id'],
                        [
                            'jabatan',
                            'updated_at',
                        ]
                    );
                }

                $chunkCreated = collect($codes)->reject(fn ($kode) => $existingPegawai->has($kode))->count();
                $created += $chunkCreated;
                $updated += count($codes) - $chunkCreated;
                $synced += count($codes);
            });
        }

        return response()->json([
            'status' => true,
            'data' => [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed_fetches' => $failedFetches,
                'batch_size' => $batchSize,
            ],
            'message' => "Sinkronisasi staff Web Absensi selesai. {$synced} data diproses.",
        ]);
    }

    private function pegawaiExportFileName(Request $request): string
    {
        return match ($request->input('tipe')) {
            'dosen' => 'Data Dosen.xlsx',
            'staff' => 'Data Staff.xlsx',
            default => 'Data Pegawai.xlsx',
        };
    }

    private function pegawaiImportRows($file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);

        if (! $rawRows) {
            return [];
        }

        $headings = array_shift($rawRows);
        $columnKeys = [];

        foreach ($headings as $column => $heading) {
            $key = $this->normalizeImportHeading($heading);
            if ($key) {
                $columnKeys[$column] = $key;
            }
        }

        if (! $columnKeys) {
            return [];
        }

        $rows = [];
        $rowNumber = 2;

        foreach ($rawRows as $rawRow) {
            $row = [];

            foreach ($columnKeys as $column => $key) {
                $row[$key] = $rawRow[$column] ?? null;
            }

            if (! $this->isImportRowEmpty($row)) {
                $rows[] = [
                    'number' => $rowNumber,
                    'data' => $row,
                ];
            }

            $rowNumber++;
        }

        return $rows;
    }

    private function pegawaiImportPayload(array $row, ?string $fixedTipe): ?array
    {
        $tipe = $this->normalizeTipe($this->importValue($row, ['tipe', 'type'])) ?: $fixedTipe;
        $kode = $this->importValue($row, ['kode', 'kode_pegawai', 'niy', 'nip']);
        $nama = $this->importValue($row, ['nama', 'nama_pegawai', 'name']);

        if (! $tipe || ! $kode || ! $nama) {
            return null;
        }

        $payload = [
            'nama' => $nama,
            'jenis_kelamin' => $this->normalizeJenisKelamin($this->importValue($row, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
            'tipe' => $tipe,
            'kode' => $kode,
            'tempat_lahir' => $this->nullableImportValue($row, ['tempat_lahir', 'tmp_lahir']),
            'tanggal_lahir' => $this->normalizeImportDate($this->importValue($row, ['tanggal_lahir', 'tgl_lahir'])),
            'alamat' => $this->nullableImportValue($row, ['alamat', 'alamat_lengkap']),
            'email' => $this->normalizeEmail($this->importValue($row, ['email'])),
            'hp' => $this->nullableImportValue($row, ['hp', 'no_hp', 'telepon', 'telp']),
            'nomer_rekening' => $this->nullableImportValue($row, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
            'nama_pemilik_rekening' => $this->nullableImportValue($row, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
            'bank' => $this->nullableImportValue($row, ['bank', 'nama_bank']),
            'status' => $this->normalizeStatus($this->importValue($row, ['status', 'aktif'])),
        ];

        if ($tipe === 'dosen') {
            $payload += [
                'dosen_kode' => $this->nullableImportValue($row, ['dosen_kode', 'kode_dosen']) ?: $kode,
                'nidn' => $this->nullableImportValue($row, ['nidn']),
                'gelar_depan' => $this->nullableImportValue($row, ['gelar_depan', 'gelar_dpn']),
                'gelar_belakang' => $this->nullableImportValue($row, ['gelar_belakang', 'gelar_blk', 'gelar']),
                'prodi_id' => $this->resolveImportProdiId($row),
            ];
        }

        if ($tipe === 'staff') {
            $payload['jabatan'] = $this->nullableImportValue($row, ['jabatan', 'position', 'posisi']);
        }

        return $payload;
    }

    private function findPegawaiForImport(array $row, string $kode): ?Pegawai
    {
        $id = $this->importValue($row, ['id', 'pegawai_id', 'id_pegawai']);

        if (is_numeric($id)) {
            $pegawai = Pegawai::with(['dosen', 'staff'])->find((int) $id);
            if ($pegawai) {
                return $pegawai;
            }
        }

        return Pegawai::with(['dosen', 'staff'])->where('kode', $kode)->first();
    }

    private function resolveImportProdiId(array $row): ?int
    {
        $prodiId = $this->importValue($row, ['prodi_id', 'id_prodi']);
        if (is_numeric($prodiId)) {
            return (int) $prodiId;
        }

        $lookup = $this->normalizeLookupKey($this->importValue($row, ['prodi_kode', 'kode_prodi', 'prodi_nama', 'nama_prodi', 'prodi']));
        if (! $lookup) {
            return null;
        }

        return $this->prodiMap()[$lookup] ?? null;
    }

    private function normalizeImportHeading($heading): ?string
    {
        $normalized = mb_strtolower(trim((string) $heading));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);
        $normalized = trim($normalized, '_');

        $aliases = [
            'pegawai_id' => 'id',
            'id_pegawai' => 'id',
            'pegawai_tipe' => 'tipe',
            'pegawai_type' => 'tipe',
            'pegawai_kode' => 'kode',
            'kode_pegawai' => 'kode',
            'pegawai_nama' => 'nama',
            'nama_pegawai' => 'nama',
            'pegawai_jenis_kelamin' => 'jenis_kelamin',
            'pegawai_status' => 'status',
            'pegawai_tempat_lahir' => 'tempat_lahir',
            'pegawai_tanggal_lahir' => 'tanggal_lahir',
            'pegawai_alamat' => 'alamat',
            'pegawai_email' => 'email',
            'pegawai_hp' => 'hp',
            'pegawai_no_hp' => 'hp',
            'pegawai_telepon' => 'hp',
            'pegawai_telp' => 'hp',
            'pegawai_nomer_rekening' => 'nomer_rekening',
            'pegawai_nomor_rekening' => 'nomer_rekening',
            'pegawai_no_rekening' => 'nomer_rekening',
            'pegawai_rekening' => 'nomer_rekening',
            'nomor_rekening' => 'nomer_rekening',
            'no_rekening' => 'nomer_rekening',
            'rekening' => 'nomer_rekening',
            'pegawai_nama_pemilik_rekening' => 'nama_pemilik_rekening',
            'pegawai_nama_rekening' => 'nama_pemilik_rekening',
            'pegawai_atas_nama_rekening' => 'nama_pemilik_rekening',
            'pegawai_atas_nama' => 'nama_pemilik_rekening',
            'nama_rekening' => 'nama_pemilik_rekening',
            'atas_nama_rekening' => 'nama_pemilik_rekening',
            'atas_nama' => 'nama_pemilik_rekening',
            'pegawai_bank' => 'bank',
            'pegawai_nama_bank' => 'bank',
            'kode_dosen' => 'dosen_kode',
            'dosen_nidn' => 'nidn',
            'dosen_gelar_depan' => 'gelar_depan',
            'dosen_gelar_dpn' => 'gelar_depan',
            'dosen_gelar_belakang' => 'gelar_belakang',
            'dosen_gelar_blk' => 'gelar_belakang',
            'dosen_gelar' => 'gelar_belakang',
            'dosen_prodi_id' => 'prodi_id',
            'dosen_id_prodi' => 'prodi_id',
            'dosen_prodi_kode' => 'prodi_kode',
            'dosen_kode_prodi' => 'prodi_kode',
            'dosen_prodi_nama' => 'prodi_nama',
            'dosen_nama_prodi' => 'prodi_nama',
            'dosen_prodi' => 'prodi_nama',
            'kode_prodi' => 'prodi_kode',
            'nama_prodi' => 'prodi_nama',
            'prodi' => 'prodi_nama',
            'staff_jabatan' => 'jabatan',
            'jabatan_staff' => 'jabatan',
            'position' => 'jabatan',
            'posisi' => 'jabatan',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function importValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function nullableImportValue(array $row, array $keys): ?string
    {
        return $this->importValue($row, $keys) ?: null;
    }

    private function normalizeTipe(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['dosen', 'lecturer'], true)) {
            return 'dosen';
        }

        if (in_array($normalized, ['staff', 'staf', 'pegawai'], true)) {
            return 'staff';
        }

        return null;
    }

    private function normalizeImportDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->normalizeDate($value);
    }

    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));

            $query->where(function ($q) use ($term) {
                $q->where('nama', 'LIKE', "%{$term}%")
                    ->orWhere('kode', 'LIKE', "%{$term}%")
                    ->orWhere('tempat_lahir', 'LIKE', "%{$term}%")
                    ->orWhere('alamat', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%")
                    ->orWhere('hp', 'LIKE', "%{$term}%")
                    ->orWhere('nomer_rekening', 'LIKE', "%{$term}%")
                    ->orWhere('bank', 'LIKE', "%{$term}%")
                    ->orWhereHas('dosen', function ($dosen) use ($term) {
                        $dosen->where('kode', 'LIKE', "%{$term}%")
                            ->orWhere('nidn', 'LIKE', "%{$term}%")
                            ->orWhere('gelar_depan', 'LIKE', "%{$term}%")
                            ->orWhere('gelar_belakang', 'LIKE', "%{$term}%")
                            ->orWhereHas('prodi', function ($prodi) use ($term) {
                                $prodi->where('nama', 'LIKE', "%{$term}%")
                                    ->orWhere('kode', 'LIKE', "%{$term}%");
                            });
                    })
                    ->orWhereHas('staff', function ($staff) use ($term) {
                        $staff->where('jabatan', 'LIKE', "%{$term}%");
                    });

                if ($this->hasNamaPemilikRekeningColumn()) {
                    $q->orWhere('nama_pemilik_rekening', 'LIKE', "%{$term}%");
                }
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->input('tipe'));
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('prodi_id')) {
            $query->whereHas('dosen', function ($dosen) use ($request) {
                $dosen->where('prodi_id', $request->input('prodi_id'));
            });
        }
    }

    private function scopedPegawaiQuery(array $with = [])
    {
        $query = Pegawai::query();

        if ($with !== []) {
            $query->with($with);
        }

        return $query;
    }

    private function stats(Request $request): array
    {
        $query = Pegawai::query();
        $this->applyFilters($query, $request);

        return [
            'total' => (clone $query)->count(),
            'dosen' => (clone $query)->where('tipe', 'dosen')->count(),
            'staff' => (clone $query)->where('tipe', 'staff')->count(),
            'aktif' => (clone $query)->where('status', 'aktif')->count(),
            'tidak_aktif' => (clone $query)->where('status', 'tidak aktif')->count(),
        ];
    }

    private function validator(Request $request, ?Pegawai $pegawai = null): array
    {
        $payload = $request->all();

        if (($payload['tipe'] ?? null) === 'dosen' && empty($payload['dosen_kode'])) {
            $payload['dosen_kode'] = $payload['kode'] ?? null;
        }

        $pegawaiId = $pegawai?->id;
        $dosenId = $pegawai?->dosen?->id;

        $validator = Validator::make($payload, [
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'tipe' => ['required', Rule::in(['dosen', 'staff'])],
            'kode' => ['required', 'string', 'max:255', Rule::unique('pegawai', 'kode')->ignore($pegawaiId)],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('pegawai', 'email')->ignore($pegawaiId)],
            'hp' => ['nullable', 'string', 'max:255'],
            'nomer_rekening' => ['nullable', 'string', 'max:255'],
            'nama_pemilik_rekening' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'tidak aktif'])],
            'dosen_kode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dosen', 'kode')->ignore($dosenId),
            ],
            'nidn' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dosen', 'nidn')->ignore($dosenId),
            ],
            'gelar_depan' => ['nullable', 'string', 'max:255'],
            'gelar_belakang' => ['nullable', 'string', 'max:255'],
            'prodi_id' => ['nullable', 'exists:prodi,id'],
            'jabatan' => [
                Rule::requiredIf(($payload['tipe'] ?? null) === 'staff'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return [$validator, $payload];
    }

    private function pegawaiPayload(array $payload): array
    {
        $columns = [
            'nama',
            'jenis_kelamin',
            'tipe',
            'kode',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'email',
            'hp',
            'nomer_rekening',
            'bank',
            'status',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            $columns[] = 'nama_pemilik_rekening';
        }

        return Arr::only($payload, $columns);
    }

    private function pegawaiUpsertUpdateColumns(): array
    {
        $columns = [
            'nama',
            'jenis_kelamin',
            'tipe',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'email',
            'hp',
            'nomer_rekening',
            'bank',
            'status',
            'updated_at',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            array_splice($columns, 9, 0, ['nama_pemilik_rekening']);
        }

        return $columns;
    }

    private function hasNamaPemilikRekeningColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('pegawai', 'nama_pemilik_rekening');
        }

        return $hasColumn;
    }

    private function syncDetail(Pegawai $pegawai, array $payload): void
    {
        if ($payload['tipe'] === 'dosen') {
            $pegawai->staff()->delete();
            $pegawai->dosen()->updateOrCreate(
                ['pegawai_id' => $pegawai->id],
                [
                    'kode' => $payload['dosen_kode'] ?? $payload['kode'],
                    'nidn' => $payload['nidn'] ?? null,
                    'gelar_depan' => $payload['gelar_depan'] ?? null,
                    'gelar_belakang' => $payload['gelar_belakang'] ?? null,
                    'prodi_id' => $payload['prodi_id'] ?? null,
                ]
            );

            return;
        }

        $pegawai->dosen()->delete();
        $pegawai->staff()->updateOrCreate(
            ['pegawai_id' => $pegawai->id],
            ['jabatan' => $payload['jabatan'] ?? null]
        );
    }

    private function mapSiakadDosen($source, array $prodiMap): ?array
    {
        $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);
        $nama = $this->sourceValue($source, ['nama', 'nama_dosen', 'name']);

        if (! $kode || ! $nama) {
            return null;
        }

        $prodiName = $this->sourceValue($source, [
            'nama_prodi',
            'prodi_nama',
            'program_studi',
            'prodi',
            'prodi.nama',
            'prodi.alias',
            'prodi.kode',
        ]);
        $prodiKey = $this->normalizeLookupKey($prodiName);
        $nidn = $this->sourceValue($source, ['nidn', 'nidn_dosen']);

        return [
            'pegawai' => [
                'nama' => $nama,
                'jenis_kelamin' => $this->normalizeJenisKelamin($this->sourceValue($source, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
                'tipe' => 'dosen',
                'kode' => $kode,
                'tempat_lahir' => $this->sourceValue($source, ['tempat_lahir', 'tmp_lahir']),
                'tanggal_lahir' => $this->normalizeDate($this->sourceValue($source, ['tanggal_lahir', 'tgl_lahir', 'lahir_tanggal'])),
                'alamat' => $this->sourceValue($source, ['alamat', 'alamat_lengkap']),
                'email' => $this->normalizeEmail($this->sourceValue($source, ['email', 'email_dosen'])),
                'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'telepon', 'telp']),
                'nomer_rekening' => $this->sourceValue($source, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
                'nama_pemilik_rekening' => $this->sourceValue($source, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
                'bank' => $this->sourceValue($source, ['bank', 'nama_bank']),
                'status' => $this->normalizeStatus($this->sourceValue($source, ['status', 'aktif'])),
            ],
            'dosen' => [
                'kode' => $kode,
                'nidn' => $nidn ?: null,
                'gelar_depan' => $this->sourceValue($source, ['gelar_depan', 'gelar_dpn']),
                'gelar_belakang' => $this->sourceValue($source, ['gelar_belakang', 'gelar_blk', 'gelar']),
                'prodi_id' => $prodiKey ? ($prodiMap[$prodiKey] ?? null) : null,
            ],
        ];
    }

    private function siakadDosenSources()
    {
        return collect(SiakadDosen::all() ?? []);
    }

    private function filteredSiakadDosenSources(Request $request)
    {
        $sources = $this->siakadDosenSources();

        if ($request->filled('search')) {
            $term = mb_strtolower(trim((string) $request->input('search')));
            $sources = $sources->filter(function ($source) use ($term) {
                $haystacks = [
                    $this->sourceValue($source, ['nama', 'nama_dosen', 'name']),
                    $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']),
                    $this->sourceValue($source, ['nidn', 'nidn_dosen']),
                    $this->sourceValue($source, ['email', 'email_dosen']),
                    $this->sourceValue($source, ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode']),
                ];

                return collect($haystacks)
                    ->filter()
                    ->contains(fn ($value) => str_contains(mb_strtolower($value), $term));
            });
        }

        $sortKey = $request->input('order_by', $request->input('sort_key', 'nama'));
        $sortOrder = $request->input('order_dir', $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMap = [
            'id' => ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip'],
            'kode' => ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip'],
            'nama' => ['nama', 'nama_dosen', 'name'],
            'name' => ['nama', 'nama_dosen', 'name'],
            'nidn' => ['nidn', 'nidn_dosen'],
            'prodi' => ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode'],
        ];
        $keys = $sortMap[$sortKey] ?? $sortMap['nama'];
        $sources = $sources->sortBy(
            fn ($source) => mb_strtolower($this->sourceValue($source, $keys) ?? ''),
            SORT_NATURAL,
            $sortOrder === 'desc'
        );

        return $sources->values();
    }

    private function siakadDosenPreviewRow($source, $existing): ?array
    {
        $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);
        $nama = $this->sourceValue($source, ['nama', 'nama_dosen', 'name']);

        if (! $kode || ! $nama) {
            return null;
        }

        $kode = (string) $kode;
        $pegawai = $existing->get($kode);
        $existingTipe = $pegawai?->tipe;

        return [
            'id' => $kode,
            'kode' => $kode,
            'nama' => $nama,
            'nidn' => $this->sourceValue($source, ['nidn', 'nidn_dosen']),
            'email' => $this->sourceValue($source, ['email', 'email_dosen']),
            'prodi' => $this->sourceValue($source, ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode']) ?: '-',
            'exists' => (bool) $pegawai,
            'existing_pegawai_id' => $pegawai?->id,
            'existing_tipe' => $existingTipe,
            'can_sync' => ! $pegawai || $existingTipe === 'dosen',
        ];
    }

    private function absensiOrderBy($key): string
    {
        $map = [
            'id' => 'id',
            'kode' => 'id',
            'nama' => 'name',
            'name' => 'name',
            'role' => 'role',
            'departemen' => 'departemen',
            'created_at' => 'created_at',
        ];

        return $map[trim((string) $key)] ?? 'name';
    }

    private function staffAbsensiQuery(Request $request, int $page, int $perPage): array
    {
        return [
            'departemen_id' => 2,
            'page' => $page,
            'per_page' => $perPage,
            'search' => $request->input('search'),
            'order_by' => $this->absensiOrderBy($request->input('order_by', $request->input('sort_key', 'name'))),
            'order_dir' => $request->input('order_dir', $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function absensiStaffSourcesForSync(array $ids): array
    {
        $wanted = collect($ids)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($wanted->count() <= 10) {
            return $this->absensiStaffDetailSourcesForSync($wanted->all());
        }

        $remaining = array_fill_keys($wanted->all(), true);
        $sources = [];
        $page = 1;
        $perPage = 100;
        $maxPages = 500;
        $seenPageSignatures = [];

        do {
            $payload = $this->absensiPaginatorPayload(
                Absensi::users([
                    'departemen_id' => 2,
                    'page' => $page,
                    'per_page' => $perPage,
                    'order_by' => 'id',
                    'order_dir' => 'asc',
                ]),
                $page,
                $perPage
            );
            $items = collect($payload['data']);

            if ($items->isEmpty()) {
                break;
            }

            $signature = $this->absensiItemsSignature($items);
            if ($signature && isset($seenPageSignatures[$signature])) {
                break;
            }

            if ($signature) {
                $seenPageSignatures[$signature] = true;
            }

            foreach ($items as $source) {
                $kode = $this->sourceValue($source, ['id', 'user_id']);
                if (! $kode) {
                    continue;
                }

                $kode = (string) $kode;
                if (! isset($remaining[$kode]) || isset($sources[$kode])) {
                    continue;
                }

                $sources[$kode] = $source;
                unset($remaining[$kode]);
            }

            if (! $remaining) {
                break;
            }

            $page++;
        } while ($page <= (int) $payload['last_page'] && $page <= $maxPages);

        return [array_values($sources), count($remaining)];
    }

    private function absensiStaffDetailSourcesForSync(array $ids): array
    {
        $sources = [];
        $failedFetches = 0;

        foreach ($ids as $id) {
            try {
                $sources[] = $this->absensiUserPayload(Absensi::user($id));
            } catch (\Throwable) {
                $failedFetches++;
            }
        }

        return [$sources, $failedFetches];
    }

    private function absensiItemsSignature($items): ?string
    {
        $signature = collect($items)
            ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->implode('|');

        return $signature !== '' ? $signature : null;
    }

    private function absensiPaginatorPayload($response, int $page, int $perPage): array
    {
        $payload = $this->toArray($response);
        $container = $this->absensiPageContainer($payload);
        $items = $this->absensiPageItems($container);
        $metaSources = $this->absensiMetaSources($payload, $container);

        $normalizedPerPage = max(1, (int) ($this->firstDataValue($metaSources, [
            'per_page',
            'perPage',
            'limit',
            'page_size',
            'pageSize',
            'items_per_page',
        ]) ?? $perPage));
        $currentPage = max(1, (int) ($this->firstDataValue($metaSources, [
            'current_page',
            'currentPage',
            'page',
        ]) ?? $page));
        $totalValue = $this->firstDataValue($metaSources, [
            'total',
            'total_data',
            'totalData',
            'total_records',
            'totalRecords',
            'recordsTotal',
            'recordsFiltered',
            'filtered',
            'total_count',
            'totalCount',
            'jumlah_data',
            'jumlahData',
        ]);
        $lastPageValue = $this->firstDataValue($metaSources, [
            'last_page',
            'lastPage',
            'total_page',
            'total_pages',
            'totalPages',
            'pages',
        ]);

        $hasExplicitTotal = $totalValue !== null;
        $total = $hasExplicitTotal ? (int) $totalValue : (($currentPage - 1) * $normalizedPerPage) + count($items);
        $lastPage = $lastPageValue
            ? max(1, (int) $lastPageValue)
            : max(1, (int) ceil($total / $normalizedPerPage));

        if (! $hasExplicitTotal) {
            if ($lastPageValue) {
                $total = max($total, $lastPage * $normalizedPerPage);
            } elseif ($this->absensiHasNextPage($metaSources, $currentPage, $lastPage) || count($items) >= $normalizedPerPage) {
                $total = max($total, ($currentPage * $normalizedPerPage) + 1);
                $lastPage = max($lastPage, $currentPage + 1);
            }
        }

        $from = $this->firstDataValue($metaSources, ['from']);
        $to = $this->firstDataValue($metaSources, ['to']);

        return [
            'current_page' => $currentPage,
            'data' => $items,
            'from' => $from ?? (count($items) ? (($currentPage - 1) * $normalizedPerPage) + 1 : null),
            'last_page' => $lastPage,
            'per_page' => $normalizedPerPage,
            'to' => $to ?? (count($items) ? (($currentPage - 1) * $normalizedPerPage) + count($items) : null),
            'total' => $total,
        ];
    }

    private function absensiPageContainer(array $payload): array
    {
        if ($payload && $this->isList($payload)) {
            return ['data' => $payload];
        }

        foreach ([$payload, data_get($payload, 'data'), data_get($payload, 'result'), data_get($payload, 'response')] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($this->absensiPageItems($candidate) || $this->hasAnyDataKey($candidate, ['data', 'users', 'items', 'results', 'records'])) {
                return $candidate;
            }
        }

        return $payload;
    }

    private function absensiPageItems(array $container): array
    {
        if ($container && $this->isList($container)) {
            return $container;
        }

        foreach (['data', 'users', 'items', 'results', 'records'] as $key) {
            $value = data_get($container, $key);
            if (! is_array($value)) {
                continue;
            }

            if ($this->isList($value)) {
                return $value;
            }

            $nested = $this->absensiPageItems($value);
            if ($nested || $this->hasAnyDataKey($value, ['data', 'users', 'items', 'results', 'records'])) {
                return $nested;
            }
        }

        return [];
    }

    private function absensiMetaSources(array $payload, array $container): array
    {
        return array_values(array_filter([
            data_get($payload, 'meta'),
            data_get($payload, 'pagination'),
            data_get($payload, 'links'),
            data_get($container, 'meta'),
            data_get($container, 'pagination'),
            data_get($container, 'links'),
            $container,
            $payload,
        ], fn ($source) => is_array($source)));
    }

    private function absensiHasNextPage(array $sources, int $currentPage, int $lastPage): bool
    {
        if ($lastPage > $currentPage) {
            return true;
        }

        $next = $this->firstDataValue($sources, [
            'next',
            'next_page',
            'nextPage',
            'next_page_url',
            'has_more',
            'hasMore',
        ]);

        if (is_bool($next)) {
            return $next;
        }

        if (is_numeric($next)) {
            return (int) $next > 0;
        }

        return is_string($next) && trim($next) !== '';
    }

    private function firstDataValue(array $sources, array $keys)
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                $value = data_get($source, $key);

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function hasAnyDataKey(array $source, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }

        return false;
    }

    private function absensiPreviewRow($source, $existing): ?array
    {
        $kode = $this->sourceValue($source, ['id', 'user_id']);
        $nama = $this->sourceValue($source, ['name', 'nama', 'username']);

        if (! $kode || ! $nama) {
            return null;
        }

        $kode = (string) $kode;
        $pegawai = $existing->get($kode);
        $existingTipe = $pegawai?->tipe;

        return [
            'id' => $kode,
            'kode' => $kode,
            'nama' => $nama,
            'email' => $this->sourceValue($source, ['email', 'email_user']),
            'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'phone', 'phone_number', 'telepon', 'telp']),
            'role' => $this->sourceValue($source, ['role.name', 'role.nama', 'role_name', 'nama_role']) ?: '-',
            'departemen' => $this->sourceValue($source, ['departemen.name', 'departemen.nama', 'departemen_name', 'nama_departemen', 'department.name', 'department.nama']) ?: '-',
            'jabatan' => $this->absensiJabatan($source),
            'exists' => (bool) $pegawai,
            'existing_pegawai_id' => $pegawai?->id,
            'existing_tipe' => $existingTipe,
            'can_sync' => ! $pegawai || $existingTipe === 'staff',
        ];
    }

    private function absensiUserPayload($response): array
    {
        $payload = $this->toArray($response);
        $data = data_get($payload, 'data');

        if (is_array($data) && isset($data['data']) && is_array($data['data']) && ! $this->isList($data['data'])) {
            return $data['data'];
        }

        if (is_array($data) && ! $this->isList($data)) {
            return $data;
        }

        return $payload;
    }

    private function mapAbsensiStaff(array $source): ?array
    {
        $kode = $this->sourceValue($source, ['id', 'user_id']);
        $nama = $this->sourceValue($source, ['name', 'nama', 'username']);

        if (! $kode || ! $nama) {
            return null;
        }

        return [
            'pegawai' => [
                'nama' => $nama,
                'jenis_kelamin' => $this->normalizeJenisKelamin($this->sourceValue($source, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
                'tipe' => 'staff',
                'kode' => (string) $kode,
                'tempat_lahir' => $this->sourceValue($source, ['tempat_lahir', 'tmp_lahir', 'birth_place']),
                'tanggal_lahir' => $this->normalizeDate($this->sourceValue($source, ['tanggal_lahir', 'tgl_lahir', 'birth_date', 'date_of_birth'])),
                'alamat' => $this->sourceValue($source, ['alamat', 'alamat_lengkap', 'address']),
                'email' => $this->normalizeEmail($this->sourceValue($source, ['email', 'email_user'])),
                'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'phone', 'phone_number', 'telepon', 'telp']),
                'nomer_rekening' => $this->sourceValue($source, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
                'nama_pemilik_rekening' => $this->sourceValue($source, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
                'bank' => $this->sourceValue($source, ['bank', 'nama_bank']),
                'status' => $this->normalizeStatus($this->sourceValue($source, ['status', 'aktif', 'is_active'])),
            ],
            'staff' => [
                'jabatan' => $this->absensiJabatan($source),
            ],
        ];
    }

    private function absensiJabatan($source): ?string
    {
        return $this->sourceValue($source, [
            'jabatan',
            'position',
            'posisi',
            'role.name',
            'role.nama',
            'role_name',
            'nama_role',
            'departemen.name',
            'departemen.nama',
            'departemen_name',
            'nama_departemen',
            'department.name',
            'department.nama',
        ]);
    }

    private function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }

    private function isList(array $value): bool
    {
        return array_values($value) === $value;
    }

    private function prodiMap(): array
    {
        $map = [];

        Prodi::query()
            ->select(['id', 'kode', 'alias', 'nama'])
            ->get()
            ->each(function ($prodi) use (&$map) {
                foreach ([$prodi->kode, $prodi->alias, $prodi->nama] as $value) {
                    $key = $this->normalizeLookupKey($value);
                    if ($key) {
                        $map[$key] = $prodi->id;
                    }
                }
            });

        return $map;
    }

    private function sourceValue($source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizeLookupKey(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));

        return $normalized !== '' ? mb_strtolower($normalized) : null;
    }

    private function normalizeJenisKelamin(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['p', 'perempuan', 'wanita', 'female'], true)) {
            return 'Perempuan';
        }

        return 'Laki-laki';
    }

    private function normalizeStatus(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['n', 'nonaktif', 'tidak aktif', 'inactive', '0'], true)) {
            return 'tidak aktif';
        }

        return 'aktif';
    }

    private function normalizeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeEmail(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $email = mb_strtolower(trim($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
