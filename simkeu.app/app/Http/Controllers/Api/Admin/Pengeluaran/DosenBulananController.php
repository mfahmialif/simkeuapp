<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\ExcelExport;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranPegawaiBulanan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class DosenBulananController extends Controller
{
    protected const PEGAWAI_TIPE = 'dosen';
    protected const MODULE_NAME = 'Barokah Dosen Bulanan';
    protected const PEGAWAI_LABEL = 'Dosen';
    protected const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];
    protected const REQUIRE_PERIODE = false;

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
        ]);

        $this->joinPegawaiDetail($query);
        $query->where('pegawai.tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);

        $stats = $this->stats($query);

        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applySorting($query, $request);

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $stats,
            'message' => static::MODULE_NAME . ' retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(false));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = new KeuanganPengeluaranPegawaiBulanan();
        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->findWithPegawai($data->id) ?? $data,
            'message' => static::MODULE_NAME . ' created successfully',
        ], 201);
    }

    public function show($id)
    {
        $data = $this->findWithPegawai($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME . ' not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => static::MODULE_NAME . ' retrieved successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = KeuanganPengeluaranPegawaiBulanan::find($id);

        if (! $data || ! $this->findWithPegawai($id)) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME . ' not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->findWithPegawai($data->id) ?? $data,
            'message' => static::MODULE_NAME . ' updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranPegawaiBulanan::find($id);

        if (! $data || ! $this->findWithPegawai($id)) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME . ' not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => static::MODULE_NAME . ' deleted successfully',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.tanggal',
            'keuangan_pengeluaran_pegawai_bulanan.bulan',
            'keuangan_pengeluaran_pegawai_bulanan.tahun',
            'pegawai.kode as kode_pegawai',
            'pegawai.nama as nama_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin',
            'prodi.nama as prodi',
            'staff.jabatan as jabatan',
            'keuangan_pengeluaran_pegawai_bulanan.hari',
            'keuangan_pengeluaran_pegawai_bulanan.barokah_harian',
            DB::raw('(keuangan_pengeluaran_pegawai_bulanan.barokah_harian * keuangan_pengeluaran_pegawai_bulanan.hari) as subtotal_harian'),
            'keuangan_pengeluaran_pegawai_bulanan.barokah_bulanan',
            'keuangan_pengeluaran_pegawai_bulanan.total',
            'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'keuangan_pengeluaran_pegawai_bulanan.keterangan',
        ]);

        $this->joinPegawaiDetail($query);
        $query->where('pegawai.tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);

        $data = $query
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan ' . static::MODULE_NAME . '.xlsx');
    }

    protected function joinPegawaiDetail($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    private function findWithPegawai($id)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
        ]);

        $this->joinPegawaiDetail($query);
        $query->where('pegawai.tipe', static::PEGAWAI_TIPE);

        return $query->where('keuangan_pengeluaran_pegawai_bulanan.id', $id)->first();
    }

    protected function applySearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->orWhere('keuangan_pengeluaran_pegawai_bulanan.tanggal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.bulan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.tahun', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.hari', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_harian', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_bulanan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.nama', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.jenis_kelamin', 'LIKE', "%{$search}%")
                ->orWhere('prodi.nama', 'LIKE', "%{$search}%")
                ->orWhere('staff.jabatan', 'LIKE', "%{$search}%");
        });
    }

    protected function applyPegawaiFilter($query, Request $request): void
    {
        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_id', $request->pegawai_id);
        }
    }

    protected function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_pegawai_bulanan.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai) {
            $query->where('keuangan_pengeluaran_pegawai_bulanan.tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalAkhir) {
            $query->where('keuangan_pengeluaran_pegawai_bulanan.tanggal', '<=', $tanggalAkhir);
        }
    }

    protected function applyPeriodFilter($query, Request $request): void
    {
        if ($request->filled('bulan')) {
            $query->where('keuangan_pengeluaran_pegawai_bulanan.bulan', (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->where('keuangan_pengeluaran_pegawai_bulanan.tahun', (int) $request->tahun);
        }
    }

    protected function applySorting($query, Request $request): void
    {
        $sortColumns = [
            'id' => 'keuangan_pengeluaran_pegawai_bulanan.id',
            'tanggal' => 'keuangan_pengeluaran_pegawai_bulanan.tanggal',
            'bulan' => 'keuangan_pengeluaran_pegawai_bulanan.bulan',
            'tahun' => 'keuangan_pengeluaran_pegawai_bulanan.tahun',
            'pegawai_id' => 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id',
            'kode_pegawai' => 'pegawai.kode',
            'nama_pegawai' => 'pegawai.nama',
            'hari' => 'keuangan_pengeluaran_pegawai_bulanan.hari',
            'barokah_harian' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_harian',
            'barokah_bulanan' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_bulanan',
            'total' => 'keuangan_pengeluaran_pegawai_bulanan.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'created_at' => 'keuangan_pengeluaran_pegawai_bulanan.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_pegawai_bulanan.id', $sortOrder);
    }

    protected function stats($query): array
    {
        $dateColumn = 'keuangan_pengeluaran_pegawai_bulanan.tanggal';
        $today = now();
        $todayDate = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $weekEnd = $today->copy()->endOfWeek()->toDateString();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        return [
            'hari_ini' => $this->periodStats(
                $query,
                fn ($periodQuery) => $periodQuery->whereDate($dateColumn, $todayDate)
            ),
            'mingguan' => $this->periodStats(
                $query,
                fn ($periodQuery) => $periodQuery->whereBetween($dateColumn, [$weekStart, $weekEnd])
            ),
            'bulanan' => $this->periodStats(
                $query,
                fn ($periodQuery) => $periodQuery->whereBetween($dateColumn, [$monthStart, $monthEnd])
            ),
            'keseluruhan' => $this->periodStats($query),
        ];
    }

    protected function periodStats($baseQuery, ?callable $period = null): array
    {
        $query = clone $baseQuery;

        if ($period) {
            $period($query);
        }

        return [
            'total' => (int) (clone $query)->sum('keuangan_pengeluaran_pegawai_bulanan.total'),
            'jumlah' => (int) $query->count(),
        ];
    }

    protected function rules(bool $isUpdate): array
    {
        return [
            'tanggal' => 'required|date',
            'bulan' => (static::REQUIRE_PERIODE ? 'required' : 'nullable') . '|integer|min:1|max:12',
            'tahun' => (static::REQUIRE_PERIODE ? 'required' : 'nullable') . '|integer|min:1900|max:2100',
            'pegawai_id' => [
                $isUpdate ? 'nullable' : 'required',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('tipe', static::PEGAWAI_TIPE)),
            ],
            'hari' => 'nullable|numeric|min:0',
            'barokah_harian' => 'nullable|numeric|min:0',
            'barokah_bulanan' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => 'required|in:' . implode(',', static::JENIS_PEMBAYARAN),
            'keterangan' => 'nullable|string',
        ];
    }

    protected function fillData(KeuanganPengeluaranPegawaiBulanan $data, Request $request): void
    {
        $hari = $this->number($request->hari);
        $barokahHarian = $this->number($request->barokah_harian);
        $barokahBulanan = $this->number($request->barokah_bulanan);

        $data->tanggal = $request->tanggal;
        $data->bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $data->tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }

        $data->hari = $hari;
        $data->barokah_harian = $barokahHarian;
        $data->barokah_bulanan = $barokahBulanan;
        $data->total = (int) round(($barokahHarian * $hari) + $barokahBulanan);
        $data->jenis_pembayaran = $request->jenis_pembayaran;
        $data->keterangan = $request->keterangan;
    }

    protected function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
