<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\ExcelExport;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranUmum;
use App\Models\KeuanganPengeluaranUmumRekap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class UmumController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranLpj;
    use ManagesPengeluaranRekap;

    private const JENIS_PEMBAYARAN = ['Tunai', 'CUZ BSI', 'Transfer'];

    private const BUKTI_TRANSFER_DIR = 'umum';

    private const LAMPIRAN_DIR = 'umum';

    public function lpjShow(Request $request, $id)
    {
        return $this->showModule($request, 'umum', $id);
    }

    public function lpjCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'umum', $id);
    }

    public function lpjUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'umum', $id);
    }

    public function lpjDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'umum', $id);
    }

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranUmum::query()
            ->select([
                'keuangan_pengeluaran_umum.*',
                'pengeluaran_rekap.nama as nama_rekap',
                'petugas.name as petugas_nama',
            ]);

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $stats = $this->aggregatePengeluaranStats(
            $this->newIndexStatsQuery($request),
            'keuangan_pengeluaran_umum'
        );

        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_umum',
            'keuangan_pengeluaran_umum_rekap',
            'umum'
        );

        $sortColumns = [
            'id' => 'keuangan_pengeluaran_umum.id',
            'tanggal' => 'keuangan_pengeluaran_umum.tanggal',
            'nama_kegiatan' => 'keuangan_pengeluaran_umum.nama_kegiatan',
            'nominal' => 'keuangan_pengeluaran_umum.nominal',
            'total' => 'keuangan_pengeluaran_umum.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_umum.jenis_pembayaran',
            'nama_rekap' => 'pengeluaran_rekap.nama',
            'created_at' => 'keuangan_pengeluaran_umum.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_umum.id', $sortOrder);

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
            'message' => 'Pengeluaran Umum retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = new KeuanganPengeluaranUmum;
        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithRekap($data->id) ?? $data),
            'message' => 'Pengeluaran Umum created successfully',
        ], 201);
    }

    public function batchStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranUmumRekap)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.nominal' => ['required', 'numeric', 'min:0'],
            'items.*.jenis_pembayaran' => ['required', Rule::in(self::JENIS_PEMBAYARAN)],
            'items.*.bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'items.*.keterangan' => ['nullable', 'string'],
            'items.*.lampiran' => ['nullable', 'array', 'max:10'],
            'items.*.lampiran.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
        ]);

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

                    $data = new KeuanganPengeluaranUmum;
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
            foreach ($storedLampiran as $itemLampiran) {
                $this->deleteLampiran($itemLampiran);
            }

            throw $exception;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'created' => count($createdIds),
                'ids' => $createdIds,
            ],
            'message' => count($createdIds).' data Pengeluaran Umum berhasil ditambahkan.',
        ], 201);
    }

    public function batchUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranUmumRekap)->getTable(), 'id'),
            ],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => [
                'integer',
                Rule::exists((new KeuanganPengeluaranUmum)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'max:100'],
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists((new KeuanganPengeluaranUmum)->getTable(), 'id'),
            ],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.nominal' => ['required', 'numeric', 'min:0'],
            'items.*.jenis_pembayaran' => ['required', Rule::in(self::JENIS_PEMBAYARAN)],
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
                $existingRows = KeuanganPengeluaranUmum::query()
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
                        : new KeuanganPengeluaranUmum;

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
            foreach ($storedLampiran as $itemLampiran) {
                $this->deleteLampiran($itemLampiran);
            }

            throw $exception;
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => ($result['created'] + $result['updated']).' data Pengeluaran Umum berhasil diperbarui.',
        ]);
    }

    public function show($id)
    {
        $data = $this->findWithRekap($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Umum not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($data),
            'message' => 'Pengeluaran Umum retrieved successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranUmum::class, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Umum not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithRekap($data->id) ?? $data),
            'message' => 'Pengeluaran Umum updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranUmum::class, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Umum not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $this->deleteLampiran($data->lampiran);
        $this->deletePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'message' => 'Pengeluaran Umum deleted successfully',
        ]);
    }

    public function rekapDestroy($id)
    {
        $deletedFiles = [
            'bukti_transfer' => [],
            'lampiran' => [],
        ];

        $result = DB::transaction(function () use ($id, &$deletedFiles) {
            $rekap = KeuanganPengeluaranUmumRekap::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $rekap) {
                return null;
            }

            $details = KeuanganPengeluaranUmum::query()
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

            $deletedDetails = KeuanganPengeluaranUmum::query()
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

        foreach ($deletedFiles['lampiran'] as $itemLampiran) {
            $this->deleteLampiran($itemLampiran);
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

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranUmum::query()
            ->select([
                'keuangan_pengeluaran_umum.tanggal',
                'pengeluaran_rekap.nama as rekap',
                'keuangan_pengeluaran_umum.nama_kegiatan as nama_pengeluaran',
                'keuangan_pengeluaran_umum.nominal',
                'keuangan_pengeluaran_umum.total',
                'keuangan_pengeluaran_umum.jenis_pembayaran',
                'keuangan_pengeluaran_umum.keterangan',
            ]);

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $data = $query
            ->orderBy('keuangan_pengeluaran_umum.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_umum.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan Pengeluaran Umum.xlsx');
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Umum'), 'CUZ BSI Pengeluaran Umum.xlsx');
    }

    public function copyBsi(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Umum');

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
        $export = new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Umum');

        $filename = 'Template Batch Payment_' . date('Y-m-d_H-i-s') . '.txt';

        return response($export->txtContent())
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function bsiRows(Request $request)
    {
        $query = KeuanganPengeluaranUmum::query()
            ->select([
                'keuangan_pengeluaran_umum.total as amount',
                'keuangan_pengeluaran_umum.nama_kegiatan as message',
            ]);

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query
            ->where('keuangan_pengeluaran_umum.jenis_pembayaran', 'CUZ BSI')
            ->orderBy('keuangan_pengeluaran_umum.tanggal')
            ->get();
    }

    protected function rekapModelClass(): string
    {
        return KeuanganPengeluaranUmumRekap::class;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_umum';
    }

    protected function newRekapPengeluaranQuery()
    {
        return KeuanganPengeluaranUmum::query();
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranUmum::query();

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
    }

    protected function requiresRekapForPengeluaran(): bool
    {
        return true;
    }

    private function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'jenis_pembayaran' => ['required', Rule::in(self::JENIS_PEMBAYARAN)],
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranUmumRekap)->getTable(), 'id'),
            ],
            'bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'keterangan' => ['nullable', 'string'],
            ...$this->lampiranRules(),
        ];
    }

    private function fillData(KeuanganPengeluaranUmum $data, Request $request): void
    {
        $nominal = (int) round($this->number($request->nominal));

        $data->tanggal = $request->tanggal;
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
        $data->nama_kegiatan = $request->nama_kegiatan;
        $data->nominal = $nominal;
        $data->total = $nominal;
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

    private function applySearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->search);

        $query->where(function ($q) use ($search) {
            $q->orWhere('keuangan_pengeluaran_umum.tanggal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_umum.nama_kegiatan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_umum.nominal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_umum.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_umum.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_umum.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%{$search}%");
        });
    }

    private function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_umum.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai) {
            $query->where('keuangan_pengeluaran_umum.tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalAkhir) {
            $query->where('keuangan_pengeluaran_umum.tanggal', '<=', $tanggalAkhir);
        }
    }

    private function findWithRekap($id)
    {
        $query = KeuanganPengeluaranUmum::query()
            ->select([
                'keuangan_pengeluaran_umum.*',
                'pengeluaran_rekap.nama as nama_rekap',
            ]);

        $this->joinRekap($query);
        $this->applyPetugasFilter($query, new Request);

        return $query->where('keuangan_pengeluaran_umum.id', $id)->first();
    }

    private function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranUmum::query();

        if ($request->filled('search')) {
            $this->joinRekap($query);
            $this->applySearchFilter($query, $request);
        }

        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query;
    }

    private function appendBuktiTransferUrl($data)
    {
        $path = $this->migrateLegacyBuktiTransfer(
            $data->bukti_transfer,
            self::BUKTI_TRANSFER_DIR
        );

        if ($path !== $data->bukti_transfer) {
            KeuanganPengeluaranUmum::whereKey($data->id)->update([
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
