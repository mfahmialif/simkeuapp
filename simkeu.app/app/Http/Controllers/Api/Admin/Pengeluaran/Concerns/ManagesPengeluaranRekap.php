<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use App\Exports\BarokahBulananRekapExport;
use App\Models\User;
use App\Services\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

trait ManagesPengeluaranRekap
{
    protected array $rekapPetugasIdCache = [];

    abstract protected function rekapModelClass(): string;

    abstract protected function pengeluaranTable(): string;

    abstract protected function newRekapPengeluaranQuery();

    abstract protected function newRekapBulkPengeluaranQuery(Request $request);

    protected function requiresRekapForPengeluaran(): bool
    {
        return false;
    }

    public function rekapIndex(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();

        if ($request->input('mode') === 'simple') {
            return $this->simpleRekapIndex($request, $modelClass, $rekapTable);
        }

        $filteredRekaps = $this->filteredRekapBaseQuery($request, $modelClass, $rekapTable);
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $baseSortColumns = [
            'id' => "{$rekapTable}.id",
            'nama' => "{$rekapTable}.nama",
            'tanggal_rekap' => "{$rekapTable}.tanggal_rekap",
            'tanggal_pencairan' => "{$rekapTable}.tanggal_pencairan",
            'created_at' => "{$rekapTable}.created_at",
        ];

        if (isset($baseSortColumns[$sortKey])) {
            return $this->fastRekapIndex($request, $filteredRekaps, $baseSortColumns[$sortKey], $sortOrder);
        }

        $summary = $this->rekapSummaryQuery($request, $filteredRekaps);
        $lpjSummary = $this->lpjSummaryQuery($request, $filteredRekaps);
        $lpjModuleKey = $this->lpjModuleKey($rekapTable);

        $select = [
            "{$rekapTable}.*",
            DB::raw('COALESCE(rekap_summary.jumlah_data, 0) as jumlah_data'),
            DB::raw('COALESCE(rekap_summary.total_pengeluaran, 0) as total_pengeluaran'),
            DB::raw($this->effectiveAmountSql($rekapTable).' as jumlah'),
            DB::raw('CASE WHEN COALESCE(rekap_summary.jumlah_data, 0) = 0 THEN 1 ELSE 0 END as is_jumlah_sementara'),
            DB::raw($this->temporaryDifferenceSql($rekapTable).' as selisih_sementara'),
        ];

        if ($lpjSummary && $lpjModuleKey && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
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

        if ($lpjSummary && $lpjModuleKey && Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
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
            'created_at' => "{$rekapTable}.created_at",
        ];
        $query->orderBy($sortColumns[$sortKey] ?? "{$rekapTable}.id", $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->each(fn ($item) => $this->castRekapSummary($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapExportExcel(Request $request)
    {
        $data = $this->exportableRekapRows($request);
        $period = $this->genericRekapExportPeriodLabel($request);
        $moduleName = $this->genericRekapExportModuleName();
        $title = trim('REKAP '.strtoupper($moduleName).' '.$period);
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
                $this->formatGenericRekapExportPeriod($item->bulan_tahun),
                $this->formatGenericRekapExportDate($item->tanggal_rekap),
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

        return Excel::download(
            new BarokahBulananRekapExport(
                $title,
                $headings,
                $rows,
                [6, 7, 8],
                $totalRow
            ),
            $this->genericRekapExportFilename(trim('Rekapan '.$moduleName.' '.$period))
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
        $data = $this->genericRekapDetailExportRows($request, (int) $rekap->id, $tab);

        if (
            $tab === 'lpj'
            && $data->isEmpty()
            && $this->genericRekapDetailLpjSameAsRab((int) $rekap->id)
        ) {
            $data = $this->genericRekapDetailExportRows($request, (int) $rekap->id, 'rab');
        }

        $config = $this->genericRekapDetailExportConfig();
        $rows = $data->values()->map($config['row'])->all();
        $totalRow = array_fill(0, count($config['headings']), '');
        $totalRow[1] = 'TOTAL';
        $totalRow[$config['total_column'] - 1] = $data->sum(fn ($item) => (int) ($item->total ?? 0));
        $period = $this->formatGenericRekapExportPeriod($rekap->bulan_tahun);
        $moduleName = $this->genericRekapExportModuleName();
        $titlePrefix = $tab === 'lpj' ? 'DETAIL LPJ' : 'DETAIL RAB';

        return Excel::download(
            new BarokahBulananRekapExport(
                trim($titlePrefix.' '.strtoupper($moduleName).' '.$period),
                $config['headings'],
                $rows,
                $config['amount_columns'],
                $totalRow,
                $config['text_columns'] ?? []
            ),
            $this->genericRekapExportFilename($titlePrefix.' '.($rekap->nama ?: $moduleName))
        );
    }

    private function fastRekapIndex(Request $request, $filteredRekaps, string $sortColumn, string $sortOrder)
    {
        $data = (clone $filteredRekaps)
            ->orderBy($sortColumn, $sortOrder)
            ->paginate($request->get('limit', 10));

        $ids = $data->getCollection()->pluck('id')->filter()->values();
        $summaries = $this->rekapSummariesForIds($request, $ids->all());
        $lpjSummaries = $this->lpjSummariesForIds($request, $ids->all());
        $lpjStatuses = $this->lpjStatusesForIds($ids->all());

        $data->getCollection()->each(function ($item) use ($summaries, $lpjSummaries, $lpjStatuses) {
            $summary = $summaries->get($item->id);
            $jumlahData = (int) ($summary->jumlah_data ?? 0);
            $totalPengeluaran = (int) ($summary->total_pengeluaran ?? 0);
            $amounts = $this->resolveRekapAmounts(
                $item->jumlah_sementara === null ? null : (int) $item->jumlah_sementara,
                $jumlahData,
                $totalPengeluaran
            );
            $lpjSummary = $lpjSummaries->get($item->id);
            $lpjStatus = $lpjStatuses->get($item->id);
            $jumlahLpj = (int) ($lpjSummary->jumlah_lpj ?? 0);
            $sameAsRab = (bool) ($lpjStatus->sama_dengan_rab ?? false);

            $item->jumlah_data = $jumlahData;
            $item->total_pengeluaran = $totalPengeluaran;
            $item->jumlah = $amounts['jumlah'];
            $item->is_jumlah_sementara = $amounts['is_jumlah_sementara'];
            $item->selisih_sementara = $amounts['selisih_sementara'];
            $item->jumlah_lpj = $jumlahLpj;
            $item->total_lpj = $jumlahLpj > 0
                ? (int) ($lpjSummary->total_lpj ?? 0)
                : ($sameAsRab ? (int) (($lpjStatus->total_lpj ?? 0) ?: $item->jumlah) : 0);
            $item->lpj_sama_dengan_rab = $sameAsRab;
            $this->castRekapSummary($item);
        });

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    private function exportableRekapRows(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $filteredRekaps = $this->filteredRekapBaseQuery($request, $modelClass, $rekapTable);
        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'id' => "{$rekapTable}.id",
            'nama' => "{$rekapTable}.nama",
            'tanggal_rekap' => "{$rekapTable}.tanggal_rekap",
            'tanggal_pencairan' => "{$rekapTable}.tanggal_pencairan",
            'created_at' => "{$rekapTable}.created_at",
        ];

        $data = (clone $filteredRekaps)
            ->orderBy($sortColumns[$sortKey] ?? "{$rekapTable}.id", $sortOrder)
            ->get();

        $ids = $data->pluck('id')->filter()->values()->all();
        $summaries = $this->rekapSummariesForIds($request, $ids);
        $lpjSummaries = $this->lpjSummariesForIds($request, $ids);
        $lpjStatuses = $this->lpjStatusesForIds($ids);

        return $data->each(function ($item) use ($summaries, $lpjSummaries, $lpjStatuses) {
            $summary = $summaries->get($item->id);
            $jumlahData = (int) ($summary->jumlah_data ?? 0);
            $totalPengeluaran = (int) ($summary->total_pengeluaran ?? 0);
            $amounts = $this->resolveRekapAmounts(
                $item->jumlah_sementara === null ? null : (int) $item->jumlah_sementara,
                $jumlahData,
                $totalPengeluaran
            );
            $lpjSummary = $lpjSummaries->get($item->id);
            $lpjStatus = $lpjStatuses->get($item->id);
            $jumlahLpj = (int) ($lpjSummary->jumlah_lpj ?? 0);
            $sameAsRab = (bool) ($lpjStatus->sama_dengan_rab ?? false);

            $item->jumlah_data = $jumlahData;
            $item->total_pengeluaran = $totalPengeluaran;
            $item->jumlah = $amounts['jumlah'];
            $item->is_jumlah_sementara = $amounts['is_jumlah_sementara'];
            $item->selisih_sementara = $amounts['selisih_sementara'];
            $item->jumlah_lpj = $jumlahLpj;
            $item->total_lpj = $jumlahLpj > 0
                ? (int) ($lpjSummary->total_lpj ?? 0)
                : ($sameAsRab ? (int) (($lpjStatus->total_lpj ?? 0) ?: $item->jumlah) : 0);
            $item->lpj_sama_dengan_rab = $sameAsRab;
            $this->castRekapSummary($item);
        });
    }

    private function genericRekapDetailExportRows(Request $request, int $rekapId, string $tab)
    {
        $table = $tab === 'lpj' ? $this->lpjPengeluaranTable() : $this->pengeluaranTable();

        if (! Schema::hasTable($table)) {
            return collect();
        }

        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $query = DB::table("{$table} as detail")
            ->leftJoin("{$rekapTable} as rekap", 'rekap.id', '=', 'detail.rekap_id')
            ->leftJoin('users as petugas', function ($join) {
                $join->on('petugas.id', '=', DB::raw('COALESCE(detail.petugas_id, rekap.petugas_id)'));
            })
            ->where('detail.rekap_id', $rekapId);

        $this->joinGenericRekapDetailExportRelations($query);
        $this->applyPengeluaranGenderScope($query, $table, 'detail');
        $this->applyGenericRekapDetailExportSearch($query, $request, $table);

        $select = [
            'detail.*',
            'rekap.nama as rekap_nama',
            'petugas.name as petugas_nama',
        ];

        if (in_array($this->pengeluaranTable(), [
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_kegiatan',
        ], true)) {
            $select = [
                ...$select,
                'pegawai.kode as kode_pegawai',
                'pegawai.nama as nama_pegawai',
                'pegawai.tipe as tipe_pegawai',
                'prodi.nama as prodi',
                'staff.jabatan as jabatan',
            ];
        } else {
            $select = [
                ...$select,
                DB::raw('NULL as kode_pegawai'),
                DB::raw('NULL as nama_pegawai'),
                DB::raw('NULL as tipe_pegawai'),
                DB::raw('NULL as prodi'),
                DB::raw('NULL as jabatan'),
            ];
        }

        $query->select($select);

        $sortKey = (string) $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'id' => 'detail.id',
            'tanggal' => 'detail.tanggal',
            'pegawai' => 'pegawai.nama',
            'kategori_detail' => 'detail.kategori_detail',
            'kelompok_anggaran' => 'detail.kelompok_anggaran',
            'uraian' => 'detail.nama_kegiatan',
            'volume' => 'detail.volume',
            'satuan' => 'detail.satuan',
            'nominal' => 'detail.nominal',
            'prioritas' => 'detail.prioritas',
            'total' => 'detail.total',
        ];
        $sortColumn = $sortColumns[$sortKey] ?? 'detail.id';

        if ($this->sortColumnExistsForGenericDetail($sortColumn, $table)) {
            $query->orderBy($sortColumn, $sortOrder);
        }

        if ($sortColumn !== 'detail.id') {
            $query->orderBy('detail.id', $sortOrder);
        }

        return $query->get();
    }

    private function joinGenericRekapDetailExportRelations($query): void
    {
        if (! in_array($this->pengeluaranTable(), [
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_kegiatan',
        ], true)) {
            return;
        }

        $query
            ->leftJoin('pegawai', 'pegawai.id', '=', 'detail.pegawai_id')
            ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
            ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id')
            ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id');
    }

    private function applyGenericRekapDetailExportSearch($query, Request $request, string $table): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->search);
        $detailColumns = [
            'tanggal',
            'kategori_detail',
            'kelompok_anggaran',
            'nama_kegiatan',
            'prioritas',
            'volume',
            'satuan',
            'nominal',
            'transport',
            'transport_motor',
            'transport_mobil',
            'transport_mobil_tol',
            'transport_mobil_tanpa_tol',
            'hari_transport_motor',
            'hari_transport_mobil',
            'hari_transport_mobil_tol',
            'hari_transport_mobil_tanpa_tol',
            'barokah',
            'barokah_mengajar_biasa',
            'barokah_mengajar_double_degree',
            'jam',
            'jam_mengajar_double_degree',
            'barokah_uas',
            'jumlah_mahasiswa_uas',
            'barokah_sempro',
            'jam_sempro',
            'keterangan_sempro',
            'total',
            'jenis_pembayaran',
            'keterangan',
        ];
        $joinedColumns = in_array($this->pengeluaranTable(), [
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_kegiatan',
        ], true)
            ? ['pegawai.kode', 'pegawai.nama', 'pegawai.tipe', 'prodi.nama', 'staff.jabatan']
            : [];

        $query->where(function ($q) use ($detailColumns, $joinedColumns, $search, $table) {
            foreach ($detailColumns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $q->orWhere("detail.{$column}", 'LIKE', "%{$search}%");
                }
            }

            foreach ($joinedColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$search}%");
            }

            $q->orWhere('petugas.name', 'LIKE', "%{$search}%");
        });
    }

    private function genericRekapDetailExportConfig(): array
    {
        return match ($this->pengeluaranTable()) {
            'keuangan_pengeluaran_dosen' => [
                'headings' => [
                    'NO',
                    'TANGGAL',
                    'KODE',
                    'NAMA',
                    'PRODI',
                    'TRANSPORT MOTOR',
                    'HARI MOTOR',
                    'TRANSPORT MOBIL',
                    'HARI MOBIL',
                    'BAROKAH MENGAJAR',
                    'JAM',
                    'BAROKAH DOUBLE DEGREE',
                    'JAM DOUBLE DEGREE',
                    'BAROKAH UAS',
                    'JML MHS UAS',
                    'BAROKAH SEMPRO',
                    'JAM SEMPRO',
                    'TOTAL',
                    'JENIS PEMBAYARAN',
                    'KETERANGAN',
                ],
                'row' => fn ($item, $index) => [
                    $index + 1,
                    $this->formatGenericRekapExportDate($item->tanggal ?? null),
                    (string) ($item->kode_pegawai ?? ''),
                    $item->nama_pegawai ?: '-',
                    $item->prodi ?: '-',
                    (int) ($item->transport_motor ?? $item->transport ?? 0),
                    (int) ($item->hari_transport_motor ?? $item->hari ?? 0),
                    (int) ($item->transport_mobil ?? $item->transport_mobil_tanpa_tol ?? 0),
                    (int) ($item->hari_transport_mobil ?? $item->hari_transport_mobil_tanpa_tol ?? 0),
                    (int) ($item->barokah_mengajar_biasa ?? $item->barokah ?? 0),
                    (int) ($item->jam ?? 0),
                    (int) ($item->barokah_mengajar_double_degree ?? 0),
                    (int) ($item->jam_mengajar_double_degree ?? 0),
                    (int) ($item->barokah_uas ?? 0),
                    (int) ($item->jumlah_mahasiswa_uas ?? 0),
                    (int) ($item->barokah_sempro ?? 0),
                    (int) ($item->jam_sempro ?? 0),
                    (int) ($item->total ?? 0),
                    $item->jenis_pembayaran ?: '',
                    $item->keterangan ?: ($item->keterangan_sempro ?? ''),
                ],
                'amount_columns' => [6, 8, 10, 12, 14, 16, 18],
                'text_columns' => [3],
                'total_column' => 18,
            ],
            'keuangan_pengeluaran_dosen_kegiatan' => [
                'headings' => [
                    'NO',
                    'TANGGAL',
                    'KATEGORI',
                    'KODE',
                    'NAMA',
                    'TIPE',
                    'PRODI/JABATAN',
                    'NAMA KEGIATAN',
                    'TRANSPORT',
                    'BAROKAH',
                    'NOMINAL',
                    'TOTAL',
                    'JENIS PEMBAYARAN',
                    'KETERANGAN',
                ],
                'row' => fn ($item, $index) => [
                    $index + 1,
                    $this->formatGenericRekapExportDate($item->tanggal ?? null),
                    $item->kategori_detail ?: '-',
                    (string) ($item->kode_pegawai ?? ''),
                    $item->nama_pegawai ?: '-',
                    $item->tipe_pegawai ?: '-',
                    $item->prodi ?: ($item->jabatan ?: '-'),
                    $item->nama_kegiatan ?: '-',
                    (int) ($item->transport ?? 0),
                    (int) ($item->barokah ?? 0),
                    (int) ($item->nominal ?? 0),
                    (int) ($item->total ?? 0),
                    $item->jenis_pembayaran ?: '',
                    $item->keterangan ?: '',
                ],
                'amount_columns' => [9, 10, 11, 12],
                'text_columns' => [4],
                'total_column' => 12,
            ],
            'keuangan_pengeluaran_transportasi' => [
                'headings' => [
                    'NO',
                    'TANGGAL',
                    'PRIORITAS',
                    'URAIAN',
                    'VOLUME',
                    'SATUAN',
                    'HARGA SATUAN',
                    'TOTAL',
                    'JENIS PEMBAYARAN',
                    'PETUGAS',
                    'KETERANGAN',
                ],
                'row' => fn ($item, $index) => [
                    $index + 1,
                    $this->formatGenericRekapExportDate($item->tanggal ?? null),
                    $item->prioritas ?: '-',
                    $item->nama_kegiatan ?: '-',
                    $item->volume === null ? '' : (int) $item->volume,
                    $item->satuan ?: '',
                    (int) ($item->nominal ?? 0),
                    (int) ($item->total ?? 0),
                    $item->jenis_pembayaran ?: '',
                    $item->petugas_nama ?: '',
                    $item->keterangan ?: '',
                ],
                'amount_columns' => [7, 8],
                'total_column' => 8,
            ],
            default => [
                'headings' => [
                    'NO',
                    'TANGGAL',
                    'KELOMPOK ANGGARAN',
                    'URAIAN',
                    'VOLUME',
                    'SATUAN',
                    'HARGA SATUAN',
                    'TOTAL',
                    'JENIS PEMBAYARAN',
                    'PETUGAS',
                    'KETERANGAN',
                ],
                'row' => fn ($item, $index) => [
                    $index + 1,
                    $this->formatGenericRekapExportDate($item->tanggal ?? null),
                    $item->kelompok_anggaran ?: '-',
                    $item->nama_kegiatan ?: '-',
                    $item->volume === null ? '' : (int) $item->volume,
                    $item->satuan ?: '',
                    (int) ($item->nominal ?? 0),
                    (int) ($item->total ?? 0),
                    $item->jenis_pembayaran ?: '',
                    $item->petugas_nama ?: '',
                    $item->keterangan ?: '',
                ],
                'amount_columns' => [7, 8],
                'total_column' => 8,
            ],
        };
    }

    private function sortColumnExistsForGenericDetail(string $sortColumn, string $table): bool
    {
        if (! str_starts_with($sortColumn, 'detail.')) {
            return true;
        }

        return Schema::hasColumn($table, substr($sortColumn, strlen('detail.')));
    }

    private function genericRekapDetailLpjSameAsRab(int $rekapId): bool
    {
        if (! Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            return false;
        }

        $rekapTable = (new ($this->rekapModelClass()))->getTable();
        $lpjModuleKey = $this->lpjModuleKey($rekapTable);

        if (! $lpjModuleKey) {
            return false;
        }

        return DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', $lpjModuleKey)
            ->where('rekap_id', $rekapId)
            ->where('sama_dengan_rab', 1)
            ->exists();
    }

    private function simpleRekapIndex(Request $request, string $modelClass, string $rekapTable)
    {
        $query = $modelClass::query()
            ->leftJoin('users as petugas', 'petugas.id', '=', "{$rekapTable}.petugas_id")
            ->select([
                "{$rekapTable}.id",
                "{$rekapTable}.nama",
                "{$rekapTable}.bulan_tahun",
                "{$rekapTable}.tanggal_rekap",
                "{$rekapTable}.tanggal_pencairan",
                "{$rekapTable}.jumlah_sementara",
                "{$rekapTable}.keterangan",
                'petugas.name as petugas_nama',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search, $rekapTable) {
                $q->where("{$rekapTable}.nama", 'LIKE', "%{$search}%")
                    ->orWhere("{$rekapTable}.keterangan", 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth("{$rekapTable}.bulan_tahun", (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear("{$rekapTable}.bulan_tahun", (int) $request->tahun);
        }

        $this->applyRekapPetugasFilter($query, $request, $rekapTable);

        $data = $query
            ->orderByDesc("{$rekapTable}.id")
            ->limit(min(max((int) $request->input('limit', 20), 1), 50))
            ->get()
            ->each(function ($item) {
                $item->jumlah = (int) ($item->jumlah_sementara ?? 0);
                $item->jumlah_data = 0;
                $item->is_jumlah_sementara = true;
            });

        return response()->json([
            'status' => true,
            'data' => [
                'data' => $data,
                'total' => $data->count(),
            ],
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapStore(Request $request)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $input = $this->rekapInput($request);

        $validator = Validator::make($input, [
            'nama' => ['required', 'string', 'max:255', Rule::unique($rekapTable, 'nama')],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'tanggal_pencairan' => ['nullable', 'date_format:Y-m-d'],
            'petugas_id' => ['required', 'integer'],
            'jumlah_sementara' => $this->allowsEmptyRekapTemporary()
                ? ['nullable', 'integer', 'min:0']
                : ['required', 'integer', 'min:0'],
            'keterangan' => 'nullable|string',
        ]);
        $this->validateRekapPetugas($validator, $input['petugas_id'] ?? null);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['bulan_tahun'] .= '-01';
        $validated['petugas_id'] = (int) $validated['petugas_id'];

        $data = $modelClass::create($validated);
        $this->applyRekapSummary($data, [
            'jumlah_data' => 0,
            'total_pengeluaran' => 0,
        ]);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap created successfully',
        ], 201);
    }

    public function rekapUpdate(Request $request, $id)
    {
        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $data = $this->findScopedRekapModel($modelClass, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $summary = $this->rekapSummary($data->id);
        $hasDetails = $summary['jumlah_data'] > 0;
        $input = $this->rekapInput($request);

        $validator = Validator::make($input, [
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique($rekapTable, 'nama')->ignore($data->id),
            ],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'tanggal_pencairan' => ['nullable', 'date_format:Y-m-d'],
            'jumlah_sementara' => $hasDetails || $this->allowsEmptyRekapTemporary()
                ? ['nullable', 'integer', 'min:0']
                : ['required', 'integer', 'min:0'],
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['bulan_tahun'] .= '-01';

        if ($hasDetails) {
            unset($validated['jumlah_sementara']);
        }

        $data->update($validated);
        $this->applyRekapSummary($data, $summary);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap updated successfully',
        ]);
    }

    public function rekapShow($id)
    {
        $modelClass = $this->rekapModelClass();
        $data = $this->findScopedRekapModel($modelClass, $id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $this->applyRekapSummary($data, $this->rekapSummary($data->id));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap retrieved successfully',
        ]);
    }

    public function rekapBulkUpdate(Request $request)
    {
        $modelClass = $this->rekapModelClass();

        $rekapIdRules = $this->requiresRekapForPengeluaran()
            ? ['required', Rule::exists((new $modelClass)->getTable(), 'id')]
            : ['present', 'nullable', Rule::exists((new $modelClass)->getTable(), 'id')];

        $validator = Validator::make($request->all(), [
            'rekap_id' => $rekapIdRules,
            'all_pages' => 'nullable|boolean',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if (
            $request->filled('rekap_id')
            && ! $this->findScopedRekapModel($modelClass, $request->rekap_id)
        ) {
            return response()->json([
                'status' => false,
                'message' => [
                    'rekap_id' => ['Rekap tidak sesuai scope navbar aktif.'],
                ],
            ], 422);
        }

        $ids = $request->boolean('all_pages')
            ? $this->newRekapBulkPengeluaranQuery(new Request($request->input('filters', [])))
                ->pluck($this->pengeluaranTable().'.id')
                ->unique()
                ->values()
                ->all()
            : $this->newRekapBulkPengeluaranQuery(new Request)
                ->whereIn(
                    $this->pengeluaranTable().'.id',
                    collect($request->input('ids', []))->filter()->unique()->values()->all()
                )
                ->pluck($this->pengeluaranTable().'.id')
                ->unique()
                ->values()
                ->all();

        if (empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => [
                    'ids' => ['Pilih data pengeluaran terlebih dahulu.'],
                ],
            ], 422);
        }

        $updated = DB::transaction(function () use ($ids, $request) {
            $this->lockAllRekapRows();

            $oldRekapIds = DB::table($this->pengeluaranTable())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->pluck('rekap_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $affectedRekapIds = [
                ...$oldRekapIds,
                $request->rekap_id,
            ];

            $emptyFallbackAmounts = $this->snapshotRekapTotals($oldRekapIds);
            $updatePayload = [
                'rekap_id' => $request->rekap_id,
                'updated_at' => now(),
            ];

            if (
                $request->filled('rekap_id')
                && Schema::hasColumn($this->pengeluaranTable(), 'petugas_id')
            ) {
                $updatePayload['petugas_id'] = $this->petugasIdForRekapId((int) $request->rekap_id)
                    ?? auth()->id();
            }

            $updated = DB::table($this->pengeluaranTable())
                ->whereIn('id', $ids)
                ->update($updatePayload);

            $this->validateAndSyncRekapTemporary(
                $affectedRekapIds,
                $emptyFallbackAmounts
            );

            return $updated;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'updated' => $updated,
            ],
            'message' => $request->filled('rekap_id')
                ? "{$updated} data berhasil diupdate ke rekap."
                : "{$updated} data berhasil dibatalkan dari rekap.",
        ]);
    }

    public function rekapRelease($id)
    {
        if ($this->requiresRekapForPengeluaran()) {
            return response()->json([
                'status' => false,
                'message' => 'Data Pengeluaran Kegiatan wajib berada dalam rekap.',
            ], 422);
        }

        $modelClass = $this->rekapModelClass();
        $rekap = $this->findScopedRekapModel($modelClass, $id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $updated = DB::transaction(function () use ($rekap) {
            $this->lockRekapRows([$rekap->id]);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$rekap->id]);

            $updated = DB::table($this->pengeluaranTable())
                ->where('rekap_id', $rekap->id)
                ->update([
                    'rekap_id' => null,
                    'updated_at' => now(),
                ]);

            $this->validateAndSyncRekapTemporary(
                [$rekap->id],
                $emptyFallbackAmounts
            );

            return $updated;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'updated' => $updated,
            ],
            'message' => "{$updated} data berhasil dibatalkan dari rekap {$rekap->nama}.",
        ]);
    }

    public function rekapDestroy($id)
    {
        $modelClass = $this->rekapModelClass();
        $rekap = $this->findScopedRekapModel($modelClass, $id);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $nama = $rekap->nama;

        DB::transaction(function () use ($rekap) {
            if ($this->requiresRekapForPengeluaran()) {
                DB::table($this->pengeluaranTable())
                    ->where('rekap_id', $rekap->id)
                    ->delete();
            } else {
                DB::table($this->pengeluaranTable())
                    ->where('rekap_id', $rekap->id)
                    ->update(['rekap_id' => null, 'updated_at' => now()]);
            }
            $rekap->delete();
        });

        return response()->json([
            'status' => true,
            'message' => "Rekap {$nama} berhasil dihapus.",
        ]);
    }

    protected function joinRekap($query): void
    {
        $modelClass = $this->rekapModelClass();
        $pengeluaranTable = $this->pengeluaranTable();
        $query->leftJoin(
            (new $modelClass)->getTable().' as pengeluaran_rekap',
            'pengeluaran_rekap.id',
            '=',
            "{$pengeluaranTable}.rekap_id"
        );

        if (Schema::hasColumn($pengeluaranTable, 'petugas_id')) {
            $query->leftJoin('users as petugas', function ($join) use ($pengeluaranTable) {
                $join->on('petugas.id', '=', DB::raw("COALESCE({$pengeluaranTable}.petugas_id, pengeluaran_rekap.petugas_id)"));
            });
        }
    }

    protected function applyRekapFilter($query, Request $request): void
    {
        if ($request->filled('rekap_id')) {
            $query->where($this->pengeluaranTable().'.rekap_id', $request->rekap_id);
        }
    }

    protected function applyPetugasFilter($query, Request $request, ?string $table = null): void
    {
        $tableName = $table ?? $this->pengeluaranTable();

        $this->applyPengeluaranGenderScope($query, $tableName);

        if (
            ! $request->filled('petugas_id')
            || ! Schema::hasColumn($tableName, 'petugas_id')
        ) {
            return;
        }

        $query->where("{$tableName}.petugas_id", $request->petugas_id);
    }

    protected function petugasIdForPengeluaran(Request $request): int
    {
        if ($request->filled('rekap_id')) {
            $petugasId = $this->petugasIdForRekapId((int) $request->rekap_id);

            if ($petugasId) {
                return $petugasId;
            }
        }

        return (int) auth()->id();
    }

    protected function petugasIdForRekapId(int $rekapId): ?int
    {
        if ($rekapId <= 0) {
            return null;
        }

        $modelClass = $this->rekapModelClass();
        $rekapTable = (new $modelClass)->getTable();
        $cacheKey = "{$rekapTable}:{$rekapId}";

        if (! array_key_exists($cacheKey, $this->rekapPetugasIdCache)) {
            $this->rekapPetugasIdCache[$cacheKey] = $modelClass::query()
                ->whereKey($rekapId)
                ->value('petugas_id');
        }

        return $this->rekapPetugasIdCache[$cacheKey] === null
            ? null
            : (int) $this->rekapPetugasIdCache[$cacheKey];
    }

    private function validateRekapPetugas($validator, $petugasId): void
    {
        $validator->after(function ($validator) use ($petugasId) {
            if (! $petugasId || ! $this->petugasAllowedForRekap((int) $petugasId)) {
                $validator->errors()->add(
                    'petugas_id',
                    'Petugas tidak valid untuk modul atau scope navbar aktif.'
                );
            }
        });
    }

    private function petugasAllowedForRekap(int $petugasId): bool
    {
        $query = User::query()
            ->where('users.id', $petugasId)
            ->whereHas(
                'role',
                fn ($role) => $role->whereIn('name', Helper::pengeluaranPetugasRoles($this->petugasModuleKey()))
            );
        Helper::applyGenderScope($query, 'users.jenis_kelamin');

        return $query->exists();
    }

    private function petugasModuleKey(): string
    {
        return match ($this->pengeluaranTable()) {
            'keuangan_pengeluaran_dosen' => 'dosen_tatapmuka',
            'keuangan_pengeluaran_dosen_kegiatan' => 'dosen_kegiatan',
            'keuangan_pengeluaran_pegawai_bulanan' => 'bulanan',
            'keuangan_pengeluaran_rumah_tangga' => 'rumah_tangga',
            'keuangan_pengeluaran_sarana_prasarana' => 'sarana_prasarana',
            'keuangan_pengeluaran_transportasi' => 'transportasi',
            default => 'rab',
        };
    }

    protected function applyRekapPetugasFilter($query, Request $request, string $rekapTable): void
    {
        if (Schema::hasColumn($rekapTable, 'petugas_id')) {
            Helper::applyRelatedGenderScope(
                $query,
                "{$rekapTable}.petugas_id",
                'users'
            );
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($rekapTable, 'petugas_id')
        ) {
            $query->where("{$rekapTable}.petugas_id", $request->petugas_id);
        }
    }

    protected function applyPengeluaranGenderScope(
        $query,
        string $table,
        ?string $alias = null
    ): void {
        Helper::applyExpenseGenderScope($query, $table, $alias);
    }

    protected function findScopedPengeluaranModel(string $modelClass, $id): ?Model
    {
        $model = new $modelClass;
        $query = $modelClass::query();

        $this->applyPengeluaranGenderScope($query, $model->getTable());

        return $query->whereKey($id)->first();
    }

    protected function findScopedRekapModel(string $modelClass, $id): ?Model
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $query = $modelClass::query();

        if (Schema::hasColumn($table, 'petugas_id')) {
            Helper::applyRelatedGenderScope(
                $query,
                "{$table}.petugas_id",
                'users'
            );
        }

        return $query->whereKey($id)->first();
    }

