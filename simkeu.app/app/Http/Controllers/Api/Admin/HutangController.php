<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HutangController extends Controller
{
    private const MANAGE_ALL_ROLES = ['admin', 'keuangan', 'kabag'];

    public function index(Request $request)
    {
        $canManageAll = $this->canManageAll($request);
        $query = $this->baseFilteredQuery($request, $canManageAll)
            ->leftJoin('users', 'users.id', '=', 'hutang.petugas_id')
            ->select([
                'hutang.id',
                'hutang.petugas_id',
                DB::raw("COALESCE(users.name, '-') as petugas_name"),
                'hutang.pemberi_pinjaman',
                'hutang.tanggal',
                'hutang.tipe',
                'hutang.nominal',
                'hutang.keterangan',
                'hutang.created_at',
                'hutang.updated_at',
            ]);

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'LIKE', "%{$term}%")
                    ->orWhere('hutang.pemberi_pinjaman', 'LIKE', "%{$term}%")
                    ->orWhere('hutang.keterangan', 'LIKE', "%{$term}%");
            });
        }

        $sortable = ['tanggal', 'tipe', 'nominal', 'petugas_name', 'pemberi_pinjaman', 'created_at'];
        $sortKey = in_array($request->input('sort_key'), $sortable, true)
            ? $request->input('sort_key')
            : 'tanggal';
        $sortColumn = $sortKey === 'petugas_name' ? 'users.name' : "hutang.{$sortKey}";
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumn, $sortOrder)->orderByDesc('hutang.id');

        $limit = (int) $request->get('limit', 10);
        $data = $limit === 0 ? $query->get() : $query->paginate($limit);
        $lenderSummary = $this->lenderSummary($request, $canManageAll);

        return response()->json([
            'status' => true,
            'data' => $data,
            'summary' => $this->summaryFromLenderRows($lenderSummary),
            'lender_summary' => $lenderSummary,
            'lender_options' => $request->boolean('include_options') ? $this->lenderOptions($request, $canManageAll) : null,
            'can_manage_all' => $canManageAll,
            'message' => 'Data hutang berhasil dimuat.',
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
        $payload['pemberi_pinjaman'] = trim($payload['pemberi_pinjaman']);
        $payload['petugas_id'] = $this->canManageAll($request)
            ? (int) $payload['petugas_id']
            : $request->user()->id;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        $id = DB::table('keuangan_hutang')->insertGetId($payload);

        return response()->json([
            'status' => true,
            'data' => ['id' => $id],
            'message' => 'Transaksi hutang berhasil disimpan.',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $data = $this->findAuthorized($request, (int) $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Data hutang tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data hutang berhasil dimuat.',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findAuthorized($request, (int) $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Data hutang tidak ditemukan.',
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
        $payload['pemberi_pinjaman'] = trim($payload['pemberi_pinjaman']);
        $payload['petugas_id'] = $this->canManageAll($request)
            ? (int) $payload['petugas_id']
            : $request->user()->id;
        $payload['updated_at'] = now();

        DB::table('keuangan_hutang')->where('id', $data->id)->update($payload);

        return response()->json([
            'status' => true,
            'message' => 'Transaksi hutang berhasil diperbarui.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $data = $this->findAuthorized($request, (int) $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Data hutang tidak ditemukan.',
            ], 404);
        }

        DB::table('keuangan_hutang')->where('id', $data->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Transaksi hutang berhasil dihapus.',
        ]);
    }

    private function validator(Request $request)
    {
        $rules = [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'pemberi_pinjaman' => ['required', 'string', 'max:150', 'not_regex:/^\s*$/'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tipe' => ['required', Rule::in(['hutang', 'pelunasan'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $this->canManageAll($request)) {
            $rules['petugas_id'] = ['nullable', 'integer'];
        }

        return Validator::make($request->all(), $rules);
    }

    private function findAuthorized(Request $request, int $id)
    {
        $query = DB::table('keuangan_hutang as hutang')
            ->leftJoin('users', 'users.id', '=', 'hutang.petugas_id')
            ->select([
                'hutang.*',
                DB::raw("COALESCE(users.name, '-') as petugas_name"),
            ])
            ->where('hutang.id', $id);

        if (! $this->canManageAll($request)) {
            $query->where('hutang.petugas_id', $request->user()->id);
        }

        return $query->first();
    }

    private function baseFilteredQuery(Request $request, bool $canManageAll)
    {
        $query = DB::table('keuangan_hutang as hutang');

        if (! $canManageAll) {
            $query->where('hutang.petugas_id', $request->user()->id);
        } elseif ($request->filled('petugas_id')) {
            $query->where('hutang.petugas_id', $request->integer('petugas_id'));
        }

        if ($request->filled('pemberi_pinjaman')) {
            $query->where('hutang.pemberi_pinjaman', $request->input('pemberi_pinjaman'));
        }

        if ($request->filled('tipe')) {
            $query->where('hutang.tipe', $request->input('tipe'));
        }

        if ($request->filled('tanggal_mulai')) {
            $query->where('hutang.tanggal', '>=', $request->input('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->where('hutang.tanggal', '<=', $request->input('tanggal_selesai'));
        }

        return $query;
    }

    private function summaryFromLenderRows($rows): array
    {
        $totalHutang = (int) $rows->sum('total_hutang');
        $totalPelunasan = (int) $rows->sum('total_pelunasan');


        return [
            'total_hutang' => $totalHutang,
            'total_pelunasan' => $totalPelunasan,
            'saldo' => $totalHutang - $totalPelunasan,
        ];
    }

    private function lenderSummary(Request $request, bool $canManageAll)
    {
        $query = $this->baseFilteredQuery($request, $canManageAll)
            ->selectRaw("
                hutang.pemberi_pinjaman,
                COALESCE(SUM(CASE WHEN hutang.tipe = 'hutang' THEN hutang.nominal ELSE 0 END), 0) as total_hutang,
                COALESCE(SUM(CASE WHEN hutang.tipe = 'pelunasan' THEN hutang.nominal ELSE 0 END), 0) as total_pelunasan
            ");

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->leftJoin('users', 'users.id', '=', 'hutang.petugas_id');
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'LIKE', "%{$term}%")
                    ->orWhere('hutang.pemberi_pinjaman', 'LIKE', "%{$term}%")
                    ->orWhere('hutang.keterangan', 'LIKE', "%{$term}%");
            });
        }

        return $query
            ->groupBy('hutang.pemberi_pinjaman')
            ->orderBy('hutang.pemberi_pinjaman')
            ->get()
            ->map(function ($row) {
                $totalHutang = (int) $row->total_hutang;
                $totalPelunasan = (int) $row->total_pelunasan;

                return [
                    'pemberi_pinjaman' => $row->pemberi_pinjaman,
                    'total_hutang' => $totalHutang,
                    'total_pelunasan' => $totalPelunasan,
                    'saldo' => $totalHutang - $totalPelunasan,
                ];
            });
    }

    private function lenderOptions(Request $request, bool $canManageAll)
    {
        $query = DB::table('keuangan_hutang')
            ->select('pemberi_pinjaman')
            ->whereNotNull('pemberi_pinjaman');

        if (! $canManageAll) {
            $query->where('petugas_id', $request->user()->id);
        } elseif ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->integer('petugas_id'));
        }

        return $query
            ->distinct()
            ->orderBy('pemberi_pinjaman')
            ->pluck('pemberi_pinjaman')
            ->values();
    }

    private function canManageAll(Request $request): bool
    {
        $role = strtolower((string) optional($request->user()->role)->name);

        return in_array($role, self::MANAGE_ALL_ROLES, true);
    }
}
