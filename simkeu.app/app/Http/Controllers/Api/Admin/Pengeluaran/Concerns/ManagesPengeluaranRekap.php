<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ManagesPengeluaranRekap
{
    abstract protected function rekapModelClass(): string;

    abstract protected function pengeluaranTable(): string;

    abstract protected function newRekapPengeluaranQuery();

    abstract protected function newRekapBulkPengeluaranQuery(Request $request);

    protected function requiresRekapForPengeluaran(): bool
    {
        return false;
    }

    public function rekapIndex(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $summary = $this->rekapSummaryQuery($request);
        $lpjSummary = $this->lpjSummaryQuery($request);
        $lpjModuleKey = $this->lpjModuleKey($rekapTable);

        $select = [
            "{$rekapTable}.*",
            DB::raw('COALESCE(rekap_summary.jumlah_data, 0) as jumlah_data'),
            DB::raw('COALESCE(rekap_summary.total_pengeluaran, 0) as total_pengeluaran'),
            DB::raw($this->effectiveAmountSql($rekapTable).' as jumlah'),
            DB::raw('CASE WHEN COALESCE(rekap_summary.jumlah_data, 0) = 0 THEN 1 ELSE 0 END as is_jumlah_sementara'),
            DB::raw($this->temporaryDifferenceSql($rekapTable).' as selisih_sementara'),
        ];

        if ($lpjSummary && $lpjModuleKey && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            $select[] = DB::raw('COALESCE(lpj_summary.jumlah_lpj, 0) as jumlah_lpj');
            $select[] = DB::raw($this->effectiveLpjAmountSql($rekapTable, $request->filled('petugas_id')).' as total_lpj');
            $select[] = DB::raw('COALESCE(lpj_status.sama_dengan_rab, 0) as lpj_sama_dengan_rab');
        } else {
            $select[] = DB::raw('0 as jumlah_lpj');
            $select[] = DB::raw('0 as total_lpj');
            $select[] = DB::raw('0 as lpj_sama_dengan_rab');
        }

        $query = $modelClass::query()
            ->select($select)
            ->leftJoinSub(
                $summary,
                'rekap_summary',
                'rekap_summary.rekap_id',
                '=',
                "{$rekapTable}.id"
            );

        if ($lpjSummary && $lpjModuleKey && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            $query
                ->leftJoinSub(
                    $lpjSummary,
                    'lpj_summary',
                    'lpj_summary.rekap_id',
                    '=',
                    "{$rekapTable}.id"
                )
                ->leftJoin('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($rekapTable, $lpjModuleKey) {
                    $join->on('lpj_status.rekap_id', '=', "{$rekapTable}.id")
                        ->where('lpj_status.module_key', '=', $lpjModuleKey);
                });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $rekapTable) {
                $q->where("{$rekapTable}.nama", 'LIKE', "%{$search}%")
                    ->orWhere("{$rekapTable}.keterangan", 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth("{$rekapTable}.bulan_tahun", (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear("{$rekapTable}.bulan_tahun", (int) $request->tahun);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate("{$rekapTable}.tanggal_rekap", '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate("{$rekapTable}.tanggal_rekap", '<=', $request->tanggal_akhir);
        }

        $this->applyRekapPetugasFilter($query, $request, $rekapTable);

        $sortColumns = [
            'id' => "{$rekapTable}.id",
            'nama' => "{$rekapTable}.nama",
            'tanggal_rekap' => "{$rekapTable}.tanggal_rekap",
            'jumlah' => 'jumlah',
            'jumlah_data' => 'jumlah_data',
            'total_pengeluaran' => 'total_pengeluaran',
            'created_at' => "{$rekapTable}.created_at",
        ];
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? "{$rekapTable}.id", $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->each(fn ($item) => $this->castRekapSummary($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapStore(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $input = $this->rekapInput($request);

        $validator = Validator::make($input, [
            'nama' => ['required', 'string', 'max:255', Rule::unique($rekapTable, 'nama')],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'jumlah_sementara' => ['required', 'integer', 'min:0'],
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['bulan_tahun'] .= '-01';
        $validated['petugas_id'] = auth()->id();

        $data = $modelClass::create($validated);
        $this->applyRekapSummary($data, [
            'jumlah_data' => 0,
            'total_pengeluaran' => 0,
        ]);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap created successfully',
        ], 201);
    }

    public function rekapUpdate(Request $request, $id)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $data = $modelClass::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $summary = $this->rekapSummary($data->id);
        $hasDetails = $summary['jumlah_data'] > 0;
        $input = $this->rekapInput($request);

        $validator = Validator::make($input, [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique($rekapTable, 'nama')->ignore($data->id),
            ],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'jumlah_sementara' => $hasDetails
                ? ['nullable', 'integer', 'min:0']
                : ['required', 'integer', 'min:0'],
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['bulan_tahun'] .= '-01';

        if ($hasDetails) {
            unset($validated['jumlah_sementara']);
        }

        $data->update($validated);
        $this->applyRekapSummary($data, $summary);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap updated successfully',
        ]);
    }

    public function rekapShow($id)
    {
        $modelClass = $this->rekapModelClass();
        $data = $modelClass::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $this->applyRekapSummary($data, $this->rekapSummary($data->id));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapBulkUpdate(Request $request)
    {
        $modelClass = $this->rekapModelClass();

        $rekapIdRules = $this->requiresRekapForPengeluaran()
            ? ['required', Rule::exists((new $modelClass)->getTable(), 'id')]
            : ['present', 'nullable', Rule::exists((new $modelClass)->getTable(), 'id')];

        $validator = Validator::make($request->all(), [
            'rekap_id' => $rekapIdRules,
            'all_pages' => 'nullable|boolean',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $ids = $request->boolean('all_pages')
            ? $this->newRekapBulkPengeluaranQuery(new Request($request->input('filters', [])))
                ->pluck($this->pengeluaranTable().'.id')
                ->unique()
                ->values()
                ->all()
            : $this->newRekapBulkPengeluaranQuery(new Request)
                ->whereIn(
                    $this->pengeluaranTable().'.id',
                    collect($request->input('ids', []))->filter()->unique()->values()->all()
                )
                ->pluck($this->pengeluaranTable().'.id')
                ->unique()
                ->values()
                ->all();

        if (empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => [
                    'ids' => ['Pilih data pengeluaran terlebih dahulu.'],
                ],
            ], 422);
        }

        $updated = DB::transaction(function () use ($ids, $request) {
            $this->lockAllRekapRows();

            $oldRekapIds = DB::table($this->pengeluaranTable())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->pluck('rekap_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $affectedRekapIds = [
                ...$oldRekapIds,
                $request->rekap_id,
            ];

            $emptyFallbackAmounts = $this->snapshotRekapTotals($oldRekapIds);

            $updated = DB::table($this->pengeluaranTable())
                ->whereIn('id', $ids)
                ->update([
                    'rekap_id' => $request->rekap_id,
                    'updated_at' => now(),
                ]);

            $this->validateAndSyncRekapTemporary(
                $affectedRekapIds,
                $emptyFallbackAmounts
            );

            return $updated;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'updated' => $updated,
            ],
            'message' => $request->filled('rekap_id')
                ? "{$updated} data berhasil diupdate ke rekap."
                : "{$updated} data berhasil dibatalkan dari rekap.",
        ]);
    }

    public function rekapRelease($id)
    {
        if ($this->requiresRekapForPengeluaran()) {
            return response()->json([
                'status' => false,
                'message' => 'Data Pengeluaran Kegiatan wajib berada dalam rekap.',
            ], 422);
        }

        $modelClass = $this->rekapModelClass();
        $rekap = $modelClass::find($id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $updated = DB::transaction(function () use ($rekap) {
            $this->lockRekapRows([$rekap->id]);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$rekap->id]);

            $updated = DB::table($this->pengeluaranTable())
                ->where('rekap_id', $rekap->id)
                ->update([
                    'rekap_id' => null,
                    'updated_at' => now(),
                ]);

            $this->validateAndSyncRekapTemporary(
                [$rekap->id],
                $emptyFallbackAmounts
            );

            return $updated;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'updated' => $updated,
            ],
            'message' => "{$updated} data berhasil dibatalkan dari rekap {$rekap->nama}.",
        ]);
    }

    public function rekapDestroy($id)
    {
        $modelClass = $this->rekapModelClass();
        $rekap = $modelClass::find($id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $jumlahData = $this->newRekapPengeluaranQuery()
            ->where($this->pengeluaranTable().'.rekap_id', $rekap->id)
            ->count();

        if ($jumlahData > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap hanya dapat dihapus ketika jumlah datanya 0.',
            ], 422);
        }

        $nama = $rekap->nama;
        $rekap->delete();

        return response()->json([
            'status' => true,
            'message' => "Rekap {$nama} berhasil dihapus.",
        ]);
    }

    protected function joinRekap($query): void
    {
        $modelClass = $this->rekapModelClass();
        $query->leftJoin(
            (new $modelClass)->getTable().' as pengeluaran_rekap',
            'pengeluaran_rekap.id',
            '=',
            $this->pengeluaranTable().'.rekap_id'
        );
    }

    protected function applyRekapFilter($query, Request $request): void
    {
        if ($request->filled('rekap_id')) {
            $query->where($this->pengeluaranTable().'.rekap_id', $request->rekap_id);
        }
    }

    protected function applyPetugasFilter($query, Request $request, ?string $table = null): void
    {
        $tableName = $table ?? $this->pengeluaranTable();

        if (
            ! $request->filled('petugas_id')
            || ! Schema::hasColumn($tableName, 'petugas_id')
        ) {
            return;
        }

        $query->where("{$tableName}.petugas_id", $request->petugas_id);
    }

    protected function applyRekapPetugasFilter($query, Request $request, string $rekapTable): void
    {
        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($rekapTable, 'petugas_id')
        ) {
            $query->where("{$rekapTable}.petugas_id", $request->petugas_id);
        }
    }

    protected function rekapIdRules(): array
    {
        $modelClass = $this->rekapModelClass();

        return [
            'nullable',
            Rule::exists((new $modelClass)->getTable(), 'id'),
        ];
    }

    protected function savePengeluaranWithRekapValidation(Model $data): void
    {
        $oldRekapId = $data->exists ? $data->getOriginal('rekap_id') : null;

        DB::transaction(function () use ($data, $oldRekapId) {
            $affectedRekapIds = [$oldRekapId, $data->rekap_id];
            $this->lockRekapRows($affectedRekapIds);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$oldRekapId]);

            $data->save();
            $this->validateAndSyncRekapTemporary(
                $affectedRekapIds,
                $emptyFallbackAmounts
            );
        });
    }

    protected function deletePengeluaranWithRekapValidation(Model $data): void
    {
        $rekapId = $data->rekap_id;

        DB::transaction(function () use ($data, $rekapId) {
            $this->lockRekapRows([$rekapId]);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$rekapId]);

            $data->delete();
            $this->validateAndSyncRekapTemporary(
                [$rekapId],
                $emptyFallbackAmounts
            );
        });
    }

    protected function lockRekapRows(array $rekapIds): void
    {
        $ids = $this->normalizeRekapIds($rekapIds);

        if ($ids->isEmpty()) {
            return;
        }

        $modelClass = $this->rekapModelClass();
        $modelClass::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    protected function lockAllRekapRows(): void
    {
        $modelClass = $this->rekapModelClass();
        $modelClass::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    protected function snapshotRekapTotals(array $rekapIds): array
    {
        return $this->normalizeRekapIds($rekapIds)
            ->mapWithKeys(fn ($id) => [
                $id => $this->rekapSummary($id)['total_pengeluaran'],
            ])
            ->all();
    }

    protected function validateAndSyncRekapTemporary(
        array $rekapIds,
        array $emptyFallbackAmounts = []
    ): void {
        $ids = $this->normalizeRekapIds($rekapIds);

        if ($ids->isEmpty()) {
            return;
        }

        $modelClass = $this->rekapModelClass();
        $rekaps = $modelClass::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $rekap = $rekaps->get($id);

            if (! $rekap) {
                continue;
            }

            $summary = $this->rekapSummary($id);

            if (
                $rekap->jumlah_sementara === null
                && $summary['jumlah_data'] === 0
                && array_key_exists($id, $emptyFallbackAmounts)
            ) {
                $rekap->jumlah_sementara = (int) $emptyFallbackAmounts[$id];
                $rekap->save();
            }

            if ($rekap->jumlah_sementara === null) {
                continue;
            }

            $temporaryAmount = (int) $rekap->jumlah_sementara;
            $detailAmount = $summary['total_pengeluaran'];
            $amounts = $this->resolveRekapAmounts(
                $temporaryAmount,
                $summary['jumlah_data'],
                $detailAmount
            );

            if ($amounts['exceeds_temporary']) {
                throw ValidationException::withMessages([
                    'total' => [
                        'Total detail Rp '.number_format($detailAmount, 0, ',', '.')
                        .' melebihi jumlah sementara Rp '
                        .number_format($temporaryAmount, 0, ',', '.')
                        ." pada rekap {$rekap->nama}.",
                    ],
                ]);
            }

            if ($amounts['should_clear_temporary']) {
                $rekap->jumlah_sementara = null;
                $rekap->save();
            }
        }
    }

    protected function resolveRekapAmounts(
        ?int $temporaryAmount,
        int $detailCount,
        int $detailAmount
    ): array {
        return [
            'jumlah' => $detailCount > 0
                ? $detailAmount
                : ($temporaryAmount ?? 0),
            'is_jumlah_sementara' => $detailCount === 0,
            'selisih_sementara' => $temporaryAmount !== null
                ? max(0, $temporaryAmount - $detailAmount)
                : 0,
            'exceeds_temporary' => $temporaryAmount !== null
                && $detailAmount > $temporaryAmount,
            'should_clear_temporary' => $temporaryAmount !== null
                && $detailCount > 0
                && $detailAmount === $temporaryAmount,
        ];
    }

    private function rekapInput(Request $request): array
    {
        return [
            'nama' => trim((string) $request->input('nama')),
            'bulan_tahun' => $request->input('bulan_tahun'),
            'tanggal_rekap' => $request->input('tanggal_rekap'),
            'jumlah_sementara' => $request->input(
                'jumlah_sementara',
                $request->input('jumlah')
            ),
            'keterangan' => $request->input('keterangan'),
        ];
    }

    private function rekapSummaryQuery(Request $request)
    {
        $query = $this->newRekapPengeluaranQuery();
        $this->applyPetugasFilter($query, $request);

        return $query
            ->whereNotNull($this->pengeluaranTable().'.rekap_id')
            ->select([
                $this->pengeluaranTable().'.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw(
                    'COALESCE(SUM('.$this->pengeluaranTable().'.total), 0) as total_pengeluaran'
                ),
            ])
            ->groupBy($this->pengeluaranTable().'.rekap_id');
    }

    private function lpjSummaryQuery(Request $request)
    {
        $lpjTable = $this->lpjPengeluaranTable();

        if (! Schema::hasTable($lpjTable)) {
            return null;
        }

        $query = DB::table("{$lpjTable} as lpj_detail")
            ->whereNotNull('lpj_detail.rekap_id')
            ->select([
                'lpj_detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_lpj'),
                DB::raw('COALESCE(SUM(lpj_detail.total), 0) as total_lpj'),
            ]);

        $pegawaiTipe = $this->pegawaiTipeForLpj();
        if ($pegawaiTipe && Schema::hasColumn($lpjTable, 'pegawai_tipe')) {
            $query->where('lpj_detail.pegawai_tipe', $pegawaiTipe);
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($lpjTable, 'petugas_id')
        ) {
            $query->where('lpj_detail.petugas_id', $request->petugas_id);
        }

        return $query->groupBy('lpj_detail.rekap_id');
    }

    private function rekapSummary($rekapId): array
    {
        $summary = $this->newRekapPengeluaranQuery()
            ->where($this->pengeluaranTable().'.rekap_id', $rekapId)
            ->select([
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw(
                    'COALESCE(SUM('.$this->pengeluaranTable().'.total), 0) as total_pengeluaran'
                ),
            ])
            ->first();

        return [
            'jumlah_data' => (int) ($summary->jumlah_data ?? 0),
            'total_pengeluaran' => (int) ($summary->total_pengeluaran ?? 0),
        ];
    }

    private function applyRekapSummary($data, array $summary): void
    {
        $amounts = $this->resolveRekapAmounts(
            $data->jumlah_sementara === null ? null : (int) $data->jumlah_sementara,
            (int) $summary['jumlah_data'],
            (int) $summary['total_pengeluaran']
        );

        $data->jumlah_data = (int) $summary['jumlah_data'];
        $data->total_pengeluaran = (int) $summary['total_pengeluaran'];
        $data->jumlah = $amounts['jumlah'];
        $data->is_jumlah_sementara = $amounts['is_jumlah_sementara'];
        $data->selisih_sementara = $amounts['selisih_sementara'];
    }

    private function castRekapSummary($data): void
    {
        $data->jumlah_data = (int) $data->jumlah_data;
        $data->total_pengeluaran = (int) $data->total_pengeluaran;
        $data->jumlah = (int) $data->jumlah;
        $data->is_jumlah_sementara = (bool) $data->is_jumlah_sementara;
        $data->selisih_sementara = (int) $data->selisih_sementara;
        $data->jumlah_lpj = (int) ($data->jumlah_lpj ?? 0);
        $data->total_lpj = (int) ($data->total_lpj ?? 0);
        $data->lpj_sama_dengan_rab = (bool) ($data->lpj_sama_dengan_rab ?? false);
    }

    private function effectiveAmountSql(string $rekapTable): string
    {
        return "CASE
            WHEN COALESCE(rekap_summary.jumlah_data, 0) > 0
                THEN COALESCE(rekap_summary.total_pengeluaran, 0)
            ELSE COALESCE({$rekapTable}.jumlah_sementara, 0)
        END";
    }

    private function temporaryDifferenceSql(string $rekapTable): string
    {
        return "CASE
            WHEN {$rekapTable}.jumlah_sementara IS NOT NULL
                AND {$rekapTable}.jumlah_sementara
                    > COALESCE(rekap_summary.total_pengeluaran, 0)
                THEN {$rekapTable}.jumlah_sementara
                    - COALESCE(rekap_summary.total_pengeluaran, 0)
            ELSE 0
        END";
    }

    private function effectiveLpjAmountSql(string $rekapTable, bool $useFilteredRabAmount = false): string
    {
        $sameAsRabAmount = $useFilteredRabAmount
            ? $this->effectiveAmountSql($rekapTable)
            : "COALESCE(NULLIF(lpj_status.total_lpj, 0), {$this->effectiveAmountSql($rekapTable)})";

        return "COALESCE(
            CASE
                WHEN COALESCE(lpj_summary.jumlah_lpj, 0) > 0
                    THEN COALESCE(lpj_summary.total_lpj, 0)
                WHEN COALESCE(lpj_status.sama_dengan_rab, 0) = 1
                    THEN {$sameAsRabAmount}
                ELSE 0
            END,
            0
        )";
    }

    private function lpjPengeluaranTable(): string
    {
        return $this->pengeluaranTable().'_lpj';
    }

    private function lpjModuleKey(string $rekapTable): ?string
    {
        return match ($rekapTable) {
            'keuangan_pengeluaran_dosen_rekap' => 'tatap_muka',
            'keuangan_pengeluaran_dosen_kegiatan_rekap' => 'kegiatan',
            'keuangan_pengeluaran_rumah_tangga_rekap' => 'rumah_tangga',
            'keuangan_pengeluaran_dosen_bulanan_rekap' => 'dosen_bulanan',
            'keuangan_pengeluaran_staff_bulanan_rekap' => 'staff_bulanan',
            default => null,
        };
    }

    private function pegawaiTipeForLpj(): ?string
    {
        return defined(static::class.'::PEGAWAI_TIPE') ? static::PEGAWAI_TIPE : null;
    }

    private function normalizeRekapIds(array $rekapIds)
    {
        return collect($rekapIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
    }
}
