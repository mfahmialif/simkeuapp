<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\BsiPayrollExport;
use App\Exports\BarokahBulananRekapExport;
use App\Exports\ExcelExport;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosenBulananRekap;
use App\Models\KeuanganPengeluaranPegawaiBulanan;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DosenBulananController extends Controller
{
    use BuildsPengeluaranIndex;
    use ManagesBuktiTransfer;
    use ManagesLampiran;
    use ManagesPengeluaranLpj;
    use ManagesPengeluaranRekap;

    private ?array $searchPegawaiIds = null;

    protected const PEGAWAI_TIPE = ['dosen', 'staff'];

    protected const MODULE_NAME = 'Barokah Bulanan';

    protected const PEGAWAI_LABEL = 'Pegawai';

    protected const JENIS_PEMBAYARAN = ['CUZ BSI', 'Transfer'];

    protected const REQUIRE_PERIODE = false;

    protected const SUPPORTS_BUKTI_TRANSFER = false;

    protected const BUKTI_TRANSFER_DIR = '';

    protected const LAMPIRAN_DIR = 'bulanan';

    protected const REKAP_MODEL = KeuanganPengeluaranDosenBulananRekap::class;

    public function lpjShow(Request $request, $id)
    {
        return $this->showModule($request, 'dosen_bulanan', $id);
    }

    public function lpjCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen_bulanan', $id);
    }

    public function lpjUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen_bulanan', $id);
    }

    public function lpjDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'dosen_bulanan', $id);
    }

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
            'keuangan_pengeluaran_pegawai_bulanan'
        );

        $saldoRekapTable = (new (static::REKAP_MODEL))->getTable();
        $stats['saldo'] = $this->indexSaldoStats(
            $request,
            'keuangan_pengeluaran_pegawai_bulanan',
            $saldoRekapTable,
            $this->lpjModuleKey($saldoRekapTable)
        );

        return $stats;
    }

    private function searchIndexStats(Request $request): array
    {
        $summary = $this->newIndexStatsQuery($request)
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(keuangan_pengeluaran_pegawai_bulanan.total), 0) as total')
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
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(keuangan_pengeluaran_pegawai_bulanan.total), 0) as total')
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

        $data = new KeuanganPengeluaranPegawaiBulanan;
        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

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
            $existing = KeuanganPengeluaranPegawaiBulanan::query()
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
            $copiedAmounts = KeuanganPengeluaranPegawaiBulanan::query()
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

        $pegawaiBulanan = Pegawai::query()
            ->with(['dosen.prodi', 'staff'])
            ->whereIn('tipe', static::PEGAWAI_TIPE)
            ->where('status', 'aktif')
            ->orderBy('tipe')
            ->orderBy('nama')
            ->get()
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
                    'hari' => (float) ($amountSource?->hari ?? 0),
                    'barokah_harian' => (int) ($amountSource?->barokah_harian ?? 0),
                    'barokah_bulanan' => (int) ($amountSource?->barokah_bulanan ?? 0),
                    'barokah_dosen_tetap' => (int) ($amountSource?->barokah_dosen_tetap ?? 0),
                    'barokah_struktural' => (int) ($amountSource?->barokah_struktural ?? 0),
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
            'data' => $pegawaiBulanan,
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
            'items.*.hari' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah_harian' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah_bulanan' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah_dosen_tetap' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah_struktural' => ['nullable', 'numeric', 'min:0'],
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
            $recordsByPegawai = KeuanganPengeluaranPegawaiBulanan::query()
                ->where('rekap_id', $payload['rekap_id'])
                ->whereIn('pegawai_id', $pegawaiIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('pegawai_id');

            foreach ($payload['items'] as $index => $item) {
                $hari = $this->number($item['hari'] ?? 0);
                $barokahHarian = $this->number($item['barokah_harian'] ?? 0);
                $barokahBulanan = $this->number($item['barokah_bulanan'] ?? 0);
                $dosenTetap = (int) round($this->number($item['barokah_dosen_tetap'] ?? 0));
                $struktural = (int) round($this->number($item['barokah_struktural'] ?? 0));
                $total = $dosenTetap + $struktural;
                $records = $recordsByPegawai->get($item['pegawai_id'], collect());
                $paymentType = $item['jenis_pembayaran'] ?? $payload['jenis_pembayaran'] ?? 'CUZ BSI';

                if ($total === 0) {
                    if ($records->isNotEmpty()) {
                        foreach ($records as $record) {
                            $this->deleteBuktiTransfer($record->bukti_transfer);
                            $this->deleteLampiran($record->lampiran);
                        }

                        $deleted += KeuanganPengeluaranPegawaiBulanan::query()
                            ->whereIn('id', $records->pluck('id'))
                            ->delete();
                    }

                    continue;
                }

                $data = $records->first() ?? new KeuanganPengeluaranPegawaiBulanan;
                $isNew = ! $data->exists;
                $data->pegawai_id = $item['pegawai_id'];
                $data->petugas_id = $this->petugasIdForRekapId((int) $payload['rekap_id']) ?? auth()->id();
                $data->pegawai_tipe = $pegawaiTypes->get($item['pegawai_id']);
                $data->rekap_id = $payload['rekap_id'];
                $data->tanggal = $payload['tanggal'];
                $data->bulan = $payload['bulan'] ?? (int) date('n', strtotime($payload['tanggal']));
                $data->tahun = $payload['tahun'] ?? (int) date('Y', strtotime($payload['tanggal']));
                $data->hari = 0;
                $data->barokah_harian = 0;
                $data->barokah_bulanan = 0;
                $data->barokah_dosen_tetap = $dosenTetap;
                $data->barokah_struktural = $struktural;
                $data->total = $total;
                $data->jenis_pembayaran = $paymentType;
                $rowRequest = Request::create('/', 'POST', $item);
                $rowLampiran = $request->file("items.{$index}.lampiran", []);
                if ($rowLampiran) {
                    $rowRequest->files->set('lampiran', $rowLampiran);
                }
                $data->lampiran = $this->updateLampiran($rowRequest, $data->lampiran, static::LAMPIRAN_DIR);
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
                    $deleted += KeuanganPengeluaranPegawaiBulanan::query()
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
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranPegawaiBulanan::class, $id);

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

        $this->fillData($data, $request);
        $this->savePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithPegawai($data->id) ?? $data),
            'message' => static::MODULE_NAME.' updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = $this->findScopedPengeluaranModel(KeuanganPengeluaranPegawaiBulanan::class, $id);

        if (! $data || ! $this->findWithPegawai($id)) {
            return response()->json([
                'status' => false,
                'message' => static::MODULE_NAME.' not found',
            ], 404);
        }

        if (static::SUPPORTS_BUKTI_TRANSFER) {
            $this->deleteBuktiTransfer($data->bukti_transfer);
        }

        $this->deleteLampiran($data->lampiran);
        $this->deletePengeluaranWithRekapValidation($data);

        return response()->json([
            'status' => true,
            'message' => static::MODULE_NAME.' deleted successfully',
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
            'pengeluaran_rekap.nama as rekap',
            'keuangan_pengeluaran_pegawai_bulanan.hari',
            'keuangan_pengeluaran_pegawai_bulanan.barokah_harian',
            DB::raw('(keuangan_pengeluaran_pegawai_bulanan.barokah_harian * keuangan_pengeluaran_pegawai_bulanan.hari) as subtotal_harian'),
            'keuangan_pengeluaran_pegawai_bulanan.barokah_bulanan',
            'keuangan_pengeluaran_pegawai_bulanan.barokah_dosen_tetap',
            'keuangan_pengeluaran_pegawai_bulanan.barokah_struktural',
            'keuangan_pengeluaran_pegawai_bulanan.total',
            'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'keuangan_pengeluaran_pegawai_bulanan.keterangan',
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

        $data = $query
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan '.static::MODULE_NAME.'.xlsx');
    }

    public function rekapExportExcel(Request $request)
    {
        $data = $this->barokahBulananRekapRows($request);
        $period = $this->requestExportPeriodLabel($request);
        $title = trim('REKAP BAROKAH BULANAN '.$period);
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

        return $this->downloadBarokahBulananRekapListSpreadsheet(
            $title,
            $headings,
            $rows,
            [6, 7, 8],
            $totalRow,
            $this->excelExportFilename(trim('Rekap Barokah Bulanan '.$period))
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
            ? $this->barokahBulananLpjDetailRows($request, (int) $rekap->id)
            : $this->barokahBulananRabDetailRows($request, (int) $rekap->id);

        if ($tab === 'lpj' && $data->isEmpty() && $this->barokahBulananLpjSameAsRab((int) $rekap->id)) {
            $data = $this->barokahBulananRabDetailRows($request, (int) $rekap->id);
        }

        $headings = ['NO', 'NAMA', 'NOMINAL', 'No Rek', 'KETERANGAN'];
        $rows = $data->values()->map(function ($item, $index) {
            return [
                $index + 1,
                $item->nama_pegawai ?: '-',
                (int) ($item->total ?? 0),
                (string) ($item->nomer_rekening ?? ''),
                $item->keterangan ?: ($item->jenis_pembayaran ?: ''),
            ];
        })->all();
        $totalRow = ['', 'TOTAL', $data->sum(fn ($item) => (int) ($item->total ?? 0)), '', ''];
        $period = $this->formatExportPeriod($rekap->bulan_tahun);
        $titlePrefix = $tab === 'lpj' ? 'LIST LPJ BAROKAH DOSEN' : 'LIST BAROKAH DOSEN';

        return Excel::download(
            new BarokahBulananRekapExport(
                trim($titlePrefix.' '.$period),
                $headings,
                $rows,
                [3],
                $totalRow,
                [4]
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
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'pegawai.nomer_rekening as beneficiary_acct',
            $this->bsiBeneficiaryNameSelect(),
            DB::raw('SUM(keuangan_pengeluaran_pegawai_bulanan.total) as amount'),
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
            ->where('keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran', 'CUZ BSI')
            ->groupBy($this->bsiGroupColumns())
            ->orderBy('pegawai.nama')
            ->get();
    }

    protected function bsiGroupColumns(): array
    {
        $columns = [
            'keuangan_pengeluaran_pegawai_bulanan.pegawai_id',
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
        return 'barokah bulanan';
    }

    private function barokahBulananRekapRows(Request $request)
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

    private function barokahBulananRabDetailRows(Request $request, int $rekapId)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.id',
            'keuangan_pengeluaran_pegawai_bulanan.tanggal',
            'keuangan_pengeluaran_pegawai_bulanan.total',
            'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'keuangan_pengeluaran_pegawai_bulanan.keterangan',
            'pegawai.nama as nama_pegawai',
            'pegawai.nomer_rekening as nomer_rekening',
        ]);

        $this->joinPegawaiDetail($query);
        $this->applyPegawaiTipeScope($query);
        $this->applyPetugasFilter($query, $request);
        $this->applySearchFilter($query, $request);
        $this->applyBarokahBulananDetailSorting($query, $request, 'keuangan_pengeluaran_pegawai_bulanan');

        return $query
            ->where('keuangan_pengeluaran_pegawai_bulanan.rekap_id', $rekapId)
            ->get();
    }

    private function barokahBulananLpjDetailRows(Request $request, int $rekapId)
    {
        $table = 'keuangan_pengeluaran_pegawai_bulanan_lpj';

        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table("{$table} as lpj")
            ->leftJoin('pegawai', 'pegawai.id', '=', 'lpj.pegawai_id')
            ->where('lpj.rekap_id', $rekapId)
            ->select([
                'lpj.id',
                'lpj.tanggal',
                'lpj.total',
                'lpj.jenis_pembayaran',
                'lpj.keterangan',
                'pegawai.nama as nama_pegawai',
                'pegawai.nomer_rekening as nomer_rekening',
            ]);

        $this->applyPengeluaranGenderScope($query, $table, 'lpj');

        if (Schema::hasColumn($table, 'pegawai_tipe')) {
            $query->whereIn('lpj.pegawai_tipe', static::PEGAWAI_TIPE);
        }

        if ($request->filled('petugas_id') && Schema::hasColumn($table, 'petugas_id')) {
            $query->where('lpj.petugas_id', $request->petugas_id);
        }

        $this->applyBarokahBulananLpjSearchFilter($query, $request);
        $this->applyBarokahBulananDetailSorting($query, $request, 'lpj');

        return $query->get();
    }

    private function applyBarokahBulananDetailSorting($query, Request $request, string $table): void
    {
        $sortColumns = [
            'id' => "{$table}.id",
            'tanggal' => "{$table}.tanggal",
            'total' => "{$table}.total",
            'jenis_pembayaran' => "{$table}.jenis_pembayaran",
            'keterangan' => "{$table}.keterangan",
            'pegawai' => 'pegawai.nama',
            'nama_pegawai' => 'pegawai.nama',
        ];
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumns[$sortKey] ?? "{$table}.id", $sortOrder);
    }

    private function applyBarokahBulananLpjSearchFilter($query, Request $request): void
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
                    ->orWhere('lpj.total', (int) $search)
                    ->orWhere('lpj.barokah_dosen_tetap', (int) $search)
                    ->orWhere('lpj.barokah_struktural', (int) $search);
            }
        });
    }

    private function barokahBulananLpjSameAsRab(int $rekapId): bool
    {
        return Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')
            && DB::table('keuangan_pengeluaran_lpj_rekap_status')
                ->where('module_key', 'dosen_bulanan')
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
        $safeName = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return ($safeName ?: 'Export').'.xlsx';
    }

    private function downloadBarokahBulananRekapListSpreadsheet(
        string $title,
        array $headings,
        array $rows,
        array $amountColumns,
        array $totalRow,
        string $filename
    ) {
        $spreadsheet = $this->barokahBulananRekapListSpreadsheet($title, $headings, $rows, $amountColumns, $totalRow);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function barokahBulananRekapListSpreadsheet(
        string $title,
        array $headings,
        array $rows,
        array $amountColumns,
        array $totalRow
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Bulanan');

        $headerRow = 14;
        $firstDataRow = 15;
        $lastColumnIndex = count($headings) + 1;
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumnIndex);
        $lastRow = $firstDataRow + count($rows);

        $this->addBarokahKopDrawing($sheet);
        $this->applyBarokahRekapColumnWidths($sheet, $lastColumnIndex);

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

    private function addBarokahKopDrawing(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
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

    private function applyBarokahRekapColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $lastColumnIndex): void
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
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
    }

    protected function applyPegawaiTipeScope($query): void
    {
        $query->whereIn('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);
    }

    protected function rekapModelClass(): string
    {
        return static::REKAP_MODEL;
    }

    protected function pengeluaranTable(): string
    {
        return 'keuangan_pengeluaran_pegawai_bulanan';
    }

    protected function newRekapPengeluaranQuery()
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();
        $this->joinPegawaiDetail($query);

        $this->applyPegawaiTipeScope($query);

        return $query;
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

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
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.*',
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

        return $query->where('keuangan_pengeluaran_pegawai_bulanan.id', $id)->first();
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

            $query->whereIn('keuangan_pengeluaran_pegawai_bulanan.pegawai_id', $pegawaiIds);

            return;
        }

        $query->where(function ($q) use ($search, $pegawaiIds, $isNumericSearch, $isDateSearch) {
            if ($pegawaiIds) {
                $q->orWhereIn('keuangan_pengeluaran_pegawai_bulanan.pegawai_id', $pegawaiIds);
            }

            if ($isDateSearch) {
                $q->orWhere('keuangan_pengeluaran_pegawai_bulanan.tanggal', 'LIKE', "{$search}%");
            }

            if ($isNumericSearch) {
                $q->orWhere('keuangan_pengeluaran_pegawai_bulanan.bulan', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_bulanan.tahun', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_bulanan.total', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_dosen_tetap', (int) $search)
                    ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_struktural', (int) $search);
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
            'barokah_dosen_tetap' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_dosen_tetap',
            'barokah_struktural' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_struktural',
            'total' => 'keuangan_pengeluaran_pegawai_bulanan.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'nama_rekap' => 'pengeluaran_rekap.nama',
            'created_at' => 'keuangan_pengeluaran_pegawai_bulanan.created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumns[$sortKey] ?? 'keuangan_pengeluaran_pegawai_bulanan.id', $sortOrder);
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
        $sortColumn = $this->fastIndexSortColumns()[$sortKey] ?? 'keuangan_pengeluaran_pegawai_bulanan.id';

        $idQuery = KeuanganPengeluaranPegawaiBulanan::query();
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
            ->pluck('keuangan_pengeluaran_pegawai_bulanan.id')
            ->values();

        if ($ids->isEmpty()) {
            return new LengthAwarePaginator(collect(), $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $query = KeuanganPengeluaranPegawaiBulanan::query();
        $query->select([
            'keuangan_pengeluaran_pegawai_bulanan.*',
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
            ->whereIn('keuangan_pengeluaran_pegawai_bulanan.id', $ids)
            ->orderByRaw("FIELD(keuangan_pengeluaran_pegawai_bulanan.id, {$orderedIds})")
            ->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function fastIndexSortColumns(): array
    {
        return [
            'id' => 'keuangan_pengeluaran_pegawai_bulanan.id',
            'tanggal' => 'keuangan_pengeluaran_pegawai_bulanan.tanggal',
            'bulan' => 'keuangan_pengeluaran_pegawai_bulanan.bulan',
            'tahun' => 'keuangan_pengeluaran_pegawai_bulanan.tahun',
            'pegawai_id' => 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id',
            'hari' => 'keuangan_pengeluaran_pegawai_bulanan.hari',
            'barokah_harian' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_harian',
            'barokah_bulanan' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_bulanan',
            'barokah_dosen_tetap' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_dosen_tetap',
            'barokah_struktural' => 'keuangan_pengeluaran_pegawai_bulanan.barokah_struktural',
            'total' => 'keuangan_pengeluaran_pegawai_bulanan.total',
            'jenis_pembayaran' => 'keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran',
            'created_at' => 'keuangan_pengeluaran_pegawai_bulanan.created_at',
        ];
    }

    protected function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();
        $this->applyPegawaiTipeScope($query);

        if ($request->filled('search')) {
            $this->applySearchFilter($query, $request);
        } elseif ($request->filled('kode')) {
            $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id');
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
            'hari' => 'nullable|numeric|min:0',
            'barokah_harian' => 'nullable|numeric|min:0',
            'barokah_bulanan' => 'nullable|numeric|min:0',
            'barokah_dosen_tetap' => 'nullable|numeric|min:0',
            'barokah_struktural' => 'nullable|numeric|min:0',
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

    protected function fillData(KeuanganPengeluaranPegawaiBulanan $data, Request $request): void
    {
        $barokahDosenTetap = $this->number($request->barokah_dosen_tetap);
        $barokahStruktural = $this->number($request->barokah_struktural);

        $data->tanggal = $request->tanggal;
        $data->bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $data->tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }
        $data->petugas_id = $this->petugasIdForPengeluaran($request);
        $data->pegawai_tipe = Pegawai::query()->whereKey($data->pegawai_id)->value('tipe');
        $data->hari = 0;
        $data->barokah_harian = 0;
        $data->barokah_bulanan = 0;
        $data->barokah_dosen_tetap = $barokahDosenTetap;
        $data->barokah_struktural = $barokahStruktural;
        $data->total = (int) round($barokahDosenTetap + $barokahStruktural);
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

    protected function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranPegawaiBulanan $data): bool
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
}
