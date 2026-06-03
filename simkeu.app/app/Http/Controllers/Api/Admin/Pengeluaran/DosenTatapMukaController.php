<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\ExcelExport;
use App\Exports\pdf\SlipGajiPdf;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DosenTatapMukaController extends Controller
{
    private const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];
    private const BUKTI_TRANSFER_DIR = 'bukti-transfer/barokah-dosen/tatapmuka';

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.*',
            'pegawai.nama as nama_dosen',
            'pegawai.kode as kode_dosen',
            'prodi.nama as nama_prodi_dosen',
            'prodi.alias as alias_prodi_dosen',
        ]);

        $this->joinPegawaiDosen($query);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_pengeluaran_dosen.transport', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_motor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tanpa_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_motor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tanpa_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_biasa', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_double_degree', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jam_mengajar_double_degree', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_uas', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jam_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.total', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.kode', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%");
            });
        }

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $stats = $this->stats($query);

        $this->applyDateFilter($query, $request);

        $sortColumns = [
            'id' => 'keuangan_pengeluaran_dosen.id',
            'tanggal' => 'keuangan_pengeluaran_dosen.tanggal',
            'jam' => 'keuangan_pengeluaran_dosen.jam',
            'jam_mengajar_double_degree' => 'keuangan_pengeluaran_dosen.jam_mengajar_double_degree',
            'hari' => 'keuangan_pengeluaran_dosen.hari',
            'hari_transport_motor' => 'keuangan_pengeluaran_dosen.hari_transport_motor',
            'hari_transport_mobil' => 'keuangan_pengeluaran_dosen.hari_transport_mobil',
            'hari_transport_mobil_tol' => 'keuangan_pengeluaran_dosen.hari_transport_mobil_tol',
            'hari_transport_mobil_tanpa_tol' => 'keuangan_pengeluaran_dosen.hari_transport_mobil_tanpa_tol',
            'transport' => 'keuangan_pengeluaran_dosen.transport',
            'transport_motor' => 'keuangan_pengeluaran_dosen.transport_motor',
            'transport_mobil' => 'keuangan_pengeluaran_dosen.transport_mobil',
            'transport_mobil_tol' => 'keuangan_pengeluaran_dosen.transport_mobil_tol',
            'transport_mobil_tanpa_tol' => 'keuangan_pengeluaran_dosen.transport_mobil_tanpa_tol',
            'barokah' => 'keuangan_pengeluaran_dosen.barokah',
            'barokah_mengajar_biasa' => 'keuangan_pengeluaran_dosen.barokah_mengajar_biasa',
            'barokah_mengajar_double_degree' => 'keuangan_pengeluaran_dosen.barokah_mengajar_double_degree',
            'barokah_uas' => 'keuangan_pengeluaran_dosen.barokah_uas',
            'jumlah_mahasiswa_uas' => 'keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas',
            'barokah_sempro' => 'keuangan_pengeluaran_dosen.barokah_sempro',
            'jam_sempro' => 'keuangan_pengeluaran_dosen.jam_sempro',
            'total' => 'keuangan_pengeluaran_dosen.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_dosen.jenis_pembayaran',
            'nama_dosen' => 'pegawai.nama',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_dosen.id', $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->transform(fn ($item) => $this->appendBuktiTransferUrl($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $stats,
            'message' => 'Barokah Dosen Tatapmuka retrieved successfully',
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

        $data = KeuanganPengeluaranDosen::where('pegawai_id', $request->pegawai_id)
            ->whereDate('tanggal', $request->tanggal)
            ->first();

        if ($this->needsBuktiTransfer($request, $data)) {
            return $this->buktiTransferRequiredResponse();
        }

        $isExistingData = (bool) $data;
        $data ??= new KeuanganPengeluaranDosen();
        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($this->findWithDosen($data->id) ?? $data),
            'message' => $isExistingData
                ? 'Barokah Dosen Tatapmuka updated successfully'
                : 'Barokah Dosen Tatapmuka created successfully',
        ], $isExistingData ? 200 : 201);
    }

    public function show($id)
    {
        $data = $this->findWithDosen($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Tatapmuka not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Dosen Tatapmuka retrieved successfully',
        ], 200);
    }

    public function byDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => [
                'required',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('tipe', 'dosen')),
            ],
            'tanggal' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganPengeluaranDosen::where('pegawai_id', $request->pegawai_id)
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
                ? 'Barokah Dosen Tatapmuka retrieved successfully'
                : 'Barokah Dosen Tatapmuka not found for selected date',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = KeuanganPengeluaranDosen::find($id);
        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Tatapmuka not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $pegawaiId = $request->input('pegawai_id', $data->pegawai_id);
        $duplicate = $pegawaiId
            ? KeuanganPengeluaranDosen::where('pegawai_id', $pegawaiId)
                ->whereDate('tanggal', $request->tanggal)
                ->where('id', '!=', $data->id)
                ->first()
            : null;

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => [
                    'tanggal' => ['Data barokah dosen untuk tanggal ini sudah ada. Gunakan data tersebut untuk memperbarui data.'],
                ],
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
            'message' => 'Barokah Dosen Tatapmuka updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranDosen::find($id);

        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Tatapmuka not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Barokah Dosen Tatapmuka deleted successfully',
        ]);
    }

    public function printSlip($id)
    {
        $data = $this->findWithDosen($id);
        abort_if(! $data, 404, 'Barokah Dosen Tatapmuka not found');

        $data->dosen = (object) [
            'id' => $data->pegawai_id,
            'kode' => $data->kode_dosen,
            'nama' => $data->nama_dosen,
            'prodi' => (object) [
                'nama' => $data->nama_prodi_dosen,
                'alias' => $data->alias_prodi_dosen ?? $data->nama_prodi_dosen,
            ],
        ];

        return SlipGajiPdf::pdf($data);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.tanggal',
            'pegawai.nama as dosen',
            'pegawai.kode as niy',
            'prodi.nama as prodi',
            'keuangan_pengeluaran_dosen.jam',
            'keuangan_pengeluaran_dosen.jam_mengajar_double_degree',
            'keuangan_pengeluaran_dosen.hari',
            'keuangan_pengeluaran_dosen.hari_transport_motor',
            'keuangan_pengeluaran_dosen.hari_transport_mobil',
            'keuangan_pengeluaran_dosen.transport_motor',
            'keuangan_pengeluaran_dosen.transport_mobil',
            'keuangan_pengeluaran_dosen.transport',
            DB::raw('COALESCE(keuangan_pengeluaran_dosen.barokah_mengajar_biasa, keuangan_pengeluaran_dosen.barokah) as barokah_mengajar_biasa'),
            'keuangan_pengeluaran_dosen.barokah_mengajar_double_degree',
            'keuangan_pengeluaran_dosen.barokah_uas',
            'keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas',
            'keuangan_pengeluaran_dosen.barokah_sempro',
            'keuangan_pengeluaran_dosen.jam_sempro',
            'keuangan_pengeluaran_dosen.keterangan_sempro',
            'keuangan_pengeluaran_dosen.total',
            'keuangan_pengeluaran_dosen.jenis_pembayaran',
            'keuangan_pengeluaran_dosen.keterangan',
        ]);

        $this->joinPegawaiDosen($query);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('keuangan_pengeluaran_dosen.transport', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_motor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tanpa_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_motor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tanpa_tol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_biasa', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_double_degree', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jam_mengajar_double_degree', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_uas', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.barokah_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jam_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan_sempro', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.total', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pegawai.kode', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%");
            });
        }

        $this->applyDateFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $data = $query->get();

        return Excel::download(new ExcelExport($data), 'Laporan Barokah Dosen Tatapmuka.xlsx');
    }

    private function joinPegawaiDosen($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    private function findWithDosen($id)
    {
        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.*',
            'pegawai.nama as nama_dosen',
            'pegawai.kode as kode_dosen',
            'prodi.nama as nama_prodi_dosen',
            'prodi.alias as alias_prodi_dosen',
        ]);

        $this->joinPegawaiDosen($query);

        return $query->where('keuangan_pengeluaran_dosen.id', $id)->first();
    }

    private function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_dosen.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai && ! $tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '>=', $tanggalMulai);
        } elseif (! $tanggalMulai && $tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen.tanggal', '<=', $tanggalAkhir);
        }
    }

    private function stats($query): array
    {
        $dateColumn = 'keuangan_pengeluaran_dosen.tanggal';
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
            'total' => (int) (clone $query)->sum('keuangan_pengeluaran_dosen.total'),
            'jumlah' => (int) $query->count(),
        ];
    }

    private function rules(bool $isUpdate): array
    {
        return [
            'tanggal' => 'required|date',
            'pegawai_id' => [
                $isUpdate ? 'nullable' : 'required',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('tipe', 'dosen')),
            ],
            'hari' => 'nullable|numeric|min:0',
            'hari_transport_motor' => 'nullable|numeric|min:0',
            'hari_transport_mobil' => 'nullable|numeric|min:0',
            'hari_transport_mobil_tol' => 'nullable|numeric|min:0',
            'hari_transport_mobil_tanpa_tol' => 'nullable|numeric|min:0',
            'jam' => 'required|numeric|min:0',
            'jam_mengajar_double_degree' => 'nullable|numeric|min:0',
            'transport' => 'nullable|numeric|min:0',
            'transport_motor' => 'nullable|numeric|min:0',
            'transport_mobil' => 'nullable|numeric|min:0',
            'transport_mobil_tol' => 'nullable|numeric|min:0',
            'transport_mobil_tanpa_tol' => 'nullable|numeric|min:0',
            'barokah' => 'nullable|numeric|min:0',
            'barokah_mengajar_biasa' => 'nullable|numeric|min:0',
            'barokah_mengajar_double_degree' => 'nullable|numeric|min:0',
            'barokah_uas' => 'nullable|numeric|min:0',
            'jumlah_mahasiswa_uas' => 'nullable|numeric|min:0',
            'barokah_sempro' => 'nullable|numeric|min:0',
            'jam_sempro' => 'nullable|numeric|min:0',
            'keterangan_sempro' => 'nullable|string',
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => 'required|in:' . implode(',', self::JENIS_PEMBAYARAN),
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'keterangan' => 'nullable|string',
        ];
    }

    private function fillData(KeuanganPengeluaranDosen $data, Request $request): void
    {
        $barokahMengajarBiasa = $this->number($request->input('barokah_mengajar_biasa', $request->input('barokah')));
        $barokahMengajarDoubleDegree = $this->number($request->barokah_mengajar_double_degree);
        $barokahUas = $this->number($request->barokah_uas);
        $jumlahMahasiswaUas = $this->number($request->jumlah_mahasiswa_uas);
        $barokahSempro = $this->number($request->barokah_sempro);
        $jamSempro = $request->has('jam_sempro')
            ? $this->number($request->jam_sempro)
            : ($barokahSempro > 0 ? 1 : 0);
        $transportMotor = $this->number($request->transport_motor);
        $transportMobil = $request->has('transport_mobil')
            ? $this->number($request->transport_mobil)
            : $this->number($request->transport_mobil_tol) + $this->number($request->transport_mobil_tanpa_tol);
        if (
            ! $request->has('transport_motor')
            && ! $request->has('transport_mobil')
            && ! $request->has('transport_mobil_tol')
            && ! $request->has('transport_mobil_tanpa_tol')
        ) {
            $transportMotor = $this->number($request->transport);
        }
        $transport = $transportMotor + $transportMobil;
        $jam = $this->number($request->jam);
        $jamMengajarDoubleDegree = $request->has('jam_mengajar_double_degree')
            ? $this->number($request->jam_mengajar_double_degree)
            : $jam;
        $hariTransportMotor = $this->number($request->hari_transport_motor);
        $hariTransportMobil = $request->has('hari_transport_mobil')
            ? $this->number($request->hari_transport_mobil)
            : $this->number($request->hari_transport_mobil_tol) + $this->number($request->hari_transport_mobil_tanpa_tol);
        if (
            ! $request->has('hari_transport_motor')
            && ! $request->has('hari_transport_mobil')
            && ! $request->has('hari_transport_mobil_tol')
            && ! $request->has('hari_transport_mobil_tanpa_tol')
        ) {
            $hariTransportMotor = $this->number($request->hari);
        }
        $hari = $hariTransportMotor + $hariTransportMobil;

        $data->tanggal = $request->tanggal;
        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }
        $data->hari = $hari;
        $data->hari_transport_motor = $hariTransportMotor;
        $data->hari_transport_mobil = $hariTransportMobil;
        $data->hari_transport_mobil_tol = 0;
        $data->hari_transport_mobil_tanpa_tol = $hariTransportMobil;
        $data->jam = $jam;
        $data->jam_mengajar_double_degree = $jamMengajarDoubleDegree;
        $data->transport = $transport;
        $data->transport_motor = $transportMotor;
        $data->transport_mobil = $transportMobil;
        $data->transport_mobil_tol = 0;
        $data->transport_mobil_tanpa_tol = $transportMobil;
        $data->barokah = $barokahMengajarBiasa;
        $data->barokah_mengajar_biasa = $barokahMengajarBiasa;
        $data->barokah_mengajar_double_degree = $barokahMengajarDoubleDegree;
        $data->barokah_uas = $barokahUas;
        $data->jumlah_mahasiswa_uas = $jumlahMahasiswaUas;
        $data->barokah_sempro = $barokahSempro;
        $data->jam_sempro = $jamSempro;
        $data->keterangan_sempro = $request->keterangan_sempro;
        $data->total = $this->calculateTotal(
            $transportMotor,
            $hariTransportMotor,
            $transportMobil,
            $hariTransportMobil,
            $barokahMengajarBiasa,
            $barokahMengajarDoubleDegree,
            $jam,
            $jamMengajarDoubleDegree,
            $barokahUas,
            $jumlahMahasiswaUas,
            $barokahSempro,
            $jamSempro
        );
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

    private function calculateTotal(
        float $transportMotor,
        float $hariTransportMotor,
        float $transportMobil,
        float $hariTransportMobil,
        float $barokahMengajarBiasa,
        float $barokahMengajarDoubleDegree,
        float $jam,
        float $jamMengajarDoubleDegree,
        float $barokahUas,
        float $jumlahMahasiswaUas,
        float $barokahSempro,
        float $jamSempro
    ): int {
        return (int) round(
            ($transportMotor * $hariTransportMotor)
            + ($transportMobil * $hariTransportMobil)
            + ($barokahMengajarBiasa * $jam)
            + ($barokahMengajarDoubleDegree * $jamMengajarDoubleDegree)
            + ($barokahUas * $jumlahMahasiswaUas)
            + ($barokahSempro * $jamSempro)
        );
    }

    private function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranDosen $data): bool
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