    protected function rekapIdRules(): array
    {
        $modelClass = $this->rekapModelClass();
        $table = (new $modelClass)->getTable();

        return [
            'nullable',
            Rule::exists($table, 'id')->where(function ($query) use ($table) {
                if (Schema::hasColumn($table, 'petugas_id')) {
                    Helper::applyRelatedGenderScope(
                        $query,
                        "{$table}.petugas_id",
                        'users'
                    );
                }
            }),
        ];
    }

    protected function savePengeluaranWithRekapValidation(Model $data): void
    {
        $oldRekapId = $data->exists ? $data->getOriginal('rekap_id') : null;

        DB::transaction(function () use ($data, $oldRekapId) {
            $affectedRekapIds = [$oldRekapId, $data->rekap_id];
            $this->lockRekapRows($affectedRekapIds);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$oldRekapId]);

            $data->save();
            $this->validateAndSyncRekapTemporary(
                $affectedRekapIds,
                $emptyFallbackAmounts
            );
        });
    }

    protected function deletePengeluaranWithRekapValidation(Model $data): void
    {
        $rekapId = $data->rekap_id;

        DB::transaction(function () use ($data, $rekapId) {
            $this->lockRekapRows([$rekapId]);
            $emptyFallbackAmounts = $this->snapshotRekapTotals([$rekapId]);

            $data->delete();
            $this->validateAndSyncRekapTemporary(
                [$rekapId],
                $emptyFallbackAmounts
            );
        });
    }

    protected function lockRekapRows(array $rekapIds): void
    {
        $ids = $this->normalizeRekapIds($rekapIds);

        if ($ids->isEmpty()) {
            return;
        }

        $modelClass = $this->rekapModelClass();
        $modelClass::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    protected function lockAllRekapRows(): void
    {
        $modelClass = $this->rekapModelClass();
        $modelClass::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    protected function snapshotRekapTotals(array $rekapIds): array
    {
        return $this->normalizeRekapIds($rekapIds)
            ->mapWithKeys(fn ($id) => [
                $id => $this->rekapSummary($id)['total_pengeluaran'],
            ])
            ->all();
    }

    protected function validateAndSyncRekapTemporary(
        array $rekapIds,
        array $emptyFallbackAmounts = []
    ): void {
        $ids = $this->normalizeRekapIds($rekapIds);

        if ($ids->isEmpty()) {
            return;
        }

        $modelClass = $this->rekapModelClass();
        $rekaps = $modelClass::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $rekap = $rekaps->get($id);

            if (! $rekap) {
                continue;
            }

            $summary = $this->rekapSummary($id);

            if (
                $rekap->jumlah_sementara === null
                && $summary['jumlah_data'] === 0
                && array_key_exists($id, $emptyFallbackAmounts)
            ) {
                $rekap->jumlah_sementara = (int) $emptyFallbackAmounts[$id];
                $rekap->save();
            }

            if ($rekap->jumlah_sementara === null) {
                continue;
            }

            $temporaryAmount = (int) $rekap->jumlah_sementara;
            $detailAmount = $summary['total_pengeluaran'];
            $amounts = $this->resolveRekapAmounts(
                $temporaryAmount,
                $summary['jumlah_data'],
                $detailAmount
            );

            if ($amounts['exceeds_temporary']) {
                throw ValidationException::withMessages([
                    'total' => [
                        'Total detail Rp '.number_format($detailAmount, 0, ',', '.')
                        .' melebihi jumlah sementara Rp '
                        .number_format($temporaryAmount, 0, ',', '.')
                        ." pada rekap {$rekap->nama}.",
                    ],
                ]);
            }

            if ($amounts['should_clear_temporary']) {
                $rekap->jumlah_sementara = null;
                $rekap->save();
            }
        }
    }

    protected function resolveRekapAmounts(
        ?int $temporaryAmount,
        int $detailCount,
        int $detailAmount
    ): array {
        return [
            'jumlah' => $detailCount > 0
                ? $detailAmount
                : ($temporaryAmount ?? 0),
            'is_jumlah_sementara' => $detailCount === 0,
            'selisih_sementara' => $temporaryAmount !== null
                ? max(0, $temporaryAmount - $detailAmount)
                : 0,
            'exceeds_temporary' => $temporaryAmount !== null
                && $detailAmount > $temporaryAmount,
            'should_clear_temporary' => $temporaryAmount !== null
                && $detailCount > 0
                && $detailAmount === $temporaryAmount,
        ];
    }

    private function rekapInput(Request $request): array
    {
        return [
            'nama' => trim((string) $request->input('nama')),
            'petugas_id' => $request->input('petugas_id'),
            'bulan_tahun' => $request->input('bulan_tahun'),
            'tanggal_rekap' => $request->input('tanggal_rekap'),
            'tanggal_pencairan' => $request->input('tanggal_pencairan'),
            'jumlah_sementara' => $request->input(
                'jumlah_sementara',
                $request->input('jumlah')
            ),
            'keterangan' => $request->input('keterangan'),
        ];
    }

    protected function allowsEmptyRekapTemporary(): bool
    {
        return true;
    }

    private function filteredRekapBaseQuery(Request $request, string $modelClass, string $rekapTable)
    {
        $query = $modelClass::query()
            ->leftJoin('users as petugas', 'petugas.id', '=', "{$rekapTable}.petugas_id")
            ->select([
                "{$rekapTable}.*",
                'petugas.name as petugas_nama',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search, $rekapTable) {
                $q->where("{$rekapTable}.nama", 'LIKE', "%{$search}%")
                    ->orWhere("{$rekapTable}.keterangan", 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('bulan')) {
            $query->whereMonth("{$rekapTable}.bulan_tahun", (int) $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear("{$rekapTable}.bulan_tahun", (int) $request->tahun);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate("{$rekapTable}.tanggal_rekap", '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate("{$rekapTable}.tanggal_rekap", '<=', $request->tanggal_akhir);
        }

        $this->applyRekapPetugasFilter($query, $request, $rekapTable);

        return $query;
    }

    private function rekapSummaryQuery(Request $request, $filteredRekaps = null)
    {
        $rekapTable = (new ($this->rekapModelClass()))->getTable();
        $query = $filteredRekaps
            ? DB::query()
                ->fromSub((clone $filteredRekaps)->select("{$rekapTable}.id"), 'filtered_rekap')
                ->join($this->pengeluaranTable(), $this->pengeluaranTable().'.rekap_id', '=', 'filtered_rekap.id')
            : $this->newRekapPengeluaranQuery();

        $this->applyPetugasFilter($query, $request);

        return $query
            ->whereNotNull($this->pengeluaranTable().'.rekap_id')
            ->select([
                $this->pengeluaranTable().'.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw(
                    'COALESCE(SUM('.$this->pengeluaranTable().'.total), 0) as total_pengeluaran'
                ),
            ])
            ->groupBy($this->pengeluaranTable().'.rekap_id');
    }

    private function lpjSummaryQuery(Request $request, $filteredRekaps = null)
    {
        $lpjTable = $this->lpjPengeluaranTable();
        $rekapTable = (new ($this->rekapModelClass()))->getTable();

        if (! Schema::hasTable($lpjTable)) {
            return null;
        }

        $query = $filteredRekaps
            ? DB::query()
                ->fromSub((clone $filteredRekaps)->select("{$rekapTable}.id"), 'filtered_rekap')
                ->join("{$lpjTable} as lpj_detail", 'lpj_detail.rekap_id', '=', 'filtered_rekap.id')
            : DB::table("{$lpjTable} as lpj_detail");

        $query
            ->whereNotNull('lpj_detail.rekap_id')
            ->select([
                'lpj_detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_lpj'),
                DB::raw('COALESCE(SUM(lpj_detail.total), 0) as total_lpj'),
            ]);

        $pegawaiTipe = $this->pegawaiTipeForLpj();
        if ($pegawaiTipe && Schema::hasColumn($lpjTable, 'pegawai_tipe')) {
            if (is_array($pegawaiTipe)) {
                $query->whereIn('lpj_detail.pegawai_tipe', $pegawaiTipe);
            } else {
                $query->where('lpj_detail.pegawai_tipe', $pegawaiTipe);
            }
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($lpjTable, 'petugas_id')
        ) {
            $query->where('lpj_detail.petugas_id', $request->petugas_id);
        }

        $this->applyPengeluaranGenderScope($query, $lpjTable, 'lpj_detail');

        return $query->groupBy('lpj_detail.rekap_id');
    }

    private function rekapSummariesForIds(Request $request, array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        $query = $this->newRekapPengeluaranQuery();
        $this->applyPetugasFilter($query, $request);

        return $query
            ->whereIn($this->pengeluaranTable().'.rekap_id', $ids)
            ->select([
                $this->pengeluaranTable().'.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM('.$this->pengeluaranTable().'.total), 0) as total_pengeluaran'),
            ])
            ->groupBy($this->pengeluaranTable().'.rekap_id')
            ->get()
            ->keyBy('rekap_id');
    }

    private function lpjSummariesForIds(Request $request, array $ids)
    {
        $lpjTable = $this->lpjPengeluaranTable();

        if ($ids === [] || ! Schema::hasTable($lpjTable)) {
            return collect();
        }

        $query = DB::table("{$lpjTable} as lpj_detail")
            ->whereIn('lpj_detail.rekap_id', $ids)
            ->select([
                'lpj_detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_lpj'),
                DB::raw('COALESCE(SUM(lpj_detail.total), 0) as total_lpj'),
            ]);

        $pegawaiTipe = $this->pegawaiTipeForLpj();
        if ($pegawaiTipe && Schema::hasColumn($lpjTable, 'pegawai_tipe')) {
            if (is_array($pegawaiTipe)) {
                $query->whereIn('lpj_detail.pegawai_tipe', $pegawaiTipe);
            } else {
                $query->where('lpj_detail.pegawai_tipe', $pegawaiTipe);
            }
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($lpjTable, 'petugas_id')
        ) {
            $query->where('lpj_detail.petugas_id', $request->petugas_id);
        }

        $this->applyPengeluaranGenderScope($query, $lpjTable, 'lpj_detail');

        return $query
            ->groupBy('lpj_detail.rekap_id')
            ->get()
            ->keyBy('rekap_id');
    }

    private function lpjStatusesForIds(array $ids)
    {
        $rekapTable = (new ($this->rekapModelClass()))->getTable();
        $lpjModuleKey = $this->lpjModuleKey($rekapTable);

        if (
            $ids === []
            || ! $lpjModuleKey
            || ! Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')
        ) {
            return collect();
        }

        return DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', $lpjModuleKey)
            ->whereIn('rekap_id', $ids)
            ->get()
            ->keyBy('rekap_id');
    }

    private function rekapSummary($rekapId): array
    {
        $query = $this->newRekapPengeluaranQuery();
        $this->applyPengeluaranGenderScope($query, $this->pengeluaranTable());

        $summary = $query
            ->where($this->pengeluaranTable().'.rekap_id', $rekapId)
            ->select([
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw(
                    'COALESCE(SUM('.$this->pengeluaranTable().'.total), 0) as total_pengeluaran'
                ),
            ])
            ->first();

        return [
            'jumlah_data' => (int) ($summary->jumlah_data ?? 0),
            'total_pengeluaran' => (int) ($summary->total_pengeluaran ?? 0),
        ];
    }

    private function applyRekapSummary($data, array $summary): void
    {
        $amounts = $this->resolveRekapAmounts(
            $data->jumlah_sementara === null ? null : (int) $data->jumlah_sementara,
            (int) $summary['jumlah_data'],
            (int) $summary['total_pengeluaran']
        );

        $data->jumlah_data = (int) $summary['jumlah_data'];
        $data->total_pengeluaran = (int) $summary['total_pengeluaran'];
        $data->jumlah = $amounts['jumlah'];
        $data->is_jumlah_sementara = $amounts['is_jumlah_sementara'];
        $data->selisih_sementara = $amounts['selisih_sementara'];
    }

    private function castRekapSummary($data): void
    {
        $data->jumlah_data = (int) $data->jumlah_data;
        $data->total_pengeluaran = (int) $data->total_pengeluaran;
        $data->jumlah = (int) $data->jumlah;
        $data->is_jumlah_sementara = (bool) $data->is_jumlah_sementara;
        $data->selisih_sementara = (int) $data->selisih_sementara;
        $data->jumlah_lpj = (int) ($data->jumlah_lpj ?? 0);
        $data->total_lpj = (int) ($data->total_lpj ?? 0);
        $data->lpj_sama_dengan_rab = (bool) ($data->lpj_sama_dengan_rab ?? false);
    }

    private function effectiveAmountSql(string $rekapTable): string
    {
        return "CASE
            WHEN COALESCE(rekap_summary.jumlah_data, 0) > 0
                THEN COALESCE(rekap_summary.total_pengeluaran, 0)
            ELSE COALESCE({$rekapTable}.jumlah_sementara, 0)
        END";
    }

    private function temporaryDifferenceSql(string $rekapTable): string
    {
        return "CASE
            WHEN {$rekapTable}.jumlah_sementara IS NOT NULL
                AND {$rekapTable}.jumlah_sementara
                    > COALESCE(rekap_summary.total_pengeluaran, 0)
                THEN {$rekapTable}.jumlah_sementara
                    - COALESCE(rekap_summary.total_pengeluaran, 0)
            ELSE 0
        END";
    }

    private function effectiveLpjAmountSql(string $rekapTable, bool $useFilteredRabAmount = false): string
    {
        $sameAsRabAmount = $useFilteredRabAmount
            ? $this->effectiveAmountSql($rekapTable)
            : "COALESCE(NULLIF(lpj_status.total_lpj, 0), {$this->effectiveAmountSql($rekapTable)})";

        return "COALESCE(
            CASE
                WHEN COALESCE(lpj_summary.jumlah_lpj, 0) > 0
                    THEN COALESCE(lpj_summary.total_lpj, 0)
                WHEN COALESCE(lpj_status.sama_dengan_rab, 0) = 1
                    THEN {$sameAsRabAmount}
                ELSE 0
            END,
            0
        )";
    }

    private function genericRekapExportModuleName(): string
    {
        return match ($this->pengeluaranTable()) {
            'keuangan_pengeluaran_dosen' => 'Barokah Dosen Tatapmuka',
            'keuangan_pengeluaran_dosen_kegiatan' => 'Barokah Pegawai Kegiatan',
            'keuangan_pengeluaran_rumah_tangga' => 'Rumah Tangga',
            'keuangan_pengeluaran_sarana_prasarana' => 'Sarana Prasarana',
            'keuangan_pengeluaran_transportasi' => 'Transportasi',
            default => 'Pengeluaran',
        };
    }

    private function genericRekapExportPeriodLabel(Request $request): string
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

    private function formatGenericRekapExportPeriod($value): string
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

    private function formatGenericRekapExportDate($value): string
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

    private function genericRekapExportFilename(string $name): string
    {
        $safeName = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return ($safeName ?: 'Rekapan Pengeluaran').'.xlsx';
    }

    private function lpjPengeluaranTable(): string
    {
        return $this->pengeluaranTable().'_lpj';
    }

    protected function lpjModuleKey(string $rekapTable): ?string
    {
        return match ($rekapTable) {
            'keuangan_pengeluaran_dosen_rekap' => 'tatap_muka',
            'keuangan_pengeluaran_dosen_kegiatan_rekap' => 'kegiatan',
            'keuangan_pengeluaran_rumah_tangga_rekap' => 'rumah_tangga',
            'keuangan_pengeluaran_sarana_prasarana_rekap' => 'sarana_prasarana',
            'keuangan_pengeluaran_transportasi_rekap' => 'transportasi',
            'keuangan_pengeluaran_dosen_bulanan_rekap' => 'dosen_bulanan',
            default => null,
        };
    }

    private function pegawaiTipeForLpj(): string|array|null
    {
        return defined(static::class.'::PEGAWAI_TIPE') ? static::PEGAWAI_TIPE : null;
    }

    private function normalizeRekapIds(array $rekapIds)
    {
        return collect($rekapIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
    }
}
