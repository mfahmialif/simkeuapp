<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\BsiPayrollExport;
use App\Exports\BarokahBulananRekapExport;
use App\Exports\ExcelExport;
use App\Exports\WebAbsensiExport;
use App\Exports\WebAbsensiRekapExport;
use App\Exports\WebAbsensiSlipExport;
use App\Exports\pdf\WebAbsensiPdf;
use App\Exports\pdf\WebAbsensiRekapPdf;
use App\Exports\pdf\WebAbsensiSlipPdf;
use App\Services\WebAbsensiService;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranAbsensiRekap;
use App\Models\KeuanganPengeluaranPegawaiAbsensi;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AbsensiController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranLpj;
    use ManagesPengeluaranRekap;

    private ?array $searchPegawaiIds = null;

    protected const PEGAWAI_TIPE = ['dosen', 'staff'];

    protected const MODULE_NAME = 'Barokah Absensi';

    protected const PEGAWAI_LABEL = 'Pegawai';

    protected const JENIS_PEMBAYARAN = ['CUZ BSI', 'Transfer'];

    protected const REQUIRE_PERIODE = false;

    protected const SUPPORTS_BUKTI_TRANSFER = true;

    protected const BUKTI_TRANSFER_DIR = '';

    protected const LAMPIRAN_DIR = 'absensi';

    protected const REKAP_MODEL = KeuanganPengeluaranAbsensiRekap::class;

    public function lpjShow(Request $request, $id)
    {
        return $this->showModule($request, 'absensi', $id);
    }

    public function lpjCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'absensi', $id);
    }

    public function lpjUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'absensi', $id);
    }

    public function lpjDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'absensi', $id);
    }

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_absensi.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
            'pengeluaran_rekap.nama as nama_rekap',
            'petugas.name as petugas_nama',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPegawaiTipeScope($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPetugasFilter($query, $request);

        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        $stats = $request->filled('rekap_id')
            ? $this->rekapIndexStats($request)
            : $this->fullIndexStats($request);

        if ($this->canUseFastIndexPagination($request)) {
            $data = $this->fastIndexPagination($request, $stats['keseluruhan']['jumlah']);
        } else {
            $this->applySorting($query, $request);

            $data = $this->paginateWithKnownTotal(
                $query,
                $request,
                $stats['keseluruhan']['jumlah']
            );
        }

        $data->getCollection()->transform(fn ($item) => $this->appendPengeluaranFiles($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $stats,
            'message' => static::MODULE_NAME.' retrieved successfully',
        ]);
    }

    private function fullIndexStats(Request $request): array
    {
        if ($request->filled('search')) {
            return $this->searchIndexStats($request);
        }

        $stats = $this->aggregatePengeluaranStats(
            $this->newIndexStatsQuery($request),
            'keuangan_pengeluaran_pegawai_absensi'
        );

        $saldoRekapTable = (new (static::REKAP_MODEL))->getTable();
        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_pegawai_absensi',
            $saldoRekapTable,
            $this->lpjModuleKey($saldoRekapTable)
        );

        return $stats;
    }

    private function searchIndexStats(Request $request): array
    {
        $summary = $this->newIndexStatsQuery($request)
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(keuangan_pengeluaran_pegawai_absensi.total), 0) as total')
            ->first();

        $current = [
            'total' => (int) ($summary->total ?? 0),
            'jumlah' => (int) ($summary->jumlah ?? 0),
        ];

        $empty = ['total' => 0, 'jumlah' => 0];

        return [
            'hari_ini' => $empty,
            'mingguan' => $empty,
            'bulanan' => $current,
            'keseluruhan' => $current,
            'belum_rekap' => $empty,
            'saldo' => [],
        ];
    }

    private function rekapIndexStats(Request $request): array
    {
        $summary = $this->newIndexStatsQuery($request)
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(keuangan_pengeluaran_pegawai_absensi.total), 0) as total')
            ->first();

        $jumlah = (int) ($summary->jumlah ?? 0);
        $total = (int) ($summary->total ?? 0);

        $empty = ['total' => 0, 'jumlah' => 0];
        $current = ['total' => $total, 'jumlah' => $jumlah];

        return [
            'hari_ini' => $empty,
            'mingguan' => $empty,
            'bulanan' => $empty,
            'keseluruhan' => $current,
            'belum_rekap' => $empty,
            'saldo' => [],
        ];
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

        $data = new KeuanganPengeluaranPegawaiAbsensi;
        try {
            DB::transaction(function () use ($data, $request) {
                $this->fillData($data, $request);
                $this->savePengeluaranWithRekapValidation($data);
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithPegawai($data->id) ?? $data),
            'message' => static::MODULE_NAME.' created successfully',
        ], 201);
    }

    public function formData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rekap_id' => $this->rekapIdRules(),
            'copy_rekap_id' => $this->rekapIdRules(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $existing = collect();
        $copiedAmounts = collect();
        $useCopiedAmounts = $request->filled('copy_rekap_id')
            && (string) $request->copy_rekap_id !== (string) $request->rekap_id;

        if ($request->filled('rekap_id')) {
            $existing = KeuanganPengeluaranPegawaiAbsensi::query()
                ->where('rekap_id', $request->rekap_id)
                ->whereIn('pegawai_id', Pegawai::query()
                    ->whereIn('tipe', static::PEGAWAI_TIPE)
                    ->where('status', 'aktif')
                    ->select('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('pegawai_id')
                ->keyBy('pegawai_id');
        }

        if ($useCopiedAmounts) {
            $copiedAmounts = KeuanganPengeluaranPegawaiAbsensi::query()
                ->where('rekap_id', $request->copy_rekap_id)
                ->whereIn('pegawai_id', Pegawai::query()
                    ->whereIn('tipe', static::PEGAWAI_TIPE)
                    ->where('status', 'aktif')
                    ->select('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('pegawai_id')
                ->keyBy('pegawai_id');
        }

        $pegawaiList = Pegawai::query()
            ->with(['dosen.prodi', 'staff'])
            ->whereIn('tipe', static::PEGAWAI_TIPE)
            ->where('status', 'aktif')
            ->orderBy('tipe')
            ->orderBy('nama')
            ->get();

        $pegawaiAbsensi = $pegawaiList
            ->map(function ($pegawai) use ($existing, $copiedAmounts, $useCopiedAmounts) {
                $pengeluaran = $existing->get($pegawai->id);
                $amountSource = $useCopiedAmounts
                    ? $copiedAmounts->get($pegawai->id)
                    : $pengeluaran;

                return [
                    'pegawai_id' => $pegawai->id,
                    'kode' => $pegawai->kode,
                    'nama' => $pegawai->nama,
                    'tipe' => $pegawai->tipe,
                    'status' => $pegawai->status,
                    'jenis_kelamin' => $pegawai->jenis_kelamin,
                    'prodi' => $pegawai->dosen?->prodi?->nama
                        ?? $pegawai->dosen?->prodi?->alias
                        ?? $pegawai->staff?->jabatan,
                    'pengeluaran_id' => $pengeluaran?->id,
                    'total_hari' => (int) ($amountSource?->total_hari ?? 0),
                    'total_jam' => (float) ($amountSource?->total_jam ?? 0),
                    'total_barokah' => (int) ($amountSource?->total_barokah ?? 0),
                    'jenis_pembayaran' => $pengeluaran?->jenis_pembayaran ?? 'CUZ BSI',
                    'bukti_transfer_url' => $this->buktiTransferUrl($pengeluaran?->bukti_transfer),
                    'lampiran' => $pengeluaran
                        ? $this->appendLampiranUrls((object) ['lampiran' => $pengeluaran->lampiran])->lampiran
                        : [],
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $pegawaiAbsensi,
            'message' => 'Data form '.static::MODULE_NAME.' berhasil dimuat.',
        ]);
    }

    public function batchStore(Request $request)
    {
        if ($request->filled('items_json')) {
            $items = json_decode($request->input('items_json'), true);

            if (! is_array($items)) {
                return response()->json([
                    'status' => false,
                    'message' => ['items_json' => ['Data baris tidak valid.']],
                ], 422);
            }

            $request->merge(['items' => $items]);
        }

        $rekapModel = static::REKAP_MODEL;
        $rekapTable = (new $rekapModel)->getTable();
        $validator = Validator::make($request->all(), [
            'rekap_id' => ['required', Rule::exists($rekapTable, 'id')],
            'tanggal' => ['required', 'date'],
            'bulan' => array_merge(static::REQUIRE_PERIODE ? ['required'] : ['nullable'], ['integer', 'min:1', 'max:12']),
            'tahun' => array_merge(static::REQUIRE_PERIODE ? ['required'] : ['nullable'], ['integer', 'min:1900', 'max:2100']),
            'jenis_pembayaran' => ['nullable', Rule::in(static::JENIS_PEMBAYARAN)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pegawai_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->whereIn('tipe', static::PEGAWAI_TIPE)),
            ],
            'items.*.total_hari' => ['nullable', 'integer', 'min:0'],
            'items.*.total_jam' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_barokah' => ['nullable', 'integer', 'min:0'],
            'items.*.jenis_pembayaran' => ['nullable', Rule::in(static::JENIS_PEMBAYARAN)],
            'items.*.bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'items.*.lampiran' => ['nullable', 'array', 'max:10'],
            'items.*.lampiran.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:10240'],
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
        try {
            $result = DB::transaction(function () use ($payload, $request) {
            $created = 0;
            $updated = 0;
            $deleted = 0;
            $this->lockRekapRows([$payload['rekap_id']]);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$payload['rekap_id']]);
            $pegawaiIds = collect($payload['items'])->pluck('pegawai_id')->unique()->values();
            $pegawaiTypes = Pegawai::query()
                ->whereIn('id', $pegawaiIds)
                ->pluck('tipe', 'id');
            $recordsByPegawai = KeuanganPengeluaranPegawaiAbsensi::query()
                ->where('rekap_id', $payload['rekap_id'])
                ->whereIn('pegawai_id', $pegawaiIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('pegawai_id');

            foreach ($payload['items'] as $index => $item) {
                $totalHari = (int) ($item['total_hari'] ?? 0);
                $totalJam = round($this->number($item['total_jam'] ?? 0), 2);
                $totalBarokah = (int) ($item['total_barokah'] ?? 0);
                $total = $totalBarokah;
                $records = $recordsByPegawai->get($item['pegawai_id'], collect());
                $paymentType = $item['jenis_pembayaran'] ?? $payload['jenis_pembayaran'] ?? 'CUZ BSI';

                if ($totalBarokah === 0) {
                    if ($records->isNotEmpty()) {
                        foreach ($records as $record) {
                            $this->deleteBuktiTransfer($record->bukti_transfer);
                            $this->deleteLampiran($record->lampiran);
                        }

                        $deleted += KeuanganPengeluaranPegawaiAbsensi::query()
                            ->whereIn('id', $records->pluck('id'))
                            ->delete();
                    }

                    continue;
                }

                $data = $records->first() ?? new KeuanganPengeluaranPegawaiAbsensi;
                $isNew = ! $data->exists;
                $data->pegawai_id = $item['pegawai_id'];
                $data->petugas_id = $this->petugasIdForRekapId((int) $payload['rekap_id']) ?? auth()->id();
                $data->pegawai_tipe = $pegawaiTypes->get($item['pegawai_id']);
                $data->rekap_id = $payload['rekap_id'];
                $data->tanggal = $payload['tanggal'];
                $data->bulan = $payload['bulan'] ?? (int) date('n', strtotime($payload['tanggal']));
                $data->tahun = $payload['tahun'] ?? (int) date('Y', strtotime($payload['tanggal']));
                $data->total_hari = $totalHari;
                $data->total_jam = $totalJam;
                $data->total_barokah = $totalBarokah;
                $data->total = $total;
                $data->jenis_pembayaran = $paymentType;
                $rowRequest = Request::create('/', 'POST', $item);
                $rowLampiran = $request->file("items.{$index}.lampiran", []);
                if ($rowLampiran) {
                    $rowRequest->files->set('lampiran', $rowLampiran);
                }
                $data->lampiran = $this->updateLampiran($rowRequest, $data->lampiran, static::LAMPIRAN_DIR);
                if (static::SUPPORTS_BUKTI_TRANSFER) {
                    $buktiTransfer = $request->file("items.{$index}.bukti_transfer");

                    if ($buktiTransfer) {
                        $directory = static::BUKTI_TRANSFER_DIR ?: static::LAMPIRAN_DIR;
                        $newBuktiTransfer = $this->storeBuktiTransfer($buktiTransfer, $directory);
                        $this->deleteBuktiTransfer($data->bukti_transfer);
                        $data->bukti_transfer = $newBuktiTransfer;
                    }

                    if ($paymentType !== 'Transfer') {
                        $this->deleteBuktiTransfer($data->bukti_transfer);
                        $data->bukti_transfer = null;
                    }
                }
                $data->save();

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }

                if ($records->count() > 1) {
                    $duplicates = $records->skip(1);
                    foreach ($duplicates as $duplicate) {
                        $this->deleteBuktiTransfer($duplicate->bukti_transfer);
                        $this->deleteLampiran($duplicate->lampiran);
                    }

                    $duplicateIds = $duplicates->pluck('id');
                    $deleted += KeuanganPengeluaranPegawaiAbsensi::query()
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            }

            $this->validateAndSyncRekapTemporary(
                [$payload['rekap_id']],
                $emptyFallbackAmounts
            );

            return compact('created', 'updated', 'deleted');
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => "{$result['created']} data ditambahkan, {$result['updated']} data diperbarui, {$result['deleted']} data kosong dihapus.",
        ]);
    }

    public function show($id)
    {
        $data = $this->findWithPegawai($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME.' not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($data),
            'message' => static::MODULE_NAME.' retrieved successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranPegawaiAbsensi::class, $id);

        if (! $data || ! $this->findWithPegawai($id)) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME.' not found',
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

        try {
            DB::transaction(function () use ($data, $request) {
                $this->fillData($data, $request);
                $this->savePengeluaranWithRekapValidation($data);
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithPegawai($data->id) ?? $data),
            'message' => static::MODULE_NAME.' updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranPegawaiAbsensi::class, $id);

        if (! $data || ! $this->findWithPegawai($id)) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME.' not found',
            ], 404);
        }

        if (static::SUPPORTS_BUKTI_TRANSFER) {
            $this->deleteBuktiTransfer($data->bukti_transfer);
        }

        DB::transaction(function () use ($data) {
            $this->deleteLampiran($data->lampiran);
            $this->deletePengeluaranWithRekapValidation($data);
        });

        return response()->json([
            'status' => true,
            'message' => static::MODULE_NAME.' deleted successfully',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_absensi.tanggal',
            'keuangan_pengeluaran_pegawai_absensi.bulan',
            'keuangan_pengeluaran_pegawai_absensi.tahun',
            'pegawai.kode as kode_pegawai',
            'pegawai.nama as nama_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin',
            'prodi.nama as prodi',
            'staff.jabatan as jabatan',
            'pengeluaran_rekap.nama as rekap',
            'keuangan_pengeluaran_pegawai_absensi.total_hari',
            'keuangan_pengeluaran_pegawai_absensi.total_jam',
            'keuangan_pengeluaran_pegawai_absensi.total_barokah',
            'keuangan_pengeluaran_pegawai_absensi.total',
            'keuangan_pengeluaran_pegawai_absensi.jenis_pembayaran',
            'keuangan_pengeluaran_pegawai_absensi.keterangan',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPegawaiTipeScope($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPetugasFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        if ($request->filled('rekap_id') && (! $request->filled('sort_key') || $request->input('sort_key') === 'id')) {
            $data = $query->orderBy('pegawai.nama', 'asc')->get();
        } else {
            $data = $query
                ->orderBy('keuangan_pengeluaran_pegawai_absensi.tanggal', 'desc')
                ->orderBy('keuangan_pengeluaran_pegawai_absensi.id', 'desc')
                ->get();
        }

        return Excel::download(new ExcelExport($data), 'Laporan '.static::MODULE_NAME.'.xlsx');
    }

    public function rekapExportExcel(Request $request)
    {
        $data = $this->absensiRekapRows($request);
        $period = $this->requestExportPeriodLabel($request);
        $title = trim('REKAP BAROKAH ABSENSI '.$period);
        $headings = [
            'NO',
            'NAMA REKAP',
            'PERIODE',
            'TGL REKAP',
            'DATA',
            'TOTAL RAB',
            'TOTAL LPJ',
            'SELISIH',
            'KETERANGAN',
        ];

        $rows = $data->values()->map(function ($item, $index) {
            $totalRab = (int) ($item->jumlah ?? 0);
            $totalLpj = (int) ($item->total_lpj ?? 0);

            return [
                $index + 1,
                $item->nama,
                $this->formatExportPeriod($item->bulan_tahun),
                $this->formatExportDate($item->tanggal_rekap),
                (int) ($item->jumlah_data ?? 0),
                $totalRab,
                $totalLpj,
                $totalRab - $totalLpj,
                $item->keterangan ?: '',
            ];
        })->all();

        $totalRow = [
            '',
            'TOTAL',
            '',
            '',
            $data->sum(fn ($item) => (int) ($item->jumlah_data ?? 0)),
            $data->sum(fn ($item) => (int) ($item->jumlah ?? 0)),
            $data->sum(fn ($item) => (int) ($item->total_lpj ?? 0)),
            $data->sum(fn ($item) => (int) ($item->jumlah ?? 0) - (int) ($item->total_lpj ?? 0)),
            '',
        ];

        return $this->downloadAbsensiRekapListSpreadsheet(
            $title,
            $headings,
            $rows,
            [6, 7, 8],
            $totalRow,
            $this->excelExportFilename(trim('Rekap Barokah Absensi '.$period))
        );
    }

    public function rekapDetailExportExcel(Request $request, $id)
    {
        $modelClass = $this->rekapModelClass();
        $rekap = $this->findScopedRekapModel($modelClass, $id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $tab = $request->input('tab') === 'lpj' ? 'lpj' : 'rab';
        $data = $tab === 'lpj'
            ? $this->absensiLpjDetailRows($request, (int) $rekap->id)
            : $this->absensiRabDetailRows($request, (int) $rekap->id);

        if ($tab === 'lpj' && $data->isEmpty() && $this->absensiLpjSameAsRab((int) $rekap->id)) {
            $data = $this->absensiRabDetailRows($request, (int) $rekap->id);
        }

        $headings = ['NO', 'NAMA', 'TOTAL HARI', 'TOTAL JAM', 'TOTAL BAROKAH', 'No Rek', 'KETERANGAN'];
        $rows = $data->values()->map(function ($item, $index) {
            return [
                $index + 1,
                $item->nama_pegawai ?: '-',
                (int) ($item->total_hari ?? 0),
                (int) ($item->total_jam ?? 0),
                (int) ($item->total_barokah ?? $item->total ?? 0),
                (string) ($item->nomer_rekening ?? ''),
                $item->keterangan ?: ($item->jenis_pembayaran ?: ''),
            ];
        })->all();
        $totalRow = [
            '',
            'TOTAL',
            $data->sum(fn ($item) => (int) ($item->total_hari ?? 0)),
            $data->sum(fn ($item) => (int) ($item->total_jam ?? 0)),
            $data->sum(fn ($item) => (int) ($item->total_barokah ?? $item->total ?? 0)),
            '',
            '',
        ];
        $period = $this->formatExportPeriod($rekap->bulan_tahun);
        $titlePrefix = $tab === 'lpj' ? 'LIST LPJ BAROKAH ABSENSI' : 'LIST BAROKAH ABSENSI';

        return Excel::download(
            new BarokahBulananRekapExport(
                trim($titlePrefix.' '.$period),
                $headings,
                $rows,
                [5],
                $totalRow,
                [6]
            ),
            $this->excelExportFilename(trim(($tab === 'lpj' ? 'Detail LPJ ' : 'Detail RAB ').($rekap->nama ?: static::MODULE_NAME)))
        );
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new BsiPayrollExport($data, $this->bsiMessage()), 'CUZ BSI '.static::MODULE_NAME.'.xlsx');
    }

    public function copyBsi(Request $request)
    {
        $data = $this->bsiRows($request);
        $export = new BsiPayrollExport($data, $this->bsiMessage());

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
        $export = new BsiPayrollExport($data, $this->bsiMessage());

        $filename = 'Template Batch Payment_' . date('Y-m-d_H-i-s') . '.txt';

        return response($export->txtContent())
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function bsiRows(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $query->select([
            'pegawai.nomer_rekening as beneficiary_acct',
            $this->bsiBeneficiaryNameSelect(),
            DB::raw('SUM(keuangan_pengeluaran_pegawai_absensi.total) as amount'),
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPegawaiTipeScope($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPetugasFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        return $query
            ->where('keuangan_pengeluaran_pegawai_absensi.jenis_pembayaran', 'CUZ BSI')
            ->groupBy($this->bsiGroupColumns())
            ->orderBy('pegawai.nama')
            ->get();
    }

    protected function bsiGroupColumns(): array
    {
        $columns = [
            'keuangan_pengeluaran_pegawai_absensi.pegawai_id',
            'pegawai.nomer_rekening',
            'pegawai.nama',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            $columns[] = 'pegawai.nama_pemilik_rekening';
        }

        return $columns;
    }

    protected function bsiMessage(): string
    {
        return 'barokah absensi';
    }

    private function absensiRekapRows(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $filteredRekaps = $this->filteredRekapBaseQuery($request, $modelClass, $rekapTable);
        $summary = $this->rekapSummaryQuery($request, $filteredRekaps);
        $lpjSummary = $this->lpjSummaryQuery($request, $filteredRekaps);
        $lpjModuleKey = $this->lpjModuleKey($rekapTable);
        $hasLpjStatus = $lpjSummary
            && $lpjModuleKey
            && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status');

        $select = [
            "{$rekapTable}.*",
            DB::raw('COALESCE(rekap_summary.jumlah_data, 0) as jumlah_data'),
            DB::raw('COALESCE(rekap_summary.total_pengeluaran, 0) as total_pengeluaran'),
            DB::raw($this->effectiveAmountSql($rekapTable).' as jumlah'),
            DB::raw('CASE WHEN COALESCE(rekap_summary.jumlah_data, 0) = 0 THEN 1 ELSE 0 END as is_jumlah_sementara'),
            DB::raw($this->temporaryDifferenceSql($rekapTable).' as selisih_sementara'),
        ];

        if ($hasLpjStatus) {
            $select[] = DB::raw('COALESCE(lpj_summary.jumlah_lpj, 0) as jumlah_lpj');
            $select[] = DB::raw($this->effectiveLpjAmountSql($rekapTable, $request->filled('petugas_id')).' as total_lpj');
            $select[] = DB::raw('COALESCE(lpj_status.sama_dengan_rab, 0) as lpj_sama_dengan_rab');
        } else {
            $select[] = DB::raw('0 as jumlah_lpj');
            $select[] = DB::raw('0 as total_lpj');
            $select[] = DB::raw('0 as lpj_sama_dengan_rab');
        }

        $query = DB::query()
            ->fromSub($filteredRekaps, $rekapTable)
            ->select($select)
            ->leftJoinSub(
                $summary,
                'rekap_summary',
                'rekap_summary.rekap_id',
                '=',
                "{$rekapTable}.id"
            );

        if ($hasLpjStatus) {
            $query
                ->leftJoinSub(
                    $lpjSummary,
                    'lpj_summary',
                    'lpj_summary.rekap_id',
                    '=',
                    "{$rekapTable}.id"
                )
                ->leftJoin('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($rekapTable, $lpjModuleKey) {
                    $join->on('lpj_status.rekap_id', '=', "{$rekapTable}.id")
                        ->where('lpj_status.module_key', '=', $lpjModuleKey);
                });
        }

        $sortColumns = [
            'id' => "{$rekapTable}.id",
            'nama' => "{$rekapTable}.nama",
            'tanggal_rekap' => "{$rekapTable}.tanggal_rekap",
            'tanggal_pencairan' => "{$rekapTable}.tanggal_pencairan",
            'jumlah' => 'jumlah',
            'jumlah_data' => 'jumlah_data',
            'total_pengeluaran' => 'total_pengeluaran',
            'total_lpj' => 'total_lpj',
            'created_at' => "{$rekapTable}.created_at",
        ];
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $data = $query
            ->orderBy($sortColumns[$sortKey] ?? "{$rekapTable}.id", $sortOrder)
            ->get();

        $data->each(fn ($item) => $this->castRekapSummary($item));

        return $data;
    }

    private function absensiRabDetailRows(Request $request, int $rekapId)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_absensi.id',
            'keuangan_pengeluaran_pegawai_absensi.tanggal',
            'keuangan_pengeluaran_pegawai_absensi.total_hari',
            'keuangan_pengeluaran_pegawai_absensi.total_jam',
            'keuangan_pengeluaran_pegawai_absensi.total_barokah',
            'keuangan_pengeluaran_pegawai_absensi.total',
            'keuangan_pengeluaran_pegawai_absensi.jenis_pembayaran',
            'keuangan_pengeluaran_pegawai_absensi.keterangan',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.nomer_rekening as nomer_rekening',
        ]);

        $this->joinPegawaiDetail($query);
        $this->applyPegawaiTipeScope($query);
        $this->applyPetugasFilter($query, $request);
        $this->applySearchFilter($query, $request);
        $this->applyAbsensiDetailSorting($query, $request, 'keuangan_pengeluaran_pegawai_absensi');

        return $query
            ->where('keuangan_pengeluaran_pegawai_absensi.rekap_id', $rekapId)
            ->get();
    }

    private function absensiLpjDetailRows(Request $request, int $rekapId)
    {
        $table = 'keuangan_pengeluaran_pegawai_absensi_lpj';

        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table("{$table} as lpj")
            ->leftJoin('pegawai', 'pegawai.id', '=', 'lpj.pegawai_id')
            ->where('lpj.rekap_id', $rekapId)
            ->select([
                'lpj.id',
                'lpj.tanggal',
                'lpj.total_hari',
                'lpj.total_jam',
                'lpj.total_barokah',
                'lpj.total',
                'lpj.jenis_pembayaran',
                'lpj.keterangan',
                'pegawai.nama as nama_pegawai',
                'pegawai.kode as kode_pegawai',
                'pegawai.nomer_rekening as nomer_rekening',
            ]);

        $this->applyPengeluaranGenderScope($query, $table, 'lpj');

        if (Schema::hasColumn($table, 'pegawai_tipe')) {
            $query->whereIn('lpj.pegawai_tipe', static::PEGAWAI_TIPE);
        }

        if ($request->filled('petugas_id') && Schema::hasColumn($table, 'petugas_id')) {
            $query->where('lpj.petugas_id', $request->petugas_id);
        }

        $this->applyAbsensiLpjSearchFilter($query, $request);
        $this->applyAbsensiDetailSorting($query, $request, 'lpj');

        return $query->get();
    }

    private function applyAbsensiDetailSorting($query, Request $request, string $table): void
    {
        $sortColumns = [
            'id' => "{$table}.id",
            'tanggal' => "{$table}.tanggal",
            'total' => "{$table}.total",
            'jenis_pembayaran' => "{$table}.jenis_pembayaran",
            'keterangan' => "{$table}.keterangan",
            'pegawai' => 'pegawai.nama',
            'nama_pegawai' => 'pegawai.nama',
            'nama' => 'pegawai.nama',
        ];

        if (! $request->filled('sort_key') || $request->input('sort_key') === 'id') {
            $query->orderBy('pegawai.nama', 'asc');
            return;
        }

        $sortKey = $request->input('sort_key', 'nama_pegawai');
        $sortOrder = $request->input('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortColumns[$sortKey] ?? 'pegawai.nama', $sortOrder);
    }

    private function applyAbsensiLpjSearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->search);

        if ($search === '') {
            return;
        }

        $pegawaiIds = $this->searchPegawaiIds($search);
        $isNumericSearch = is_numeric($search);
        $isDateSearch = (bool) preg_match('/^\d{4}(-\d{1,2})?(-\d{1,2})?$/', $search);

        $query->where(function ($q) use ($search, $pegawaiIds, $isNumericSearch, $isDateSearch) {
            if ($pegawaiIds) {
                $q->orWhereIn('lpj.pegawai_id', $pegawaiIds);
            }

            $q->orWhere('lpj.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('lpj.jenis_pembayaran', 'LIKE', "%{$search}%");

            if ($isDateSearch) {
                $q->orWhere('lpj.tanggal', 'LIKE', "{$search}%");
            }

            if ($isNumericSearch) {
                $q->orWhere('lpj.bulan', (int) $search)
                    ->orWhere('lpj.tahun', (int) $search)
                    ->orWhere('lpj.total', (int) $search);
            }
        });
    }

    private function absensiLpjSameAsRab(int $rekapId): bool
    {
        return Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')
            && DB::table('keuangan_pengeluaran_lpj_rekap_status')
                ->where('module_key', 'absensi')
                ->where('rekap_id', $rekapId)
                ->where('sama_dengan_rab', true)
                ->exists();
    }

    private function requestExportPeriodLabel(Request $request): string
    {
        if ($request->filled('bulan') && $request->filled('tahun')) {
            $bulan = (int) $request->bulan;
            $tahun = (int) $request->tahun;

            if ($bulan >= 1 && $bulan <= 12 && $tahun > 0) {
                return strtoupper(Carbon::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y'));
            }
        }

        if ($request->filled('tahun')) {
            return (string) $request->tahun;
        }

        return '';
    }

    private function formatExportPeriod($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return strtoupper(Carbon::parse($value)->locale('id')->translatedFormat('F Y'));
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatExportDate($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function excelExportFilename(string $name): string
    {
        $safeName = trim(preg_replace('/[\\\\\\/:*?"<>|]+/', '-', $name));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return ($safeName ?: 'Export').'.xlsx';
    }

    private function downloadAbsensiRekapListSpreadsheet(
        string $title,
        array $headings,
        array $rows,
        array $amountColumns,
        array $totalRow,
        string $filename
    ) {
        $spreadsheet = $this->absensiRekapListSpreadsheet($title, $headings, $rows, $amountColumns, $totalRow);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function absensiRekapListSpreadsheet(
        string $title,
        array $headings,
        array $rows,
        array $amountColumns,
        array $totalRow
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');

        $headerRow = 14;
        $firstDataRow = 15;
        $lastColumnIndex = count($headings) + 1;
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumnIndex);
        $lastRow = $firstDataRow + count($rows);

        $this->addAbsensiKopDrawing($sheet);
        $this->applyAbsensiRekapColumnWidths($sheet, $lastColumnIndex);

        for ($row = 1; $row <= 12; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $sheet->setCellValue('C13', $title);
        $sheet->getStyle('C13')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('C13')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        foreach (array_values($headings) as $index => $heading) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 2);
            $sheet->setCellValue($column.$headerRow, $heading);
        }

        foreach (array_values($rows) as $rowIndex => $rowData) {
            $rowNumber = $firstDataRow + $rowIndex;
            foreach (array_values($rowData) as $columnIndex => $value) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 2);
                $sheet->setCellValue($column.$rowNumber, $columnIndex === 0 ? $rowIndex + 1 : $value);
            }
        }

        foreach (array_values($totalRow) as $columnIndex => $value) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 2);
            $sheet->setCellValue($column.$lastRow, $value);
        }

        foreach ($amountColumns as $columnNumber) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber + 1);
            $sheet->getStyle($column.$firstDataRow.':'.$column.$lastRow)
                ->getNumberFormat()
                ->setFormatCode('_-"Rp"* #,##0_-;_-"Rp"* -#,##0_-;_-"Rp"* "-"_-;_-@_-');
        }

        $headerRange = 'B'.$headerRow.':'.$lastColumn.$headerRow;
        $tableRange = 'B'.$headerRow.':'.$lastColumn.$lastRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($tableRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('B'.$firstDataRow.':B'.$lastRow)
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.$lastRow.':'.$lastColumn.$lastRow)->getFont()->setBold(true);

        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function addAbsensiKopDrawing(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $path = public_path('img/kop uiidalwa mantap.png');

        if (! is_file($path)) {
            return;
        }

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('B1');
        $drawing->setCoordinates2('J12');
        $drawing->setEditAs(\PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing::EDIT_AS_ONECELL);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(957);
        $drawing->setHeight(213);
        $drawing->setWorksheet($sheet);
    }

    private function applyAbsensiRekapColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $lastColumnIndex): void
    {
        $defaults = [
            'A' => 8.78,
            'B' => 5,
            'C' => 57.78,
            'D' => 19.78,
            'E' => 25.66,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 36,
        ];

        for ($index = 1; $index <= $lastColumnIndex; $index++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($column)->setWidth($defaults[$column] ?? 18);
        }
    }

    protected function joinPegawaiDetail($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_absensi.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    protected function applyPegawaiTipeScope($query): void
    {
        $query->whereIn('keuangan_pengeluaran_pegawai_absensi.pegawai_tipe', static::PEGAWAI_TIPE);
    }

    protected function rekapModelClass(): string
    {
        return static::REKAP_MODEL;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_pegawai_absensi';
    }

    protected function newRekapPengeluaranQuery()
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();
        $this->joinPegawaiDetail($query);

        $this->applyPegawaiTipeScope($query);

        return $query;
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPegawaiTipeScope($query);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPetugasFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        return $query;
    }

    protected function bsiBeneficiaryNameSelect()
    {
        if ($this->hasNamaPemilikRekeningColumn()) {
            return DB::raw("COALESCE(NULLIF(TRIM(pegawai.nama_pemilik_rekening), ''), pegawai.nama) as beneficiary_acct_name");
        }

        return 'pegawai.nama as beneficiary_acct_name';
    }

    protected function hasNamaPemilikRekeningColumn(): bool
    {
        return Schema::hasColumn('pegawai', 'nama_pemilik_rekening');
    }

    private function findWithPegawai($id)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_absensi.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
            'pengeluaran_rekap.nama as nama_rekap',
            'petugas.name as petugas_nama',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $this->applyPegawaiTipeScope($query);
        $this->applyPetugasFilter($query, new Request);

        return $query->where('keuangan_pengeluaran_pegawai_absensi.id', $id)->first();
    }

    protected function applySearchFilter($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->search);

        if ($search === '') {
            return;
        }

        $pegawaiIds = $this->searchPegawaiIds($search);
        $isNumericSearch = is_numeric($search);
        $isDateSearch = (bool) preg_match('/^\d{4}(-\d{1,2})?(-\d{1,2})?$/', $search);

        if (! $isNumericSearch && ! $isDateSearch) {
            if (! $pegawaiIds) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('keuangan_pengeluaran_pegawai_absensi.pegawai_id', $pegawaiIds);

            return;
        }

        $query->where(function ($q) use ($search, $pegawaiIds, $isNumericSearch, $isDateSearch) {
            if ($pegawaiIds) {
                $q->orWhereIn('keuangan_pengeluaran_pegawai_absensi.pegawai_id', $pegawaiIds);
            }

            if ($isDateSearch) {
                $q->orWhere('keuangan_pengeluaran_pegawai_absensi.tanggal', 'LIKE', "{$search}%");
            }

            if ($isNumericSearch) {
                $q->orWhere('keuangan_pengeluaran_pegawai_absensi.bulan', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_absensi.tahun', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_absensi.total', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_absensi.total_hari', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_absensi.total_barokah', (int) $search);
            }
        });
    }

    private function searchPegawaiIds(string $search): array
    {
        if ($this->searchPegawaiIds !== null) {
            return $this->searchPegawaiIds;
        }

        return $this->searchPegawaiIds = DB::table('pegawai')
            ->where(function ($query) use ($search) {
                $query
                    ->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('kode', 'LIKE', "%{$search}%")
                    ->orWhere('jenis_kelamin', 'LIKE', "%{$search}%");
            })
            ->limit(2000)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function applyPegawaiFilter($query, Request $request): void
    {
        if ($request->filled('kode')) {
            $query->where('pegawai.kode', $request->kode);
        }

        if ($request->filled('pegawai_id')) {
            $query->where('keuangan_pengeluaran_pegawai_absensi.pegawai_id', $request->pegawai_id);
        }
    }

    protected function applyDateFilter($query, Request $request): void
    {
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        if ($tanggalMulai && $tanggalAkhir) {
            $query->whereBetween('keuangan_pengeluaran_pegawai_absensi.tanggal', [
                $tanggalMulai,
                $tanggalAkhir,
            ]);
        } elseif ($tanggalMulai) {
            $query->where('keuangan_pengeluaran_pegawai_absensi.tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalAkhir) {
            $query->where('keuangan_pengeluaran_pegawai_absensi.tanggal', '<=', $tanggalAkhir);
        }
    }

    protected function applyPeriodFilter($query, Request $request): void
    {
        if ($request->filled('bulan')) {
            $query->where('keuangan_pengeluaran_pegawai_absensi.bulan', (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->where('keuangan_pengeluaran_pegawai_absensi.tahun', (int) $request->tahun);
        }
    }

    protected function applySorting($query, Request $request): void
    {
        $sortColumns = [
            'id' => 'keuangan_pengeluaran_pegawai_absensi.id',
            'tanggal' => 'keuangan_pengeluaran_pegawai_absensi.tanggal',
            'bulan' => 'keuangan_pengeluaran_pegawai_absensi.bulan',
            'tahun' => 'keuangan_pengeluaran_pegawai_absensi.tahun',
            'pegawai_id' => 'keuangan_pengeluaran_pegawai_absensi.pegawai_id',
            'kode_pegawai' => 'pegawai.kode',
            'nama_pegawai' => 'pegawai.nama',
            'nama' => 'pegawai.nama',
            'pegawai' => 'pegawai.nama',
            'total_hari' => 'keuangan_pengeluaran_pegawai_absensi.total_hari',
            'total_jam' => 'keuangan_pengeluaran_pegawai_absensi.total_jam',
            'total_barokah' => 'keuangan_pengeluaran_pegawai_absensi.total_barokah',
            'total' => 'keuangan_pengeluaran_pegawai_absensi.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_pegawai_absensi.jenis_pembayaran',
            'nama_rekap' => 'pengeluaran_rekap.nama',
            'created_at' => 'keuangan_pengeluaran_pegawai_absensi.created_at',
        ];

        if ($request->filled('rekap_id') && (! $request->filled('sort_key') || $request->input('sort_key') === 'id')) {
            $query->orderBy('pegawai.nama', 'asc');
            return;
        }

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_pegawai_absensi.id', $sortOrder);
    }

    private function canUseFastIndexPagination(Request $request): bool
    {
        if ($request->filled('kode')) {
            return false;
        }

        return array_key_exists($request->input('sort_key', 'id'), $this->fastIndexSortColumns());
    }

    private function fastIndexPagination(Request $request, int $total): LengthAwarePaginator
    {
        $perPage = max(1, (int) $request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumn = $this->fastIndexSortColumns()[$sortKey] ?? 'keuangan_pengeluaran_pegawai_absensi.id';

        $idQuery = KeuanganPengeluaranPegawaiAbsensi::query();
        $this->applyPegawaiTipeScope($idQuery);

        $this->applyPegawaiFilter($idQuery, $request);
        $this->applySearchFilter($idQuery, $request);
        $this->applyPetugasFilter($idQuery, $request);
        $this->applyPeriodFilter($idQuery, $request);
        $this->applyDateFilter($idQuery, $request);
        $this->applyRekapFilter($idQuery, $request);

        $ids = $idQuery
            ->orderBy($sortColumn, $sortOrder)
            ->forPage($page, $perPage)
            ->pluck('keuangan_pengeluaran_pegawai_absensi.id')
            ->values();

        if ($ids->isEmpty()) {
            return new LengthAwarePaginator(collect(), $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $query = KeuanganPengeluaranPegawaiAbsensi::query();
        $query->select([
            'keuangan_pengeluaran_pegawai_absensi.*',
            'pegawai.nama as nama_pegawai',
            'pegawai.kode as kode_pegawai',
            'pegawai.tipe as tipe_pegawai',
            'pegawai.jenis_kelamin as jenis_kelamin_pegawai',
            'prodi.nama as nama_prodi_dosen',
            'staff.jabatan as jabatan_staff',
            'pengeluaran_rekap.nama as nama_rekap',
            'petugas.name as petugas_nama',
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);

        $orderedIds = $ids->implode(',');
        $items = $query
            ->whereIn('keuangan_pengeluaran_pegawai_absensi.id', $ids)
            ->orderByRaw("FIELD(keuangan_pengeluaran_pegawai_absensi.id, {$orderedIds})")
            ->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function fastIndexSortColumns(): array
    {
        return [
            'id' => 'keuangan_pengeluaran_pegawai_absensi.id',
            'tanggal' => 'keuangan_pengeluaran_pegawai_absensi.tanggal',
            'bulan' => 'keuangan_pengeluaran_pegawai_absensi.bulan',
            'tahun' => 'keuangan_pengeluaran_pegawai_absensi.tahun',
            'pegawai_id' => 'keuangan_pengeluaran_pegawai_absensi.pegawai_id',
            'total_hari' => 'keuangan_pengeluaran_pegawai_absensi.total_hari',
            'total_jam' => 'keuangan_pengeluaran_pegawai_absensi.total_jam',
            'total_barokah' => 'keuangan_pengeluaran_pegawai_absensi.total_barokah',
            'total' => 'keuangan_pengeluaran_pegawai_absensi.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_pegawai_absensi.jenis_pembayaran',
            'created_at' => 'keuangan_pengeluaran_pegawai_absensi.created_at',
        ];
    }

    protected function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiAbsensi::query();
        $this->applyPegawaiTipeScope($query);

        if ($request->filled('search')) {
            $this->applySearchFilter($query, $request);
        } elseif ($request->filled('kode')) {
            $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_absensi.pegawai_id');
        }

        $this->applyPegawaiFilter($query, $request);
        $this->applyPetugasFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        return $query;
    }

    protected function rules(bool $isUpdate): array
    {
        $rules = [
            'tanggal' => 'required|date',
            'bulan' => (static::REQUIRE_PERIODE ? 'required' : 'nullable').'|integer|min:1|max:12',
            'tahun' => (static::REQUIRE_PERIODE ? 'required' : 'nullable').'|integer|min:1900|max:2100',
            'pegawai_id' => [
                $isUpdate ? 'nullable' : 'required',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->whereIn('tipe', static::PEGAWAI_TIPE)),
            ],
            'total_hari' => 'nullable|integer|min:0',
            'total_jam' => 'nullable|numeric|min:0',
            'total_barokah' => 'nullable|integer|min:0',
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => 'required|in:'.implode(',', static::JENIS_PEMBAYARAN),
            'rekap_id' => $this->rekapIdRules(),
            'keterangan' => 'nullable|string',
            ...$this->lampiranRules(),
        ];

        if (static::SUPPORTS_BUKTI_TRANSFER) {
            $rules['bukti_transfer'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096';
        }

        return $rules;
    }

    protected function fillData(KeuanganPengeluaranPegawaiAbsensi $data, Request $request): void
    {
        $totalHari = (int) $this->number($request->total_hari);
        $totalJam = round($this->number($request->total_jam), 2);
        $totalBarokah = (int) $this->number($request->total_barokah);

        $data->tanggal = $request->tanggal;
        $data->bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $data->tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
        $data->pegawai_tipe = Pegawai::query()->whereKey($data->pegawai_id)->value('tipe');
        $data->total_hari = $totalHari;
        $data->total_jam = $totalJam;
        $data->total_barokah = $totalBarokah;
        $data->total = $totalBarokah;
        $data->jenis_pembayaran = $request->jenis_pembayaran;
        if ($request->has('rekap_id')) {
            $data->rekap_id = $request->filled('rekap_id') ? $request->rekap_id : null;
        }
        $data->keterangan = $request->keterangan;
        $data->lampiran = $this->updateLampiran(
            $request,
            $data->lampiran,
            static::LAMPIRAN_DIR
        );

        if (! static::SUPPORTS_BUKTI_TRANSFER) {
            return;
        }

        if ($request->hasFile('bukti_transfer')) {
            $newBuktiTransfer = $this->storeBuktiTransfer(
                $request->file('bukti_transfer'),
                static::BUKTI_TRANSFER_DIR
            );
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = $newBuktiTransfer;
        }

        if ($request->jenis_pembayaran !== 'Transfer') {
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = null;
        }
    }

    protected function appendBuktiTransferUrl($data)
    {
        if (! static::SUPPORTS_BUKTI_TRANSFER) {
            return $data;
        }

        $data->bukti_transfer_url = $this->buktiTransferUrl($data->bukti_transfer);

        return $data;
    }

    protected function appendPengeluaranFiles($data)
    {
        return $this->appendLampiranUrls($this->appendBuktiTransferUrl($data));
    }

    protected function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranPegawaiAbsensi $data): bool
    {
        return static::SUPPORTS_BUKTI_TRANSFER
            && $request->jenis_pembayaran === 'Transfer'
            && ! $request->hasFile('bukti_transfer')
            && ! ($data?->bukti_transfer);
    }

    protected function buktiTransferRequiredResponse()
    {
        return response()->json([
            'status' => false,
            'message' => [
                'bukti_transfer' => ['Bukti transfer wajib diupload jika jenis pembayaran Transfer.'],
            ],
        ], 422);
    }

    protected function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }

    public function fetchWebAbsensiRekap(Request $request, WebAbsensiService $service)
    {
        try {
            return response()->json($service->fetchRekap($request));
        } catch (\Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() <= 500 ? $e->getCode() : 500;
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function fetchWebAbsensiData(Request $request, WebAbsensiService $service)
    {
        try {
            return response()->json($service->fetchHarian($request));
        } catch (\Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() <= 500 ? $e->getCode() : 500;
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function exportWebAbsensiExcel(Request $request, WebAbsensiService $service)
    {
        try {
            $result = $service->fetchHarianAll($request);
            $data = $result['data'];
            $periode = $result['periode'];

            $filename = "Data_Web_Absensi";
            if (!empty($periode['mode']) && $periode['mode'] === 'bulan_tahun') {
                $months = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                ];
                $b = (int) ($periode['bulan'] ?? date('n'));
                $t = $periode['tahun'] ?? date('Y');
                $filename .= "_" . ($months[$b] ?? $b) . "_" . $t;
            } elseif (!empty($periode['start_date']) && !empty($periode['end_date'])) {
                $filename .= "_" . $periode['start_date'] . "_sd_" . $periode['end_date'];
            }

            return Excel::download(new WebAbsensiExport($data), $filename . ".xlsx");
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportWebAbsensiPdf(Request $request, WebAbsensiService $service)
    {
        try {
            $result = $service->fetchHarianAll($request);
            $data = $result['data'];
            $periode = $result['periode'];

            return WebAbsensiPdf::pdf($data, $periode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportWebAbsensiRekapExcel(Request $request, WebAbsensiService $service)
    {
        try {
            $result = $service->fetchRekap($request);
            $data = $result['data'] ?? [];
            $periode = $result['periode'] ?? [];

            $filename = "Rekap_Total_Web_Absensi";
            if (!empty($periode['mode']) && $periode['mode'] === 'bulan_tahun') {
                $months = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                ];
                $b = (int) ($periode['bulan'] ?? date('n'));
                $t = $periode['tahun'] ?? date('Y');
                $filename .= "_" . ($months[$b] ?? $b) . "_" . $t;
            } elseif (!empty($periode['start_date']) && !empty($periode['end_date'])) {
                $filename .= "_" . $periode['start_date'] . "_sd_" . $periode['end_date'];
            }

            return Excel::download(new WebAbsensiRekapExport($data), $filename . ".xlsx");
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportWebAbsensiRekapPdf(Request $request, WebAbsensiService $service)
    {
        try {
            $result = $service->fetchRekap($request);
            $data = $result['data'] ?? [];
            $periode = $result['periode'] ?? [];

            return WebAbsensiRekapPdf::pdf($data, $periode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function getWebAbsensiSlipBundle(Request $request, WebAbsensiService $service): array
    {
        $kodeUser = strtolower(trim($request->input('kode_user', $request->input('search', ''))));

        $resultHarian = $service->fetchHarianAll($request);
        $allLogs = $resultHarian['data'] ?? [];
        $periode = $resultHarian['periode'] ?? [];

        $employeeLogs = [];
        if ($kodeUser !== '') {
            foreach ($allLogs as $log) {
                $itemKode = strtolower($log['user']['kode'] ?? $log['kode_user'] ?? $log['user']['username'] ?? '');
                if ($itemKode === $kodeUser || strtolower($log['user']['name'] ?? '') === $kodeUser) {
                    $employeeLogs[] = $log;
                }
            }
        } else {
            $employeeLogs = $allLogs;
        }

        $resultRekap = $service->fetchRekap($request);
        $allRekap = $resultRekap['data'] ?? [];
        $rekapItem = null;
        if ($kodeUser !== '') {
            foreach ($allRekap as $item) {
                $itemKode = strtolower($item['user']['kode'] ?? $item['kode_user'] ?? $item['user']['username'] ?? '');
                if ($itemKode === $kodeUser || strtolower($item['user']['name'] ?? '') === $kodeUser) {
                    $rekapItem = $item;
                    break;
                }
            }
        } elseif (!empty($allRekap)) {
            $rekapItem = $allRekap[0];
        }

        if (!$rekapItem && !empty($employeeLogs)) {
            $firstUser = $employeeLogs[0]['user'] ?? [];
            $totalJam = array_reduce($employeeLogs, function ($acc, $item) {
                return $acc + (float) ($item['durasi_jam'] ?? 0);
            }, 0);
            $totalHari = count($employeeLogs);
            $totalBarokah = array_reduce($employeeLogs, function ($acc, $item) {
                return $acc + (float) ($item['perolehan_dana'] ?? 0);
            }, 0);

            $rekapItem = [
                'user' => $firstUser,
                'total_jam_keseluruhan' => ['total_jam' => round($totalJam, 2)],
                'total_perolehan_dana' => $totalBarokah,
                '_total_hari_calculated' => $totalHari,
            ];
        } elseif ($rekapItem) {
            $totalHari = 0;
            if (!empty($rekapItem['rekap_per_kategori']) && is_array($rekapItem['rekap_per_kategori'])) {
                foreach ($rekapItem['rekap_per_kategori'] as $kat) {
                    $totalHari += (int) ($kat['jumlah'] ?? 0);
                }
            } else {
                $totalHari = count($employeeLogs);
            }
            $rekapItem['_total_hari_calculated'] = $totalHari;
        } else {
            $rekapItem = [
                'user' => ['name' => '-', 'departemen' => '-'],
                'total_jam_keseluruhan' => ['total_jam' => 0],
                'total_perolehan_dana' => 0,
                '_total_hari_calculated' => 0,
            ];
        }

        return [
            'data' => array_values($employeeLogs),
            'rekap_item' => $rekapItem,
            'periode' => $periode,
        ];
    }

    public function fetchWebAbsensiSlipData(Request $request, WebAbsensiService $service)
    {
        try {
            $bundle = $this->getWebAbsensiSlipBundle($request, $service);
            return response()->json([
                'status' => true,
                'data' => $bundle['data'],
                'rekap_item' => $bundle['rekap_item'],
                'periode' => $bundle['periode'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportWebAbsensiSlipExcel(Request $request, WebAbsensiService $service)
    {
        try {
            $bundle = $this->getWebAbsensiSlipBundle($request, $service);
            $namaClean = preg_replace('/[^A-Za-z0-9_-]/', '_', $bundle['rekap_item']['user']['name'] ?? $bundle['rekap_item']['user']['nama'] ?? 'Pegawai');
            $filename = "Slip_Rekap_Kehadiran_" . $namaClean . ".xlsx";

            return Excel::download(new WebAbsensiSlipExport($bundle['data'], $bundle['rekap_item'], $bundle['periode']), $filename);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export Excel Slip: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportWebAbsensiSlipPdf(Request $request, WebAbsensiService $service)
    {
        try {
            $bundle = $this->getWebAbsensiSlipBundle($request, $service);
            return WebAbsensiSlipPdf::pdf($bundle['data'], $bundle['rekap_item'], $bundle['periode']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal export PDF Slip: ' . $e->getMessage(),
            ], 500);
        }
    }
}
