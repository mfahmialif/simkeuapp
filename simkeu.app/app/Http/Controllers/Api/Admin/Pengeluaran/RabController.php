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
    private const PIUTANG_MODULE_KEY = 'piutang';
    private const PIUTANG_MODULE_NAME = 'Cashbon';

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
        'absensi' => [
            'rekap_table' => 'keuangan_pengeluaran_absensi_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_absensi',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_absensi_lpj',
            'module_name' => 'Barokah Absensi',
            'detail_path' => '/admin/pengeluaran/absensi/rekap/',
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

    public function update(Request $request, string $moduleKey, $id)
    {
        if (! array_key_exists($moduleKey, self::SOURCES)) {
            return response()->json([
                'status' => false,
                'message' => 'Jenis rekap tidak valid.',
            ], 422);
        }

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
        $targetModuleKey = $validated['module_key'];
        $id = (int) $id;

        if (! $this->petugasAllowedForModule((int) $validated['petugas_id'], $targetModuleKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Petugas tidak sesuai dengan jenis rekap yang dipilih.',
            ], 422);
        }

        if (! $this->scopedRekapQuery($moduleKey, $id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap tidak ditemukan atau tidak dapat diakses.',
            ], 404);
        }

        $source = self::SOURCES[$moduleKey];
        $targetSource = self::SOURCES[$targetModuleKey];
        $isMovingModule = $moduleKey !== $targetModuleKey;
        $usage = $this->rekapUsageSummary($moduleKey, $id);

        if ($isMovingModule && (
            $usage['detail_count'] > 0
            || $usage['lpj_count'] > 0
            || $usage['lpj_status_count'] > 0
            || $usage['process_count'] > 0
            || $usage['cetak_rab']
        )) {
            return response()->json([
                'status' => false,
                'message' => [
                    'module_key' => [
                        'Jenis rekap hanya bisa diubah jika rekap belum memiliki data RAB, LPJ, atau proses RAB.',
                    ],
                ],
            ], 422);
        }

        $nameExists = DB::table($targetSource['rekap_table'])
            ->where('nama', $validated['nama'])
            ->when(
                ! $isMovingModule,
                fn ($query) => $query->where('id', '<>', $id)
            )
            ->exists();

        if ($nameExists) {
            return response()->json([
                'status' => false,
                'message' => [
                    'nama' => ['Nama rekap sudah digunakan pada jenis rekap ini.'],
                ],
            ], 422);
        }

        $newId = $id;
        $updated = false;

        DB::transaction(function () use (
            $id,
            $moduleKey,
            $targetModuleKey,
            $source,
            $targetSource,
            $validated,
            $isMovingModule,
            &$newId,
            &$updated
        ) {
            $oldRekap = $this->scopedRekapQuery($moduleKey, $id)
                ->lockForUpdate()
                ->first();

            if (! $oldRekap) {
                return;
            }

            $currentUsage = $this->rekapUsageSummary($moduleKey, $id);

            if ($isMovingModule) {
                if (
                    $currentUsage['detail_count'] > 0
                    || $currentUsage['lpj_count'] > 0
                    || $currentUsage['lpj_status_count'] > 0
                    || $currentUsage['process_count'] > 0
                    || $currentUsage['cetak_rab']
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'module_key' => [
                            'Jenis rekap hanya bisa diubah jika rekap belum memiliki data RAB, LPJ, atau proses RAB.',
                        ],
                    ]);
                }
            }

            $now = now();
            $payload = [
                'nama' => $validated['nama'],
                'bulan_tahun' => $validated['bulan_tahun'].'-01',
                'tanggal_rekap' => $validated['tanggal_rekap'],
                'tanggal_pencairan' => $validated['tanggal_pencairan'] ?? null,
                'petugas_id' => (int) $validated['petugas_id'],
                'keterangan' => $validated['keterangan'] ?? null,
                'updated_at' => $now,
            ];

            if (! $isMovingModule && $currentUsage['detail_count'] > 0) {
                DB::table($source['rekap_table'])
                    ->where('id', $id)
                    ->update($payload);

                $updated = true;

                return;
            }

            $payload['jumlah_sementara'] = (int) $validated['jumlah_sementara'];

            if (! $isMovingModule) {
                DB::table($source['rekap_table'])
                    ->where('id', $id)
                    ->update($payload);

                $updated = true;

                return;
            }

            $insertPayload = [
                ...$payload,
                'created_at' => $oldRekap->created_at ?? $now,
            ];

            if ($this->rekapHasCetakRabColumn($targetModuleKey)) {
                $insertPayload['cetak_rab'] = false;
            }

            $newId = DB::table($targetSource['rekap_table'])->insertGetId($insertPayload);

            DB::table($source['rekap_table'])
                ->where('id', $id)
                ->delete();

            $updated = true;
        });

        if (! $updated) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap tidak ditemukan atau tidak dapat diakses.',
            ], 404);
        }

        $data = $this->findRabRow($targetModuleKey, $newId);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap tidak ditemukan atau tidak dapat diakses.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => $isMovingModule
                ? 'Kategori rekap berhasil diubah.'
                : 'Rekap anggaran berhasil diperbarui.',
        ]);
    }

    public function merger(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:2', 'max:100'],
            'items.*.module_key' => ['required', 'string'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
            'nama' => ['required', 'string', 'max:255'],
            'bulan_tahun' => ['required', 'date_format:Y-m'],
            'tanggal_rekap' => ['required', 'date_format:Y-m-d'],
            'tanggal_pencairan' => ['nullable', 'date_format:Y-m-d'],
            'keterangan' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $moduleKeys = collect($validated['items'])->pluck('module_key')->unique()->values();

        if ($moduleKeys->count() > 1) {
            return response()->json([
                'status' => false,
                'message' => 'Hanya bisa merger rekap dengan jenis/kategori yang sejenis.',
            ], 422);
        }

        $moduleKey = $moduleKeys->first();

        if ($moduleKey === self::PIUTANG_MODULE_KEY || ! array_key_exists($moduleKey, self::SOURCES)) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap Cashbon atau jenis rekap tidak valid untuk dimerger.',
            ], 422);
        }

        if (! $this->petugasAllowedForModule((int) $validated['petugas_id'], $moduleKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Petugas tidak sesuai dengan jenis rekap yang dipilih.',
            ], 422);
        }

        $oldIds = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($oldIds->count() < 2) {
            return response()->json([
                'status' => false,
                'message' => 'Minimal 2 rekap yang berbeda yang dipilih untuk merger.',
            ], 422);
        }

        foreach ($oldIds as $oldId) {
            $query = $this->scopedRekapQuery($moduleKey, $oldId);
            if (! $query->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Rekap dengan ID {$oldId} tidak ditemukan atau tidak dapat diakses.",
                ], 404);
            }
        }

        $source = self::SOURCES[$moduleKey];
        $rekapTable = $source['rekap_table'];
        $detailTable = $source['detail_table'];
        $lpjTable = $source['lpj_table'];

        $nameExists = DB::table($rekapTable)
            ->where('nama', $validated['nama'])
            ->whereNotIn('id', $oldIds->all())
            ->exists();

        if ($nameExists) {
            return response()->json([
                'status' => false,
                'message' => [
                    'nama' => ['Nama rekap sudah digunakan pada jenis rekap ini.'],
                ],
            ], 422);
        }

        $newId = null;

        DB::transaction(function () use ($oldIds, $validated, $moduleKey, $rekapTable, $detailTable, $lpjTable, &$newId) {
            $now = now();

            $hasCetakRabDetail = Schema::hasTable('keuangan_cetak_rab_detail');
            $affectedCetakRabIds = collect();
            if ($hasCetakRabDetail) {
                $affectedCetakRabIds = DB::table('keuangan_cetak_rab_detail')
                    ->where('module_key', $moduleKey)
                    ->whereIn('rekap_id', $oldIds->all())
                    ->pluck('cetak_rab_id')
                    ->unique();
            }

            $detailCount = DB::table($detailTable)->whereIn('rekap_id', $oldIds->all())->count();
            $initialJumlahSementara = $detailCount > 0
                ? null
                : (int) DB::table($rekapTable)->whereIn('id', $oldIds->all())->sum('jumlah_sementara');

            $insertPayload = [
                'nama' => $validated['nama'],
                'bulan_tahun' => $validated['bulan_tahun'].'-01',
                'tanggal_rekap' => $validated['tanggal_rekap'],
                'tanggal_pencairan' => $validated['tanggal_pencairan'] ?? null,
                'jumlah_sementara' => $initialJumlahSementara,
                'petugas_id' => (int) $validated['petugas_id'],
                'keterangan' => $validated['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($this->rekapHasCetakRabColumn($moduleKey)) {
                $insertPayload['cetak_rab'] = $affectedCetakRabIds->isNotEmpty();
            }

            $newId = DB::table($rekapTable)->insertGetId($insertPayload);

            DB::table($detailTable)
                ->whereIn('rekap_id', $oldIds->all())
                ->update([
                    'rekap_id' => $newId,
                    'updated_at' => $now,
                ]);

            if (Schema::hasTable($lpjTable)) {
                DB::table($lpjTable)
                    ->whereIn('rekap_id', $oldIds->all())
                    ->update([
                        'rekap_id' => $newId,
                        'updated_at' => $now,
                    ]);
            }

            if ($hasCetakRabDetail && $affectedCetakRabIds->isNotEmpty()) {
                foreach ($affectedCetakRabIds as $cetakRabId) {
                    $existsNew = DB::table('keuangan_cetak_rab_detail')
                        ->where('cetak_rab_id', $cetakRabId)
                        ->where('module_key', $moduleKey)
                        ->where('rekap_id', $newId)
                        ->exists();

                    if (! $existsNew) {
                        DB::table('keuangan_cetak_rab_detail')->insert([
                            'cetak_rab_id' => $cetakRabId,
                            'module_key' => $moduleKey,
                            'rekap_id' => $newId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('keuangan_cetak_rab_detail')
                        ->where('cetak_rab_id', $cetakRabId)
                        ->where('module_key', $moduleKey)
                        ->whereIn('rekap_id', $oldIds->all())
                        ->delete();
                }
            }

            DB::table($rekapTable)
                ->whereIn('id', $oldIds->all())
                ->delete();
        });

        $data = DB::table("{$rekapTable} as rekap")
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rekap.petugas_id')
            ->where('rekap.id', $newId)
            ->first([
                'rekap.*',
                'petugas.name as petugas_nama',
            ]);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Rekap anggaran berhasil dimerger.',
        ], 201);
    }

    public function updateTanggalPencairan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.module_key' => ['required', Rule::in($this->rabProcessModuleKeys())],
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

        if (
            $items->contains(fn (array $item) => $this->isPiutangModule($item['module_key']))
            && ! $this->piutangTableHasColumn('tanggal_pencairan')
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Migration tanggal pencairan Cashbon belum dijalankan.',
            ], 422);
        }

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

        $rows = $this->prosesRabRows($request);

        return response()->json([
            'status' => true,
            'data' => $rows,
            'filters' => [
                'kategori_options' => $this->prosesRabKategoriOptions(),
                'kategori_history' => $this->prosesRabKategoriHistory(),
            ],
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

        if (! $this->hasCetakRabKategoriColumn()) {
            return response()->json([
                'status' => false,
                'message' => 'Migration kategori Proses RAB belum dijalankan.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_cetak' => ['required', 'date_format:Y-m-d'],
            'kategori' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.module_key' => ['required', Rule::in($this->rabProcessModuleKeys())],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $kategori = trim((string) $validated['kategori']);

        if ($kategori === '') {
            return response()->json([
                'status' => false,
                'message' => [
                    'kategori' => ['Kategori wajib diisi.'],
                ],
            ], 422);
        }

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

        DB::transaction(function () use ($items, $validated, $kategori, $keterangan, &$cetakRabId) {
            $now = now();
            $cetakRabId = DB::table('keuangan_cetak_rab')->insertGetId([
                'tanggal_cetak' => $validated['tanggal_cetak'],
                'kategori' => $kategori,
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
                'kategori' => $kategori,
                'row_keys' => $items
                    ->map(fn (array $item) => "{$item['module_key']}:{$item['id']}")
                    ->all(),
            ],
            'message' => $items->count().' rekap berhasil diproses untuk RAB.',
        ], 201);
    }

    public function showProsesRab(Request $request, $id)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        $selectColumns = [
            'id',
            'tanggal_cetak',
            'keterangan',
            'created_at',
        ];

        $selectColumns[] = $this->hasCetakRabKategoriColumn()
            ? 'kategori'
            : DB::raw('NULL as kategori');

        $cetak = DB::table('keuangan_cetak_rab')
            ->where('id', $id)
            ->first($selectColumns);

        if (! $cetak) {
            return response()->json([
                'status' => false,
                'message' => 'Proses RAB tidak ditemukan.',
            ], 404);
        }

        $data = $this->prosesRabExportRows((int) $id, $this->prosesRabRekapFilterRequest($request));
        $totalRab = $data->sum(fn ($item) => (int) ($item->jumlah ?? 0));

        return response()->json([
            'status' => true,
            'data' => [
                'proses' => [
                    'id' => (int) $cetak->id,
                    'tanggal_cetak' => $cetak->tanggal_cetak,
                    'kategori' => trim((string) ($cetak->kategori ?? '')) ?: null,
                    'keterangan' => $cetak->keterangan,
                    'jumlah_rekap' => $data->count(),
                    'total_rab' => $totalRab,
                ],
                'items' => $data->values(),
            ],
            'message' => 'Detail proses RAB retrieved successfully',
        ]);
    }

    public function appendProsesRabItems(Request $request, $id)
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

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.module_key' => ['required', Rule::in($this->rabProcessModuleKeys())],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $itemKey = fn (array $item) => "{$item['module_key']}:{$item['id']}";
        $items = collect($validator->validated()['items'])
            ->map(fn (array $item) => [
                'module_key' => $item['module_key'],
                'id' => (int) $item['id'],
            ])
            ->unique(fn (array $item) => $itemKey($item))
            ->values();

        foreach ($items as $item) {
            $query = $this->scopedRekapQuery($item['module_key'], $item['id']);

            if (! $query->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Rekap {$item['module_key']}:{$item['id']} tidak ditemukan atau tidak dapat diakses.",
                ], 404);
            }
        }

        $existingKeys = DB::table('keuangan_cetak_rab_detail')
            ->where('cetak_rab_id', $id)
            ->get(['module_key', 'rekap_id'])
            ->mapWithKeys(fn ($item) => ["{$item->module_key}:{$item->rekap_id}" => true]);

        $newItems = $items
            ->reject(fn (array $item) => $existingKeys->has($itemKey($item)))
            ->values();

        $duplicateRowKeys = $items
            ->filter(fn (array $item) => $existingKeys->has($itemKey($item)))
            ->map(fn (array $item) => $itemKey($item))
            ->values()
            ->all();

        if ($newItems->isNotEmpty()) {
            DB::transaction(function () use ($id, $newItems) {
                $now = now();

                DB::table('keuangan_cetak_rab_detail')->insert(
                    $newItems->map(fn (array $item) => [
                        'cetak_rab_id' => $id,
                        'module_key' => $item['module_key'],
                        'rekap_id' => $item['id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );

                DB::table('keuangan_cetak_rab')
                    ->where('id', $id)
                    ->update(['updated_at' => $now]);

                foreach ($newItems as $item) {
                    if ($this->rekapHasCetakRabColumn($item['module_key'])) {
                        $this->scopedRekapQuery($item['module_key'], $item['id'])
                            ->update([
                                'cetak_rab' => true,
                                'updated_at' => $now,
                            ]);
                    }
                }
            });
        }

        $addedRowKeys = $newItems
            ->map(fn (array $item) => $itemKey($item))
            ->values()
            ->all();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => (int) $id,
                'row_keys' => $items
                    ->map(fn (array $item) => $itemKey($item))
                    ->all(),
                'added_row_keys' => $addedRowKeys,
                'duplicate_row_keys' => $duplicateRowKeys,
            ],
            'message' => $newItems->count() > 0
                ? $newItems->count().' rekap berhasil dimasukkan ke proses RAB.'
                : 'Rekap yang dipilih sudah ada di proses RAB ini.',
        ]);
    }

    public function updateProsesRab(Request $request, $id)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        if (! $this->hasCetakRabKategoriColumn()) {
            return response()->json([
                'status' => false,
                'message' => 'Migration kategori Proses RAB belum dijalankan.',
            ], 422);
        }

        $cetak = DB::table('keuangan_cetak_rab')->where('id', $id)->first();

        if (! $cetak) {
            return response()->json([
                'status' => false,
                'message' => 'Proses RAB tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tanggal_cetak' => ['required', 'date_format:Y-m-d'],
            'kategori' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'array', 'max:500'],
            'items.*.module_key' => ['required_with:items', Rule::in($this->rabProcessModuleKeys())],
            'items.*.id' => ['required_with:items', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $kategori = trim((string) $validated['kategori']);

        if ($kategori === '') {
            return response()->json([
                'status' => false,
                'message' => [
                    'kategori' => ['Kategori wajib diisi.'],
                ],
            ], 422);
        }

        $itemKey = fn (array $item) => "{$item['module_key']}:{$item['id']}";

        $existingItems = DB::table('keuangan_cetak_rab_detail')
            ->where('cetak_rab_id', $id)
            ->get(['module_key', 'rekap_id'])
            ->map(fn ($item) => [
                'module_key' => $item->module_key,
                'id' => (int) $item->rekap_id,
            ])
            ->unique(fn (array $item) => $itemKey($item))
            ->values();

        foreach ($existingItems as $item) {
            if (! $this->rabItemExistsIgnoringScope($item['module_key'], $item['id'])) {
                continue;
            }

            if (! $this->scopedRekapQuery($item['module_key'], $item['id'])->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Proses RAB tidak ditemukan atau tidak dapat diakses.',
                ], 404);
            }
        }

        $itemsWereSubmitted = array_key_exists('items', $validated);
        $remainingItems = $existingItems;

        if ($itemsWereSubmitted) {
            $remainingItems = collect($validated['items'])
                ->map(fn (array $item) => [
                    'module_key' => $item['module_key'],
                    'id' => (int) $item['id'],
                ])
                ->unique(fn (array $item) => $itemKey($item))
                ->values();

            $existingItemsByKey = $existingItems->keyBy(fn (array $item) => $itemKey($item));
            $unknownItem = $remainingItems->first(fn (array $item) => ! $existingItemsByKey->has($itemKey($item)));

            if ($unknownItem) {
                return response()->json([
                    'status' => false,
                    'message' => 'Item RAB tidak termasuk dalam proses ini.',
                ], 422);
            }
        }

        foreach ($remainingItems as $item) {
            if (! $this->rabItemExistsIgnoringScope($item['module_key'], $item['id'])) {
                continue;
            }

            if (! $this->scopedRekapQuery($item['module_key'], $item['id'])->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Proses RAB tidak ditemukan atau tidak dapat diakses.',
                ], 404);
            }
        }

        $remainingKeys = $remainingItems
            ->mapWithKeys(fn (array $item) => [$itemKey($item) => true]);
        $removedItems = $itemsWereSubmitted
            ? $existingItems->reject(fn (array $item) => $remainingKeys->has($itemKey($item)))->values()
            : collect();
        $keterangan = trim((string) ($validated['keterangan'] ?? ''));
        $resetRowKeys = [];

        if ($itemsWereSubmitted && $remainingItems->isEmpty()) {
            DB::transaction(function () use ($id, $existingItems, &$resetRowKeys) {
                $now = now();

                DB::table('keuangan_cetak_rab_detail')
                    ->where('cetak_rab_id', $id)
                    ->delete();

                DB::table('keuangan_cetak_rab')
                    ->where('id', $id)
                    ->delete();

                foreach ($existingItems as $item) {
                    $hasOtherProcess = DB::table('keuangan_cetak_rab_detail')
                        ->where('module_key', $item['module_key'])
                        ->where('rekap_id', $item['id'])
                        ->exists();

                    if ($hasOtherProcess || ! $this->rekapHasCetakRabColumn($item['module_key'])) {
                        continue;
                    }

                    $this->scopedRekapQuery($item['module_key'], $item['id'])
                        ->update([
                            'cetak_rab' => false,
                            'updated_at' => $now,
                        ]);

                    $resetRowKeys[] = "{$item['module_key']}:{$item['id']}";
                }
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => (int) $id,
                    'deleted' => true,
                    'row_keys' => $existingItems
                        ->map(fn (array $item) => $itemKey($item))
                        ->all(),
                    'reset_row_keys' => $resetRowKeys,
                ],
                'message' => 'Semua rekap dilepas, proses RAB dihapus.',
            ]);
        }

        DB::transaction(function () use ($id, $validated, $kategori, $keterangan, $removedItems, &$resetRowKeys) {
            $now = now();

            DB::table('keuangan_cetak_rab')
                ->where('id', $id)
                ->update([
                    'tanggal_cetak' => $validated['tanggal_cetak'],
                    'kategori' => $kategori,
                    'keterangan' => $keterangan !== '' ? $keterangan : null,
                    'updated_at' => $now,
                ]);

            foreach ($removedItems as $item) {
                DB::table('keuangan_cetak_rab_detail')
                    ->where('cetak_rab_id', $id)
                    ->where('module_key', $item['module_key'])
                    ->where('rekap_id', $item['id'])
                    ->delete();

                $hasOtherProcess = DB::table('keuangan_cetak_rab_detail')
                    ->where('module_key', $item['module_key'])
                    ->where('rekap_id', $item['id'])
                    ->exists();

                if ($hasOtherProcess || ! $this->rekapHasCetakRabColumn($item['module_key'])) {
                    continue;
                }

                $this->scopedRekapQuery($item['module_key'], $item['id'])
                    ->update([
                        'cetak_rab' => false,
                        'updated_at' => $now,
                    ]);

                $resetRowKeys[] = "{$item['module_key']}:{$item['id']}";
            }
        });

        return response()->json([
            'status' => true,
            'data' => [
                'id' => (int) $id,
                'tanggal_cetak' => $validated['tanggal_cetak'],
                'kategori' => $kategori,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'jumlah_rekap' => $remainingItems->count(),
                'row_keys' => $remainingItems
                    ->map(fn (array $item) => $itemKey($item))
                    ->all(),
                'removed_row_keys' => $removedItems
                    ->map(fn (array $item) => $itemKey($item))
                    ->all(),
                'reset_row_keys' => $resetRowKeys,
            ],
            'message' => 'Proses RAB berhasil diperbarui.',
        ]);
    }

    private function prosesRabRows(Request $request)
    {
        $rekapFilterRequest = $this->prosesRabRekapFilterRequest($request);
        $summary = DB::table('keuangan_cetak_rab_detail as detail')
            ->joinSub($this->filteredRekapQuery($rekapFilterRequest), 'rab', function ($join) {
                $join->on('rab.module_key', '=', 'detail.module_key')
                    ->on('rab.id', '=', 'detail.rekap_id');
            })
            ->selectRaw(
                'detail.cetak_rab_id,
                COUNT(*) as jumlah_rekap,
                COALESCE(SUM(rab.jumlah), 0) as total_rab'
            )
            ->groupBy('detail.cetak_rab_id');

        $selectColumns = [
            'cetak.id',
            'cetak.tanggal_cetak',
            'cetak.keterangan',
            'cetak.created_at',
            DB::raw('COALESCE(summary.jumlah_rekap, 0) as jumlah_rekap'),
            DB::raw('COALESCE(summary.total_rab, 0) as total_rab'),
        ];

        $selectColumns[] = $this->hasCetakRabKategoriColumn()
            ? 'cetak.kategori'
            : DB::raw('NULL as kategori');

        $rows = DB::table('keuangan_cetak_rab as cetak')
            ->leftJoinSub($summary, 'summary', 'summary.cetak_rab_id', '=', 'cetak.id')
            ->select($selectColumns);

        if ($this->hasProsesRabRekapFilters($rekapFilterRequest)) {
            $rows->whereNotNull('summary.cetak_rab_id');
        }

        $this->applyProsesRabFilters($rows, $request);

        return $rows
            ->orderByDesc('cetak.tanggal_cetak')
            ->orderByDesc('cetak.id')
            ->limit(100)
            ->get()
            ->map(function ($item) {
                $item->id = (int) $item->id;
                $item->jumlah_rekap = (int) $item->jumlah_rekap;
                $item->total_rab = (int) $item->total_rab;
                $item->kategori = trim((string) ($item->kategori ?? '')) ?: null;

                return $item;
            });
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
            if (! $this->rabItemExistsIgnoringScope($item['module_key'], $item['id'])) {
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

    public function exportProsesRabRekapan(Request $request)
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasTable('keuangan_cetak_rab_detail')) {
            return response()->json([
                'status' => false,
                'message' => 'Migration Proses RAB belum dijalankan.',
            ], 422);
        }

        $processRows = $this->prosesRabRows($request);
        $data = $this->prosesRabRekapanExportRows($processRows->pluck('id')->all(), $request);
        $rows = $data->values()->map(function ($item, $index) {
            $totalRab = (int) ($item->jumlah ?? 0);
            $totalLpj = (int) ($item->total_lpj ?? 0);
            $keterangan = trim((string) ($item->keterangan ?: ''));
            $moduleName = trim((string) ($item->module_name ?: ''));

            return [
                $index + 1,
                trim((string) ($item->proses_kategori ?? '')),
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

        $label = $this->requestProsesExportPeriodLabel($request);
        $title = trim('REKAPAN RAB LIST PROSES '.$label);
        $safeName = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $title ?: 'Rekapan RAB'));
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));

        return $this->downloadRekapanSpreadsheet(
            $title ?: 'REKAPAN RAB LIST PROSES',
            $rows,
            [
                'rab' => $data->sum(fn ($item) => (int) ($item->jumlah ?? 0)),
                'laporan' => $data->sum(fn ($item) => (int) ($item->total_lpj ?? 0)),
            ],
            ($safeName ?: 'Rekapan RAB').'.xlsx'
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
                '',
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

        if ($piutangSummary = $this->piutangKasSummary($request)) {
            $rows->push($piutangSummary);
        }

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

    private function prosesRabRekapanExportRows(array $cetakRabIds, Request $request)
    {
        if ($cetakRabIds === []) {
            return collect();
        }

        $rekapFilterRequest = $this->prosesRabRekapFilterRequest($request);
        $selectColumns = [
            'rab.*',
            'petugas.name as petugas_nama',
            'detail.id as detail_id',
            'detail.cetak_rab_id',
            'cetak.tanggal_cetak as proses_tanggal_cetak',
            'cetak.keterangan as proses_keterangan',
        ];

        $selectColumns[] = $this->hasCetakRabKategoriColumn()
            ? 'cetak.kategori as proses_kategori'
            : DB::raw('NULL as proses_kategori');

        $data = DB::table('keuangan_cetak_rab_detail as detail')
            ->join('keuangan_cetak_rab as cetak', 'cetak.id', '=', 'detail.cetak_rab_id')
            ->joinSub($this->filteredRekapQuery($rekapFilterRequest), 'rab', function ($join) {
                $join->on('rab.module_key', '=', 'detail.module_key')
                    ->on('rab.id', '=', 'detail.rekap_id');
            })
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->whereIn('detail.cetak_rab_id', $cetakRabIds)
            ->select($selectColumns)
            ->orderBy('cetak.id')
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
            $item->proses_kategori = trim((string) ($item->proses_kategori ?? ''));
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
        $sheet->mergeCells('C1:J1');
        $sheet->setCellValue('A1', 'NO');
        $sheet->setCellValue('C1', $title);
        $sheet->setCellValue('B2', 'KATEGORI');
        $sheet->setCellValue('C2', 'NAMA ACARA');
        $sheet->setCellValue('D2', 'TANGGAL EVENT / PERMOHONAN');
        $sheet->setCellValue('E2', 'TANGGAL PENCAIRAN');
        $sheet->setCellValue('F2', 'RAB');
        $sheet->setCellValue('G2', 'LAPORAN');
        $sheet->setCellValue('H2', 'SELISIH');
        $sheet->setCellValue('I2', 'LAMPIRAN');
        $sheet->setCellValue('J2', 'KETERANGAN');

        if ($rows === []) {
            $sheet->mergeCells('C3:J3');
            $sheet->setCellValue('A3', 1);
            $sheet->setCellValue('C3', 'Tidak ada data');
        } else {
            foreach ($rows as $index => $rowData) {
                $rowNumber = $firstDataRow + $index;
                $sheet->setCellValue("A{$rowNumber}", $index + 1);
                $sheet->setCellValue("B{$rowNumber}", $rowData[1] ?: '-');
                $sheet->setCellValue("C{$rowNumber}", $rowData[2]);
                $sheet->setCellValue("D{$rowNumber}", $this->excelDateValue($rowData[3]));
                $sheet->setCellValue("E{$rowNumber}", $this->excelDateValue($rowData[4]));
                $sheet->setCellValue("F{$rowNumber}", $rowData[5]);
                $sheet->setCellValue("G{$rowNumber}", $rowData[6]);
                $sheet->setCellValue("H{$rowNumber}", $rowData[7]);
                $sheet->setCellValue("I{$rowNumber}", '');
                $sheet->setCellValue("J{$rowNumber}", $rowData[9]);
                $sheet->getRowDimension($rowNumber)->setRowHeight(22.95);
            }

            $this->mergeRekapanColumnGroups($sheet, $rows, 1, $firstDataRow, 'B');
            $this->mergeRekapanColumnGroups($sheet, $rows, 4, $firstDataRow, 'E');
        }

        $totalRab = (int) ($totals['rab'] ?? 0);
        $totalLaporan = (int) ($totals['laporan'] ?? 0);
        $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->setCellValue("F{$totalRow}", $totalRab);
        $sheet->setCellValue("G{$totalRow}", $totalLaporan);
        $sheet->setCellValue("H{$totalRow}", $totalRab - $totalLaporan);

        $tableRange = "A1:J{$totalRow}";
        $headerRange = 'A1:J2';
        $bodyRange = "A{$firstDataRow}:J{$totalRow}";
        $dateRange = "D{$firstDataRow}:E{$lastDataRow}";
        $amountRange = "F{$firstDataRow}:H{$totalRow}";

        $sheet->getStyle($tableRange)->getFont()->setSize(14);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($bodyRange)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$firstDataRow}:B{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$firstDataRow}:I{$totalRow}")->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$totalRow}:J{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getAlignment()
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
            'B' => 16.22,
            'C' => 89.78,
            'D' => 46.33,
            'E' => 31.66,
            'F' => 31.22,
            'G' => 31.22,
            'H' => 31.22,
            'I' => 31.22,
            'J' => 54.22,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A3');
        $sheet->setTopLeftCell('A1');
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function mergeRekapanColumnGroups(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $rows,
        int $valueIndex,
        int $firstDataRow,
        string $column,
        bool $skipEmpty = true
    ): void {
        $currentValue = null;
        $currentStart = null;
        $currentEnd = null;

        $flush = function () use ($sheet, $column, &$currentValue, &$currentStart, &$currentEnd): void {
            if ($currentStart !== null && $currentEnd !== null && $currentStart < $currentEnd) {
                $sheet->mergeCells("{$column}{$currentStart}:{$column}{$currentEnd}");
            }

            $currentValue = null;
            $currentStart = null;
            $currentEnd = null;
        };

        foreach ($rows as $index => $rowData) {
            $value = trim((string) ($rowData[$valueIndex] ?? ''));
            $rowNumber = $firstDataRow + $index;

            if ($skipEmpty && $value === '') {
                $flush();

                continue;
            }

            if ($currentStart === null || $currentValue !== $value) {
                $flush();
                $currentValue = $value;
                $currentStart = $rowNumber;
                $currentEnd = $rowNumber;

                continue;
            }

            $currentEnd = $rowNumber;
        }

        $flush();
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
        return $this->requestPeriodLabel($request, 'bulan', 'tahun');
    }

    private function requestProsesExportPeriodLabel(Request $request): string
    {
        return $this->requestPeriodLabel($request, 'proses_bulan', 'proses_tahun');
    }

    private function requestPeriodLabel(Request $request, string $monthKey, string $yearKey): string
    {
        $months = $this->monthFilterValues($request, $monthKey);
        $tahun = $request->filled($yearKey) ? (int) $request->input($yearKey) : null;

        if ($months !== []) {
            $yearForMonthName = $tahun && $tahun > 0 ? $tahun : 2000;
            $monthLabel = collect($months)
                ->map(fn (int $month) => strtoupper(
                    \Carbon\Carbon::create($yearForMonthName, $month, 1)
                        ->locale('id')
                        ->translatedFormat('F')
                ))
                ->implode(', ');

            return trim($monthLabel.' '.($tahun && $tahun > 0 ? $tahun : ''));
        }

        if ($tahun) {
            return (string) $tahun;
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

        if ($piutangQuery = $this->piutangRabQuery($request)) {
            $queries[] = $piutangQuery;
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

        if ($piutangQuery = $this->piutangRabQuery($request)) {
            $queries[] = $piutangQuery;
        }

        return DB::query()->fromSub($this->unionAll($queries), 'rab');
    }

    private function rekapUnionQuery(?Request $request = null): Builder
    {
        $queries = [];

        foreach (self::SOURCES as $moduleKey => $source) {
            $queries[] = $this->rekapSourceQuery($moduleKey, $source, $request);
        }

        if ($request && ($piutangQuery = $this->piutangRabQuery($request))) {
            $queries[] = $piutangQuery;
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

    private function findRabRow(string $moduleKey, int $id): ?object
    {
        if (! array_key_exists($moduleKey, self::SOURCES)) {
            return null;
        }

        $source = self::SOURCES[$moduleKey];
        $query = DB::query()
            ->fromSub($this->rekapSourceQuery($moduleKey, $source), 'rab')
            ->leftJoin('users as petugas', 'petugas.id', '=', 'rab.petugas_id')
            ->where('rab.id', $id)
            ->select('rab.*', 'petugas.name as petugas_nama');

        $item = $query->first();

        if (! $item) {
            return null;
        }

        $this->castRabRow($item);

        return $item;
    }

    private function castRabRow(object $item): void
    {
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
    }

    private function rekapUsageSummary(string $moduleKey, int $id): array
    {
        $source = self::SOURCES[$moduleKey];
        $detailCount = Schema::hasTable($source['detail_table'])
            ? DB::table($source['detail_table'])
                ->where('rekap_id', $id)
                ->count()
            : 0;
        $lpjCount = Schema::hasTable($source['lpj_table'])
            ? DB::table($source['lpj_table'])
                ->where('rekap_id', $id)
                ->count()
            : 0;
        $lpjStatusCount = Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')
            ? DB::table('keuangan_pengeluaran_lpj_rekap_status')
                ->where('module_key', $moduleKey)
                ->where('rekap_id', $id)
                ->count()
            : 0;
        $processCount = Schema::hasTable('keuangan_cetak_rab_detail')
            ? DB::table('keuangan_cetak_rab_detail')
                ->where('module_key', $moduleKey)
                ->where('rekap_id', $id)
                ->count()
            : 0;
        $cetakRab = $this->rekapHasCetakRabColumn($moduleKey)
            && (bool) DB::table($source['rekap_table'])
                ->where('id', $id)
                ->value('cetak_rab');

        return [
            'detail_count' => (int) $detailCount,
            'lpj_count' => (int) $lpjCount,
            'lpj_status_count' => (int) $lpjStatusCount,
            'process_count' => (int) $processCount,
            'cetak_rab' => $cetakRab,
        ];
    }

    private function piutangRabQuery(Request $request): ?Builder
    {
        if (! $this->shouldIncludePiutang($request)) {
            return null;
        }

        $query = DB::table('keuangan_piutang as piutang')
            ->join('pegawai', 'pegawai.id', '=', 'piutang.pegawai_id');
        Helper::applyGenderScope($query, 'pegawai.jenis_kelamin');

        if (
            $request->boolean('belum_cetak_rab')
            && $this->piutangTableHasColumn('cetak_rab')
        ) {
            $query->where(function (Builder $filter) {
                $filter->whereNull('piutang.cetak_rab')
                    ->orWhere('piutang.cetak_rab', false);
            });
        }

        $this->applyPeriodFilter($query, $request, 'piutang.tanggal');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            if (stripos(self::PIUTANG_MODULE_NAME, $search) === false) {
                $query->where(function (Builder $filter) use ($search) {
                    $filter->where('pegawai.nama', 'LIKE', "%{$search}%")
                        ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                        ->orWhere('piutang.keterangan', 'LIKE', "%{$search}%");
                });
            }
        }

        $tanggalPencairanSelect = $this->piutangTableHasColumn('tanggal_pencairan')
            ? 'piutang.tanggal_pencairan'
            : DB::raw('NULL as tanggal_pencairan');
        $cetakRabSelect = $this->piutangTableHasColumn('cetak_rab')
            ? 'piutang.cetak_rab'
            : DB::raw('0 as cetak_rab');

        return $query->select([
            'piutang.id',
            DB::raw("TRIM(CONCAT('CASHBON ', pegawai.nama, CASE WHEN piutang.keterangan IS NOT NULL AND TRIM(piutang.keterangan) <> '' THEN CONCAT(' ( ', TRIM(piutang.keterangan), ' )') ELSE '' END)) as nama"),
            DB::raw('piutang.tanggal as bulan_tahun'),
            DB::raw('piutang.tanggal as tanggal_rekap'),
            $tanggalPencairanSelect,
            $cetakRabSelect,
            DB::raw('NULL as jumlah_sementara'),
            DB::raw('NULL as petugas_id'),
            DB::raw('piutang.nominal as jumlah'),
            DB::raw('0 as total_lpj'),
            'piutang.keterangan',
            'piutang.created_at',
            DB::raw("CONCAT('".self::PIUTANG_MODULE_KEY.":', piutang.id) as row_key"),
            DB::raw("'".self::PIUTANG_MODULE_KEY."' as module_key"),
            DB::raw("'".self::PIUTANG_MODULE_NAME."' as module_name"),
            DB::raw("'/admin/piutang/edit/' as detail_path"),
            DB::raw('1 as jumlah_data'),
            DB::raw('piutang.nominal as total_pengeluaran'),
            DB::raw('0 as is_jumlah_sementara'),
            DB::raw('0 as selisih_sementara'),
        ]);
    }

    private function shouldIncludePiutang(Request $request): bool
    {
        if (! Schema::hasTable('keuangan_piutang') || ! Schema::hasTable('pegawai')) {
            return false;
        }

        if ($request->filled('module_key') && $request->module_key !== self::PIUTANG_MODULE_KEY) {
            return false;
        }

        return ! $request->filled('petugas_id');
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
                ->leftJoin("{$source['detail_table']} as detail", function ($join) use ($source) {
                    $join->on('detail.rekap_id', '=', 'filtered_rekap.id');

                    if ($source['pegawai_tipe']) {
                        $join->where('detail.pegawai_tipe', '=', $source['pegawai_tipe']);
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
        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
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
        if ($source['pegawai_tipe']) {
            $query->where('detail.pegawai_tipe', $source['pegawai_tipe']);
        }

        return $query;
    }

    private function lpjStatsForRabQuery(Builder $rabQuery, array $source, string $moduleKey, Request $request): array
    {
        $sameAsRabAmount = 'COALESCE(NULLIF(lpj_status.total_lpj, 0), rab_filtered.jumlah)';

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

    private function piutangKasSummary(Request $request): ?array
    {
        $query = $this->piutangRabQuery($request);

        if (! $query) {
            return null;
        }

        $summary = DB::query()
            ->fromSub($query, 'piutang_rab')
            ->selectRaw('COALESCE(SUM(jumlah), 0) as total_rab')
            ->first();
        $totalRab = (int) ($summary->total_rab ?? 0);

        return [
            'module_key' => self::PIUTANG_MODULE_KEY,
            'module_name' => self::PIUTANG_MODULE_NAME,
            'total_rab' => $totalRab,
            'total_lpj' => 0,
            'jumlah_lpj' => 0,
            'manual_masuk' => 0,
            'manual_keluar' => 0,
            'saldo_kas' => $totalRab,
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

            $queries[] = $query->selectRaw(
                'COUNT(detail.id) as total_data,
                COALESCE(SUM(detail.total), 0) as total_anggaran'
            );
        }

        if ($piutangQuery = $this->piutangRabQuery($request)) {
            $queries[] = DB::query()
                ->fromSub($piutangQuery, 'piutang_rab')
                ->selectRaw(
                    'COUNT(*) as total_data,
                    COALESCE(SUM(jumlah), 0) as total_anggaran'
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

        if (
            $request->boolean('belum_cetak_rab')
            && $this->rekapTableHasColumn($source['rekap_table'], 'cetak_rab')
        ) {
            $query->where(function (Builder $filter) {
                $filter->whereNull('rekap.cetak_rab')
                    ->orWhere('rekap.cetak_rab', false);
            });
        }

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
        $bulan = $this->monthFilterValues($request, 'proses_bulan');
        $tahun = $request->filled('proses_tahun') ? (int) $request->proses_tahun : null;

        $this->applyDateMonthFilter($query, 'cetak.tanggal_cetak', $bulan, $tahun);

        $search = trim((string) $request->input('proses_search', ''));

        if ($search === '') {
            return;
        }

        $searchRequest = Request::create('/', 'GET', [
            'search' => $search,
        ]);

        $matchingRekaps = $this->filteredRekapQuery($searchRequest);

        $hasKategori = $this->hasCetakRabKategoriColumn();

        $query->where(function (Builder $filter) use ($search, $matchingRekaps, $hasKategori) {
            $filter->where('cetak.keterangan', 'LIKE', "%{$search}%");

            if ($hasKategori) {
                $filter->orWhere('cetak.kategori', 'LIKE', "%{$search}%");
            }

            $filter->orWhereExists(function ($exists) use ($matchingRekaps) {
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

    private function prosesRabRekapFilterRequest(Request $request): Request
    {
        $filters = collect(['module_key', 'petugas_id', 'search'])
            ->mapWithKeys(fn (string $key) => $request->filled($key)
                ? [$key => $request->input($key)]
                : [])
            ->all();

        return Request::create('/', 'GET', $filters);
    }

    private function hasProsesRabRekapFilters(Request $request): bool
    {
        return collect(['module_key', 'petugas_id', 'search'])
            ->contains(fn (string $key) => $request->filled($key));
    }

    private function prosesRabKategoriOptions(): array
    {
        if (! $this->hasCetakRabKategoriColumn()) {
            return [];
        }

        return DB::table('keuangan_cetak_rab')
            ->whereNotNull('kategori')
            ->whereRaw("TRIM(kategori) <> ''")
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori')
            ->map(fn ($kategori) => trim((string) $kategori))
            ->filter()
            ->values()
            ->all();
    }

    private function prosesRabKategoriHistory(): array
    {
        if (! $this->hasCetakRabKategoriColumn()) {
            return [];
        }

        return DB::table('keuangan_cetak_rab')
            ->whereNotNull('kategori')
            ->whereRaw("TRIM(kategori) <> ''")
            ->orderBy('tanggal_cetak')
            ->orderBy('id')
            ->get(['kategori', 'tanggal_cetak'])
            ->map(fn ($item) => [
                'kategori' => trim((string) $item->kategori),
                'tanggal_cetak' => (string) $item->tanggal_cetak,
            ])
            ->filter(fn (array $item) => $item['kategori'] !== '')
            ->values()
            ->all();
    }

    private function hasCetakRabKategoriColumn(): bool
    {
        return Schema::hasTable('keuangan_cetak_rab')
            && Schema::hasColumn('keuangan_cetak_rab', 'kategori');
    }

    private function monthFilterValues(Request $request, string $key): array
    {
        if (! $request->filled($key)) {
            return [];
        }

        $values = $request->input($key);
        $values = is_array($values) ? $values : [$values];

        return collect($values)
            ->flatMap(fn ($value) => is_array($value)
                ? $value
                : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $month) => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function applyDateMonthFilter(
        Builder $query,
        string $column,
        array $months,
        ?int $year
    ): void {
        if ($year && $months !== []) {
            $query->where(function (Builder $filter) use ($column, $months, $year) {
                foreach ($months as $month) {
                    $start = sprintf('%04d-%02d-01', $year, $month);
                    $end = date('Y-m-d', strtotime("{$start} +1 month"));

                    $filter->orWhere(function (Builder $monthFilter) use ($column, $start, $end) {
                        $monthFilter->where($column, '>=', $start)
                            ->where($column, '<', $end);
                    });
                }
            });

            return;
        }

        if ($year) {
            $query->where($column, '>=', "{$year}-01-01")
                ->where($column, '<', ($year + 1).'-01-01');

            return;
        }

        if ($months !== []) {
            $query->where(function (Builder $filter) use ($column, $months) {
                foreach ($months as $index => $month) {
                    if ($index === 0) {
                        $filter->whereMonth($column, $month);
                    } else {
                        $filter->orWhereMonth($column, $month);
                    }
                }
            });
        }
    }

    private function applyPeriodFilter(Builder $query, Request $request, string $column): void
    {
        $bulan = $this->monthFilterValues($request, 'bulan');
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        $this->applyDateMonthFilter($query, $column, $bulan, $tahun);
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

        if (Schema::hasTable('keuangan_piutang') && Schema::hasTable('pegawai')) {
            $piutangYears = DB::table('keuangan_piutang as piutang')
                ->join('pegawai', 'pegawai.id', '=', 'piutang.pegawai_id')
                ->whereNotNull('piutang.tanggal')
                ->selectRaw('YEAR(piutang.tanggal) as tahun');
            Helper::applyGenderScope($piutangYears, 'pegawai.jenis_kelamin');
            $queries[] = $piutangYears;
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
        if ($this->isPiutangModule($moduleKey)) {
            return $this->piutangTableHasColumn('cetak_rab');
        }

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

    private function piutangTableHasColumn(string $column): bool
    {
        static $cache = [];

        if (! array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasTable('keuangan_piutang')
                && Schema::hasColumn('keuangan_piutang', $column);
        }

        return $cache[$column];
    }

    private function isPiutangModule(string $moduleKey): bool
    {
        return $moduleKey === self::PIUTANG_MODULE_KEY;
    }

    private function rabProcessModuleKeys(): array
    {
        $keys = array_keys(self::SOURCES);

        if (Schema::hasTable('keuangan_piutang') && Schema::hasTable('pegawai')) {
            $keys[] = self::PIUTANG_MODULE_KEY;
        }

        return $keys;
    }

    private function rabItemExistsIgnoringScope(string $moduleKey, int $id): bool
    {
        if ($this->isPiutangModule($moduleKey)) {
            return Schema::hasTable('keuangan_piutang')
                && DB::table('keuangan_piutang')->where('id', $id)->exists();
        }

        $table = self::SOURCES[$moduleKey]['rekap_table'] ?? null;

        return $table && Schema::hasTable($table) && DB::table($table)->where('id', $id)->exists();
    }

    private function scopedRekapQuery(string $moduleKey, int $rekapId): Builder
    {
        if ($this->isPiutangModule($moduleKey)) {
            $query = DB::table('keuangan_piutang')
                ->where('keuangan_piutang.id', $rekapId);
            Helper::applyRelatedGenderScope(
                $query,
                'keuangan_piutang.pegawai_id',
                'pegawai'
            );

            return $query;
        }

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
            'absensi' => ['barokahdosen_bulanan'],
            default => [],
        };
    }

    private function moduleOptions(): array
    {
        $options = [
            ['title' => 'Dosen Tatap Muka', 'value' => 'tatap_muka'],
            ['title' => 'Pegawai Kegiatan', 'value' => 'kegiatan'],
            ['title' => 'Rumah Tangga', 'value' => 'rumah_tangga'],
            ['title' => 'Sarana Prasarana', 'value' => 'sarana_prasarana'],
            ['title' => 'Transportasi', 'value' => 'transportasi'],
            ['title' => 'Pengeluaran Umum', 'value' => 'umum'],
            ['title' => 'Bulanan', 'value' => 'dosen_bulanan'],
            ['title' => 'Barokah Absensi', 'value' => 'absensi'],
        ];

        if (Schema::hasTable('keuangan_piutang') && Schema::hasTable('pegawai')) {
            $options[] = ['title' => self::PIUTANG_MODULE_NAME, 'value' => self::PIUTANG_MODULE_KEY];
        }

        return $options;
    }
}
