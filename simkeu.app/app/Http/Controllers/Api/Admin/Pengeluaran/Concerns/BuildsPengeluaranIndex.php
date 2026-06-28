<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BuildsPengeluaranIndex
{
    protected function aggregatePengeluaranStats($query, string $table): array
    {
        $dateColumn = "{$table}.tanggal";
        $totalColumn = "{$table}.total";
        $rekapColumn = "{$table}.rekap_id";
        $today = now();
        $todayDate = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $weekEnd = $today->copy()->endOfWeek()->toDateString();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $todayRow = $this->aggregatePengeluaranTotal(
            $this->statQuery($query)->where($dateColumn, $todayDate),
            $totalColumn
        );
        $weekRow = $this->aggregatePengeluaranTotal(
            $this->statQuery($query)->whereBetween($dateColumn, [$weekStart, $weekEnd]),
            $totalColumn
        );
        $monthRow = $this->aggregatePengeluaranTotal(
            $this->statQuery($query)->whereBetween($dateColumn, [$monthStart, $monthEnd]),
            $totalColumn
        );
        $allRow = $this->aggregatePengeluaranTotal($this->statQuery($query), $totalColumn);
        $unrekapRow = $this->aggregatePengeluaranTotal(
            $this->statQuery($query)->whereNull($rekapColumn),
            $totalColumn
        );

        return [
            'hari_ini' => [
                'total' => (int) ($todayRow->total ?? 0),
                'jumlah' => (int) ($todayRow->jumlah ?? 0),
            ],
            'mingguan' => [
                'total' => (int) ($weekRow->total ?? 0),
                'jumlah' => (int) ($weekRow->jumlah ?? 0),
            ],
            'bulanan' => [
                'total' => (int) ($monthRow->total ?? 0),
                'jumlah' => (int) ($monthRow->jumlah ?? 0),
            ],
            'keseluruhan' => [
                'total' => (int) ($allRow->total ?? 0),
                'jumlah' => (int) ($allRow->jumlah ?? 0),
            ],
            'belum_rekap' => [
                'total' => (int) ($unrekapRow->total ?? 0),
                'jumlah' => (int) ($unrekapRow->jumlah ?? 0),
            ],
        ];
    }

    protected function statQuery($query)
    {
        $statsQuery = clone $query;
        $statsQuery->getQuery()->columns = null;
        $statsQuery->reorder();

        return $statsQuery;
    }

    protected function aggregatePengeluaranTotal($query, string $totalColumn)
    {
        return $query
            ->selectRaw("COUNT(*) as jumlah, COALESCE(SUM({$totalColumn}), 0) as total")
            ->first();
    }

    protected function paginateWithKnownTotal(
        $query,
        Request $request,
        int $total,
        int $defaultLimit = 10
    ): LengthAwarePaginator {
        $perPage = max(1, (int) $request->input('limit', $defaultLimit));
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

    protected function aggregateSaldoPerPetugas(
        string $pengeluaranTable,
        string $rekapTable,
        ?string $lpjModuleKey = null,
        ?int $petugasId = null
    ): array {
        $lpjTable = $pengeluaranTable.'_lpj';
        $hasLpj = Schema::hasTable($lpjTable);
        $hasLpjStatus = Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status');
        $pegawaiTipe = defined(static::class.'::PEGAWAI_TIPE') ? static::PEGAWAI_TIPE : null;

        $rabQuery = DB::table($pengeluaranTable)
            ->select([
                "{$pengeluaranTable}.petugas_id",
                DB::raw("COALESCE(SUM({$pengeluaranTable}.total), 0) as total_rab"),
            ])
            ->whereNotNull("{$pengeluaranTable}.petugas_id")
            ->groupBy("{$pengeluaranTable}.petugas_id");
        $this->applyPengeluaranGenderScope($rabQuery, $pengeluaranTable);

        if ($petugasId) {
            $rabQuery->where("{$pengeluaranTable}.petugas_id", $petugasId);
        }

        if ($pegawaiTipe && Schema::hasColumn($pengeluaranTable, 'pegawai_tipe')) {
            if (is_array($pegawaiTipe)) {
                $rabQuery->whereIn("{$pengeluaranTable}.pegawai_tipe", $pegawaiTipe);
            } else {
                $rabQuery->where("{$pengeluaranTable}.pegawai_tipe", $pegawaiTipe);
            }
        }

        $rabRows = $rabQuery->get()->keyBy('petugas_id');

        // LPJ per petugas from LPJ detail table
        $lpjRows = collect();
        if ($hasLpj && Schema::hasColumn($lpjTable, 'petugas_id')) {
            $lpjQuery = DB::table($lpjTable)
                ->select([
                    "{$lpjTable}.petugas_id",
                    DB::raw("COALESCE(SUM({$lpjTable}.total), 0) as total_lpj"),
                ])
                ->groupBy("{$lpjTable}.petugas_id");
            $this->applyPengeluaranGenderScope($lpjQuery, $lpjTable);

            if ($pegawaiTipe && Schema::hasColumn($lpjTable, 'pegawai_tipe')) {
                if (is_array($pegawaiTipe)) {
                    $lpjQuery->whereIn("{$lpjTable}.pegawai_tipe", $pegawaiTipe);
                } else {
                    $lpjQuery->where("{$lpjTable}.pegawai_tipe", $pegawaiTipe);
                }
            }

            if ($petugasId) {
                $lpjQuery->where("{$lpjTable}.petugas_id", $petugasId);
            }

            $lpjRows = $lpjQuery->get()->keyBy('petugas_id');
        }

        // sama_dengan_rab: add RAB amounts for rekaps marked as LPJ = RAB
        $samaRabAmounts = collect();
        if ($hasLpj && $lpjModuleKey && $hasLpjStatus && Schema::hasColumn($rekapTable, 'petugas_id')) {
            $samaRabQuery = DB::table('keuangan_pengeluaran_lpj_rekap_status as lpj_status')
                ->join("{$rekapTable} as rekap", 'rekap.id', '=', 'lpj_status.rekap_id')
                ->leftJoin("{$pengeluaranTable} as rab_detail", 'rab_detail.rekap_id', '=', 'rekap.id')
                ->where('lpj_status.module_key', $lpjModuleKey)
                ->where('lpj_status.sama_dengan_rab', 1)
                ->whereNotExists(function ($query) use ($lpjTable) {
                    $query->select(DB::raw(1))
                        ->from("{$lpjTable} as lpj_detail")
                        ->whereColumn('lpj_detail.rekap_id', 'rekap.id');
                })
                ->select([
                    'rekap.petugas_id',
                    DB::raw('COALESCE(SUM(rab_detail.total), 0) as sama_rab_lpj'),
                ])
                ->groupBy('rekap.petugas_id');
            Helper::applyRelatedGenderScope(
                $samaRabQuery,
                'rekap.petugas_id',
                'users'
            );
            $this->applyPengeluaranGenderScope(
                $samaRabQuery,
                $pengeluaranTable,
                'rab_detail'
            );

            if ($petugasId) {
                $samaRabQuery->where('rekap.petugas_id', $petugasId);
            }

            if ($pegawaiTipe && Schema::hasColumn($pengeluaranTable, 'pegawai_tipe')) {
                if (is_array($pegawaiTipe)) {
                    $samaRabQuery->whereIn('rab_detail.pegawai_tipe', $pegawaiTipe);
                } else {
                    $samaRabQuery->where('rab_detail.pegawai_tipe', $pegawaiTipe);
                }
            }

            $samaRabAmounts = $samaRabQuery->get()->keyBy('petugas_id');
        }

        $moduleKey = $lpjModuleKey ?: $this->saldoModuleKeyFromTable($pengeluaranTable, $rekapTable);
        $manualRows = $this->manualSaldoPerPetugas($moduleKey, $petugasId);

        // Merge all petugas IDs
        $allPetugasIds = $rabRows->keys()
            ->merge($lpjRows->keys())
            ->merge($samaRabAmounts->keys())
            ->merge($manualRows->keys())
            ->unique();

        if ($allPetugasIds->isEmpty()) {
            return [];
        }

        $petugasNames = DB::table('users')
            ->whereIn('id', $allPetugasIds)
            ->pluck('name', 'id');

        return $allPetugasIds->map(function ($id) use ($rabRows, $lpjRows, $samaRabAmounts, $manualRows, $petugasNames) {
            $rab = $rabRows->get($id);
            $totalRab = (int) ($rab->total_rab ?? 0);
            $totalLpj = (int) ($lpjRows->get($id)->total_lpj ?? 0);
            $totalLpj += (int) ($samaRabAmounts->get($id)->sama_rab_lpj ?? 0);
            $tambahan = (int) ($manualRows->get($id)->total_tambahan ?? 0);

            return [
                'petugas_id' => $id,
                'petugas_name' => $petugasNames->get($id) ?? '-',
                'total_rab' => $totalRab,
                'total_lpj' => $totalLpj,
                'tambahan' => $tambahan,
                'saldo' => $totalRab - $totalLpj + $tambahan,
            ];
        })->sortBy('petugas_name')->values()->all();
    }

    protected function indexSaldoStats(
        Request $request,
        string $pengeluaranTable,
        string $rekapTable,
        ?string $lpjModuleKey = null
    ): array {
        $petugasId = $request->filled('petugas_id') ? (int) $request->petugas_id : null;

        if ($petugasId) {
            return $this->aggregateSaldoPerPetugas(
                $pengeluaranTable,
                $rekapTable,
                $lpjModuleKey,
                $petugasId
            );
        }

        $cacheKey = implode(':', [
            'pengeluaran-index-saldo',
            static::class,
            $pengeluaranTable,
            $rekapTable,
            $lpjModuleKey ?? 'none',
            Helper::activeGenderScope() ?? 'semua',
            $this->manualSaldoUpdatedAt($lpjModuleKey ?: $this->saldoModuleKeyFromTable($pengeluaranTable, $rekapTable)),
        ]);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            fn () => $this->aggregateSaldoPerPetugas($pengeluaranTable, $rekapTable, $lpjModuleKey)
        );
    }

    private function manualSaldoPerPetugas(?string $moduleKey, ?int $petugasId = null)
    {
        if (! $moduleKey || ! Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return collect();
        }

        $query = DB::table('keuangan_pengeluaran_saldo')
            ->select([
                'petugas_id',
                DB::raw("COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE -nominal END), 0) as total_tambahan"),
            ])
            ->where('module_key', $moduleKey)
            ->whereNotNull('petugas_id')
            ->when($petugasId, fn ($query) => $query->where('petugas_id', $petugasId));

        Helper::applyRelatedGenderScope(
            $query,
            'keuangan_pengeluaran_saldo.petugas_id',
            'users'
        );

        return $query
            ->groupBy('petugas_id')
            ->get()
            ->keyBy('petugas_id');
    }

    private function manualSaldoUpdatedAt(?string $moduleKey): string
    {
        if (! $moduleKey || ! Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return 'none';
        }

        return (string) (DB::table('keuangan_pengeluaran_saldo')
            ->where('module_key', $moduleKey)
            ->max('updated_at') ?? 'none');
    }

    private function saldoModuleKeyFromTable(string $pengeluaranTable, string $rekapTable): ?string
    {
        return match (true) {
            $pengeluaranTable === 'keuangan_pengeluaran_dosen' => 'tatap_muka',
            $pengeluaranTable === 'keuangan_pengeluaran_dosen_kegiatan' => 'kegiatan',
            $pengeluaranTable === 'keuangan_pengeluaran_rumah_tangga' => 'rumah_tangga',
            $pengeluaranTable === 'keuangan_pengeluaran_sarana_prasarana' => 'sarana_prasarana',
            $pengeluaranTable === 'keuangan_pengeluaran_transportasi' => 'transportasi',
            $pengeluaranTable === 'keuangan_pengeluaran_umum' => 'umum',
            $rekapTable === 'keuangan_pengeluaran_dosen_bulanan_rekap' => 'dosen_bulanan',
            default => null,
        };
    }
}
