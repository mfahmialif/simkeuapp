<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaldoController extends Controller
{
    private const MODULES = [
        [
            'key' => 'tatap_muka',
            'name' => 'Tatap Muka',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'lpj_table' => 'keuangan_pengeluaran_dosen_lpj',
            'pegawai_tipe' => null,
        ],
        [
            'key' => 'kegiatan',
            'name' => 'Kegiatan',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'lpj_table' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
            'pegawai_tipe' => null,
        ],
        [
            'key' => 'rumah_tangga',
            'name' => 'Rumah Tangga',
            'detail_table' => 'keuangan_pengeluaran_rumah_tangga',
            'rekap_table' => 'keuangan_pengeluaran_rumah_tangga_rekap',
            'lpj_table' => 'keuangan_pengeluaran_rumah_tangga_lpj',
            'pegawai_tipe' => null,
        ],
        [
            'key' => 'sarana_prasarana',
            'name' => 'Sarana Prasarana',
            'detail_table' => 'keuangan_pengeluaran_sarana_prasarana',
            'rekap_table' => 'keuangan_pengeluaran_sarana_prasarana_rekap',
            'lpj_table' => 'keuangan_pengeluaran_sarana_prasarana_lpj',
            'pegawai_tipe' => null,
        ],
        [
            'key' => 'transportasi',
            'name' => 'Transportasi',
            'detail_table' => 'keuangan_pengeluaran_transportasi',
            'rekap_table' => 'keuangan_pengeluaran_transportasi_rekap',
            'lpj_table' => 'keuangan_pengeluaran_transportasi_lpj',
            'pegawai_tipe' => null,
        ],
        [
            'key' => 'dosen_bulanan',
            'name' => 'Dosen Bulanan',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'pegawai_tipe' => 'dosen',
        ],
        [
            'key' => 'hutang',
            'name' => 'Hutang',
            'detail_table' => 'keuangan_hutang',
            'rekap_table' => null,
            'lpj_table' => null,
            'pegawai_tipe' => null,
        ],
    ];

    public function index(Request $request)
    {
        $rows = $this->saldoRows(self::MODULES);
        $manualRows = $this->manualSaldoRows();
        $petugasData = $this->groupSaldoRows($rows, $manualRows, self::MODULES);

        // Convert to indexed array and sort by name
        $results = array_values($petugasData);
        usort($results, function ($a, $b) {
            return strcmp($a['petugas_name'], $b['petugas_name']);
        });

        return response()->json([
            'status' => true,
            'data' => $results,
        ]);
    }

    public function show($petugasId)
    {
        $petugas = DB::table('users')
            ->select('id', 'name')
            ->where('id', $petugasId)
            ->first();

        if (! $petugas) {
            return response()->json([
                'status' => false,
                'message' => 'Petugas not found',
            ], 404);
        }

        $rows = $this->saldoRows(self::MODULES, (int) $petugasId);
        $manualRows = $this->manualSaldoRows((int) $petugasId);
        $grouped = $this->groupSaldoRows($rows, $manualRows, self::MODULES);
        $summary = $grouped[$petugasId] ?? [
            'petugas_id' => (int) $petugasId,
            'petugas_name' => $petugas->name,
            'total_saldo' => 0,
            'total_tambahan' => 0,
            'modules' => [],
        ];

        foreach (self::MODULES as $module) {
            if (! isset($summary['modules'][$module['key']])) {
                $summary['modules'][$module['key']] = [
                    'total_rab' => 0,
                    'total_lpj' => 0,
                    'tambahan' => 0,
                    'saldo' => 0,
                ];
            }

            $summary['modules'][$module['key']]['module_name'] = $module['name'];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'summary' => $summary,
                'adjustments' => $this->manualSaldoDetailRows((int) $petugasId),
                'modules' => $this->moduleOptions(),
            ],
        ]);
    }

    public function storeAdjustment(Request $request, $petugasId)
    {
        $validator = Validator::make([
            ...$request->all(),
            'petugas_id' => $petugasId,
        ], [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in($this->adjustmentModuleKeys())],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $id = DB::table('keuangan_pengeluaran_saldo')->insertGetId([
            'petugas_id' => $data['petugas_id'],
            'module_key' => $data['module_key'],
            'tanggal' => $data['tanggal'],
            'tipe' => 'masuk',
            'nominal' => $data['nominal'],
            'keterangan' => $data['keterangan'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'data' => ['id' => $id],
            'message' => 'Saldo tambahan berhasil ditambahkan.',
        ], 201);
    }

    private function saldoRows(array $modules, ?int $petugasId = null)
    {
        $union = null;

        foreach ($modules as $module) {
            foreach ($this->moduleSaldoQueries($module) as $query) {
                if ($union === null) {
                    $union = $query;
                    continue;
                }

                $union->unionAll($query);
            }
        }

        if ($union === null) {
            return collect();
        }

        return DB::query()
            ->fromSub($union, 'saldo')
            ->leftJoin('users', 'users.id', '=', 'saldo.petugas_id')
            ->select([
                'saldo.module_key',
                'saldo.petugas_id',
                DB::raw("COALESCE(users.name, '-') as petugas_name"),
                DB::raw('COALESCE(SUM(saldo.total_rab), 0) as total_rab'),
                DB::raw('COALESCE(SUM(saldo.total_lpj), 0) as total_lpj'),
            ])
            ->whereNotNull('saldo.petugas_id')
            ->when($petugasId, fn ($query) => $query->where('saldo.petugas_id', $petugasId))
            ->groupBy('saldo.module_key', 'saldo.petugas_id', 'users.name')
            ->get();
    }

    private function moduleSaldoQueries(array $module): array
    {
        $queries = [];
        $detailTable = $module['detail_table'];
        $lpjTable = $module['lpj_table'];
        $rekapTable = $module['rekap_table'];

        if ($module['key'] === 'hutang') {
            if (Schema::hasTable('keuangan_hutang')) {
                $queries[] = DB::table('keuangan_hutang as detail')
                    ->select([
                        DB::raw("'hutang' as module_key"),
                        'detail.petugas_id',
                        DB::raw("COALESCE(SUM(CASE WHEN detail.tipe = 'hutang' THEN detail.nominal ELSE 0 END), 0) as total_rab"),
                        DB::raw("COALESCE(SUM(CASE WHEN detail.tipe = 'pelunasan' THEN detail.nominal ELSE 0 END), 0) as total_lpj"),
                    ])
                    ->whereNotNull('detail.petugas_id')
                    ->groupBy('detail.petugas_id');
            }

            return $queries;
        }

        if (Schema::hasTable($detailTable) && Schema::hasColumn($detailTable, 'petugas_id')) {
            $rabQuery = DB::table("{$detailTable} as detail")
                ->select([
                    DB::raw("'{$module['key']}' as module_key"),
                    'detail.petugas_id',
                    DB::raw('COALESCE(SUM(detail.total), 0) as total_rab'),
                    DB::raw('0 as total_lpj'),
                ])
                ->whereNotNull('detail.petugas_id')
                ->groupBy('detail.petugas_id');

            $this->applyPegawaiTipe($rabQuery, 'detail', $detailTable, $module['pegawai_tipe']);
            $queries[] = $rabQuery;
        }

        if (Schema::hasTable($lpjTable) && Schema::hasColumn($lpjTable, 'petugas_id')) {
            $lpjQuery = DB::table("{$lpjTable} as detail")
                ->select([
                    DB::raw("'{$module['key']}' as module_key"),
                    'detail.petugas_id',
                    DB::raw('0 as total_rab'),
                    DB::raw('COALESCE(SUM(detail.total), 0) as total_lpj'),
                ])
                ->whereNotNull('detail.petugas_id')
                ->groupBy('detail.petugas_id');

            $this->applyPegawaiTipe($lpjQuery, 'detail', $lpjTable, $module['pegawai_tipe']);
            $queries[] = $lpjQuery;
        }

        if (
            Schema::hasTable($detailTable)
            && Schema::hasTable($lpjTable)
            && Schema::hasTable($rekapTable)
            && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')
            && Schema::hasColumn($rekapTable, 'petugas_id')
        ) {
            $lpjCheck = DB::table($lpjTable)
                ->select('rekap_id', DB::raw('COUNT(*) as lpj_count'))
                ->whereNotNull('rekap_id')
                ->groupBy('rekap_id');

            $rabSub = DB::table($detailTable)
                ->select('rekap_id', DB::raw('COALESCE(SUM(total), 0) as rab_total'))
                ->whereNotNull('rekap_id')
                ->groupBy('rekap_id');

            $this->applyPegawaiTipe($lpjCheck, $lpjTable, $lpjTable, $module['pegawai_tipe']);
            $this->applyPegawaiTipe($rabSub, $detailTable, $detailTable, $module['pegawai_tipe']);

            $sameAsRabQuery = DB::table("{$rekapTable} as rekap")
                ->join('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($module) {
                    $join->on('lpj_status.rekap_id', '=', 'rekap.id')
                        ->where('lpj_status.module_key', '=', $module['key'])
                        ->where('lpj_status.sama_dengan_rab', '=', 1);
                })
                ->leftJoinSub($lpjCheck, 'lpj_check', 'lpj_check.rekap_id', '=', 'rekap.id')
                ->leftJoinSub($rabSub, 'rab_sub', 'rab_sub.rekap_id', '=', 'rekap.id')
                ->select([
                    DB::raw("'{$module['key']}' as module_key"),
                    'rekap.petugas_id',
                    DB::raw('0 as total_rab'),
                    DB::raw('COALESCE(SUM(rab_sub.rab_total), 0) as total_lpj'),
                ])
                ->whereNotNull('rekap.petugas_id')
                ->whereRaw('COALESCE(lpj_check.lpj_count, 0) = 0')
                ->groupBy('rekap.petugas_id');

            $queries[] = $sameAsRabQuery;
        }

        return $queries;
    }

    private function applyPegawaiTipe($query, string $alias, string $table, ?string $pegawaiTipe): void
    {
        if ($pegawaiTipe && Schema::hasColumn($table, 'pegawai_tipe')) {
            $query->where("{$alias}.pegawai_tipe", $pegawaiTipe);
        }
    }

    private function manualSaldoRows(?int $petugasId = null)
    {
        if (! Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return collect();
        }

        return DB::table('keuangan_pengeluaran_saldo as saldo')
            ->leftJoin('users', 'users.id', '=', 'saldo.petugas_id')
            ->select([
                'saldo.module_key',
                'saldo.petugas_id',
                DB::raw("COALESCE(users.name, '-') as petugas_name"),
                DB::raw("COALESCE(SUM(CASE WHEN saldo.tipe = 'masuk' THEN saldo.nominal ELSE -saldo.nominal END), 0) as total_tambahan"),
            ])
            ->whereNotNull('saldo.petugas_id')
            ->when($petugasId, fn ($query) => $query->where('saldo.petugas_id', $petugasId))
            ->groupBy('saldo.module_key', 'saldo.petugas_id', 'users.name')
            ->get();
    }

    private function manualSaldoDetailRows(int $petugasId)
    {
        if (! Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return [];
        }

        $moduleNames = collect(self::MODULES)->pluck('name', 'key');

        return DB::table('keuangan_pengeluaran_saldo')
            ->where('petugas_id', $petugasId)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'module_key' => $row->module_key,
                'module_name' => $moduleNames->get($row->module_key, $row->module_key),
                'tanggal' => $row->tanggal,
                'tipe' => $row->tipe,
                'nominal' => (int) $row->nominal,
                'keterangan' => $row->keterangan,
            ])
            ->all();
    }

    private function moduleOptions(): array
    {
        return array_map(fn ($module) => [
            'key' => $module['key'],
            'name' => $module['name'],
        ], self::MODULES);
    }

    private function adjustmentModuleKeys(): array
    {
        return array_values(array_filter(
            array_column(self::MODULES, 'key'),
            fn ($key) => $key !== 'hutang',
        ));
    }

    private function groupSaldoRows($rows, $manualRows, array $modules): array
    {
        $petugasData = [];

        foreach ($rows as $row) {
            $petugasId = $row->petugas_id;

            if (!isset($petugasData[$petugasId])) {
                $petugasData[$petugasId] = [
                    'petugas_id' => $petugasId,
                    'petugas_name' => $row->petugas_name,
                    'total_saldo' => 0,
                    'total_tambahan' => 0,
                    'modules' => [],
                ];
            }

            $totalRab = (int) $row->total_rab;
            $totalLpj = (int) $row->total_lpj;
            $saldo = $totalRab - $totalLpj;

            $petugasData[$petugasId]['modules'][$row->module_key] = [
                'total_rab' => $totalRab,
                'total_lpj' => $totalLpj,
                'tambahan' => 0,
                'saldo' => $saldo,
            ];
            $petugasData[$petugasId]['total_saldo'] += $saldo;
        }

        foreach ($manualRows as $row) {
            $petugasId = $row->petugas_id;

            if (!isset($petugasData[$petugasId])) {
                $petugasData[$petugasId] = [
                    'petugas_id' => $petugasId,
                    'petugas_name' => $row->petugas_name,
                    'total_saldo' => 0,
                    'total_tambahan' => 0,
                    'modules' => [],
                ];
            }

            if (!isset($petugasData[$petugasId]['modules'][$row->module_key])) {
                $petugasData[$petugasId]['modules'][$row->module_key] = [
                    'total_rab' => 0,
                    'total_lpj' => 0,
                    'tambahan' => 0,
                    'saldo' => 0,
                ];
            }

            $tambahan = (int) $row->total_tambahan;
            $petugasData[$petugasId]['modules'][$row->module_key]['tambahan'] += $tambahan;
            $petugasData[$petugasId]['modules'][$row->module_key]['saldo'] += $tambahan;
            $petugasData[$petugasId]['total_tambahan'] += $tambahan;
            $petugasData[$petugasId]['total_saldo'] += $tambahan;
        }

        foreach ($petugasData as &$user) {
            foreach ($modules as $module) {
                if (!isset($user['modules'][$module['key']])) {
                    $user['modules'][$module['key']] = [
                        'total_rab' => 0,
                        'total_lpj' => 0,
                        'tambahan' => 0,
                        'saldo' => 0,
                    ];
                }
            }
        }

        return $petugasData;
    }
}
