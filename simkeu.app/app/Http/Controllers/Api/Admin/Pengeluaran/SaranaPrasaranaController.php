<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\ExcelExport;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranSaranaPrasarana;
use App\Models\KeuanganPengeluaranSaranaPrasaranaRekap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class SaranaPrasaranaController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranRekap;

    private const JENIS_PEMBAYARAN = ['Tunai', 'CUZ BSI', 'Transfer'];

    private const BUKTI_TRANSFER_DIR = 'sarana-prasarana';

    private const LAMPIRAN_DIR = 'sarana-prasarana';

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query()
            ->select([
                'keuangan_pengeluaran_sarana_prasarana.*',
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
            'keuangan_pengeluaran_sarana_prasarana'
        );

        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_sarana_prasarana',
            'keuangan_pengeluaran_sarana_prasarana_rekap',
            'sarana_prasarana'
        );

        $sortColumns = [
            'id' => 'keuangan_pengeluaran_sarana_prasarana.id',
            'tanggal' => 'keuangan_pengeluaran_sarana_prasarana.tanggal',
            'kelompok_anggaran' => 'keuangan_pengeluaran_sarana_prasarana.kelompok_anggaran',
            'nama_kegiatan' => 'keuangan_pengeluaran_sarana_prasarana.nama_kegiatan',
            'nominal' => 'keuangan_pengeluaran_sarana_prasarana.nominal',
            'volume' => 'keuangan_pengeluaran_sarana_prasarana.volume',
            'satuan' => 'keuangan_pengeluaran_sarana_prasarana.satuan',
            'total' => 'keuangan_pengeluaran_sarana_prasarana.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_sarana_prasarana.jenis_pembayaran',
            'nama_rekap' => 'pengeluaran_rekap.nama',
            'created_at' => 'keuangan_pengeluaran_sarana_prasarana.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_sarana_prasarana.id', $sortOrder);

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
            'message' => 'Pengeluaran Sarana Prasarana retrieved successfully',
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

        $data = new KeuanganPengeluaranSaranaPrasarana;
        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithRekap($data->id) ?? $data),
            'message' => 'Pengeluaran Sarana Prasarana created successfully',
        ], 201);
    }

    public function batchStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranSaranaPrasaranaRekap)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.kelompok_anggaran' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.nominal' => ['required', 'numeric', 'min:0'],
            'items.*.volume' => ['nullable', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:255'],
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

                    $data = new KeuanganPengeluaranSaranaPrasarana;
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
            'message' => count($createdIds).' data Pengeluaran Sarana Prasarana berhasil ditambahkan.',
        ], 201);
    }

    public function batchUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranSaranaPrasaranaRekap)->getTable(), 'id'),
            ],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => [
                'integer',
                Rule::exists((new KeuanganPengeluaranSaranaPrasarana)->getTable(), 'id'),
            ],
            'items' => ['required', 'array', 'max:100'],
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists((new KeuanganPengeluaranSaranaPrasarana)->getTable(), 'id'),
            ],
            'items.*.tanggal' => ['required', 'date'],
            'items.*.kelompok_anggaran' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'items.*.nama_kegiatan' => ['required', 'string', 'max:255'],
            'items.*.nominal' => ['required', 'numeric', 'min:0'],
            'items.*.volume' => ['nullable', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:255'],
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
                $existingRows = KeuanganPengeluaranSaranaPrasarana::query()
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
                        : new KeuanganPengeluaranSaranaPrasarana;

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
            'message' => ($result['created'] + $result['updated']).' data Pengeluaran Sarana Prasarana berhasil diperbarui.',
        ]);
    }

    public function show($id)
    {
        $data = $this->findWithRekap($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Sarana Prasarana not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($data),
            'message' => 'Pengeluaran Sarana Prasarana retrieved successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranSaranaPrasarana::class, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Sarana Prasarana not found',
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
            'message' => 'Pengeluaran Sarana Prasarana updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranSaranaPrasarana::class, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Pengeluaran Sarana Prasarana not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $this->deleteLampiran($data->lampiran);
        $this->deletePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'message' => 'Pengeluaran Sarana Prasarana deleted successfully',
        ]);
    }

    public function rekapDestroy($id)
    {
        $deletedFiles = [
            'bukti_transfer' => [],
            'lampiran' => [],
        ];

        $result = DB::transaction(function () use ($id, &$deletedFiles) {
            $rekap = KeuanganPengeluaranSaranaPrasaranaRekap::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $rekap) {
                return null;
            }

            $details = KeuanganPengeluaranSaranaPrasarana::query()
                ->where('rekap_id', $rekap->id)
                ->lockForUpdate()
                ->get();

            foreach ($details as $detail) {
                if ($detail->bukti_transfer) {
                    $deletedFiles['bukti_transfer'][] = $detail->bukti_transfer;
                }

                $deletedFiles['lampiran'][] = $detail->lampiran;
            }

            $deletedDetails = KeuanganPengeluaranSaranaPrasarana::query()
                ->where('rekap_id', $rekap->id)
                ->delete();

            $nama = $rekap->nama;
            $rekap->delete();

            return [
                'nama' => $nama,
                'deleted_details' => $deletedDetails,
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
            ],
            'message' => "Rekap {$result['nama']} dan {$result['deleted_details']} data pengeluaran berhasil dihapus.",
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query()
            ->select([
                'keuangan_pengeluaran_sarana_prasarana.tanggal',
                'pengeluaran_rekap.nama as rekap',
                'keuangan_pengeluaran_sarana_prasarana.kelompok_anggaran',
                'keuangan_pengeluaran_sarana_prasarana.nama_kegiatan',
                'keuangan_pengeluaran_sarana_prasarana.volume',
                'keuangan_pengeluaran_sarana_prasarana.satuan',
                'keuangan_pengeluaran_sarana_prasarana.nominal',
                'keuangan_pengeluaran_sarana_prasarana.total',
                'keuangan_pengeluaran_sarana_prasarana.jenis_pembayaran',
                'keuangan_pengeluaran_sarana_prasarana.keterangan',
            ]);

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $data = $query
            ->orderBy('keuangan_pengeluaran_sarana_prasarana.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_sarana_prasarana.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan Pengeluaran Sarana Prasarana.xlsx');
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Sarana Prasarana'), 'CUZ BSI Pengeluaran Sarana Prasarana.xlsx');
    }

    public function copyBsi(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Sarana Prasarana');

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
        $export = new \App\Exports\BsiPayrollExport($data, 'Pengeluaran Sarana Prasarana');

        $filename = 'Template Batch Payment_' . date('Y-m-d_H-i-s') . '.txt';

        return response($export->txtContent())
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function bsiRows(Request $request)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query()
            ->select([
                'keuangan_pengeluaran_sarana_prasarana.total as amount',
                'keuangan_pengeluaran_sarana_prasarana.nama_kegiatan as message',
            ]);

        $this->joinRekap($query);
        $this->applySearchFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        return $query
            ->where('keuangan_pengeluaran_sarana_prasarana.jenis_pembayaran', 'CUZ BSI')
            ->orderBy('keuangan_pengeluaran_sarana_prasarana.tanggal')
            ->get();
    }

    public function rekapDetailExportExcel(Request $request, $id)
    {
        $rekap = $this->findScopedRekapModel(KeuanganPengeluaranSaranaPrasaranaRekap::class, $id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $tab = $request->input('tab') === 'lpj' ? 'lpj' : 'rab';
        $data = $this->saranaPrasaranaDetailExportRows($request, (int) $rekap->id, $tab);

        if (
            $tab === 'lpj'
            && $data->isEmpty()
            && $this->saranaPrasaranaLpjSameAsRab((int) $rekap->id)
        ) {
            $data = $this->saranaPrasaranaDetailExportRows($request, (int) $rekap->id, 'rab');
        }

        $rows = $data->values()->map(fn ($item) => [
            'kelompok_anggaran' => $item->kelompok_anggaran ?: '-',
            'deskripsi' => $item->nama_kegiatan ?: '-',
            'volume' => $item->volume === null ? '' : (int) $item->volume,
            'satuan' => $item->satuan ?: '',
            'harga_satuan' => (int) ($item->nominal ?? 0),
            'jumlah_harga' => (int) ($item->total ?? 0),
        ])->all();
        $total = array_sum(array_column($rows, 'jumlah_harga'));
        $title = $tab === 'lpj'
            ? 'LAPORAN PERTANGGUNGJAWABAN (LPJ)'
            : 'RENCANA ANGGARAN BIAYA (RAB)';
        $filenamePrefix = $tab === 'lpj' ? 'Detail LPJ' : 'Detail RAB';
        $filename = $this->saranaPrasaranaExportFilename(
            $filenamePrefix.' '.($rekap->nama ?: 'Sarana Prasarana')
        );

        return $this->downloadSaranaPrasaranaRabSpreadsheet(
            $title,
            $rekap->nama ?: '-',
            $this->formatIndonesianDate($rekap->tanggal_rekap ?: $rekap->bulan_tahun),
            $rows,
            $total,
            $filename,
            strtoupper($tab)
        );
    }

    private function saranaPrasaranaDetailExportRows(Request $request, int $rekapId, string $tab)
    {
        $table = $tab === 'lpj'
            ? 'keuangan_pengeluaran_sarana_prasarana_lpj'
            : 'keuangan_pengeluaran_sarana_prasarana';

        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table("{$table} as detail")
            ->leftJoin('keuangan_pengeluaran_sarana_prasarana_rekap as rekap', 'rekap.id', '=', 'detail.rekap_id')
            ->leftJoin('users as petugas', function ($join) {
                $join->on('petugas.id', '=', DB::raw('COALESCE(detail.petugas_id, rekap.petugas_id)'));
            })
            ->where('detail.rekap_id', $rekapId)
            ->select([
                'detail.id',
                'detail.tanggal',
                'detail.kelompok_anggaran',
                'detail.nama_kegiatan',
                'detail.volume',
                'detail.satuan',
                'detail.nominal',
                'detail.total',
                'detail.jenis_pembayaran',
                'detail.keterangan',
                'petugas.name as petugas_nama',
            ]);

        $this->applyPengeluaranGenderScope($query, $table, 'detail');
        $this->applySaranaPrasaranaDetailExportSearch($query, $request);

        return $query
            ->orderBy('detail.kelompok_anggaran')
            ->orderBy('detail.id')
            ->get();
    }

    private function applySaranaPrasaranaDetailExportSearch($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->search);

        $query->where(function ($q) use ($search) {
            $q->where('detail.tanggal', 'LIKE', "%{$search}%")
                ->orWhere('detail.kelompok_anggaran', 'LIKE', "%{$search}%")
                ->orWhere('detail.nama_kegiatan', 'LIKE', "%{$search}%")
                ->orWhere('detail.nominal', 'LIKE', "%{$search}%")
                ->orWhere('detail.volume', 'LIKE', "%{$search}%")
                ->orWhere('detail.satuan', 'LIKE', "%{$search}%")
                ->orWhere('detail.total', 'LIKE', "%{$search}%")
                ->orWhere('detail.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('detail.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('petugas.name', 'LIKE', "%{$search}%");
        });
    }

    private function saranaPrasaranaLpjSameAsRab(int $rekapId): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            return false;
        }

        return DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', 'sarana_prasarana')
            ->where('rekap_id', $rekapId)
            ->where('sama_dengan_rab', 1)
            ->exists();
    }

    private function downloadSaranaPrasaranaRabSpreadsheet(
        string $title,
        string $rekapName,
        string $periode,
        array $rows,
        int $total,
        string $filename,
        string $sheetTitle
    ) {
        $spreadsheet = $this->saranaPrasaranaRabSpreadsheet(
            $title,
            $rekapName,
            $periode,
            $rows,
            $total,
            $sheetTitle
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function saranaPrasaranaRabSpreadsheet(
        string $title,
        string $rekapName,
        string $periode,
        array $rows,
        int $total,
        string $sheetTitle
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->setShowGridlines(true);

        $this->addSaranaPrasaranaKopDrawing($sheet);
        $this->applySaranaPrasaranaColumnWidths($sheet);

        for ($row = 1; $row <= 8; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(23);
        }

        $sheet->mergeCells('A9:I9');
        $sheet->mergeCells('A10:I10');
        $sheet->mergeCells('A11:I11');
        $sheet->setCellValue('A9', $title);
        $sheet->setCellValue('A10', 'SARANA PRASARANA UNIVERSITAS ISLAM INTERNASIONAL');
        $sheet->setCellValue("A11", "DARULLUGHA WADDA'WAH");

        $sheet->setCellValue('A12', 'Nama Rekap');
        $sheet->setCellValue('B12', ':');
        $sheet->setCellValue('C12', $rekapName);
        $sheet->setCellValue('A13', 'Priode');
        $sheet->setCellValue('B13', ':');
        $sheet->setCellValue('C13', $periode);

        $headerRow = 15;
        $firstDataRow = 16;
        $rowCount = max(count($rows), 1);
        $lastDataRow = $firstDataRow + $rowCount - 1;
        $totalRow = $lastDataRow + 1;

        $sheet->setCellValue("A{$headerRow}", 'No');
        $sheet->setCellValue("B{$headerRow}", 'Uraian Kegiatan');
        $sheet->setCellValue("C{$headerRow}", 'Deskripsi');
        $sheet->setCellValue("D{$headerRow}", 'Vol');
        $sheet->setCellValue("E{$headerRow}", 'Satuan');
        $sheet->mergeCells("F{$headerRow}:G{$headerRow}");
        $sheet->setCellValue("F{$headerRow}", 'Harga Satuan (Rp)');
        $sheet->mergeCells("H{$headerRow}:I{$headerRow}");
        $sheet->setCellValue("H{$headerRow}", 'Jumlah Harga (Rp)');

        if ($rows === []) {
            $sheet->setCellValue("A{$firstDataRow}", 1);
            $sheet->setCellValue("B{$firstDataRow}", '-');
            $sheet->setCellValue("C{$firstDataRow}", 'Tidak ada data');
            $sheet->setCellValue("F{$firstDataRow}", 'Rp');
            $sheet->setCellValue("G{$firstDataRow}", 0);
            $sheet->setCellValue("H{$firstDataRow}", 'Rp');
            $sheet->setCellValue("I{$firstDataRow}", 0);
        } else {
            $groups = [];
            $currentGroup = null;

            foreach ($rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $group = $rowData['kelompok_anggaran'] ?: '-';

                if ($currentGroup === null || $currentGroup['label'] !== $group) {
                    if ($currentGroup !== null) {
                        $groups[] = $currentGroup;
                    }

                    $currentGroup = [
                        'label' => $group,
                        'start' => $rowNumber,
                        'end' => $rowNumber,
                    ];
                } else {
                    $currentGroup['end'] = $rowNumber;
                }

                $sheet->setCellValue("A{$rowNumber}", $index + 1);
                $sheet->setCellValue("C{$rowNumber}", $rowData['deskripsi']);
                $sheet->setCellValue("D{$rowNumber}", $rowData['volume']);
                $sheet->setCellValue("E{$rowNumber}", $rowData['satuan']);
                $sheet->setCellValue("F{$rowNumber}", 'Rp');
                $sheet->setCellValue("G{$rowNumber}", $rowData['harga_satuan']);
                $sheet->setCellValue("H{$rowNumber}", 'Rp');
                $sheet->setCellValue("I{$rowNumber}", $rowData['jumlah_harga']);
                $sheet->getRowDimension($rowNumber)->setRowHeight(24);
            }

            if ($currentGroup !== null) {
                $groups[] = $currentGroup;
            }

            foreach ($groups as $group) {
                if ($group['start'] < $group['end']) {
                    $sheet->mergeCells("B{$group['start']}:B{$group['end']}");
                }

                $sheet->setCellValue("B{$group['start']}", $group['label']);
            }
        }

        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total');
        $sheet->setCellValue("H{$totalRow}", 'Rp');
        $sheet->setCellValue("I{$totalRow}", $total);

        $tableRange = "A{$headerRow}:I{$totalRow}";
        $headerRange = "A{$headerRow}:I{$headerRow}";

        $sheet->getStyle('A9:I11')->getFont()
            ->setName('Times New Roman')
            ->setBold(true)
            ->setSize(13);
        $sheet->getStyle('A9:I11')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A12:C13')->getFont()
            ->setName('Times New Roman')
            ->setSize(12);

        $sheet->getStyle($tableRange)->getFont()
            ->setName('Times New Roman')
            ->setSize(12);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($tableRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$firstDataRow}:B{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$firstDataRow}:F{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H{$firstDataRow}:H{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$firstDataRow}:G{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("I{$firstDataRow}:I{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("G{$firstDataRow}:G{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');
        $sheet->getStyle("I{$firstDataRow}:I{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');

        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function addSaranaPrasaranaKopDrawing(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $paths = [
            public_path('img/kop uiidalwa mantap.png'),
            base_path('../public_html/img/kop uiidalwa mantap.png'),
            base_path('public_html/img/kop uiidalwa mantap.png'),
        ];

        $path = collect($paths)->first(fn ($candidate) => is_file($candidate));

        if (! $path) {
            return;
        }

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(6);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(805);
        $drawing->setHeight(172);
        $drawing->setWorksheet($sheet);
    }

    private function applySaranaPrasaranaColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $widths = [
            'A' => 6,
            'B' => 24,
            'C' => 32,
            'D' => 7,
            'E' => 10,
            'F' => 5,
            'G' => 15,
            'H' => 5,
            'I' => 17,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function formatIndonesianDate($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            $date = \Carbon\Carbon::parse($value);
        } catch (Throwable) {
            return (string) $value;
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $date->day.' '.$months[$date->month].' '.$date->year;
    }

    private function saranaPrasaranaExportFilename(string $name): string
    {
        $cleanName = trim((string) preg_replace('/[\\\\\\/:"*?<>|]+/', '-', $name));
        $cleanName = $cleanName !== '' ? $cleanName : 'Detail RAB Sarana Prasarana';

        return "{$cleanName}.xlsx";
    }

    protected function rekapModelClass(): string
    {
        return KeuanganPengeluaranSaranaPrasaranaRekap::class;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_sarana_prasarana';
    }

    protected function newRekapPengeluaranQuery()
    {
        return KeuanganPengeluaranSaranaPrasarana::query();
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query();

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
            'kelompok_anggaran' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:255'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'jenis_pembayaran' => ['required', Rule::in(self::JENIS_PEMBAYARAN)],
            'rekap_id' => [
                'required',
                Rule::exists((new KeuanganPengeluaranSaranaPrasaranaRekap)->getTable(), 'id'),
            ],
            'bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'keterangan' => ['nullable', 'string'],
            ...$this->lampiranRules(),
        ];
    }

    private function fillData(KeuanganPengeluaranSaranaPrasarana $data, Request $request): void
    {
        $nominal = (int) round($this->number($request->nominal));
        $volume = $this->nullableInt($request->volume);
        $satuan = $this->nullableString($request->satuan);

        $data->tanggal = $request->tanggal;
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
        $data->kelompok_anggaran = trim((string) $request->kelompok_anggaran);
        $data->nama_kegiatan = $request->nama_kegiatan;
        $data->nominal = $nominal;
        $data->volume = $volume;
        $data->satuan = $satuan;
        $data->total = $nominal * ($volume ?? 1);
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
            $q->orWhere('keuangan_pengeluaran_sarana_prasarana.tanggal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.kelompok_anggaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.nama_kegiatan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.nominal', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.volume', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.satuan', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_sarana_prasarana.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%{$search}%");
        });
    }

    private function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_sarana_prasarana.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai) {
            $query->where('keuangan_pengeluaran_sarana_prasarana.tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalAkhir) {
            $query->where('keuangan_pengeluaran_sarana_prasarana.tanggal', '<=', $tanggalAkhir);
        }
    }

    private function findWithRekap($id)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query()
            ->select([
                'keuangan_pengeluaran_sarana_prasarana.*',
                'pengeluaran_rekap.nama as nama_rekap',
            ]);

        $this->joinRekap($query);
        $this->applyPetugasFilter($query, new Request);

        return $query->where('keuangan_pengeluaran_sarana_prasarana.id', $id)->first();
    }

    private function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranSaranaPrasarana::query();

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
            KeuanganPengeluaranSaranaPrasarana::whereKey($data->id)->update([
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

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round($this->number($value));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
