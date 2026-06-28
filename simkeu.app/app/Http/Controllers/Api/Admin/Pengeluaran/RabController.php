<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use App\Services\Helper;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RabController extends Controller
{
    private const SOURCES = [
        'tatap_muka' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'lpj_table' => 'keuangan_pengeluaran_dosen_lpj',
            'module_name' => 'Dosen Tatap Muka',
            'detail_path' => '/admin/pengeluaran/dosen-tatapmuka/rekap/',
            'pegawai_tipe' => null,
        ],
        'kegiatan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'lpj_table' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
            'module_name' => 'Pegawai Kegiatan',
            'detail_path' => '/admin/pengeluaran/dosen-kegiatan/rekap/',
            'pegawai_tipe' => null,
        ],
        'rumah_tangga' => [
            'rekap_table' => 'keuangan_pengeluaran_rumah_tangga_rekap',
            'detail_table' => 'keuangan_pengeluaran_rumah_tangga',
            'lpj_table' => 'keuangan_pengeluaran_rumah_tangga_lpj',
            'module_name' => 'Rumah Tangga',
            'detail_path' => '/admin/pengeluaran/rumah-tangga/rekap/',
            'pegawai_tipe' => null,
        ],
        'sarana_prasarana' => [
            'rekap_table' => 'keuangan_pengeluaran_sarana_prasarana_rekap',
            'detail_table' => 'keuangan_pengeluaran_sarana_prasarana',
            'lpj_table' => 'keuangan_pengeluaran_sarana_prasarana_lpj',
            'module_name' => 'Sarana Prasarana',
            'detail_path' => '/admin/pengeluaran/sarana-prasarana/rekap/',
            'pegawai_tipe' => null,
        ],
        'transportasi' => [
            'rekap_table' => 'keuangan_pengeluaran_transportasi_rekap',
            'detail_table' => 'keuangan_pengeluaran_transportasi',
            'lpj_table' => 'keuangan_pengeluaran_transportasi_lpj',
            'module_name' => 'Transportasi',
            'detail_path' => '/admin/pengeluaran/transportasi/rekap/',
            'pegawai_tipe' => null,
        ],
        'umum' => [
            'rekap_table' => 'keuangan_pengeluaran_umum_rekap',
            'detail_table' => 'keuangan_pengeluaran_umum',
            'lpj_table' => 'keuangan_pengeluaran_umum_lpj',
            'module_name' => 'Pengeluaran Umum',
            'detail_path' => '/admin/pengeluaran/umum/rekap/',
            'pegawai_tipe' => null,
        ],
        'dosen_bulanan' => [
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'module_name' => 'Bulanan',
            'detail_path' => '/admin/pengeluaran/bulanan/rekap/',
            'pegawai_tipe' => null,
        ],
    ];

    public function index(Request $request)
    {
        $sortKey = $request->input('sort_key', 'bulan_tahun');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumns = $this->rabSortColumns();

        if ($request->boolean('stats_only')) {
            return response()->json([
                'status' => true,
                'stats' => $this->rabStats($request),
                'filters' => [
                    'years' => $this->yearOptions(),
                    'modules' => $this->moduleOptions(),
                ],
                'message' => 'RAB stats retrieved successfully',
            ]);
        }

        if (
            $request->boolean('fast_list')
            && ! $this->hasRabFilters($request)
            && ! $this->sortRequiresRabSummary($sortKey)
        ) {
            return $this->fastIndex($request, $sortKey, $sortOrder, $sortColumns);
        }

        $filteredRekaps = $this->filteredRekapQuery($request);
        $rekapStats = DB::query()
            ->fromSub(clone $filteredRekaps, 'rab')
            ->selectRaw(
                'COUNT(*) as total_rekap,
                COUNT(DISTINCT module_key) as total_modul,
                COALESCE(SUM(jumlah), 0) as total_anggaran,
                COALESCE(SUM(total_lpj), 0) as total_lpj'
            )
            ->first();

        $totalRekap = (int) ($rekapStats->total_rekap ?? 0);

        $pageQuery = clone $filteredRekaps;
        $pageQuery
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->select('rab.*', 'petugas.name as petugas_nama')
            ->orderBy($sortColumns[$sortKey] ?? 'bulan_tahun', $sortOrder)
            ->orderBy('module_name')
            ->orderBy('nama');

        $data = $this->paginate($pageQuery, $request, $totalRekap);
        $data->getCollection()->each(function ($item) {
            $item->jumlah = (int) $item->jumlah;
            $item->jumlah_sementara = $item->jumlah_sementara === null
                ? null
                : (int) $item->jumlah_sementara;
            $item->jumlah_data = (int) $item->jumlah_data;
            $item->total_pengeluaran = (int) $item->total_pengeluaran;
            $item->total_lpj = (int) $item->total_lpj;
            $item->is_jumlah_sementara = (bool) $item->is_jumlah_sementara;
            $item->selisih_sementara = (int) $item->selisih_sementara;
            $item->cetak_rab = (bool) $item->cetak_rab;
            $item->petugas_nama = $item->petugas_nama ?? null;
        });

        $detailStats = $this->detailStats($request);
        $years = $this->yearOptions();

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => [
                'total_rekap' => $totalRekap,
                'total_data' => $detailStats['total_data'],
                'total_anggaran' => (int) ($rekapStats->total_anggaran ?? 0),
                'total_lpj' => (int) ($rekapStats->total_lpj ?? 0),
                'selisih' => (int) ($rekapStats->total_anggaran ?? 0) - (int) ($rekapStats->total_lpj ?? 0),
                'total_modul' => (int) ($rekapStats->total_modul ?? 0),
            ],
            'filters' => [
                'years' => $years,
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'RAB retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'nama' => ['required', 'string', 'max:255'],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'tanggal_pencairan' => ['nullable', 'date_format:Y-m-d'],
            'jumlah_sementara' => ['required', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $this->petugasAllowedForModule((int) $validated['petugas_id'], $validated['module_key'])) {
            return response()->json([
                'status' => false,
                'message' => 'Petugas tidak sesuai dengan jenis rekap yang dipilih.',
            ], 422);
        }

        $source = self::SOURCES[$validated['module_key']];
        $rekapTable = $source['rekap_table'];

        $nameExists = DB::table($rekapTable)
            ->where('nama', $validated['nama'])
            ->exists();

        if ($nameExists) {
            return response()->json([
                'status' => false,
                'message' => [
                    'nama' => ['Nama rekap sudah digunakan pada jenis rekap ini.'],
                ],
            ], 422);
        }

        $id = DB::table($rekapTable)->insertGetId([
            'nama' => $validated['nama'],
            'bulan_tahun' => $validated['bulan_tahun'].'-01',
            'tanggal_rekap' => $validated['tanggal_rekap'],
            'tanggal_pencairan' => $validated['tanggal_pencairan'] ?? null,
            'jumlah_sementara' => $validated['jumlah_sementara'],
            'petugas_id' => $validated['petugas_id'],
            'keterangan' => $validated['keterangan'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = DB::table("{$rekapTable} as rekap")
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rekap.petugas_id')
            ->where('rekap.id', $id)
            ->first([
                'rekap.*',
                'petugas.name as petugas_nama',
            ]);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap anggaran berhasil ditambahkan.',
        ], 201);
    }

    public function updateTanggalPencairan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'tanggal_pencairan' => ['present', 'nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $items = collect($validated['items'])
            ->unique(fn (array $item) => "{$item['module_key']}:{$item['id']}")
            ->values();

        foreach ($items as $item) {
            $query = $this->scopedRekapQuery($item['module_key'], (int) $item['id']);

            if (! $query->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Rekap {$item['module_key']}:{$item['id']} tidak ditemukan atau tidak dapat diakses.",
                ], 404);
            }
        }

        DB::transaction(function () use ($items, $validated) {
            foreach ($items as $item) {
                $this->scopedRekapQuery($item['module_key'], (int) $item['id'])
                    ->update([
                        'tanggal_pencairan' => $validated['tanggal_pencairan'],
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json([
            'status' => true,
            'data' => [
                'row_keys' => $items
                    ->map(fn (array $item) => "{$item['module_key']}:{$item['id']}")
                    ->all(),
                'tanggal_pencairan' => $validated['tanggal_pencairan'],
            ],
            'message' => $items->count() > 1
                ? "{$items->count()} tanggal pencairan berhasil diperbarui."
            : 'Tanggal pencairan berhasil diperbarui.',
        ]);
    }

    public function prosesIndex(Request $request)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => true,
                'data' => [],
                'message' => 'List proses RAB retrieved successfully',
            ]);
        }

        $summary = DB::table('keuangan_cetak_rab_detail as detail')
            ->joinSub($this->filteredRekapQuery($request), 'rab', function ($join) {
                $join->on('rab.module_key', '=', 'detail.module_key')
                    ->on('rab.id', '=', 'detail.rekap_id');
            })
            ->selectRaw(
                'detail.cetak_rab_id,
                COUNT(*) as jumlah_rekap,
                COALESCE(SUM(rab.jumlah), 0) as total_rab'
            )
            ->groupBy('detail.cetak_rab_id');

        $rows = DB::table('keuangan_cetak_rab as cetak')
            ->joinSub($summary, 'summary', 'summary.cetak_rab_id', '=', 'cetak.id')
            ->select([
                'cetak.id',
                'cetak.tanggal_cetak',
                'cetak.keterangan',
                'cetak.created_at',
                DB::raw('summary.jumlah_rekap as jumlah_rekap'),
                DB::raw('summary.total_rab as total_rab'),
            ]);

        $this->applyProsesRabFilters($rows, $request);

        $rows = $rows
            ->orderByDesc('cetak.tanggal_cetak')
            ->orderByDesc('cetak.id')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $item->id = (int) $item->id;
                $item->jumlah_rekap = (int) $item->jumlah_rekap;
                $item->total_rab = (int) $item->total_rab;

                return $item;
            });

        return response()->json([
            'status' => true,
            'data' => $rows,
            'message' => 'List proses RAB retrieved successfully',
        ]);
    }

    public function storeProsesRab(Request $request)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_cetak' => ['required', 'date_format:Y-m-d'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $items = collect($validated['items'])
            ->unique(fn (array $item) => "{$item['module_key']}:{$item['id']}")
            ->values();

        foreach ($items as $item) {
            $query = $this->scopedRekapQuery($item['module_key'], (int) $item['id']);

            if (! $query->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Rekap {$item['module_key']}:{$item['id']} tidak ditemukan atau tidak dapat diakses.",
                ], 404);
            }
        }

        $keterangan = trim((string) ($validated['keterangan'] ?? ''));
        $cetakRabId = null;

        DB::transaction(function () use ($items, $validated, $keterangan, &$cetakRabId) {
            $now = now();
            $cetakRabId = DB::table('keuangan_cetak_rab')->insertGetId([
                'tanggal_cetak' => $validated['tanggal_cetak'],
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('keuangan_cetak_rab_detail')->insert(
                $items->map(fn (array $item) => [
                    'cetak_rab_id' => $cetakRabId,
                    'module_key' => $item['module_key'],
                    'rekap_id' => (int) $item['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );

            foreach ($items as $item) {
                if ($this->rekapHasCetakRabColumn($item['module_key'])) {
                    $this->scopedRekapQuery($item['module_key'], (int) $item['id'])
                        ->update([
                            'cetak_rab' => true,
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $cetakRabId,
                'row_keys' => $items
                    ->map(fn (array $item) => "{$item['module_key']}:{$item['id']}")
                    ->all(),
            ],
            'message' => $items->count().' rekap berhasil diproses untuk RAB.',
        ], 201);
    }

    public function destroyProsesRab($id)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        $cetak = DB::table('keuangan_cetak_rab')->where('id', $id)->first();

        if (! $cetak) {
            return response()->json([
                'status' => false,
                'message' => 'Proses RAB tidak ditemukan.',
            ], 404);
        }

        $items = DB::table('keuangan_cetak_rab_detail')
            ->where('cetak_rab_id', $id)
            ->get(['module_key', 'rekap_id'])
            ->map(fn ($item) => [
                'module_key' => $item->module_key,
                'id' => (int) $item->rekap_id,
            ])
            ->unique(fn (array $item) => "{$item['module_key']}:{$item['id']}")
            ->values();

        foreach ($items as $item) {
            $table = self::SOURCES[$item['module_key']]['rekap_table'] ?? null;

            if (! $table || ! DB::table($table)->where('id', $item['id'])->exists()) {
                continue;
            }

            if (! $this->scopedRekapQuery($item['module_key'], $item['id'])->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Proses RAB tidak ditemukan atau tidak dapat diakses.",
                ], 404);
            }
        }

        $resetRowKeys = [];

        DB::transaction(function () use ($id, $items, &$resetRowKeys) {
            $now = now();

            DB::table('keuangan_cetak_rab_detail')
                ->where('cetak_rab_id', $id)
                ->delete();

            DB::table('keuangan_cetak_rab')
                ->where('id', $id)
                ->delete();

            foreach ($items as $item) {
                $hasOtherProcess = DB::table('keuangan_cetak_rab_detail')
                    ->where('module_key', $item['module_key'])
                    ->where('rekap_id', $item['id'])
                    ->exists();

                if ($hasOtherProcess) {
                    continue;
                }

                if ($this->rekapHasCetakRabColumn($item['module_key'])) {
                    $this->scopedRekapQuery($item['module_key'], $item['id'])
                        ->update([
                            'cetak_rab' => false,
                            'updated_at' => $now,
                        ]);
                }

                $resetRowKeys[] = "{$item['module_key']}:{$item['id']}";
            }
        });

        return response()->json([
            'status' => true,
            'data' => [
                'row_keys' => $items
                    ->map(fn (array $item) => "{$item['module_key']}:{$item['id']}")
                    ->all(),
                'reset_row_keys' => $resetRowKeys,
            ],
            'message' => 'Proses RAB berhasil dihapus.',
        ]);
    }

    public function exportProsesRab(Request $request, $id)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        $cetak = DB::table('keuangan_cetak_rab')->where('id', $id)->first();

        if (! $cetak) {
            return response()->json([
                'status' => false,
                'message' => 'Proses RAB tidak ditemukan.',
            ], 404);
        }

        $data = $this->prosesRabExportRows((int) $id, $request);
        $rows = $data->values()->map(function ($item, $index) {
            $keterangan = trim((string) ($item->keterangan ?: ''));
            $moduleName = trim((string) ($item->module_name ?: ''));

            return [
                $index + 1,
                $item->nama ?: '-',
                $item->tanggal_rekap,
                (int) ($item->jumlah ?? 0),
                $keterangan !== '' ? $keterangan : $moduleName,
            ];
        })->all();

        $label = trim((string) ($cetak->keterangan ?: ''));

        if ($label === '') {
            $label = strtoupper(\Carbon\Carbon::parse($cetak->tanggal_cetak)->locale('id')->translatedFormat('d F Y'));
        }

        $title = trim('REKAP RAB '.$label);
        $safeName = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $title ?: 'RAB'));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return $this->downloadProsesRabSpreadsheet(
            $title ?: 'REKAP RAB',
            $rows,
            $data->sum(fn ($item) => (int) ($item->jumlah ?? 0)),
            ($safeName ?: 'RAB').'.xlsx'
        );
    }

    public function exportExcel(Request $request)
    {
        $data = $this->rabExportRows($request);
        $period = $this->requestExportPeriodLabel($request);
        $title = trim('REKAP RAB '.$period);
        $rows = $data->values()->map(function ($item, $index) {
            $keterangan = trim((string) ($item->keterangan ?: ''));
            $moduleName = trim((string) ($item->module_name ?: ''));

            return [
                $index + 1,
                $item->nama ?: '-',
                $item->tanggal_rekap,
                (int) ($item->jumlah ?? 0),
                $keterangan !== '' ? $keterangan : $moduleName,
            ];
        })->all();

        return $this->downloadRabSpreadsheet(
            $title ?: 'REKAP RAB',
            $rows,
            $data->sum(fn ($item) => (int) ($item->jumlah ?? 0)),
            $this->excelExportFilename($title ?: 'Rekap RAB')
        );
    }

    public function exportRekapan(Request $request)
    {
        $data = $this->rabExportRows($request);
        $period = $this->requestExportPeriodLabel($request);
        $title = trim('REKAPAN RAB '.$period);
        $rows = $data->values()->map(function ($item, $index) {
            $totalRab = (int) ($item->jumlah ?? 0);
            $totalLpj = (int) ($item->total_lpj ?? 0);
            $keterangan = trim((string) ($item->keterangan ?: ''));
            $moduleName = trim((string) ($item->module_name ?: ''));

            return [
                $index + 1,
                $item->nama ?: '-',
                $item->tanggal_rekap,
                $item->tanggal_pencairan,
                $totalRab,
                $totalLpj,
                $totalRab - $totalLpj,
                '',
                $keterangan !== '' ? $keterangan : $moduleName,
            ];
        })->all();

        return $this->downloadRekapanSpreadsheet(
            $title ?: 'REKAPAN RAB',
            $rows,
            [
                'rab' => $data->sum(fn ($item) => (int) ($item->jumlah ?? 0)),
                'laporan' => $data->sum(fn ($item) => (int) ($item->total_lpj ?? 0)),
            ],
            $this->excelExportFilename($title ?: 'Rekapan RAB')
        );
    }

    public function kas(Request $request)
    {

        $rows = collect(self::SOURCES)
            ->map(function (array $source, string $moduleKey) use ($request) {
                if ($request->filled('module_key') && $request->module_key !== $moduleKey) {
                    return null;
                }

                $rabQuery = DB::query()->fromSub($this->rekapSourceQuery($moduleKey, $source, $request), 'rab');
                $this->applyFilters($rabQuery, $request);

                $totalRab = (int) (clone $rabQuery)->sum('jumlah');
                $lpjStats = $this->lpjStatsForRabQuery($rabQuery, $source, $moduleKey, $request);
                $manual = $this->manualKasSummary($request, $moduleKey);

                return [
                    'module_key' => $moduleKey,
                    'module_name' => $source['module_name'],
                    'total_rab' => $totalRab,
                    'total_lpj' => $lpjStats['total_lpj'],
                    'jumlah_lpj' => $lpjStats['jumlah_lpj'],
                    'manual_masuk' => $manual['masuk'],
                    'manual_keluar' => $manual['keluar'],
                    'saldo_kas' => $totalRab - $lpjStats['total_lpj'] + $manual['masuk'] - $manual['keluar'],
                ];
            })
            ->filter()
            ->values();

        $totals = [
            'total_rab' => (int) $rows->sum('total_rab'),
            'total_lpj' => (int) $rows->sum('total_lpj'),
            'manual_masuk' => (int) $rows->sum('manual_masuk'),
            'manual_keluar' => (int) $rows->sum('manual_keluar'),
            'saldo_kas' => (int) $rows->sum('saldo_kas'),
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'summary' => $rows,
                'totals' => $totals,
                'manual' => $this->manualKasRows($request),
                'petugas' => $this->selectedPetugas($request),
            ],
            'filters' => [
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'Kas RAB retrieved successfully',
        ]);
    }

    public function storeKasManual(Request $request)
    {
        $this->forceOwnPetugasForBarokah($request);

        $validator = Validator::make($request->all(), [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tipe' => ['required', Rule::in(['masuk', 'keluar'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if (! $this->petugasInActiveScope((int) $validator->validated()['petugas_id'])) {
            return response()->json([
                'status' => false,
                'message' => [
                    'petugas_id' => ['Petugas tidak sesuai scope navbar aktif.'],
                ],
            ], 422);
        }

        $data = DB::table('keuangan_pengeluaran_saldo')->insertGetId([
            ...$validator->validated(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $data,
            ],
            'message' => 'Kas manual berhasil ditambahkan.',
        ], 201);
    }

    public function updateKasManual(Request $request, $id)
    {
        $this->forceOwnPetugasForBarokah($request);

        $validator = Validator::make($request->all(), [
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in(array_keys(self::SOURCES))],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tipe' => ['required', Rule::in(['masuk', 'keluar'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if (! $this->petugasInActiveScope((int) $validator->validated()['petugas_id'])) {
            return response()->json([
                'status' => false,
                'message' => [
                    'petugas_id' => ['Petugas tidak sesuai scope navbar aktif.'],
                ],
            ], 422);
        }

        $query = DB::table('keuangan_pengeluaran_saldo')->where('id', $id);
        Helper::applyRelatedGenderScope(
            $query,
            'keuangan_pengeluaran_saldo.petugas_id',
            'users'
        );

        if ($this->shouldForceOwnPetugas()) {
            $query->where('petugas_id', auth()->id());
        }

        $updated = $query->update([
            ...$validator->validated(),
            'updated_at' => now(),
        ]);

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Kas manual not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kas manual berhasil diperbarui.',
        ]);
    }

    public function destroyKasManual($id)
    {
        $query = DB::table('keuangan_pengeluaran_saldo')->where('id', $id);
        Helper::applyRelatedGenderScope(
            $query,
            'keuangan_pengeluaran_saldo.petugas_id',
            'users'
        );

        if ($this->shouldForceOwnPetugas()) {
            $query->where('petugas_id', auth()->id());
        }

        $deleted = $query->delete();

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Kas manual not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kas manual berhasil dihapus.',
        ]);
    }

    private function prosesRabExportRows(int $cetakRabId, Request $request)
    {
        $data = DB::table('keuangan_cetak_rab_detail as detail')
            ->joinSub($this->filteredRekapQuery($request), 'rab', function ($join) {
                $join->on('rab.module_key', '=', 'detail.module_key')
                    ->on('rab.id', '=', 'detail.rekap_id');
            })
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->where('detail.cetak_rab_id', $cetakRabId)
            ->select('rab.*', 'petugas.name as petugas_nama', 'detail.id as detail_id')
            ->orderBy('detail.id')
            ->get();

        $data->each(function ($item) {
            $item->jumlah = (int) $item->jumlah;
            $item->jumlah_sementara = $item->jumlah_sementara === null
                ? null
                : (int) $item->jumlah_sementara;
            $item->jumlah_data = (int) $item->jumlah_data;
            $item->total_pengeluaran = (int) $item->total_pengeluaran;
            $item->total_lpj = (int) $item->total_lpj;
            $item->is_jumlah_sementara = (bool) $item->is_jumlah_sementara;
            $item->selisih_sementara = (int) $item->selisih_sementara;
            $item->cetak_rab = (bool) $item->cetak_rab;
            $item->petugas_nama = $item->petugas_nama ?? null;
        });

        return $data;
    }

    private function rabExportRows(Request $request)
    {
        $sortKey = $request->input('sort_key', 'tanggal_rekap');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumns = $this->rabSortColumns();
        $query = $this->filteredRekapQuery($request);

        $data = $query
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->select('rab.*', 'petugas.name as petugas_nama')
            ->orderBy($sortColumns[$sortKey] ?? 'tanggal_rekap', $sortOrder)
            ->orderBy('module_name')
            ->orderBy('nama')
            ->get();

        $data->each(function ($item) {
            $item->jumlah = (int) $item->jumlah;
            $item->jumlah_sementara = $item->jumlah_sementara === null
                ? null
                : (int) $item->jumlah_sementara;
            $item->jumlah_data = (int) $item->jumlah_data;
            $item->total_pengeluaran = (int) $item->total_pengeluaran;
            $item->total_lpj = (int) $item->total_lpj;
            $item->is_jumlah_sementara = (bool) $item->is_jumlah_sementara;
            $item->selisih_sementara = (int) $item->selisih_sementara;
            $item->cetak_rab = (bool) $item->cetak_rab;
            $item->petugas_nama = $item->petugas_nama ?? null;
        });

        return $data;
    }

    private function downloadRabSpreadsheet(string $title, array $rows, int $totalAmount, string $filename)
    {
        $spreadsheet = $this->rabSpreadsheet($title, $rows, $totalAmount);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function downloadRekapanSpreadsheet(string $title, array $rows, array $totals, string $filename)
    {
        $spreadsheet = $this->rekapanSpreadsheet($title, $rows, $totals);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function downloadProsesRabSpreadsheet(string $title, array $rows, int $totalAmount, string $filename)
    {
        $spreadsheet = $this->prosesRabSpreadsheet($title, $rows, $totalAmount);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function prosesRabSpreadsheet(string $title, array $rows, int $totalAmount): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAB');
        $sheet->setShowGridlines(true);

        $this->addProsesRabKopDrawing($sheet);
        $this->applyProsesRabColumnWidths($sheet);

        $headerTopRow = 20;
        $headerBottomRow = 21;
        $firstDataRow = 22;
        $rowCount = max(count($rows), 1);
        $lastDataRow = $firstDataRow + $rowCount - 1;
        $totalRow = $lastDataRow + 1;

        for ($row = 1; $row <= 19; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $sheet->mergeCells('B20:B21');
        $sheet->mergeCells('C20:G20');
        $sheet->mergeCells('C21:D21');
        $sheet->setCellValue('B20', 'NO');
        $sheet->setCellValue('C20', $title);
        $sheet->setCellValue('C21', 'NAMA ACARA');
        $sheet->setCellValue('E21', 'HARI TANGGAL');
        $sheet->setCellValue('F21', 'NOMINAL');
        $sheet->setCellValue('G21', 'KETERANGAN');

        if ($rows === []) {
            $sheet->mergeCells('C22:G22');
            $sheet->setCellValue('B22', 1);
            $sheet->setCellValue('C22', 'Tidak ada data');
            $sheet->setCellValue('F22', 0);
        } else {
            foreach ($rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $sheet->mergeCells("C{$rowNumber}:D{$rowNumber}");
                $sheet->setCellValue("B{$rowNumber}", $index + 1);
                $sheet->setCellValue("C{$rowNumber}", $rowData[1]);
                $sheet->setCellValue("E{$rowNumber}", $this->prosesRabExcelDateValue($rowData[2]));
                $sheet->setCellValue("F{$rowNumber}", $rowData[3]);
                $sheet->setCellValue("G{$rowNumber}", $rowData[4]);
                $sheet->getRowDimension($rowNumber)->setRowHeight(22.95);
            }
        }

        $sheet->mergeCells("B{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("B{$totalRow}", 'TOTAL');
        $sheet->setCellValue("F{$totalRow}", $totalAmount);
        $sheet->setCellValue("G{$totalRow}", '');

        $tableRange = "B{$headerTopRow}:G{$totalRow}";
        $headerRange = "B{$headerTopRow}:G{$headerBottomRow}";
        $bodyRange = "B{$firstDataRow}:G{$totalRow}";
        $amountRange = "F{$firstDataRow}:F{$totalRow}";
        $dateRange = "E{$firstDataRow}:E{$lastDataRow}";

        $sheet->getStyle($tableRange)->getFont()->setSize(18);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle($bodyRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("B{$firstDataRow}:B{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$firstDataRow}:F{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$totalRow}:G{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:E{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($dateRange)->getNumberFormat()
            ->setFormatCode('[$-421]dd mmmm yyyy;@');
        $sheet->getStyle($amountRange)->getNumberFormat()
            ->setFormatCode('_-"Rp"* #,##0_-;_-"Rp"* -#,##0_-;_-"Rp"* "-"_-;_-@_-');

        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function addProsesRabKopDrawing(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $path = public_path('img/kop uiidalwa mantap.png');

        if (! is_file($path)) {
            return;
        }

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(62);
        $drawing->setOffsetY(12);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(1543);
        $drawing->setHeight(343);
        $drawing->setWorksheet($sheet);
    }

    private function applyProsesRabColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $widths = [
            'A' => 8.78,
            'B' => 6.22,
            'C' => 66.22,
            'D' => 23.89,
            'E' => 42,
            'F' => 32.55,
            'G' => 51.22,
            'H' => 8.78,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function prosesRabExcelDateValue($value)
    {
        if (! $value) {
            return '';
        }

        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                \Carbon\Carbon::parse($value)->toDateTime()
            );
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function rekapanSpreadsheet(string $title, array $rows, array $totals): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekapan');

        $firstDataRow = 3;
        $rowCount = max(count($rows), 1);
        $lastDataRow = $firstDataRow + $rowCount - 1;
        $totalRow = $lastDataRow + 1;

        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:I1');
        $sheet->setCellValue('A1', 'NO');
        $sheet->setCellValue('B1', $title);
        $sheet->setCellValue('B2', 'NAMA ACARA');
        $sheet->setCellValue('C2', 'TANGGAL EVENT / PERMOHONAN');
        $sheet->setCellValue('D2', 'TANGGAL PENCAIRAN');
        $sheet->setCellValue('E2', 'RAB');
        $sheet->setCellValue('F2', 'LAPORAN');
        $sheet->setCellValue('G2', 'SELISIH');
        $sheet->setCellValue('H2', 'LAMPIRAN');
        $sheet->setCellValue('I2', 'KETERANGAN');

        if ($rows === []) {
            $sheet->mergeCells('B3:I3');
            $sheet->setCellValue('A3', 1);
            $sheet->setCellValue('B3', 'Tidak ada data');
        } else {
            foreach ($rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $sheet->setCellValue("A{$rowNumber}", $index + 1);
                $sheet->setCellValue("B{$rowNumber}", $rowData[1]);
                $sheet->setCellValue("C{$rowNumber}", $this->excelDateValue($rowData[2]));
                $sheet->setCellValue("D{$rowNumber}", $this->excelDateValue($rowData[3]));
                $sheet->setCellValue("E{$rowNumber}", $rowData[4]);
                $sheet->setCellValue("F{$rowNumber}", $rowData[5]);
                $sheet->setCellValue("G{$rowNumber}", $rowData[6]);
                $sheet->setCellValue("H{$rowNumber}", '');
                $sheet->setCellValue("I{$rowNumber}", $rowData[8]);
            }
        }

        $totalRab = (int) ($totals['rab'] ?? 0);
        $totalLaporan = (int) ($totals['laporan'] ?? 0);
        $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->setCellValue("E{$totalRow}", $totalRab);
        $sheet->setCellValue("F{$totalRow}", $totalLaporan);
        $sheet->setCellValue("G{$totalRow}", $totalRab - $totalLaporan);

        $tableRange = "A1:I{$totalRow}";
        $headerRange = 'A1:I2';
        $bodyRange = "A{$firstDataRow}:I{$totalRow}";
        $dateRange = "C{$firstDataRow}:D{$lastDataRow}";
        $amountRange = "E{$firstDataRow}:G{$totalRow}";

        $sheet->getStyle($tableRange)->getFont()->setSize(14);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($bodyRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$firstDataRow}:A{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$firstDataRow}:H{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($dateRange)->getNumberFormat()
            ->setFormatCode('[$-421]dd mmmm yyyy;@');
        $sheet->getStyle($amountRange)->getNumberFormat()
            ->setFormatCode('_-"Rp"* #,##0_-;_-"Rp"* -#,##0_-;_-"Rp"* "-"_-;_-@_-');

        $widths = [
            'A' => 5.89,
            'B' => 89.78,
            'C' => 46.33,
            'D' => 31.66,
            'E' => 31.22,
            'F' => 31.22,
            'G' => 31.22,
            'H' => 31.22,
            'I' => 54.22,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A3');
        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function rabSpreadsheet(string $title, array $rows, int $totalAmount): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAB');
        $sheet->setShowGridlines(true);

        $this->addRabKopDrawing($sheet);
        $this->applyRabColumnWidths($sheet);

        $headerTopRow = 20;
        $headerBottomRow = 21;
        $firstDataRow = 22;
        $rowCount = max(count($rows), 1);
        $lastDataRow = $firstDataRow + $rowCount - 1;
        $totalRow = $lastDataRow + 1;

        for ($row = 1; $row <= 19; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(15);
        }

        $sheet->mergeCells('B20:B21');
        $sheet->mergeCells('C20:G20');
        $sheet->mergeCells('C21:D21');
        $sheet->setCellValue('B20', 'NO');
        $sheet->setCellValue('C20', $title);
        $sheet->setCellValue('C21', 'NAMA ACARA');
        $sheet->setCellValue('E21', 'HARI TANGGAL');
        $sheet->setCellValue('F21', 'NOMINAL');
        $sheet->setCellValue('G21', 'KETERANGAN');

        if ($rows === []) {
            $sheet->mergeCells('C22:G22');
            $sheet->setCellValue('B22', 1);
            $sheet->setCellValue('C22', 'Tidak ada data');
            $sheet->setCellValue('F22', 0);
        } else {
            foreach ($rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $sheet->mergeCells("C{$rowNumber}:D{$rowNumber}");
                $sheet->setCellValue("B{$rowNumber}", $index + 1);
                $sheet->setCellValue("C{$rowNumber}", $rowData[1]);
                $sheet->setCellValue("E{$rowNumber}", $this->excelDateValue($rowData[2]));
                $sheet->setCellValue("F{$rowNumber}", $rowData[3]);
                $sheet->setCellValue("G{$rowNumber}", $rowData[4]);
                $sheet->getRowDimension($rowNumber)->setRowHeight(22.95);
            }
        }

        $sheet->mergeCells("B{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("B{$totalRow}", 'TOTAL');
        $sheet->setCellValue("F{$totalRow}", $totalAmount);
        $sheet->setCellValue("G{$totalRow}", '');

        $tableRange = "B{$headerTopRow}:G{$totalRow}";
        $headerRange = "B{$headerTopRow}:G{$headerBottomRow}";
        $bodyRange = "B{$firstDataRow}:G{$totalRow}";
        $amountRange = "F{$firstDataRow}:F{$totalRow}";
        $dateRange = "E{$firstDataRow}:E{$lastDataRow}";

        $sheet->getStyle($tableRange)->getFont()->setSize(18);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle($bodyRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("B{$firstDataRow}:B{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$firstDataRow}:F{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$totalRow}:G{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$totalRow}:E{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->getColor()->setRGB('000000');
        $sheet->getStyle($dateRange)->getNumberFormat()
            ->setFormatCode('[$-421]dd mmmm yyyy;@');
        $sheet->getStyle($amountRange)->getNumberFormat()
            ->setFormatCode('_-"Rp"* #,##0_-;_-"Rp"* -#,##0_-;_-"Rp"* "-"_-;_-@_-');

        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function addRabKopDrawing(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $path = public_path('img/kop uiidalwa mantap.png');

        if (! is_file($path)) {
            return;
        }

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
        $drawing->setName('Kop UIIDalwa');
        $drawing->setDescription('Kop UIIDalwa');
        $drawing->setPath($path);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(62);
        $drawing->setOffsetY(12);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(1543);
        $drawing->setHeight(343);
        $drawing->setWorksheet($sheet);
    }

    private function applyRabColumnWidths(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $widths = [
            'A' => 8.78,
            'B' => 6.22,
            'C' => 66.22,
            'D' => 23.89,
            'E' => 42,
            'F' => 32.55,
            'G' => 51.22,
            'H' => 8.78,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function excelDateValue($value)
    {
        if (! $value) {
            return '';
        }

        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                \Carbon\Carbon::parse($value)->toDateTime()
            );
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function requestExportPeriodLabel(Request $request): string
    {
        if ($request->filled('bulan') && $request->filled('tahun')) {
            $bulan = (int) $request->bulan;
            $tahun = (int) $request->tahun;

            if ($bulan >= 1 && $bulan <= 12 && $tahun > 0) {
                return strtoupper(\Carbon\Carbon::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y'));
            }
        }

        if ($request->filled('tahun')) {
            return (string) $request->tahun;
        }

        return '';
    }

    private function excelExportFilename(string $name): string
    {
        $safeName = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return ($safeName ?: 'Export').'.xlsx';
    }

    private function fastIndex(Request $request, string $sortKey, string $sortOrder, array $sortColumns)
    {
        $filteredRekaps = $this->basicFilteredRekapQuery($request);
        $totalRekap = (int) DB::query()
            ->fromSub(clone $filteredRekaps, 'rab_count')
            ->count();
        $totalModul = (int) DB::query()
            ->fromSub(clone $filteredRekaps, 'rab_modules')
            ->distinct()
            ->count('module_key');

        $pageQuery = clone $filteredRekaps;
        $pageQuery
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->select('rab.*', 'petugas.name as petugas_nama')
            ->orderBy($sortColumns[$sortKey] ?? 'bulan_tahun', $sortOrder)
            ->orderBy('module_name')
            ->orderBy('nama');

        $data = $this->paginate($pageQuery, $request, $totalRekap);
        $this->hydrateRabPageSummaries($data->getCollection(), $request);

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => [
                'partial' => true,
                'total_rekap' => $totalRekap,
                'total_data' => 0,
                'total_anggaran' => 0,
                'total_lpj' => 0,
                'selisih' => 0,
                'total_modul' => $totalModul,
            ],
            'filters' => [
                'years' => $this->yearOptions(),
                'modules' => $this->moduleOptions(),
            ],
            'message' => 'RAB retrieved successfully',
        ]);
    }

    private function rabStats(Request $request): array
    {
        $filteredRekaps = $this->filteredRekapQuery($request);
        $rekapStats = DB::query()
            ->fromSub(clone $filteredRekaps, 'rab')
            ->selectRaw(
                'COUNT(*) as total_rekap,
                COUNT(DISTINCT module_key) as total_modul,
                COALESCE(SUM(jumlah), 0) as total_anggaran,
                COALESCE(SUM(total_lpj), 0) as total_lpj'
            )
            ->first();
        $detailStats = $this->detailStats($request);
        $totalAnggaran = (int) ($rekapStats->total_anggaran ?? 0);
        $totalLpj = (int) ($rekapStats->total_lpj ?? 0);

        return [
            'total_rekap' => (int) ($rekapStats->total_rekap ?? 0),
            'total_data' => $detailStats['total_data'],
            'total_anggaran' => $totalAnggaran,
            'total_lpj' => $totalLpj,
            'selisih' => $totalAnggaran - $totalLpj,
            'total_modul' => (int) ($rekapStats->total_modul ?? 0),
        ];
    }

    private function basicFilteredRekapQuery(Request $request): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $baseRekap = $this->filteredSourceRekaps($request, $moduleKey, $source);

            if (! $baseRekap) {
                continue;
            }

            $queries[] = $this->basicRekapSourceQuery($moduleKey, $source, $baseRekap);
        }

        return DB::query()->fromSub($this->unionAll($queries), 'rab');
    }

    private function basicRekapSourceQuery(string $moduleKey, array $source, Builder $baseRekap): Builder
    {
        return DB::query()
            ->fromSub($baseRekap, 'rekap')
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.tanggal_pencairan',
                'rekap.cetak_rab',
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                DB::raw('COALESCE(rekap.jumlah_sementara, 0) as jumlah'),
                DB::raw('0 as total_lpj'),
                'rekap.keterangan',
                'rekap.created_at',
                DB::raw("CONCAT('{$moduleKey}:', rekap.id) as row_key"),
                DB::raw("'{$moduleKey}' as module_key"),
                DB::raw("'{$source['module_name']}' as module_name"),
                DB::raw("'{$source['detail_path']}' as detail_path"),
                DB::raw('0 as jumlah_data'),
                DB::raw('0 as total_pengeluaran'),
                DB::raw('1 as is_jumlah_sementara'),
                DB::raw('0 as selisih_sementara'),
            ]);
    }

    private function hydrateRabPageSummaries($items, Request $request): void
    {
        $items
            ->groupBy('module_key')
            ->each(function ($moduleItems, string $moduleKey) use ($request) {
                $source = self::SOURCES[$moduleKey] ?? null;

                if (! $source) {
                    return;
                }

                $ids = $moduleItems
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $summaries = $this->detailSummariesForRekapIds($source, $ids, $request);
                $lpjStatuses = $this->lpjStatusesForRekapIds($moduleKey, $ids);

                $moduleItems->each(function ($item) use ($summaries, $lpjStatuses) {
                    $summary = $summaries->get((int) $item->id);
                    $jumlahData = (int) ($summary->jumlah_data ?? 0);
                    $totalPengeluaran = (int) ($summary->total_pengeluaran ?? 0);
                    $jumlahSementara = $item->jumlah_sementara === null
                        ? null
                        : (int) $item->jumlah_sementara;
                    $jumlah = $jumlahData > 0
                        ? $totalPengeluaran
                        : (int) ($jumlahSementara ?? 0);
                    $lpjStatus = $lpjStatuses->get((int) $item->id);
                    $sameAsRab = (bool) ($lpjStatus->sama_dengan_rab ?? false);

                    $item->jumlah = $jumlah;
                    $item->jumlah_sementara = $jumlahSementara;
                    $item->jumlah_data = $jumlahData;
                    $item->total_pengeluaran = $totalPengeluaran;
                    $item->total_lpj = $sameAsRab
                        ? (int) (($lpjStatus->total_lpj ?? 0) ?: $jumlah)
                        : ($lpjStatus ? (int) ($lpjStatus->total_lpj ?? 0) : 0);
                    $item->is_jumlah_sementara = $jumlahData === 0;
                    $item->selisih_sementara = $jumlahSementara !== null && $jumlahSementara > $totalPengeluaran
                        ? $jumlahSementara - $totalPengeluaran
                        : 0;
                    $item->cetak_rab = (bool) $item->cetak_rab;
                    $item->petugas_nama = $item->petugas_nama ?? null;
                });
            });
    }

    private function detailSummariesForRekapIds(array $source, array $ids, Request $request)
    {
        if ($ids === []) {
            return collect();
        }

        $query = DB::table("{$source['detail_table']} as detail")
            ->whereIntegerInRaw('detail.rekap_id', $ids)
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_pengeluaran'),
            ])
            ->groupBy('detail.rekap_id');
        $this->applyDetailGenderScope($query, $source['detail_table'], 'detail');

        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn($source['detail_table'], 'petugas_id')
        ) {
            $query->where('detail.petugas_id', $request->petugas_id);
        }

        return $query
            ->get()
            ->keyBy(fn ($item) => (int) $item->rekap_id);
    }

    private function lpjStatusesForRekapIds(string $moduleKey, array $ids)
    {
        if ($ids === [] || ! Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            return collect();
        }

        return DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', $moduleKey)
            ->whereIntegerInRaw('rekap_id', $ids)
            ->get()
            ->keyBy(fn ($item) => (int) $item->rekap_id);
    }

    private function hasRabFilters(Request $request): bool
    {
        return $request->filled('search')
            || $request->filled('bulan')
            || $request->filled('tahun')
            || $request->filled('module_key')
            || $request->filled('petugas_id');
    }

    private function sortRequiresRabSummary(string $sortKey): bool
    {
        return in_array($sortKey, [
            'jumlah',
            'total_lpj',
            'jumlah_data',
            'total_pengeluaran',
        ], true);
    }

    private function rabSortColumns(): array
    {
        return [
            'nama' => 'nama',
            'bulan_tahun' => 'bulan_tahun',
            'tanggal_rekap' => 'tanggal_rekap',
            'tanggal_pencairan' => 'tanggal_pencairan',
            'jumlah' => 'jumlah',
            'total_lpj' => 'total_lpj',
            'jumlah_data' => 'jumlah_data',
            'total_pengeluaran' => 'total_pengeluaran',
            'module_name' => 'module_name',
            'created_at' => 'created_at',
        ];
    }

    private function filteredRekapQuery(Request $request): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $baseRekap = $this->filteredSourceRekaps($request, $moduleKey, $source);

            if (! $baseRekap) {
                continue;
            }

            $queries[] = $this->rekapSourceQuery($moduleKey, $source, $request, $baseRekap);
        }

        return DB::query()->fromSub($this->unionAll($queries), 'rab');
    }

    private function rekapUnionQuery(?Request $request = null): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $queries[] = $this->rekapSourceQuery($moduleKey, $source, $request);
        }

        return $this->unionAll($queries);
    }

    private function rekapSourceQuery(
        string $moduleKey,
        array $source,
        ?Request $request = null,
        ?Builder $baseRekap = null
    ): Builder
    {
        $rekap = $baseRekap ?? DB::table("{$source['rekap_table']} as rekap")
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.tanggal_pencairan',
                $this->rekapCetakRabSelect($source),
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                'rekap.keterangan',
                'rekap.created_at',
            ]);
        $summary = $this->detailSummaryQuery($source, $request, $baseRekap);
        $effectiveAmount = 'CASE
            WHEN COALESCE(summary.jumlah_data, 0) > 0
                THEN COALESCE(summary.total_pengeluaran, 0)
            ELSE COALESCE(rekap.jumlah_sementara, 0)
        END';
        $lpjAmount = "CASE
            WHEN COALESCE(lpj_status.sama_dengan_rab, 0) = 1
                THEN COALESCE(NULLIF(lpj_status.total_lpj, 0), {$effectiveAmount})
            WHEN lpj_status.id IS NOT NULL
                THEN COALESCE(lpj_status.total_lpj, 0)
            ELSE 0
        END";
        $temporaryDifference = 'CASE
            WHEN rekap.jumlah_sementara IS NOT NULL
                AND rekap.jumlah_sementara > COALESCE(summary.total_pengeluaran, 0)
                THEN rekap.jumlah_sementara - COALESCE(summary.total_pengeluaran, 0)
            ELSE 0
        END';

        return DB::query()
            ->fromSub($rekap, 'rekap')
            ->leftJoinSub($summary, 'summary', 'summary.rekap_id', '=', 'rekap.id')
            ->leftJoin('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($moduleKey) {
                $join->on('lpj_status.rekap_id', '=', 'rekap.id')
                    ->where('lpj_status.module_key', '=', $moduleKey);
            })
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.tanggal_pencairan',
                'rekap.cetak_rab',
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                DB::raw("{$effectiveAmount} as jumlah"),
                DB::raw("{$lpjAmount} as total_lpj"),
                'rekap.keterangan',
                'rekap.created_at',
                DB::raw("CONCAT('{$moduleKey}:', rekap.id) as row_key"),
                DB::raw("'{$moduleKey}' as module_key"),
                DB::raw("'{$source['module_name']}' as module_name"),
                DB::raw("'{$source['detail_path']}' as detail_path"),
                DB::raw('COALESCE(summary.jumlah_data, 0) as jumlah_data'),
                DB::raw('COALESCE(summary.total_pengeluaran, 0) as total_pengeluaran'),
                DB::raw('CASE WHEN COALESCE(summary.jumlah_data, 0) = 0 THEN 1 ELSE 0 END as is_jumlah_sementara'),
                DB::raw("{$temporaryDifference} as selisih_sementara"),
            ]);
    }

    private function detailSummaryQuery(
        array $source,
        ?Request $request = null,
        ?Builder $baseRekap = null
    ): Builder
    {
        if ($baseRekap) {
            $rekapIds = clone $baseRekap;
            $rekapIds->select('id');

            return DB::query()
                ->fromSub($rekapIds, 'filtered_rekap')
                ->leftJoin("{$source['detail_table']} as detail", function ($join) use ($source, $request) {
                    $join->on('detail.rekap_id', '=', 'filtered_rekap.id');

                    if ($source['pegawai_tipe']) {
                        $join->where('detail.pegawai_tipe', '=', $source['pegawai_tipe']);
                    }

                    if (
                        $request?->filled('petugas_id')
                        && Schema::hasColumn($source['detail_table'], 'petugas_id')
                    ) {
                        $join->where('detail.petugas_id', '=', $request->petugas_id);
                    }
                })
                ->select([
                    'filtered_rekap.id as rekap_id',
                    DB::raw('COUNT(detail.id) as jumlah_data'),
                    DB::raw('COALESCE(SUM(detail.total), 0) as total_pengeluaran'),
                ])
                ->groupBy('filtered_rekap.id');
        }

        $query = DB::table("{$source['detail_table']} as detail")
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_pengeluaran'),
            ])
            ->whereNotNull('detail.rekap_id')
            ->groupBy('detail.rekap_id');
        $this->applyDetailGenderScope($query, $source['detail_table'], 'detail');

        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        if (
            $request?->filled('petugas_id')
            && Schema::hasColumn($source['detail_table'], 'petugas_id')
        ) {
            $query->where('detail.petugas_id', $request->petugas_id);
        }

        return $query;
    }

    private function lpjSummaryQuery(array $source, ?Request $request = null): Builder
    {
        $query = DB::table("{$source['lpj_table']} as detail")
            ->select([
                'detail.rekap_id',
                DB::raw('COUNT(*) as jumlah_data'),
                DB::raw('COALESCE(SUM(detail.total), 0) as total_lpj'),
            ])
            ->whereNotNull('detail.rekap_id')
            ->groupBy('detail.rekap_id');
        $this->applyDetailGenderScope($query, $source['lpj_table'], 'detail');

        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        if (
            $request?->filled('petugas_id')
            && Schema::hasColumn($source['lpj_table'], 'petugas_id')
        ) {
            $query->where('detail.petugas_id', $request->petugas_id);
        }

        return $query;
    }

    private function lpjStatsForRabQuery(Builder $rabQuery, array $source, string $moduleKey, Request $request): array
    {
        $sameAsRabAmount = $request->filled('petugas_id')
            ? 'rab_filtered.jumlah'
            : 'COALESCE(NULLIF(lpj_status.total_lpj, 0), rab_filtered.jumlah)';

        $stats = DB::query()
            ->fromSub(clone $rabQuery, 'rab_filtered')
            ->leftJoinSub($this->lpjSummaryQuery($source, $request), 'lpj_summary', 'lpj_summary.rekap_id', '=', 'rab_filtered.id')
            ->leftJoin('keuangan_pengeluaran_lpj_rekap_status as lpj_status', function ($join) use ($moduleKey) {
                $join->on('lpj_status.rekap_id', '=', 'rab_filtered.id')
                    ->where('lpj_status.module_key', '=', $moduleKey);
            })
            ->selectRaw(
                "COALESCE(SUM(
                    CASE
                        WHEN COALESCE(lpj_summary.jumlah_data, 0) > 0
                            THEN COALESCE(lpj_summary.total_lpj, 0)
                        WHEN COALESCE(lpj_status.sama_dengan_rab, 0) = 1
                            THEN {$sameAsRabAmount}
                        ELSE 0
                    END
                ), 0) as total_lpj,
                COALESCE(SUM(COALESCE(lpj_summary.jumlah_data, 0)), 0) as jumlah_lpj"
            )
            ->first();

        return [
            'total_lpj' => (int) ($stats->total_lpj ?? 0),
            'jumlah_lpj' => (int) ($stats->jumlah_lpj ?? 0),
        ];
    }

    private function manualKasSummary(Request $request, string $moduleKey): array
    {
        $query = DB::table('keuangan_pengeluaran_saldo')
            ->where('module_key', $moduleKey);
        Helper::applyRelatedGenderScope(
            $query,
            'keuangan_pengeluaran_saldo.petugas_id',
            'users'
        );

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn('keuangan_pengeluaran_saldo', 'petugas_id')
        ) {
            $query->where('petugas_id', $request->petugas_id);
        }

        $this->applyPeriodFilter($query, $request, 'tanggal');

        $summary = $query
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE 0 END), 0) as masuk,
                COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN nominal ELSE 0 END), 0) as keluar"
            )
            ->first();

        return [
            'masuk' => (int) ($summary->masuk ?? 0),
            'keluar' => (int) ($summary->keluar ?? 0),
        ];
    }

    private function manualKasRows(Request $request)
    {
        $query = DB::table('keuangan_pengeluaran_saldo as kas')
            ->leftJoin('users as petugas', 'petugas.id', '=', 'kas.petugas_id')
            ->select([
                'kas.*',
                'petugas.name as petugas_nama',
            ]);
        Helper::applyGenderScope($query, 'petugas.jenis_kelamin');

        if ($request->filled('module_key')) {
            $query->where('kas.module_key', $request->module_key);
        }

        if (
            $request->filled('petugas_id')
            && Schema::hasColumn('keuangan_pengeluaran_saldo', 'petugas_id')
        ) {
            $query->where('kas.petugas_id', $request->petugas_id);
        }

        $this->applyPeriodFilter($query, $request, 'kas.tanggal');

        return $query
            ->orderByDesc('kas.tanggal')
            ->orderByDesc('kas.id')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $item->module_name = self::SOURCES[$item->module_key]['module_name'] ?? $item->module_key;
                $item->id = (int) $item->id;
                $item->petugas_id = $item->petugas_id === null ? null : (int) $item->petugas_id;
                $item->nominal = (int) $item->nominal;

                return $item;
            });
    }

    private function detailStats(Request $request): array
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $filteredSource = $this->filteredSourceRekaps($request, $moduleKey, $source);

            if (! $filteredSource) {
                continue;
            }

            $query = DB::query()
                ->fromSub($filteredSource->select('id'), 'rekap')
                ->leftJoin("{$source['detail_table']} as detail", function ($join) use ($source) {
                    $join->on('detail.rekap_id', '=', 'rekap.id');

                    if ($source['pegawai_tipe']) {
                        $join->where('detail.pegawai_tipe', '=', $source['pegawai_tipe']);
                    }
                });

            if (
                $request->filled('petugas_id')
                && Schema::hasColumn($source['detail_table'], 'petugas_id')
            ) {
                $query->where('detail.petugas_id', $request->petugas_id);
            }

            $queries[] = $query->selectRaw(
                'COUNT(detail.id) as total_data,
                COALESCE(SUM(detail.total), 0) as total_anggaran'
            );
        }

        if ($queries === []) {
            return [
                'total_data' => 0,
                'total_realisasi' => 0,
            ];
        }

        $stats = DB::query()
            ->fromSub($this->unionAll($queries), 'stats')
            ->selectRaw(
                'COALESCE(SUM(total_data), 0) as total_data,
                COALESCE(SUM(total_anggaran), 0) as total_realisasi'
            )
            ->first();

        return [
            'total_data' => (int) ($stats->total_data ?? 0),
            'total_realisasi' => (int) ($stats->total_realisasi ?? 0),
        ];
    }

    private function filteredSourceRekaps(
        Request $request,
        string $moduleKey,
        array $source
    ): ?Builder {
        if ($request->filled('module_key') && $request->module_key !== $moduleKey) {
            return null;
        }

        $query = DB::table("{$source['rekap_table']} as rekap")
            ->select([
                'rekap.id',
                'rekap.nama',
                'rekap.bulan_tahun',
                'rekap.tanggal_rekap',
                'rekap.tanggal_pencairan',
                $this->rekapCetakRabSelect($source),
                'rekap.jumlah_sementara',
                'rekap.petugas_id',
                'rekap.keterangan',
                'rekap.created_at',
            ]);
        Helper::applyRelatedGenderScope(
            $query,
            'rekap.petugas_id',
            'users'
        );

        $this->applyPeriodFilter($query, $request, 'rekap.bulan_tahun');

        if ($request->filled('petugas_id')) {
            $query->where('rekap.petugas_id', $request->petugas_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            if (stripos($source['module_name'], $search) === false) {
                $query->where(function (Builder $filter) use ($search) {
                    $filter->where('rekap.nama', 'LIKE', "%{$search}%")
                        ->orWhere('rekap.keterangan', 'LIKE', "%{$search}%");
                });
            }
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $this->applyPeriodFilter($query, $request, 'bulan_tahun');

        if ($request->filled('module_key')) {
            $query->where('module_key', $request->module_key);
        }

        if ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->petugas_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('keterangan', 'LIKE', "%{$search}%")
                    ->orWhere('module_name', 'LIKE', "%{$search}%");
            });
        }
    }

    private function applyProsesRabFilters(Builder $query, Request $request): void
    {
        $bulan = $request->filled('proses_bulan') ? (int) $request->proses_bulan : null;
        $tahun = $request->filled('proses_tahun') ? (int) $request->proses_tahun : null;

        if ($tahun && $bulan >= 1 && $bulan <= 12) {
            $start = sprintf('%04d-%02d-01', $tahun, $bulan);
            $end = date('Y-m-d', strtotime("{$start} +1 month"));
            $query->where('cetak.tanggal_cetak', '>=', $start)
                ->where('cetak.tanggal_cetak', '<', $end);
        } elseif ($tahun) {
            $query->where('cetak.tanggal_cetak', '>=', "{$tahun}-01-01")
                ->where('cetak.tanggal_cetak', '<', ($tahun + 1).'-01-01');
        } elseif ($bulan >= 1 && $bulan <= 12) {
            $query->whereMonth('cetak.tanggal_cetak', $bulan);
        }

        $search = trim((string) $request->input('proses_search', ''));

        if ($search === '') {
            return;
        }

        $searchRequest = Request::create('/', 'GET', [
            'search' => $search,
        ]);

        $matchingRekaps = $this->filteredRekapQuery($searchRequest);

        $query->where(function (Builder $filter) use ($search, $matchingRekaps) {
            $filter->where('cetak.keterangan', 'LIKE', "%{$search}%")
                ->orWhereExists(function ($exists) use ($matchingRekaps) {
                    $exists->selectRaw('1')
                        ->from('keuangan_cetak_rab_detail as search_detail')
                        ->joinSub($matchingRekaps, 'search_rab', function ($join) {
                            $join->on('search_rab.module_key', '=', 'search_detail.module_key')
                                ->on('search_rab.id', '=', 'search_detail.rekap_id');
                        })
                        ->whereColumn('search_detail.cetak_rab_id', 'cetak.id');
                });
        });
    }

    private function applyPeriodFilter(Builder $query, Request $request, string $column): void
    {
        $bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        if ($tahun && $bulan >= 1 && $bulan <= 12) {
            $start = sprintf('%04d-%02d-01', $tahun, $bulan);
            $end = date('Y-m-d', strtotime("{$start} +1 month"));
            $query->where($column, '>=', $start)
                ->where($column, '<', $end);
        } elseif ($tahun) {
            $query->where($column, '>=', "{$tahun}-01-01")
                ->where($column, '<', ($tahun + 1).'-01-01');
        } elseif ($bulan >= 1 && $bulan <= 12) {
            $query->whereMonth($column, $bulan);
        }
    }

    private function paginate(
        Builder $query,
        Request $request,
        int $total
    ): LengthAwarePaginator {
        $perPage = max(1, (int) $request->input('limit', 10));
        $page = max(1, (int) $request->input('page', 1));
        $items = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function yearOptions()
    {
        $queries = [];

        foreach (self::SOURCES as $source) {
            $query = DB::table($source['rekap_table'])
                ->whereNotNull('bulan_tahun')
                ->selectRaw('YEAR(bulan_tahun) as tahun');
            Helper::applyRelatedGenderScope(
                $query,
                "{$source['rekap_table']}.petugas_id",
                'users'
            );
            $queries[] = $query;
        }

        return DB::query()
            ->fromSub($this->unionAll($queries), 'years')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn ($year) => (int) $year)
            ->values();
    }

    private function unionAll(array $queries): Builder
    {
        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function forceOwnPetugasForBarokah(Request $request): void
    {
        if ($this->shouldForceOwnPetugas()) {
            $request->merge([
                'petugas_id' => auth()->id(),
            ]);
        }
    }

    private function shouldForceOwnPetugas(): bool
    {
        $roleName = strtolower((string) (auth()->user()?->role?->name ?? ''));

        return in_array($roleName, [
            'barokahdosen_tatapmuka',
            'barokahdosen_kegiatan',
            'barokahdosen_bulanan',
            'rumahtangga',
            'sarpras',
            'transportasi',
        ], true);
    }

    private function selectedPetugas(Request $request): ?object
    {
        if (! $request->filled('petugas_id')) {
            return null;
        }

        $petugas = DB::table('users')
            ->leftJoin('role', 'role.id', '=', 'users.role_id')
            ->where('users.id', $request->petugas_id);
        Helper::applyGenderScope($petugas, 'users.jenis_kelamin');
        $petugas = $petugas->first([
                'users.id',
                'users.name',
                'role.name as role_name',
            ]);

        if (! $petugas) {
            return null;
        }

        $petugas->id = (int) $petugas->id;

        return $petugas;
    }

    private function petugasAllowedForModule(int $petugasId, string $moduleKey): bool
    {
        $roleName = DB::table('users')
            ->leftJoin('role', 'role.id', '=', 'users.role_id')
            ->where('users.id', $petugasId);
        Helper::applyGenderScope($roleName, 'users.jenis_kelamin');
        $roleName = $roleName->value('role.name');

        if (! $roleName) {
            return false;
        }

        return in_array($roleName, $this->petugasRolesForModule($moduleKey), true);
    }

    private function petugasInActiveScope(int $petugasId): bool
    {
        $query = DB::table('users')->where('id', $petugasId);
        Helper::applyGenderScope($query, 'users.jenis_kelamin');

        return $query->exists();
    }

    private function rekapCetakRabSelect(array $source)
    {
        return $this->rekapTableHasColumn($source['rekap_table'], 'cetak_rab')
            ? 'rekap.cetak_rab'
            : DB::raw('0 as cetak_rab');
    }

    private function rekapHasCetakRabColumn(string $moduleKey): bool
    {
        $table = self::SOURCES[$moduleKey]['rekap_table'] ?? null;

        return $table ? $this->rekapTableHasColumn($table, 'cetak_rab') : false;
    }

    private function rekapTableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }

    private function scopedRekapQuery(string $moduleKey, int $rekapId): Builder
    {
        $table = self::SOURCES[$moduleKey]['rekap_table'];
        $query = DB::table($table)->where("{$table}.id", $rekapId);

        Helper::applyRelatedGenderScope(
            $query,
            "{$table}.petugas_id",
            'users'
        );

        if ($this->shouldForceOwnPetugas()) {
            $roleName = strtolower((string) (auth()->user()?->role?->name ?? ''));

            if (! in_array($roleName, $this->petugasRolesForModule($moduleKey), true)) {
                $query->whereRaw('1 = 0');
            }

            $query->where("{$table}.petugas_id", auth()->id());
        }

        return $query;
    }

    private function applyDetailGenderScope($query, string $table, string $alias): void
    {
        Helper::applyExpenseGenderScope($query, $table, $alias);
    }

    private function petugasRolesForModule(string $moduleKey): array
    {
        return match ($moduleKey) {
            'rumah_tangga' => ['rumahtangga'],
            'sarana_prasarana' => ['sarpras'],
            'transportasi' => ['transportasi'],
            'umum' => Helper::pengeluaranPetugasRoles('umum'),
            'tatap_muka' => ['barokahdosen_tatapmuka'],
            'kegiatan' => ['barokahdosen_kegiatan'],
            'dosen_bulanan' => ['barokahdosen_bulanan'],
            default => [],
        };
    }

    private function moduleOptions(): array
    {
        return [
            ['title' => 'Dosen Tatap Muka', 'value' => 'tatap_muka'],
            ['title' => 'Pegawai Kegiatan', 'value' => 'kegiatan'],
            ['title' => 'Rumah Tangga', 'value' => 'rumah_tangga'],
            ['title' => 'Sarana Prasarana', 'value' => 'sarana_prasarana'],
            ['title' => 'Transportasi', 'value' => 'transportasi'],
            ['title' => 'Pengeluaran Umum', 'value' => 'umum'],
            ['title' => 'Bulanan', 'value' => 'dosen_bulanan'],
        ];
    }
}
