<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosenKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DosenKegiatanController extends Controller
{
    private const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];
    private const BUKTI_TRANSFER_DIR = 'bukti-transfer/barokah-dosen/kegiatan';

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        $query->select([
            'keuangan_pengeluaran_dosen_kegiatan.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.nama as nama_dosen',
            'pegawai.kode as kode_dosen',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
        ]);

        $this->joinPegawaiDetail($query);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_pengeluaran_dosen_kegiatan.tanggal', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.nama_kegiatan', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.transport', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.barokah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.total', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen_kegiatan.keterangan', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.kode', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.tipe', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('staff.jabatan', 'LIKE', "%$request->search%");
            });
        }

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen_kegiatan.pegawai_id', $request->pegawai_id);
        }

        $stats = $this->stats($query);

        $sortColumns = [
            'id' => 'keuangan_pengeluaran_dosen_kegiatan.id',
            'tanggal' => 'keuangan_pengeluaran_dosen_kegiatan.tanggal',
            'pegawai_id' => 'keuangan_pengeluaran_dosen_kegiatan.pegawai_id',
            'kode_pegawai' => 'pegawai.kode',
            'nama_pegawai' => 'pegawai.nama',
            'kode_dosen' => 'pegawai.kode',
            'nama_dosen' => 'pegawai.nama',
            'nama_kegiatan' => 'keuangan_pengeluaran_dosen_kegiatan.nama_kegiatan',
            'transport' => 'keuangan_pengeluaran_dosen_kegiatan.transport',
            'barokah' => 'keuangan_pengeluaran_dosen_kegiatan.barokah',
            'total' => 'keuangan_pengeluaran_dosen_kegiatan.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran',
            'created_at' => 'keuangan_pengeluaran_dosen_kegiatan.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_dosen_kegiatan.id', $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->transform(fn ($item) => $this->appendBuktiTransferUrl($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $stats,
            'message' => 'Barokah Pegawai Kegiatan retrieved successfully',
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

        if ($this->needsBuktiTransfer($request, null)) {
            return $this->buktiTransferRequiredResponse();
        }

        $data = new KeuanganPengeluaranDosenKegiatan();
        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($this->findWithDosen($data->id) ?? $data),
            'message' => 'Barokah Pegawai Kegiatan created successfully',
        ], 201);
    }

    public function show($id)
    {
        $data = $this->findWithDosen($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Pegawai Kegiatan not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Pegawai Kegiatan retrieved successfully',
        ], 200);
    }

    public function byDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => [
                'required',
                Rule::exists('pegawai', 'id'),
            ],
            'tanggal' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganPengeluaranDosenKegiatan::where('pegawai_id', $request->pegawai_id)
            ->whereDate('tanggal', $request->tanggal)
            ->latest('id')
            ->first();

        if ($data) {
            $data = $this->findWithDosen($data->id);
        }

        return response()->json([
            'status' => (bool) $data,
            'data' => $data ? $this->appendBuktiTransferUrl($data) : null,
            'message' => $data
                ? 'Barokah Pegawai Kegiatan retrieved successfully'
                : 'Barokah Pegawai Kegiatan not found for selected date',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = KeuanganPengeluaranDosenKegiatan::find($id);
        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Pegawai Kegiatan not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if ($this->needsBuktiTransfer($request, $data)) {
            return $this->buktiTransferRequiredResponse();
        }

        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($this->findWithDosen($data->id) ?? $data),
            'message' => 'Barokah Pegawai Kegiatan updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranDosenKegiatan::find($id);

        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Pegawai Kegiatan not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Barokah Pegawai Kegiatan deleted successfully',
        ]);
    }

    private function joinPegawaiDetail($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen_kegiatan.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    private function findWithDosen($id)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        $query->select([
            'keuangan_pengeluaran_dosen_kegiatan.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.nama as nama_dosen',
            'pegawai.kode as kode_dosen',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
        ]);

        $this->joinPegawaiDetail($query);

        return $query->where('keuangan_pengeluaran_dosen_kegiatan.id', $id)->first();
    }

    private function stats($query): array
    {
        $dateColumn = 'keuangan_pengeluaran_dosen_kegiatan.tanggal';
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

    private function periodStats($baseQuery, ?callable $period = null): array
    {
        $query = clone $baseQuery;

        if ($period) {
            $period($query);
        }

        return [
            'total' => (int) (clone $query)->sum('keuangan_pengeluaran_dosen_kegiatan.total'),
            'jumlah' => (int) $query->count(),
        ];
    }

    private function rules(bool $isUpdate): array
    {
        return [
            'tanggal' => 'required|date',
            'pegawai_id' => [
                $isUpdate ? 'nullable' : 'required',
                Rule::exists('pegawai', 'id'),
            ],
            'nama_kegiatan' => 'required|string|max:255',
            'transport' => 'nullable|numeric|min:0',
            'barokah' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => 'required|in:' . implode(',', self::JENIS_PEMBAYARAN),
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'keterangan' => 'nullable|string',
        ];
    }

    private function fillData(KeuanganPengeluaranDosenKegiatan $data, Request $request): void
    {
        $transport = $this->number($request->transport);
        $barokah = $this->number($request->barokah);

        $data->tanggal = $request->tanggal;
        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }
        $data->nama_kegiatan = $request->nama_kegiatan;
        $data->transport = $transport;
        $data->barokah = $barokah;
        $data->total = (int) round($transport + $barokah);
        $data->jenis_pembayaran = $request->jenis_pembayaran;
        $data->keterangan = $request->keterangan;

        if ($request->hasFile('bukti_transfer')) {
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = $request->file('bukti_transfer')->store(self::BUKTI_TRANSFER_DIR, 'public');
        }

        if ($request->jenis_pembayaran !== 'Transfer') {
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = null;
        }
    }

    private function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranDosenKegiatan $data): bool
    {
        return $request->jenis_pembayaran === 'Transfer'
            && ! $request->hasFile('bukti_transfer')
            && ! ($data?->bukti_transfer);
    }

    private function buktiTransferRequiredResponse()
    {
        return response()->json([
            'status' => false,
            'message' => [
                'bukti_transfer' => ['Bukti transfer wajib diupload jika jenis pembayaran Transfer.'],
            ],
        ], 422);
    }

    private function appendBuktiTransferUrl($data)
    {
        $data->bukti_transfer_url = $data->bukti_transfer
            ? Storage::disk('public')->url($data->bukti_transfer)
            : null;

        return $data;
    }

    private function deleteBuktiTransfer(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
