<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait ManagesPengeluaranRekap
{
    abstract protected function rekapModelClass(): string;

    abstract protected function pengeluaranTable(): string;

    abstract protected function newRekapPengeluaranQuery();

    abstract protected function newRekapBulkPengeluaranQuery(Request $request);

    public function rekapIndex(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass())->getTable();

        $query = $modelClass::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('keterangan', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('bulan_tahun', (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('bulan_tahun', (int) $request->tahun);
        }

        $sortColumns = [
            'id' => "{$rekapTable}.id",
            'nama' => "{$rekapTable}.nama",
            'created_at' => "{$rekapTable}.created_at",
        ];
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? "{$rekapTable}.id", $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $this->appendRekapSummaries($data->getCollection());

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapStore(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass())->getTable();
        $input = [
            'nama' => trim((string) $request->input('nama')),
            'bulan_tahun' => $request->input('bulan_tahun'),
            'keterangan' => $request->input('keterangan'),
        ];

        $validator = Validator::make($input, [
            'nama' => ['required', 'string', 'max:255', Rule::unique($rekapTable, 'nama')],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
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

        $data = $modelClass::create($validated);

        $data->jumlah_data = 0;
        $data->total_pengeluaran = 0;

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap created successfully',
        ], 201);
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

        $summary = $this->rekapSummary($data->id);
        $data->jumlah_data = $summary['jumlah_data'];
        $data->total_pengeluaran = $summary['total_pengeluaran'];

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapBulkUpdate(Request $request)
    {
        $modelClass = $this->rekapModelClass();

        $validator = Validator::make($request->all(), [
            'rekap_id' => ['present', 'nullable', Rule::exists((new $modelClass())->getTable(), 'id')],
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
                ->pluck($this->pengeluaranTable() . '.id')
                ->unique()
                ->values()
                ->all()
            : $this->newRekapBulkPengeluaranQuery(new Request())
                ->whereIn(
                    $this->pengeluaranTable() . '.id',
                    collect($request->input('ids', []))->filter()->unique()->values()->all()
                )
                ->pluck($this->pengeluaranTable() . '.id')
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

        $updated = DB::table($this->pengeluaranTable())
            ->whereIn('id', $ids)
            ->update([
                'rekap_id' => $request->rekap_id,
                'updated_at' => now(),
            ]);

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
        $modelClass = $this->rekapModelClass();
        $rekap = $modelClass::find($id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $updated = DB::table($this->pengeluaranTable())
            ->where('rekap_id', $rekap->id)
            ->update([
                'rekap_id' => null,
                'updated_at' => now(),
            ]);

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

        $jumlahData = DB::table($this->pengeluaranTable())
            ->where('rekap_id', $rekap->id)
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
            (new $modelClass())->getTable() . ' as pengeluaran_rekap',
            'pengeluaran_rekap.id',
            '=',
            $this->pengeluaranTable() . '.rekap_id'
        );
    }

    protected function applyRekapFilter($query, Request $request): void
    {
        if ($request->filled('rekap_id')) {
            $query->where($this->pengeluaranTable() . '.rekap_id', $request->rekap_id);
        }
    }

    protected function rekapIdRules(): array
    {
        $modelClass = $this->rekapModelClass();

        return [
            'nullable',
            Rule::exists((new $modelClass())->getTable(), 'id'),
        ];
    }

    private function appendRekapSummaries($rekapCollection): void
    {
        $ids = $rekapCollection->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $summary = $this->newRekapPengeluaranQuery()
            ->whereIn($this->pengeluaranTable() . '.rekap_id', $ids)
            ->select([
                $this->pengeluaranTable() . '.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(' . $this->pengeluaranTable() . '.total), 0) as total_pengeluaran'),
            ])
            ->groupBy($this->pengeluaranTable() . '.rekap_id')
            ->get()
            ->keyBy('rekap_id');

        $rekapCollection->transform(function ($item) use ($summary) {
            $itemSummary = $summary->get($item->id);
            $item->jumlah_data = (int) ($itemSummary->jumlah_data ?? 0);
            $item->total_pengeluaran = (int) ($itemSummary->total_pengeluaran ?? 0);

            return $item;
        });
    }

    private function rekapSummary($rekapId): array
    {
        $summary = $this->newRekapPengeluaranQuery()
            ->where($this->pengeluaranTable() . '.rekap_id', $rekapId)
            ->select([
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(' . $this->pengeluaranTable() . '.total), 0) as total_pengeluaran'),
            ])
            ->first();

        return [
            'jumlah_data' => (int) ($summary->jumlah_data ?? 0),
            'total_pengeluaran' => (int) ($summary->total_pengeluaran ?? 0),
        ];
    }
}
