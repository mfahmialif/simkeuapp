<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\BsiPayrollExport;
use App\Exports\ExcelExport;
use App\Exports\pdf\SlipGajiPdf;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosen;
use App\Models\KeuanganPengeluaranDosenRekap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DosenTatapMukaController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranLpj;
    use ManagesPengeluaranRekap;

    private const JENIS_PEMBAYARAN = ['CUZ BSI', 'Transfer'];

    private const BUKTI_TRANSFER_DIR = 'tatapmuka';

    private const LAMPIRAN_DIR = 'tatapmuka';

    public function lpjShow(Request $request, $id)
    {
        return $this->showModule($request, 'dosen', $id);
    }

    public function lpjCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen', $id);
    }

    public function lpjUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen', $id);
    }

    public function lpjDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'dosen', $id);
    }

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'keuangan_pengeluaran_dosen.*',
            'pegawai.nama as nama_dosen',
            'pegawai.kode as kode_dosen',
            'prodi.nama as nama_prodi_dosen',
            'prodi.alias as alias_prodi_dosen',
            'pengeluaran_rekap.nama as nama_rekap',
            'petugas.name as petugas_nama',
        ]);

        $this->joinPegawaiDosen($query);
        $this->joinRekap($query);

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
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%$request->search%");
            });
        }

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $stats = $this->aggregatePengeluaranStats(
            $this->newIndexStatsQuery($request),
            'keuangan_pengeluaran_dosen'
        );

        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_rekap',
            'tatap_muka'
        );

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
            'nama_rekap' => 'pengeluaran_rekap.nama',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_dosen.id', $sortOrder);

        $data = $this->paginateWithKnownTotal(
            $query,
            $request,
            $stats['keseluruhan']['jumlah']
        );
        $data->getCollection()->transform(fn ($item) => $this->appendPengeluaranFiles($item));

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
        $data ??= new KeuanganPengeluaranDosen;
        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithDosen($data->id) ?? $data),
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
            'data' => $this->appendPengeluaranFiles($data),
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
            'data' => $data ? $this->appendPengeluaranFiles($data) : null,
            'message' => $data
                ? 'Barokah Dosen Tatapmuka retrieved successfully'
                : 'Barokah Dosen Tatapmuka not found for selected date',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranDosen::class, $id);
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
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithDosen($data->id) ?? $data),
            'message' => 'Barokah Dosen Tatapmuka updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranDosen::class, $id);

        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Tatapmuka not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $this->deleteLampiran($data->lampiran);
        $this->deletePengeluaranWithRekapValidation($data);

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
            'pengeluaran_rekap.nama as rekap',
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
        $this->joinRekap($query);

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
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%$request->search%");
            });
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $data = $query->get();

        return Excel::download(new ExcelExport($data), 'Laporan Barokah Dosen Tatapmuka.xlsx');
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new BsiPayrollExport($data, 'barokah mengajar'), 'CUZ BSI Barokah Dosen Tatapmuka.xlsx');
    }

    public function copyBsi(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new BsiPayrollExport($data, 'barokah mengajar');

        return response()->json([
            'status' => true,
            'data' => [
                'text' => $export->clipboardText(),
                'total' => $data->count(),
            ],
            'message' => 'Data CUZ BSI berhasil disiapkan.',
        ]);
    }

    public function exportBsiTxt(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new BsiPayrollExport($data, 'barokah mengajar');

        $filename = 'Template Batch Payment_' . date('Y-m-d_H-i-s') . '.txt';

        return response($export->txtContent())
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function bsiRows(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        $query->select([
            'pegawai.nomer_rekening as beneficiary_acct',
            $this->bsiBeneficiaryNameSelect(),
            DB::raw('SUM(keuangan_pengeluaran_dosen.total) as amount'),
        ]);

        $this->joinPegawaiDosen($query);
        $this->joinRekap($query);

        $hasNamaPemilikRekening = $this->hasNamaPemilikRekeningColumn();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request, $hasNamaPemilikRekening) {
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
                    ->orWhere('pegawai.nomer_rekening', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%$request->search%");

                if ($hasNamaPemilikRekening) {
                    $q->orWhere('pegawai.nama_pemilik_rekening', 'LIKE', "%$request->search%");
                }
            });
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        return $query
            ->where('keuangan_pengeluaran_dosen.jenis_pembayaran', 'CUZ BSI')
            ->groupBy($this->bsiGroupColumns())
            ->orderBy('pegawai.nama')
            ->get();
    }

    private function bsiGroupColumns(): array
    {
        $columns = [
            'keuangan_pengeluaran_dosen.pegawai_id',
            'pegawai.nomer_rekening',
            'pegawai.nama',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            $columns[] = 'pegawai.nama_pemilik_rekening';
        }

        return $columns;
    }

    private function joinPegawaiDosen($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    protected function rekapModelClass(): string
    {
        return KeuanganPengeluaranDosenRekap::class;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_dosen';
    }

    protected function newRekapPengeluaranQuery()
    {
        return KeuanganPengeluaranDosen::query();
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        $this->joinPegawaiDosen($query);
        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
    }

    private function applySearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->orWhere('keuangan_pengeluaran_dosen.transport', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.transport_motor', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.transport_mobil', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tol', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.transport_mobil_tanpa_tol', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.hari_transport_motor', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tol', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.hari_transport_mobil_tanpa_tol', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.barokah', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_biasa', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.barokah_mengajar_double_degree', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.jam_mengajar_double_degree', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.barokah_uas', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.jumlah_mahasiswa_uas', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.barokah_sempro', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.jam_sempro', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.keterangan_sempro', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.nama', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                ->orWhere('prodi.nama', 'LIKE', "%{$search}%")
                ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%{$search}%");
        });
    }

    private function bsiBeneficiaryNameSelect()
    {
        if ($this->hasNamaPemilikRekeningColumn()) {
            return DB::raw("COALESCE(NULLIF(TRIM(pegawai.nama_pemilik_rekening), ''), pegawai.nama) as beneficiary_acct_name");
        }

        return 'pegawai.nama as beneficiary_acct_name';
    }

    private function hasNamaPemilikRekeningColumn(): bool
    {
        return Schema::hasColumn('pegawai', 'nama_pemilik_rekening');
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
            'pengeluaran_rekap.nama as nama_rekap',
        ]);

        $this->joinPegawaiDosen($query);
        $this->joinRekap($query);
        $this->applyPetugasFilter($query, new Request);

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

    private function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranDosen::query();

        if ($request->filled('search')) {
            $this->joinPegawaiDosen($query);
            $this->joinRekap($query);
            $this->applySearchFilter($query, $request);
        } elseif ($request->filled('kode')) {
            $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen.pegawai_id');
        }

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen.pegawai_id', $request->pegawai_id);
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
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
            'jenis_pembayaran' => 'required|in:'.implode(',', self::JENIS_PEMBAYARAN),
            'rekap_id' => $this->rekapIdRules(),
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'keterangan' => 'nullable|string',
            ...$this->lampiranRules(),
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
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
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
        if ($request->has('rekap_id')) {
            $data->rekap_id = $request->filled('rekap_id') ? $request->rekap_id : null;
        }
        $data->keterangan = $request->keterangan;
        $data->lampiran = $this->updateLampiran(
            $request,
            $data->lampiran,
            self::LAMPIRAN_DIR
        );

        if ($request->hasFile('bukti_transfer')) {
            $newBuktiTransfer = $this->storeBuktiTransfer(
                $request->file('bukti_transfer'),
                self::BUKTI_TRANSFER_DIR
            );
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = $newBuktiTransfer;
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
        $path = $this->migrateLegacyBuktiTransfer(
            $data->bukti_transfer,
            self::BUKTI_TRANSFER_DIR
        );

        if ($path !== $data->bukti_transfer) {
            KeuanganPengeluaranDosen::whereKey($data->id)->update([
                'bukti_transfer' => $path,
            ]);
            $data->bukti_transfer = $path;
        }

        $data->bukti_transfer_url = $this->buktiTransferUrl($path);

        return $data;
    }

    private function appendPengeluaranFiles($data)
    {
        return $this->appendLampiranUrls($this->appendBuktiTransferUrl($data));
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
