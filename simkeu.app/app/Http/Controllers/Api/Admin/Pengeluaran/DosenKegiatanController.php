<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\BsiPayrollExport;
use App\Exports\ExcelExport;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosenKegiatan;
use App\Models\KeuanganPengeluaranDosenKegiatanRekap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class DosenKegiatanController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranLpj;
    use ManagesPengeluaranRekap;

    private const KATEGORI_DETAIL = ['pegawai', 'non_pegawai'];

    private const JENIS_PEMBAYARAN_PEGAWAI = ['CUZ BSI', 'Transfer'];

    private const JENIS_PEMBAYARAN_NON_PEGAWAI = ['Tunai', 'CUZ BSI', 'Transfer'];

    private const BUKTI_TRANSFER_DIR = 'kegiatan';

    private const LAMPIRAN_DIR = 'kegiatan';

    public function lpjShow(Request $request, $id)
    {
        return $this->showModule($request, 'kegiatan', $id);
    }

    public function lpjCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'kegiatan', $id);
    }

    public function lpjUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'kegiatan', $id);
    }

    public function lpjDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'kegiatan', $id);
    }

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
            'pengeluaran_rekap.nama as nama_rekap',
            'petugas.name as petugas_nama',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $stats = $this->aggregatePengeluaranStats(
            $this->newIndexStatsQuery($request),
            'keuangan_pengeluaran_dosen_kegiatan'
        );

        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_dosen_kegiatan',
            'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'kegiatan'
        );

        $sortColumns = [
            'id' => 'keuangan_pengeluaran_dosen_kegiatan.id',
            'tanggal' => 'keuangan_pengeluaran_dosen_kegiatan.tanggal',
            'pegawai_id' => 'keuangan_pengeluaran_dosen_kegiatan.pegawai_id',
            'kode_pegawai' => 'pegawai.kode',
            'nama_pegawai' => 'pegawai.nama',
            'kode_dosen' => 'pegawai.kode',
            'nama_dosen' => 'pegawai.nama',
            'kategori_detail' => 'keuangan_pengeluaran_dosen_kegiatan.kategori_detail',
            'nama_kegiatan' => 'keuangan_pengeluaran_dosen_kegiatan.nama_kegiatan',
            'transport' => 'keuangan_pengeluaran_dosen_kegiatan.transport',
            'barokah' => 'keuangan_pengeluaran_dosen_kegiatan.barokah',
            'nominal' => 'keuangan_pengeluaran_dosen_kegiatan.nominal',
            'total' => 'keuangan_pengeluaran_dosen_kegiatan.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran',
            'nama_rekap' => 'pengeluaran_rekap.nama',
            'created_at' => 'keuangan_pengeluaran_dosen_kegiatan.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_dosen_kegiatan.id', $sortOrder);

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
            'message' => 'Barokah Pegawai Kegiatan retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request));

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if ($this->needsBuktiTransfer($request, null)) {
            return $this->buktiTransferRequiredResponse();
        }

        $data = new KeuanganPengeluaranDosenKegiatan;
        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithDosen($data->id) ?? $data),
            'message' => 'Barokah Pegawai Kegiatan created successfully',
        ], 201);
    }

    public function batchStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranDosenKegiatanRekap)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.kategori_detail' => ['required', Rule::in(self::KATEGORI_DETAIL)],
            'items.*.pegawai_id' => ['nullable', Rule::exists('pegawai', 'id')],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.transport' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah' => ['nullable', 'numeric', 'min:0'],
            'items.*.nominal' => ['nullable', 'numeric', 'min:0'],
            'items.*.jenis_pembayaran' => ['required', 'string'],
            'items.*.bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'items.*.keterangan' => ['nullable', 'string'],
            'items.*.lampiran' => ['nullable', 'array', 'max:10'],
            'items.*.lampiran.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('items', []) as $index => $item) {
                $category = $item['kategori_detail'] ?? null;
                $paymentType = $item['jenis_pembayaran'] ?? null;
                $allowedPaymentTypes = $category === 'non_pegawai'
                    ? self::JENIS_PEMBAYARAN_NON_PEGAWAI
                    : self::JENIS_PEMBAYARAN_PEGAWAI;

                if ($category === 'pegawai' && empty($item['pegawai_id'])) {
                    $validator->errors()->add(
                        "items.{$index}.pegawai_id",
                        'Pegawai wajib dipilih untuk kategori Pegawai.'
                    );
                }

                if ($category === 'non_pegawai' && ! isset($item['nominal'])) {
                    $validator->errors()->add(
                        "items.{$index}.nominal",
                        'Nominal wajib diisi untuk kategori Nonpegawai.'
                    );
                }

                if (! in_array($paymentType, $allowedPaymentTypes, true)) {
                    $validator->errors()->add(
                        "items.{$index}.jenis_pembayaran",
                        'Jenis pembayaran tidak sesuai kategori.'
                    );
                }

            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $storedBuktiTransfer = [];
        $storedLampiran = [];

        try {
            $createdIds = DB::transaction(function () use (
                $payload,
                $request,
                &$storedBuktiTransfer,
                &$storedLampiran
            ) {
                $rekapId = $payload['rekap_id'];
                $this->lockRekapRows([$rekapId]);
                $createdIds = [];

                foreach ($payload['items'] as $index => $item) {
                    unset($item['bukti_transfer'], $item['lampiran']);
                    $item['rekap_id'] = $rekapId;
                    $rowRequest = Request::create('/', 'POST', $item);
                    $buktiTransfer = $request->file("items.{$index}.bukti_transfer");

                    if ($buktiTransfer) {
                        $rowRequest->files->set('bukti_transfer', $buktiTransfer);
                    }

                    $rowLampiran = $request->file("items.{$index}.lampiran", []);
                    if ($rowLampiran) {
                        $rowRequest->files->set('lampiran', $rowLampiran);
                    }

                    $data = new KeuanganPengeluaranDosenKegiatan;
                    $this->fillData($data, $rowRequest);

                    if ($data->bukti_transfer) {
                        $storedBuktiTransfer[] = $data->bukti_transfer;
                    }
                    $storedLampiran[] = $data->lampiran;

                    $data->save();
                    $createdIds[] = $data->id;
                }

                $this->validateAndSyncRekapTemporary([$rekapId]);

                return $createdIds;
            });
        } catch (Throwable $exception) {
            foreach ($storedBuktiTransfer as $path) {
                $this->deleteBuktiTransfer($path);
            }
            foreach ($storedLampiran as $lampiran) {
                $this->deleteLampiran($lampiran);
            }

            throw $exception;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'created' => count($createdIds),
                'ids' => $createdIds,
            ],
            'message' => count($createdIds).' data Pengeluaran Kegiatan berhasil ditambahkan.',
        ], 201);
    }

    public function batchUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranDosenKegiatanRekap)->getTable(), 'id'),
            ],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => [
                'integer',
                Rule::exists((new KeuanganPengeluaranDosenKegiatan)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'max:100'],
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists((new KeuanganPengeluaranDosenKegiatan)->getTable(), 'id'),
            ],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.kategori_detail' => ['required', Rule::in(self::KATEGORI_DETAIL)],
            'items.*.pegawai_id' => ['nullable', Rule::exists('pegawai', 'id')],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.transport' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah' => ['nullable', 'numeric', 'min:0'],
            'items.*.nominal' => ['nullable', 'numeric', 'min:0'],
            'items.*.jenis_pembayaran' => ['required', 'string'],
            'items.*.bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'items.*.keterangan' => ['nullable', 'string'],
            'items.*.lampiran' => ['nullable', 'array', 'max:10'],
            'items.*.lampiran.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
            'items.*.hapus_lampiran' => ['nullable', 'array'],
            'items.*.hapus_lampiran.*' => ['string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('items', []) as $index => $item) {
                $category = $item['kategori_detail'] ?? null;
                $paymentType = $item['jenis_pembayaran'] ?? null;
                $allowedPaymentTypes = $category === 'non_pegawai'
                    ? self::JENIS_PEMBAYARAN_NON_PEGAWAI
                    : self::JENIS_PEMBAYARAN_PEGAWAI;

                if ($category === 'pegawai' && empty($item['pegawai_id'])) {
                    $validator->errors()->add(
                        "items.{$index}.pegawai_id",
                        'Pegawai wajib dipilih untuk kategori Pegawai.'
                    );
                }

                if ($category === 'non_pegawai' && ! isset($item['nominal'])) {
                    $validator->errors()->add(
                        "items.{$index}.nominal",
                        'Nominal wajib diisi untuk kategori Nonpegawai.'
                    );
                }

                if (! in_array($paymentType, $allowedPaymentTypes, true)) {
                    $validator->errors()->add(
                        "items.{$index}.jenis_pembayaran",
                        'Jenis pembayaran tidak sesuai kategori.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $storedBuktiTransfer = [];
        $storedLampiran = [];

        try {
            $result = DB::transaction(function () use (
                $payload,
                $request,
                &$storedBuktiTransfer,
                &$storedLampiran
            ) {
                $rekapId = $payload['rekap_id'];
                $deletedIds = collect($payload['deleted_ids'] ?? [])->filter()->unique()->values();
                $itemIds = collect($payload['items'] ?? [])->pluck('id')->filter()->unique()->values();
                $existingRows = KeuanganPengeluaranDosenKegiatan::query()
                    ->whereIn('id', $deletedIds->merge($itemIds)->unique()->values())
                    ->get()
                    ->keyBy('id');

                $affectedRekapIds = collect([$rekapId])
                    ->merge($existingRows->pluck('rekap_id'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $this->lockRekapRows($affectedRekapIds);
                $emptyFallbackAmounts = $this->snapshotRekapTotals(
                    $existingRows->pluck('rekap_id')->filter()->unique()->values()->all()
                );

                $deleted = 0;
                foreach ($deletedIds as $id) {
                    $data = $existingRows->get($id);
                    if (! $data) {
                        continue;
                    }

                    $this->deleteBuktiTransfer($data->bukti_transfer);
                    $this->deleteLampiran($data->lampiran);
                    $data->delete();
                    $deleted++;
                }

                $updated = 0;
                $created = 0;
                foreach ($payload['items'] as $index => $item) {
                    unset($item['bukti_transfer'], $item['lampiran']);
                    $id = $item['id'] ?? null;
                    unset($item['id']);
                    $item['rekap_id'] = $rekapId;

                    $rowRequest = Request::create('/', 'POST', $item);
                    $buktiTransfer = $request->file("items.{$index}.bukti_transfer");

                    if ($buktiTransfer) {
                        $rowRequest->files->set('bukti_transfer', $buktiTransfer);
                    }

                    $rowLampiran = $request->file("items.{$index}.lampiran", []);
                    if ($rowLampiran) {
                        $rowRequest->files->set('lampiran', $rowLampiran);
                    }

                    $data = $id
                        ? $existingRows->get($id)
                        : new KeuanganPengeluaranDosenKegiatan;

                    if (! $data) {
                        continue;
                    }

                    $existingLampiranPaths = collect($data->lampiran ?? [])
                        ->pluck('path')
                        ->filter();
                    $this->fillData($data, $rowRequest);

                    if ($data->bukti_transfer && $buktiTransfer) {
                        $storedBuktiTransfer[] = $data->bukti_transfer;
                    }
                    $newLampiran = collect($data->lampiran ?? [])
                        ->reject(fn ($lampiran) => $existingLampiranPaths->contains($lampiran['path'] ?? null))
                        ->values()
                        ->all();
                    if ($newLampiran) {
                        $storedLampiran[] = $newLampiran;
                    }

                    $data->save();
                    $id ? $updated++ : $created++;
                }

                $this->validateAndSyncRekapTemporary($affectedRekapIds, $emptyFallbackAmounts);

                return compact('created', 'updated', 'deleted');
            });
        } catch (Throwable $exception) {
            foreach ($storedBuktiTransfer as $path) {
                $this->deleteBuktiTransfer($path);
            }
            foreach ($storedLampiran as $lampiran) {
                $this->deleteLampiran($lampiran);
            }

            throw $exception;
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => ($result['created'] + $result['updated']).' data Pengeluaran Kegiatan berhasil diperbarui.',
        ]);
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
            'data' => $this->appendPengeluaranFiles($data),
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
            'data' => $data ? $this->appendPengeluaranFiles($data) : null,
            'message' => $data
                ? 'Barokah Pegawai Kegiatan retrieved successfully'
                : 'Barokah Pegawai Kegiatan not found for selected date',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        $query->select([
            'keuangan_pengeluaran_dosen_kegiatan.tanggal',
            'pegawai.kode as kode_pegawai',
            'pegawai.nama as nama_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'prodi.nama as prodi',
            'staff.jabatan as jabatan',
            'pengeluaran_rekap.nama as rekap',
            'keuangan_pengeluaran_dosen_kegiatan.nama_kegiatan',
            'keuangan_pengeluaran_dosen_kegiatan.transport',
            'keuangan_pengeluaran_dosen_kegiatan.barokah',
            'keuangan_pengeluaran_dosen_kegiatan.kategori_detail',
            'keuangan_pengeluaran_dosen_kegiatan.nominal',
            'keuangan_pengeluaran_dosen_kegiatan.total',
            'keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran',
            'keuangan_pengeluaran_dosen_kegiatan.keterangan',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $data = $query
            ->orderBy('keuangan_pengeluaran_dosen_kegiatan.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_dosen_kegiatan.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan Barokah Pegawai Kegiatan.xlsx');
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new BsiPayrollExport($data, 'barokah kegiatan'), 'CUZ BSI Barokah Pegawai Kegiatan.xlsx');
    }

    public function copyBsi(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new BsiPayrollExport($data, 'barokah kegiatan');

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
        $export = new BsiPayrollExport($data, 'barokah kegiatan');

        $filename = 'Template Batch Payment_' . date('Y-m-d_H-i-s') . '.txt';

        return response($export->txtContent())
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function bsiRows(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        $query->select([
            'pegawai.nomer_rekening as beneficiary_acct',
            $this->bsiBeneficiaryNameSelect(),
            DB::raw('SUM(keuangan_pengeluaran_dosen_kegiatan.total) as amount'),
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query
            ->where('keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran', 'CUZ BSI')
            ->where('keuangan_pengeluaran_dosen_kegiatan.kategori_detail', 'pegawai')
            ->whereNotNull('keuangan_pengeluaran_dosen_kegiatan.pegawai_id')
            ->groupBy($this->bsiGroupColumns())
            ->orderBy('pegawai.nama')
            ->get();
    }

    private function bsiGroupColumns(): array
    {
        $columns = [
            'keuangan_pengeluaran_dosen_kegiatan.pegawai_id',
            'pegawai.nomer_rekening',
            'pegawai.nama',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            $columns[] = 'pegawai.nama_pemilik_rekening';
        }

        return $columns;
    }

    public function update(Request $request, $id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranDosenKegiatan::class, $id);
        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Pegawai Kegiatan not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request));

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
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithDosen($data->id) ?? $data),
            'message' => 'Barokah Pegawai Kegiatan updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranDosenKegiatan::class, $id);

        if (! $data || ! $this->findWithDosen($id)) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Pegawai Kegiatan not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $this->deleteLampiran($data->lampiran);
        $this->deletePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'message' => 'Barokah Pegawai Kegiatan deleted successfully',
        ]);
    }

    public function rekapDestroy($id)
    {
        $deletedFiles = [
            'bukti_transfer' => [],
            'lampiran' => [],
        ];

        $result = DB::transaction(function () use ($id, &$deletedFiles) {
            $rekap = KeuanganPengeluaranDosenKegiatanRekap::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $rekap) {
                return null;
            }

            $details = KeuanganPengeluaranDosenKegiatan::query()
                ->where('rekap_id', $rekap->id)
                ->lockForUpdate()
                ->get();

            foreach ($details as $detail) {
                if ($detail->bukti_transfer) {
                    $deletedFiles['bukti_transfer'][] = $detail->bukti_transfer;
                }

                $deletedFiles['lampiran'][] = $detail->lampiran;
            }

            $deletedLpj = $this->deleteLpjForDeletedRekap($rekap->getTable(), $rekap->id);

            $deletedDetails = KeuanganPengeluaranDosenKegiatan::query()
                ->where('rekap_id', $rekap->id)
                ->delete();

            $nama = $rekap->nama;
            $rekap->delete();

            return [
                'nama' => $nama,
                'deleted_details' => $deletedDetails,
                'deleted_lpj' => $deletedLpj,
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        foreach ($deletedFiles['bukti_transfer'] as $path) {
            $this->deleteBuktiTransfer($path);
        }

        foreach ($deletedFiles['lampiran'] as $lampiran) {
            $this->deleteLampiran($lampiran);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'deleted_details' => $result['deleted_details'],
                'deleted_lpj' => $result['deleted_lpj'],
            ],
            'message' => "Rekap {$result['nama']} dan {$result['deleted_details']} data pengeluaran berhasil dihapus.",
        ]);
    }

    private function joinPegawaiDetail($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen_kegiatan.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    protected function rekapModelClass(): string
    {
        return KeuanganPengeluaranDosenKegiatanRekap::class;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_dosen_kegiatan';
    }

    protected function newRekapPengeluaranQuery()
    {
        return KeuanganPengeluaranDosenKegiatan::query();
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
    }

    protected function requiresRekapForPengeluaran(): bool
    {
        return true;
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

    private function applySearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->orWhere('keuangan_pengeluaran_dosen_kegiatan.tanggal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.nama_kegiatan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.transport', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.barokah', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.kategori_detail', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.nominal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_dosen_kegiatan.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.nama', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.tipe', 'LIKE', "%{$search}%")
                ->orWhere('prodi.nama', 'LIKE', "%{$search}%")
                ->orWhere('staff.jabatan', 'LIKE', "%{$search}%")
                ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%{$search}%");
        });
    }

    private function applyPegawaiFilter($query, Request $request): void
    {
        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen_kegiatan.pegawai_id', $request->pegawai_id);
        }
    }

    private function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_dosen_kegiatan.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai) {
            $query->where('keuangan_pengeluaran_dosen_kegiatan.tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalAkhir) {
            $query->where('keuangan_pengeluaran_dosen_kegiatan.tanggal', '<=', $tanggalAkhir);
        }
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
            'pengeluaran_rekap.nama as nama_rekap',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPetugasFilter($query, new Request);

        return $query->where('keuangan_pengeluaran_dosen_kegiatan.id', $id)->first();
    }

    private function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        if ($request->filled('search')) {
            $this->joinPegawaiDetail($query);
            $this->joinRekap($query);
            $this->applySearchFilter($query, $request);
        } elseif ($request->filled('kode')) {
            $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_dosen_kegiatan.pegawai_id');
        }

        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_dosen_kegiatan.pegawai_id', $request->pegawai_id);
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
    }

    private function rules(Request $request): array
    {
        $kategoriDetail = $request->input('kategori_detail');
        $jenisPembayaran = $kategoriDetail === 'non_pegawai'
            ? self::JENIS_PEMBAYARAN_NON_PEGAWAI
            : self::JENIS_PEMBAYARAN_PEGAWAI;

        return [
            'tanggal' => 'required|date',
            'kategori_detail' => ['required', Rule::in(self::KATEGORI_DETAIL)],
            'pegawai_id' => [
                'nullable',
                new RequiredIf($kategoriDetail === 'pegawai'),
                Rule::exists('pegawai', 'id'),
            ],
            'nama_kegiatan' => 'required|string|max:255',
            'transport' => 'nullable|numeric|min:0',
            'barokah' => 'nullable|numeric|min:0',
            'nominal' => [
                'nullable',
                new RequiredIf($kategoriDetail === 'non_pegawai'),
                'numeric',
                'min:0',
            ],
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => ['required', Rule::in($jenisPembayaran)],
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranDosenKegiatanRekap)->getTable(), 'id'),
            ],
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'keterangan' => 'nullable|string',
            ...$this->lampiranRules(),
        ];
    }

    private function fillData(KeuanganPengeluaranDosenKegiatan $data, Request $request): void
    {
        $kategoriDetail = $request->kategori_detail;
        $isPegawai = $kategoriDetail === 'pegawai';
        $transport = $isPegawai ? $this->number($request->transport) : 0;
        $barokah = $isPegawai ? $this->number($request->barokah) : 0;
        $nominal = $isPegawai ? null : (int) round($this->number($request->nominal));

        $data->tanggal = $request->tanggal;
        $data->kategori_detail = $kategoriDetail;
        $data->pegawai_id = $isPegawai ? $request->pegawai_id : null;
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
        $data->nama_kegiatan = $request->nama_kegiatan;
        $data->transport = $transport;
        $data->barokah = $barokah;
        $data->nominal = $nominal;
        $data->total = $isPegawai
            ? (int) round($transport + $barokah)
            : $nominal;
        $data->jenis_pembayaran = $request->jenis_pembayaran;
        $data->rekap_id = $request->rekap_id;
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

    private function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranDosenKegiatan $data): bool
    {
        return false;
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
            KeuanganPengeluaranDosenKegiatan::whereKey($data->id)->update([
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
