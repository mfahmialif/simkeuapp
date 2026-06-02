<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\ExcelExport;
use App\Exports\pdf\SlipGajiPdf;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosen;
use App\Services\Dosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class DosenTatapMukaController extends Controller
{
    private const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];
    private const BUKTI_TRANSFER_DIR = 'bukti-transfer/barokah-dosen/tatapmuka';

    public function index(Request $request)
    {
        $this->syncTempDosen();

        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.*',
            'temp_dosen.nama as nama_dosen',
            'temp_dosen.kode as kode_dosen',
            'temp_dosen.nama_prodi as nama_prodi_dosen',
        ]);

        $query->join('temp_dosen', 'temp_dosen.kode', '=', 'keuangan_pengeluaran_dosen.dosen_kode');

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
                    ->orWhere('keuangan_pengeluaran_dosen.total', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan', 'LIKE', "%$request->search%")
                    ->orWhere('temp_dosen.nama', 'LIKE', "%$request->search%")
                    ->orWhere('temp_dosen.nama_prodi', 'LIKE', "%$request->search%");
            });
        }

        $this->applyDateFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('temp_dosen.kode', $request->kode);
        }

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
            'total' => 'keuangan_pengeluaran_dosen.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_dosen.jenis_pembayaran',
            'nama_dosen' => 'temp_dosen.nama',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_dosen.id', $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->transform(fn ($item) => $this->appendBuktiTransferUrl($item));

        return response()->json([
            'status' => true,
            'data' => $data,
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

        $data = KeuanganPengeluaranDosen::where('dosen_kode', $request->dosen_kode)
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
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => $isExistingData
                ? 'Barokah Dosen Tatapmuka updated successfully'
                : 'Barokah Dosen Tatapmuka created successfully',
        ], $isExistingData ? 200 : 201);
    }

    public function show($id)
    {
        $data = KeuanganPengeluaranDosen::find($id);

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
            'dosen_kode' => 'required',
            'tanggal' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganPengeluaranDosen::where('dosen_kode', $request->dosen_kode)
            ->whereDate('tanggal', $request->tanggal)
            ->latest('id')
            ->first();

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
        if (! $data) {
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

        $dosenKode = $request->input('dosen_kode', $data->dosen_kode);
        $duplicate = KeuanganPengeluaranDosen::where('dosen_kode', $dosenKode)
            ->whereDate('tanggal', $request->tanggal)
            ->where('id', '!=', $data->id)
            ->first();

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
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Dosen Tatapmuka updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranDosen::find($id);

        if (! $data) {
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
        $data = KeuanganPengeluaranDosen::findOrFail($id);
        $data->dosen = Dosen::kode($data->dosen_kode);

        return SlipGajiPdf::pdf($data);
    }

    public function exportExcel(Request $request)
    {
        $this->syncTempDosen();

        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.tanggal',
            'temp_dosen.nama as dosen',
            'temp_dosen.kode as niy',
            'temp_dosen.nama_prodi as prodi',
            'keuangan_pengeluaran_dosen.jam',
            'keuangan_pengeluaran_dosen.jam_mengajar_double_degree',
            'keuangan_pengeluaran_dosen.hari',
            'keuangan_pengeluaran_dosen.hari_transport_motor',
            'keuangan_pengeluaran_dosen.hari_transport_mobil',
            'keuangan_pengeluaran_dosen.hari_transport_mobil_tol',
            'keuangan_pengeluaran_dosen.hari_transport_mobil_tanpa_tol',
            'keuangan_pengeluaran_dosen.transport_motor',
            'keuangan_pengeluaran_dosen.transport_mobil',
            'keuangan_pengeluaran_dosen.transport_mobil_tol',
            'keuangan_pengeluaran_dosen.transport_mobil_tanpa_tol',
            'keuangan_pengeluaran_dosen.transport',
            DB::raw('COALESCE(keuangan_pengeluaran_dosen.barokah_mengajar_biasa, keuangan_pengeluaran_dosen.barokah) as barokah_mengajar_biasa'),
            'keuangan_pengeluaran_dosen.barokah_mengajar_double_degree',
            'keuangan_pengeluaran_dosen.barokah_uas',
            'keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas',
            'keuangan_pengeluaran_dosen.barokah_sempro',
            'keuangan_pengeluaran_dosen.total',
            'keuangan_pengeluaran_dosen.jenis_pembayaran',
            'keuangan_pengeluaran_dosen.keterangan',
        ]);

        $query->join('temp_dosen', 'temp_dosen.kode', '=', 'keuangan_pengeluaran_dosen.dosen_kode');

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
                    ->orWhere('keuangan_pengeluaran_dosen.total', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pengeluaran_dosen.keterangan', 'LIKE', "%$request->search%")
                    ->orWhere('temp_dosen.nama', 'LIKE', "%$request->search%")
                    ->orWhere('temp_dosen.nama_prodi', 'LIKE', "%$request->search%");
            });
        }

        $this->applyDateFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('temp_dosen.kode', $request->kode);
        }

        $data = $query->get();

        return Excel::download(new ExcelExport($data), 'Laporan Barokah Dosen Tatapmuka.xlsx');
    }

    private function syncTempDosen(): void
    {
        $dosenApi = Dosen::all();

        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_dosen');

        DB::statement("
            CREATE TEMPORARY TABLE temp_dosen (
                id INT PRIMARY KEY,
                nama VARCHAR(255),
                kode VARCHAR(255),
                nama_prodi VARCHAR(255)
            )
        ");

        foreach ($dosenApi as $d) {
            DB::table('temp_dosen')->insert([
                'id' => $d->id,
                'nama' => $d->nama,
                'kode' => $d->kode,
                'nama_prodi' => $d->nama_prodi,
            ]);
        }
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

    private function rules(bool $isUpdate): array
    {
        return [
            'tanggal' => 'required|date',
            'dosen_kode' => $isUpdate ? 'nullable' : 'required',
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
        $transportMotor = $this->number($request->transport_motor);
        $transportMobilTol = $this->number($request->transport_mobil_tol);
        $transportMobilTanpaTol = $this->number($request->transport_mobil_tanpa_tol);
        if (! $request->has('transport_mobil_tol') && ! $request->has('transport_mobil_tanpa_tol')) {
            $transportMobilTanpaTol = $this->number($request->transport_mobil);
        }
        if (
            ! $request->has('transport_motor')
            && ! $request->has('transport_mobil')
            && ! $request->has('transport_mobil_tol')
            && ! $request->has('transport_mobil_tanpa_tol')
        ) {
            $transportMotor = $this->number($request->transport);
        }
        $transportMobil = $transportMobilTol + $transportMobilTanpaTol;
        $transport = $transportMotor + $transportMobil;
        $jam = $this->number($request->jam);
        $jamMengajarDoubleDegree = $request->has('jam_mengajar_double_degree')
            ? $this->number($request->jam_mengajar_double_degree)
            : $jam;
        $hariTransportMotor = $this->number($request->hari_transport_motor);
        $hariTransportMobilTol = $this->number($request->hari_transport_mobil_tol);
        $hariTransportMobilTanpaTol = $this->number($request->hari_transport_mobil_tanpa_tol);
        if (! $request->has('hari_transport_mobil_tol') && ! $request->has('hari_transport_mobil_tanpa_tol')) {
            $hariTransportMobilTanpaTol = $this->number($request->hari_transport_mobil);
        }
        if (
            ! $request->has('hari_transport_motor')
            && ! $request->has('hari_transport_mobil')
            && ! $request->has('hari_transport_mobil_tol')
            && ! $request->has('hari_transport_mobil_tanpa_tol')
        ) {
            $hariTransportMotor = $this->number($request->hari);
        }
        $hariTransportMobil = $hariTransportMobilTol + $hariTransportMobilTanpaTol;
        $hari = $hariTransportMotor + $hariTransportMobil;

        $data->tanggal = $request->tanggal;
        if ($request->filled('dosen_kode')) {
            $data->dosen_kode = $request->dosen_kode;
        }
        $data->hari = $hari;
        $data->hari_transport_motor = $hariTransportMotor;
        $data->hari_transport_mobil = $hariTransportMobil;
        $data->hari_transport_mobil_tol = $hariTransportMobilTol;
        $data->hari_transport_mobil_tanpa_tol = $hariTransportMobilTanpaTol;
        $data->jam = $jam;
        $data->jam_mengajar_double_degree = $jamMengajarDoubleDegree;
        $data->transport = $transport;
        $data->transport_motor = $transportMotor;
        $data->transport_mobil = $transportMobil;
        $data->transport_mobil_tol = $transportMobilTol;
        $data->transport_mobil_tanpa_tol = $transportMobilTanpaTol;
        $data->barokah = $barokahMengajarBiasa;
        $data->barokah_mengajar_biasa = $barokahMengajarBiasa;
        $data->barokah_mengajar_double_degree = $barokahMengajarDoubleDegree;
        $data->barokah_uas = $barokahUas;
        $data->jumlah_mahasiswa_uas = $jumlahMahasiswaUas;
        $data->barokah_sempro = $barokahSempro;
        $data->total = $this->calculateTotal(
            $transportMotor,
            $hariTransportMotor,
            $transportMobilTol,
            $hariTransportMobilTol,
            $transportMobilTanpaTol,
            $hariTransportMobilTanpaTol,
            $barokahMengajarBiasa,
            $barokahMengajarDoubleDegree,
            $jam,
            $jamMengajarDoubleDegree,
            $barokahUas,
            $jumlahMahasiswaUas,
            $barokahSempro
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
        float $transportMobilTol,
        float $hariTransportMobilTol,
        float $transportMobilTanpaTol,
        float $hariTransportMobilTanpaTol,
        float $barokahMengajarBiasa,
        float $barokahMengajarDoubleDegree,
        float $jam,
        float $jamMengajarDoubleDegree,
        float $barokahUas,
        float $jumlahMahasiswaUas,
        float $barokahSempro
    ): int {
        return (int) round(
            ($transportMotor * $hariTransportMotor)
            + ($transportMobilTol * $hariTransportMobilTol)
            + ($transportMobilTanpaTol * $hariTransportMobilTanpaTol)
            + ($barokahMengajarBiasa * $jam)
            + ($barokahMengajarDoubleDegree * $jamMengajarDoubleDegree * 1.5)
            + ($barokahUas * $jumlahMahasiswaUas)
            + $barokahSempro
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
