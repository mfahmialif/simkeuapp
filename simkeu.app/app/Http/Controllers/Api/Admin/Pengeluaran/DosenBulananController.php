<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Exports\BsiPayrollExport;
use App\Exports\ExcelExport;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\BuildsPengeluaranIndex;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosenBulananRekap;
use App\Models\KeuanganPengeluaranPegawaiBulanan;
use App\Models\Pegawai;
use Illuminate\Http\Request;
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
    use ManagesPengeluaranRekap;

    protected const PEGAWAI_TIPE = 'dosen';

    protected const MODULE_NAME = 'Barokah Dosen Bulanan';

    protected const PEGAWAI_LABEL = 'Dosen';

    protected const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];

    protected const REQUIRE_PERIODE = false;

    protected const SUPPORTS_BUKTI_TRANSFER = false;

    protected const BUKTI_TRANSFER_DIR = '';

    protected const LAMPIRAN_DIR = 'dosen-bulanan';

    protected const REKAP_MODEL = KeuanganPengeluaranDosenBulananRekap::class;

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
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);

        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        $stats = $this->aggregatePengeluaranStats(
            $this->newIndexStatsQuery($request),
            'keuangan_pengeluaran_pegawai_bulanan'
        );

        $this->applySorting($query, $request);

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
            'message' => static::MODULE_NAME.' retrieved successfully',
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

        $data = new KeuanganPengeluaranPegawaiBulanan;
        $this->fillData($data, $request);
        $data->save();

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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $existing = collect();

        if ($request->filled('rekap_id')) {
            $existing = KeuanganPengeluaranPegawaiBulanan::query()
                ->where('rekap_id', $request->rekap_id)
                ->whereIn('pegawai_id', Pegawai::query()->where('tipe', 'dosen')->select('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('pegawai_id')
                ->keyBy('pegawai_id');
        }

        $dosen = Pegawai::query()
            ->with('dosen.prodi')
            ->where('tipe', 'dosen')
            ->orderBy('nama')
            ->get()
            ->map(function ($pegawai) use ($existing) {
                $pengeluaran = $existing->get($pegawai->id);

                return [
                    'pegawai_id' => $pegawai->id,
                    'kode' => $pegawai->kode,
                    'nama' => $pegawai->nama,
                    'status' => $pegawai->status,
                    'jenis_kelamin' => $pegawai->jenis_kelamin,
                    'prodi' => $pegawai->dosen?->prodi?->nama
                        ?? $pegawai->dosen?->prodi?->alias,
                    'pengeluaran_id' => $pengeluaran?->id,
                    'barokah_dosen_tetap' => (int) ($pengeluaran?->barokah_dosen_tetap ?? 0),
                    'barokah_struktural' => (int) ($pengeluaran?->barokah_struktural ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $dosen,
            'message' => 'Data form Barokah Dosen Bulanan berhasil dimuat.',
        ]);
    }

    public function batchStore(Request $request)
    {
        $rekapTable = (new KeuanganPengeluaranDosenBulananRekap)->getTable();
        $validator = Validator::make($request->all(), [
            'rekap_id' => ['required', Rule::exists($rekapTable, 'id')],
            'tanggal' => ['required', 'date'],
            'jenis_pembayaran' => ['required', Rule::in(static::JENIS_PEMBAYARAN)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pegawai_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('tipe', 'dosen')),
            ],
            'items.*.barokah_dosen_tetap' => ['nullable', 'numeric', 'min:0'],
            'items.*.barokah_struktural' => ['nullable', 'numeric', 'min:0'],
            ...$this->lampiranRules(),
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
            $pegawaiIds = collect($payload['items'])->pluck('pegawai_id')->unique()->values();
            $recordsByPegawai = KeuanganPengeluaranPegawaiBulanan::query()
                ->where('rekap_id', $payload['rekap_id'])
                ->whereIn('pegawai_id', $pegawaiIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('pegawai_id');

            foreach ($payload['items'] as $item) {
                $dosenTetap = (int) round($this->number($item['barokah_dosen_tetap'] ?? 0));
                $struktural = (int) round($this->number($item['barokah_struktural'] ?? 0));
                $total = $dosenTetap + $struktural;
                $records = $recordsByPegawai->get($item['pegawai_id'], collect());

                if ($total === 0) {
                    if ($records->isNotEmpty()) {
                        foreach ($records as $record) {
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
                $data->pegawai_tipe = 'dosen';
                $data->rekap_id = $payload['rekap_id'];
                $data->tanggal = $payload['tanggal'];
                $data->bulan = (int) date('n', strtotime($payload['tanggal']));
                $data->tahun = (int) date('Y', strtotime($payload['tanggal']));
                $data->hari = 0;
                $data->barokah_harian = 0;
                $data->barokah_bulanan = 0;
                $data->barokah_dosen_tetap = $dosenTetap;
                $data->barokah_struktural = $struktural;
                $data->total = $total;
                $data->jenis_pembayaran = $payload['jenis_pembayaran'];
                $data->lampiran = $this->updateLampiran(
                    $request,
                    $data->lampiran,
                    static::LAMPIRAN_DIR
                );
                $data->save();

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }

                if ($records->count() > 1) {
                    $duplicates = $records->skip(1);
                    foreach ($duplicates as $duplicate) {
                        $this->deleteLampiran($duplicate->lampiran);
                    }

                    $duplicateIds = $duplicates->pluck('id');
                    $deleted += KeuanganPengeluaranPegawaiBulanan::query()
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            }

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
        $data = KeuanganPengeluaranPegawaiBulanan::find($id);

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
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendPengeluaranFiles($this->findWithPegawai($data->id) ?? $data),
            'message' => static::MODULE_NAME.' updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranPegawaiBulanan::find($id);

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
        $data->delete();

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
        $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        $data = $query
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.tanggal', 'desc')
            ->orderBy('keuangan_pengeluaran_pegawai_bulanan.id', 'desc')
            ->get();

        return Excel::download(new ExcelExport($data), 'Laporan '.static::MODULE_NAME.'.xlsx');
    }

    public function exportBsi(Request $request)
    {
        $data = $this->bsiRows($request);

        return Excel::download(new BsiPayrollExport($data, $this->bsiMessage()), 'CUS BSI '.static::MODULE_NAME.'.xlsx');
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
            'message' => 'Data CUS BSI berhasil disiapkan.',
        ]);
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
        $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
        $this->applyPeriodFilter($query, $request);
        $this->applyDateFilter($query, $request);
        $this->applyRekapFilter($query, $request);

        return $query
            ->where('keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran', 'CUS BSI')
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
        return static::PEGAWAI_TIPE === 'staff'
            ? 'barokah staff bulanan'
            : 'barokah dosen bulanan';
    }

    protected function joinPegawaiDetail($query): void
    {
        $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');
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

        return $query->where(
            'keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe',
            static::PEGAWAI_TIPE
        );
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query();

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);
        $this->applySearchFilter($query, $request);
        $this->applyPegawaiFilter($query, $request);
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
        ]);

        $this->joinPegawaiDetail($query);
        $this->joinRekap($query);
        $query->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);

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
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_dosen_tetap', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.barokah_struktural', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.total', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.jenis_pembayaran', 'LIKE', "%{$search}%")
                ->orWhere('keuangan_pengeluaran_pegawai_bulanan.keterangan', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.nama', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                ->orWhere('pegawai.jenis_kelamin', 'LIKE', "%{$search}%")
                ->orWhere('prodi.nama', 'LIKE', "%{$search}%")
                ->orWhere('staff.jabatan', 'LIKE', "%{$search}%")
                ->orWhere('pengeluaran_rekap.nama', 'LIKE', "%{$search}%");
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

    protected function newIndexStatsQuery(Request $request)
    {
        $query = KeuanganPengeluaranPegawaiBulanan::query()
            ->where('keuangan_pengeluaran_pegawai_bulanan.pegawai_tipe', static::PEGAWAI_TIPE);

        if ($request->filled('search')) {
            $this->joinPegawaiDetail($query);
            $this->joinRekap($query);
            $this->applySearchFilter($query, $request);
        } elseif ($request->filled('kode')) {
            $query->leftJoin('pegawai', 'pegawai.id', '=', 'keuangan_pengeluaran_pegawai_bulanan.pegawai_id');
        }

        $this->applyPegawaiFilter($query, $request);
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
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query->where('tipe', static::PEGAWAI_TIPE)),
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
        $hari = $this->number($request->hari);
        $barokahHarian = $this->number($request->barokah_harian);
        $barokahBulanan = $this->number($request->barokah_bulanan);
        $barokahDosenTetap = $this->number($request->barokah_dosen_tetap);
        $barokahStruktural = $this->number($request->barokah_struktural);

        $data->tanggal = $request->tanggal;
        $data->bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $data->tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($request->filled('pegawai_id')) {
            $data->pegawai_id = $request->pegawai_id;
        }
        $data->pegawai_tipe = static::PEGAWAI_TIPE;

        if (static::PEGAWAI_TIPE === 'dosen') {
            $data->hari = 0;
            $data->barokah_harian = 0;
            $data->barokah_bulanan = 0;
            $data->barokah_dosen_tetap = $barokahDosenTetap;
            $data->barokah_struktural = $barokahStruktural;
            $data->total = (int) round($barokahDosenTetap + $barokahStruktural);
        } else {
            $data->hari = $hari;
            $data->barokah_harian = $barokahHarian;
            $data->barokah_bulanan = $barokahBulanan;
            $data->total = (int) round(($barokahHarian * $hari) + $barokahBulanan);
        }
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
