<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PiutangController extends Controller
{
    public function index(Request $request)
    {
        if ($request->input('group_by') === 'pegawai') {
            return $this->pegawaiIndex($request);
        }

        $query = $this->baseQuery();
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $limit = (int) $request->get('limit', 10);
        $data = $limit === 0 ? $query->get() : $query->paginate($limit);
        $this->transformRows($data instanceof \Illuminate\Pagination\AbstractPaginator
            ? $data->getCollection()
            : $data);

        return response()->json([
            'status' => true,
            'data' => $data,
            'summary' => $this->summary($request),
            'message' => 'Data piutang berhasil dimuat.',
        ]);
    }

    public function pegawaiDetail($pegawai)
    {
        $pegawaiId = (int) $pegawai;

        $pegawaiRequest = request();
        $pegawaiRequest->merge(['pegawai_id' => $pegawaiId]);

        $summary = $this->groupedPegawaiBaseQuery($pegawaiRequest)->first();

        if (! $summary) {
            return response()->json([
                'status' => false,
                'message' => 'Data piutang pegawai tidak ditemukan.',
            ], 404);
        }

        $piutang = $this->baseQuery()
            ->where('piutang.pegawai_id', $pegawaiId)
            ->orderByDesc('piutang.tanggal')
            ->orderByDesc('piutang.id')
            ->get();

        $this->transformRows($piutang);

        $pembayaran = $this->paymentsForPiutangIds($piutang->pluck('id')->all());

        $piutang->transform(function ($row) use ($pembayaran) {
            $row->pembayaran = $pembayaran->get((int) $row->id, collect())->values();

            return $row;
        });

        $summary = $this->transformGroupedRow($summary);
        $summary->piutang = $piutang;

        return response()->json([
            'status' => true,
            'data' => $summary,
            'message' => 'Data piutang pegawai berhasil dimuat.',
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        if (! $this->pegawaiInScope((int) $payload['pegawai_id'])) {
            return response()->json([
                'status' => false,
                'message' => [
                    'pegawai_id' => ['Pegawai tidak sesuai scope navbar aktif.'],
                ],
            ], 422);
        }

        $payload['default_cicilan'] = (int) ($payload['default_cicilan'] ?? 0);
        $payload['created_by'] = $request->user()?->id;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        $id = DB::table('keuangan_piutang')->insertGetId($payload);

        return response()->json([
            'status' => true,
            'data' => ['id' => $id],
            'message' => 'Piutang berhasil disimpan.',
        ], 201);
    }

    public function show($id)
    {
        $data = $this->findWithSummary((int) $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Data piutang tidak ditemukan.',
            ], 404);
        }

        $data->pembayaran = $this->payments((int) $data->id);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data piutang berhasil dimuat.',
        ]);
    }

    public function update(Request $request, $id)
    {
        $existing = $this->findWithSummary((int) $id);

        if (! $existing) {
            return response()->json([
                'status' => false,
                'message' => 'Data piutang tidak ditemukan.',
            ], 404);
        }

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        if (! $this->pegawaiInScope((int) $payload['pegawai_id'])) {
            return response()->json([
                'status' => false,
                'message' => [
                    'pegawai_id' => ['Pegawai tidak sesuai scope navbar aktif.'],
                ],
            ], 422);
        }

        try {
            DB::transaction(function () use ($id, $payload) {
                $piutang = DB::table('keuangan_piutang')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $piutang) {
                    throw ValidationException::withMessages([
                        'id' => ['Data piutang tidak ditemukan.'],
                    ]);
                }

                $paid = $this->paidAmount((int) $piutang->id);

                if ((int) $payload['nominal'] < $paid) {
                    throw ValidationException::withMessages([
                        'nominal' => ['Nominal piutang tidak boleh lebih kecil dari total pembayaran.'],
                    ]);
                }

                $payload['default_cicilan'] = (int) ($payload['default_cicilan'] ?? 0);
                $payload['updated_at'] = now();

                DB::table('keuangan_piutang')
                    ->where('id', $piutang->id)
                    ->update($payload);
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Piutang berhasil diperbarui.',
        ]);
    }

    public function destroy($id)
    {
        $existing = $this->findWithSummary((int) $id);

        if (! $existing) {
            return response()->json([
                'status' => false,
                'message' => 'Data piutang tidak ditemukan.',
            ], 404);
        }

        $hasPayments = DB::table('keuangan_piutang_pembayaran')
            ->where('piutang_id', $id)
            ->exists();

        if ($hasPayments) {
            return response()->json([
                'status' => false,
                'message' => [
                    'id' => ['Piutang yang sudah memiliki pembayaran tidak dapat dihapus.'],
                ],
            ], 422);
        }

        DB::table('keuangan_piutang')->where('id', $id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Piutang berhasil dihapus.',
        ]);
    }

    public function storePembayaran(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        if (! $this->findWithSummary((int) $id)) {
            return response()->json([
                'status' => false,
                'message' => 'Data piutang tidak ditemukan.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($id, $payload, $request) {
                $piutang = DB::table('keuangan_piutang')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (! $piutang || ! $this->pegawaiInScope((int) $piutang->pegawai_id)) {
                    throw ValidationException::withMessages([
                        'id' => ['Data piutang tidak ditemukan.'],
                    ]);
                }

                $sisa = (int) $piutang->nominal - $this->paidAmount((int) $piutang->id);

                if ($sisa <= 0) {
                    throw ValidationException::withMessages([
                        'nominal' => ['Piutang sudah lunas.'],
                    ]);
                }

                if ((int) $payload['nominal'] > $sisa) {
                    throw ValidationException::withMessages([
                        'nominal' => ['Nominal pembayaran tidak boleh melebihi sisa piutang.'],
                    ]);
                }

                DB::table('keuangan_piutang_pembayaran')->insert([
                    'piutang_id' => $piutang->id,
                    'tanggal' => $payload['tanggal'],
                    'nominal' => (int) $payload['nominal'],
                    'jenis' => 'bayar_langsung',
                    'pengeluaran_id' => null,
                    'keterangan' => $payload['keterangan'] ?? null,
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Pembayaran piutang berhasil disimpan.',
        ], 201);
    }

    private function pegawaiIndex(Request $request)
    {
        $query = $this->groupedPegawaiBaseQuery($request);
        $this->applyGroupedSorting($query, $request);

        $limit = (int) $request->get('limit', 10);
        $data = $limit === 0 ? $query->get() : $query->paginate($limit);
        $this->transformGroupedRows($data instanceof \Illuminate\Pagination\AbstractPaginator
            ? $data->getCollection()
            : $data);

        return response()->json([
            'status' => true,
            'data' => $data,
            'summary' => $this->summary($request, true),
            'message' => 'Data piutang pegawai berhasil dimuat.',
        ]);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'nominal' => ['required', 'integer', 'min:1'],
            'default_cicilan' => ['nullable', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function baseQuery()
    {
        $paymentSums = DB::table('keuangan_piutang_pembayaran')
            ->select([
                'piutang_id',
                DB::raw('COALESCE(SUM(nominal), 0) as total_terbayar'),
            ])
            ->groupBy('piutang_id');

        $query = DB::table('keuangan_piutang as piutang')
            ->join('pegawai', 'pegawai.id', '=', 'piutang.pegawai_id')
            ->leftJoinSub($paymentSums, 'pembayaran', 'pembayaran.piutang_id', '=', 'piutang.id')
            ->select([
                'piutang.id',
                'piutang.pegawai_id',
                'pegawai.kode as kode_pegawai',
                'pegawai.nama as nama_pegawai',
                'pegawai.tipe as tipe_pegawai',
                'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
                'pegawai.status as status_pegawai',
                'piutang.tanggal',
                'piutang.nominal',
                'piutang.default_cicilan',
                'piutang.keterangan',
                'piutang.created_by',
                'piutang.created_at',
                'piutang.updated_at',
                DB::raw('COALESCE(pembayaran.total_terbayar, 0) as total_terbayar'),
                DB::raw('(piutang.nominal - COALESCE(pembayaran.total_terbayar, 0)) as sisa'),
            ]);

        Helper::applyGenderScope($query, 'pegawai.jenis_kelamin');

        return $query;
    }

    private function groupedPegawaiBaseQuery(Request $request)
    {
        $piutangRows = $this->baseQuery();
        $this->applyFilters($piutangRows, $request, false);
        $defaultCicilanSql = $this->groupedDefaultCicilanSql($request);

        $query = DB::query()
            ->fromSub($piutangRows, 'piutang_rows')
            ->select([
                DB::raw('pegawai_id as id'),
                'pegawai_id',
                'kode_pegawai',
                'nama_pegawai',
                'tipe_pegawai',
                'jenis_kelamin_pegawai',
                'status_pegawai',
                DB::raw('MIN(tanggal) as tanggal_awal'),
                DB::raw('MAX(tanggal) as tanggal_terakhir'),
                DB::raw('MAX(tanggal) as tanggal'),
                DB::raw('COUNT(*) as jumlah_piutang'),
                DB::raw('COALESCE(SUM(nominal), 0) as nominal'),
                DB::raw('COALESCE(SUM(total_terbayar), 0) as total_terbayar'),
                DB::raw('COALESCE(SUM(sisa), 0) as sisa'),
                DB::raw("{$defaultCicilanSql} as default_cicilan"),
                DB::raw('COALESCE(SUM(CASE WHEN sisa > 0 THEN 1 ELSE 0 END), 0) as jumlah_aktif'),
            ])
            ->groupBy(
                'pegawai_id',
                'kode_pegawai',
                'nama_pegawai',
                'tipe_pegawai',
                'jenis_kelamin_pegawai',
                'status_pegawai'
            );

        if ($request->filled('status')) {
            if ($request->input('status') === 'aktif') {
                $query->havingRaw('COALESCE(SUM(sisa), 0) > 0');
            } elseif ($request->input('status') === 'lunas') {
                $query->havingRaw('COALESCE(SUM(sisa), 0) <= 0');
            }
        }

        return $query;
    }

    private function groupedDefaultCicilanSql(Request $request): string
    {
        if ($this->cicilanMode($request) === 'gabung') {
            return 'COALESCE(MAX(CASE WHEN sisa > 0 THEN default_cicilan ELSE 0 END), 0)';
        }

        return 'COALESCE(SUM(CASE WHEN sisa > 0 THEN default_cicilan ELSE 0 END), 0)';
    }

    private function cicilanMode(Request $request): string
    {
        return $request->input('cicilan_mode') === 'gabung' ? 'gabung' : 'pisah';
    }

    private function applyFilters($query, Request $request, bool $includeStatus = true): void
    {
        if ($request->filled('pegawai_id')) {
            $query->where('piutang.pegawai_id', $request->integer('pegawai_id'));
        }

        if ($includeStatus && $request->filled('status')) {
            if ($request->input('status') === 'aktif') {
                $query->whereRaw('(piutang.nominal - COALESCE(pembayaran.total_terbayar, 0)) > 0');
            } elseif ($request->input('status') === 'lunas') {
                $query->whereRaw('(piutang.nominal - COALESCE(pembayaran.total_terbayar, 0)) <= 0');
            }
        }

        if ($request->filled('tanggal_mulai')) {
            $query->where('piutang.tanggal', '>=', $request->input('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->where('piutang.tanggal', '<=', $request->input('tanggal_selesai'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($scope) use ($term) {
                $scope->where('pegawai.nama', 'LIKE', "%{$term}%")
                    ->orWhere('pegawai.kode', 'LIKE', "%{$term}%")
                    ->orWhere('piutang.keterangan', 'LIKE', "%{$term}%");
            });
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortColumns = [
            'tanggal' => 'piutang.tanggal',
            'pegawai' => 'pegawai.nama',
            'nama_pegawai' => 'pegawai.nama',
            'nominal' => 'piutang.nominal',
            'default_cicilan' => 'piutang.default_cicilan',
            'total_terbayar' => 'total_terbayar',
            'sisa' => 'sisa',
            'created_at' => 'piutang.created_at',
        ];

        $sortKey = $request->input('sort_key', 'tanggal');
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query
            ->orderBy($sortColumns[$sortKey] ?? 'piutang.tanggal', $sortOrder)
            ->orderByDesc('piutang.id');
    }

    private function applyGroupedSorting($query, Request $request): void
    {
        $sortColumns = [
            'tanggal' => 'tanggal_terakhir',
            'pegawai' => 'nama_pegawai',
            'nama_pegawai' => 'nama_pegawai',
            'nominal' => 'nominal',
            'default_cicilan' => 'default_cicilan',
            'total_terbayar' => 'total_terbayar',
            'sisa' => 'sisa',
            'jumlah_piutang' => 'jumlah_piutang',
        ];

        $sortKey = $request->input('sort_key', 'tanggal');
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query
            ->orderBy($sortColumns[$sortKey] ?? 'tanggal_terakhir', $sortOrder)
            ->orderBy('nama_pegawai');
    }

    private function findWithSummary(int $id)
    {
        $data = $this->baseQuery()
            ->where('piutang.id', $id)
            ->first();

        if (! $data) {
            return null;
        }

        return $this->transformRow($data);
    }

    private function payments(int $piutangId)
    {
        return $this->paymentsForPiutangIds([$piutangId])->get($piutangId, collect())->values();
    }

    private function paymentsForPiutangIds(array $piutangIds)
    {
        if (empty($piutangIds)) {
            return collect();
        }

        return DB::table('keuangan_piutang_pembayaran as pembayaran')
            ->leftJoin('users', 'users.id', '=', 'pembayaran.created_by')
            ->leftJoin('keuangan_pengeluaran_pegawai_bulanan as pengeluaran', 'pengeluaran.id', '=', 'pembayaran.pengeluaran_id')
            ->whereIn('pembayaran.piutang_id', $piutangIds)
            ->orderByDesc('pembayaran.tanggal')
            ->orderByDesc('pembayaran.id')
            ->select([
                'pembayaran.id',
                'pembayaran.piutang_id',
                'pembayaran.tanggal',
                'pembayaran.nominal',
                'pembayaran.jenis',
                'pembayaran.pengeluaran_id',
                'pembayaran.keterangan',
                'pembayaran.created_by',
                'pembayaran.created_at',
                DB::raw("COALESCE(users.name, '-') as created_by_name"),
                'pengeluaran.rekap_id',
            ])
            ->get()
            ->map(function ($row) {
                $row->nominal = (int) $row->nominal;

                return $row;
            })
            ->groupBy('piutang_id');
    }

    private function summary(Request $request, bool $groupByPegawai = false): array
    {
        if ($groupByPegawai) {
            $rows = DB::query()
                ->fromSub($this->groupedPegawaiBaseQuery($request), 'pegawai_summary')
                ->selectRaw('
                    COUNT(*) as jumlah,
                    COALESCE(SUM(nominal), 0) as total_piutang,
                    COALESCE(SUM(total_terbayar), 0) as total_terbayar,
                    COALESCE(SUM(sisa), 0) as sisa
                ')
                ->first();

            return [
                'jumlah' => (int) ($rows->jumlah ?? 0),
                'total_piutang' => (int) ($rows->total_piutang ?? 0),
                'total_terbayar' => (int) ($rows->total_terbayar ?? 0),
                'sisa' => (int) ($rows->sisa ?? 0),
            ];
        }

        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $rows = DB::query()
            ->fromSub($query, 'piutang_summary')
            ->selectRaw('
                COUNT(*) as jumlah,
                COALESCE(SUM(nominal), 0) as total_piutang,
                COALESCE(SUM(total_terbayar), 0) as total_terbayar,
                COALESCE(SUM(sisa), 0) as sisa
            ')
            ->first();

        return [
            'jumlah' => (int) ($rows->jumlah ?? 0),
            'total_piutang' => (int) ($rows->total_piutang ?? 0),
            'total_terbayar' => (int) ($rows->total_terbayar ?? 0),
            'sisa' => (int) ($rows->sisa ?? 0),
        ];
    }

    private function transformRows($rows): void
    {
        $rows->transform(fn ($row) => $this->transformRow($row));
    }

    private function transformGroupedRows($rows): void
    {
        $rows->transform(fn ($row) => $this->transformGroupedRow($row));
    }

    private function transformRow($row)
    {
        $row->nominal = (int) $row->nominal;
        $row->default_cicilan = (int) $row->default_cicilan;
        $row->total_terbayar = (int) $row->total_terbayar;
        $row->sisa = (int) $row->sisa;
        $row->status = $row->sisa > 0 ? 'Aktif' : 'Lunas';

        return $row;
    }

    private function transformGroupedRow($row)
    {
        $row->id = (int) $row->pegawai_id;
        $row->pegawai_id = (int) $row->pegawai_id;
        $row->jumlah_piutang = (int) $row->jumlah_piutang;
        $row->jumlah_aktif = (int) $row->jumlah_aktif;
        $row->nominal = (int) $row->nominal;
        $row->default_cicilan = (int) $row->default_cicilan;
        $row->total_terbayar = (int) $row->total_terbayar;
        $row->sisa = (int) $row->sisa;
        $row->status = $row->sisa > 0 ? 'Aktif' : 'Lunas';

        return $row;
    }

    private function paidAmount(int $piutangId): int
    {
        return (int) DB::table('keuangan_piutang_pembayaran')
            ->where('piutang_id', $piutangId)
            ->sum('nominal');
    }

    private function pegawaiInScope(int $pegawaiId): bool
    {
        $query = DB::table('pegawai')->where('id', $pegawaiId);
        Helper::applyGenderScope($query, 'pegawai.jenis_kelamin');

        return $query->exists();
    }
}
