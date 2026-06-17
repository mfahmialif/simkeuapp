<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LpjController extends Controller
{
    private const MODULES = [
        'dosen' => [
            'module_key' => 'tatap_muka',
            'title' => 'Barokah Dosen Tatapmuka',
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'lpj_table' => 'keuangan_pengeluaran_dosen_lpj',
            'pegawai_tipe' => null,
            'type' => 'tatapmuka',
        ],
        'kegiatan' => [
            'module_key' => 'kegiatan',
            'title' => 'Barokah Pegawai Kegiatan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'lpj_table' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
            'pegawai_tipe' => null,
            'type' => 'kegiatan',
        ],
        'rumah_tangga' => [
            'module_key' => 'rumah_tangga',
            'title' => 'Rumah Tangga',
            'rekap_table' => 'keuangan_pengeluaran_rumah_tangga_rekap',
            'detail_table' => 'keuangan_pengeluaran_rumah_tangga',
            'lpj_table' => 'keuangan_pengeluaran_rumah_tangga_lpj',
            'pegawai_tipe' => null,
            'type' => 'rumah-tangga',
        ],
        'sarana_prasarana' => [
            'module_key' => 'sarana_prasarana',
            'title' => 'Sarana Prasarana',
            'rekap_table' => 'keuangan_pengeluaran_sarana_prasarana_rekap',
            'detail_table' => 'keuangan_pengeluaran_sarana_prasarana',
            'lpj_table' => 'keuangan_pengeluaran_sarana_prasarana_lpj',
            'pegawai_tipe' => null,
            'type' => 'sarana-prasarana',
        ],
        'transportasi' => [
            'module_key' => 'transportasi',
            'title' => 'Transportasi',
            'rekap_table' => 'keuangan_pengeluaran_transportasi_rekap',
            'detail_table' => 'keuangan_pengeluaran_transportasi',
            'lpj_table' => 'keuangan_pengeluaran_transportasi_lpj',
            'pegawai_tipe' => null,
            'type' => 'transportasi',
        ],
        'dosen_bulanan' => [
            'module_key' => 'dosen_bulanan',
            'title' => 'Barokah Bulanan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'pegawai_tipe' => null,
            'type' => 'dosen-bulanan',
        ],
    ];

    public function dosenShow(Request $request, $id)
    {
        return $this->showModule($request, 'dosen', $id);
    }

    public function kegiatanShow(Request $request, $id)
    {
        return $this->showModule($request, 'kegiatan', $id);
    }

    public function rumahTanggaShow(Request $request, $id)
    {
        return $this->showModule($request, 'rumah_tangga', $id);
    }

    public function saranaPrasaranaShow(Request $request, $id)
    {
        return $this->showModule($request, 'sarana_prasarana', $id);
    }

    public function transportasiShow(Request $request, $id)
    {
        return $this->showModule($request, 'transportasi', $id);
    }

    public function dosenBulananShow(Request $request, $id)
    {
        return $this->showModule($request, 'dosen_bulanan', $id);
    }

    public function dosenCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen', $id);
    }

    public function kegiatanCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'kegiatan', $id);
    }

    public function rumahTanggaCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'rumah_tangga', $id);
    }

    public function saranaPrasaranaCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'sarana_prasarana', $id);
    }

    public function transportasiCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'transportasi', $id);
    }

    public function dosenBulananCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen_bulanan', $id);
    }

    public function dosenUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen', $id);
    }

    public function dosenDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'dosen', $id);
    }

    public function kegiatanUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'kegiatan', $id);
    }

    public function kegiatanDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'kegiatan', $id);
    }

    public function rumahTanggaUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'rumah_tangga', $id);
    }

    public function rumahTanggaDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'rumah_tangga', $id);
    }

    public function saranaPrasaranaUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'sarana_prasarana', $id);
    }

    public function saranaPrasaranaDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'sarana_prasarana', $id);
    }

    public function transportasiUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'transportasi', $id);
    }

    public function transportasiDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'transportasi', $id);
    }

    public function dosenBulananUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen_bulanan', $id);
    }

    public function dosenBulananDelete(Request $request, $id)
    {
        return $this->deleteModule($request, 'dosen_bulanan', $id);
    }

    private function showModule(Request $request, string $module, $rekapId)
    {
        $source = $this->source($module);
        $rekap = $this->rekapSummary($source, $rekapId);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $lpj = $this->lpjSummary($source, $rekapId, $rekap['jumlah']);

        return response()->json([
            'status' => true,
            'data' => [
                'module_key' => $source['module_key'],
                'module_type' => $source['type'],
                'title' => $source['title'],
                'rekap' => $rekap,
                'lpj' => $lpj,
                'details' => $this->lpjRows($source, $rekapId, $request),
            ],
            'message' => 'LPJ retrieved successfully',
        ]);
    }

    private function copyModule(Request $request, string $module, $rekapId)
    {
        $validator = Validator::make($request->all(), [
            'sama_dengan_rab' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $source = $this->source($module);
        $sameAsRab = $request->boolean('sama_dengan_rab');

        $result = DB::transaction(function () use ($source, $rekapId, $sameAsRab) {
            $rekapQuery = DB::table($source['rekap_table'])
                ->where('id', $rekapId);
            Helper::applyRelatedGenderScope(
                $rekapQuery,
                "{$source['rekap_table']}.petugas_id",
                'users'
            );
            $rekap = $rekapQuery->lockForUpdate()->first();

            if (! $rekap) {
                return null;
            }

            $this->deleteLpjRows($source, $rekapId);

            $copyColumns = $this->copyColumns($source);
            $now = now();
            $rows = $this->rabDetailQuery($source, $rekapId)
                ->orderBy("{$source['detail_table']}.id")
                ->get()
                ->map(function ($row) use ($copyColumns, $now, $rekap) {
                    $rowArray = (array) $row;
                    $data = Arr::only($rowArray, $copyColumns);
                    $data['rab_detail_id'] = $row->id;
                    $data['petugas_id'] = $rekap->petugas_id ?? auth()->id();
                    $data['created_at'] = $now;
                    $data['updated_at'] = $now;

                    return $data;
                })
                ->values()
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($source['lpj_table'])->insert($chunk);
            }

            $rekapSummary = $this->rekapSummary($source, $rekapId);
            $lpjSummary = $this->lpjSummary($source, $rekapId, $rekapSummary['jumlah']);
            $totalLpj = $lpjSummary['jumlah_data'] > 0
                ? $lpjSummary['total_lpj']
                : ($sameAsRab ? $rekapSummary['jumlah'] : 0);

            $this->upsertStatus($source, $rekapId, [
                'sama_dengan_rab' => $sameAsRab,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $totalLpj,
                'selesai_at' => $sameAsRab ? $now : null,
            ]);

            return [
                'copied' => count($rows),
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $totalLpj,
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                ...$result,
                'edit_required' => ! $sameAsRab,
            ],
            'message' => $sameAsRab
                ? 'LPJ berhasil disalin dari RAB dan ditandai selesai.'
                : 'LPJ berhasil disalin dari RAB. Silakan sesuaikan detail LPJ.',
        ]);
    }

    private function updateModule(Request $request, string $module, $rekapId)
    {
        $source = $this->source($module);
        $rules = [
            'partial' => ['nullable', 'boolean'],
            'deleted_ids' => ['nullable', 'array', 'max:500'],
            'deleted_ids.*' => [
                'integer',
                Rule::exists($source['lpj_table'], 'id'),
            ],
            'items' => ['present', 'array', 'max:500'],
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists($source['lpj_table'], 'id'),
            ],
        ];

        if (in_array($source['type'], ['rumah-tangga', 'sarana-prasarana'], true)) {
            $rules['items.*.kelompok_anggaran'] = ['required', 'string', 'max:255', 'not_regex:/^\s*$/'];
        }
        if ($source['type'] === 'transportasi') {
            $rules['items.*.prioritas'] = ['required', Rule::in(['Tinggi', 'Sedang', 'Rendah', 'tinggi', 'sedang', 'rendah'])];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $source, $rekapId) {
            $rekapQuery = DB::table($source['rekap_table'])
                ->where('id', $rekapId);
            Helper::applyRelatedGenderScope(
                $rekapQuery,
                "{$source['rekap_table']}.petugas_id",
                'users'
            );
            $rekap = $rekapQuery->lockForUpdate()->first();

            if (! $rekap) {
                return null;
            }

            $columns = Schema::getColumnListing($source['lpj_table']);
            $now = now();
            $isPartial = $request->boolean('partial');

            if (! $isPartial) {
                $this->deleteLpjRows($source, $rekapId);
            } else {
                $deletedIds = collect($request->input('deleted_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($deletedIds->isNotEmpty()) {
                    $deleteQuery = DB::table($source['lpj_table'])
                        ->where('rekap_id', $rekapId)
                        ->whereIn('id', $deletedIds->all());
                    $this->applyDetailGenderScope(
                        $deleteQuery,
                        $source['lpj_table'],
                        $source['lpj_table']
                    );

                    if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
                        $deleteQuery->where('pegawai_tipe', $source['pegawai_tipe']);
                    }

                    $deleteQuery->delete();
                }
            }

            $rows = collect($request->input('items', []))
                ->map(function ($item) use ($source, $rekapId, $columns, $now, $rekap, $isPartial) {
                    $item = (array) $item;
                    $id = $this->nullableInt($item['id'] ?? null);
                    unset($item['id']);

                    $row = $this->normalizeLpjRow(
                        $source,
                        $item,
                        $rekapId,
                        $columns,
                        $now,
                        $rekap->petugas_id ?? auth()->id()
                    );

                    return [
                        'id' => $isPartial ? $id : null,
                        'data' => $row,
                    ];
                })
                ->values()
                ->all();

            if ($isPartial) {
                $updated = 0;
                $created = 0;

                foreach ($rows as $row) {
                    $id = $row['id'];
                    $data = $row['data'];

                    if ($id) {
                        $updateQuery = DB::table($source['lpj_table'])
                            ->where('rekap_id', $rekapId)
                            ->where('id', $id);
                        $this->applyDetailGenderScope(
                            $updateQuery,
                            $source['lpj_table'],
                            $source['lpj_table']
                        );

                        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
                            $updateQuery->where('pegawai_tipe', $source['pegawai_tipe']);
                        }

                        unset($data['id'], $data['created_at']);
                        $affected = $updateQuery->update($data);
                        $updated += $affected;

                        continue;
                    }

                    DB::table($source['lpj_table'])->insert($data);
                    $created++;
                }
            } else {
                $insertRows = array_map(fn ($row) => $row['data'], $rows);
                foreach (array_chunk($insertRows, 500) as $chunk) {
                    if ($chunk) {
                        DB::table($source['lpj_table'])->insert($chunk);
                    }
                }
            }

            $rekapSummary = $this->rekapSummary($source, $rekapId);
            $lpjSummary = $this->lpjSummary($source, $rekapId, $rekapSummary['jumlah']);
            $this->upsertStatus($source, $rekapId, [
                'sama_dengan_rab' => false,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selesai_at' => now(),
            ]);

            return [
                'updated' => count($rows),
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selisih' => $rekapSummary['jumlah'] - $lpjSummary['total_lpj'],
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => 'Detail LPJ berhasil diperbarui.',
        ]);
    }

    private function deleteModule(Request $request, string $module, $rekapId)
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $source = $this->source($module);
        $ids = collect($validator->validated()['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $result = DB::transaction(function () use ($ids, $source, $rekapId) {
            $rekapQuery = DB::table($source['rekap_table'])
                ->where('id', $rekapId);
            Helper::applyRelatedGenderScope(
                $rekapQuery,
                "{$source['rekap_table']}.petugas_id",
                'users'
            );
            $rekap = $rekapQuery->lockForUpdate()->first();

            if (! $rekap) {
                return null;
            }

            $deleteQuery = DB::table($source['lpj_table'])
                ->where('rekap_id', $rekapId)
                ->whereIn('id', $ids->all());
            $this->applyDetailGenderScope(
                $deleteQuery,
                $source['lpj_table'],
                $source['lpj_table']
            );

            if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
                $deleteQuery->where('pegawai_tipe', $source['pegawai_tipe']);
            }

            $deleted = $deleteQuery->delete();

            $rekapSummary = $this->rekapSummary($source, $rekapId);
            $lpjSummary = $this->lpjSummary($source, $rekapId, $rekapSummary['jumlah']);

            $this->upsertStatus($source, $rekapId, [
                'sama_dengan_rab' => false,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selesai_at' => $lpjSummary['jumlah_data'] > 0 ? now() : null,
            ]);

            return [
                'deleted' => $deleted,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selisih' => $rekapSummary['jumlah'] - $lpjSummary['total_lpj'],
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $result['deleted'].' data LPJ berhasil dihapus.',
        ]);
    }

    private function source(string $module): array
    {
        abort_unless(isset(self::MODULES[$module]), 404, 'Modul LPJ tidak ditemukan.');

        return self::MODULES[$module];
    }

    private function rabDetailQuery(array $source, $rekapId)
    {
        $query = DB::table($source['detail_table'])
            ->where("{$source['detail_table']}.rekap_id", $rekapId);
        $this->applyDetailGenderScope(
            $query,
            $source['detail_table'],
            $source['detail_table']
        );

        if ($source['pegawai_tipe']) {
            $query->where("{$source['detail_table']}.pegawai_tipe", $source['pegawai_tipe']);
        }

        return $query;
    }

    private function deleteLpjRows(array $source, $rekapId): void
    {
        $query = DB::table($source['lpj_table'])->where('rekap_id', $rekapId);

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('pegawai_tipe', $source['pegawai_tipe']);
        }

        $query->delete();
    }

    private function copyColumns(array $source): array
    {
        $sourceColumns = collect(Schema::getColumnListing($source['detail_table']));
        $lpjColumns = collect(Schema::getColumnListing($source['lpj_table']));

        return $sourceColumns
            ->intersect($lpjColumns)
            ->reject(fn ($column) => in_array($column, ['id', 'created_at', 'updated_at'], true))
            ->values()
            ->all();
    }

    private function rekapSummary(array $source, $rekapId): ?array
    {
        $rekapQuery = DB::table($source['rekap_table'])->where('id', $rekapId);
        Helper::applyRelatedGenderScope(
            $rekapQuery,
            "{$source['rekap_table']}.petugas_id",
            'users'
        );
        $rekap = $rekapQuery->first();

        if (! $rekap) {
            return null;
        }

        $summary = $this->rabDetailQuery($source, $rekapId)
            ->selectRaw('COUNT(*) as jumlah_data, COALESCE(SUM(total), 0) as total_pengeluaran')
            ->first();

        $jumlahData = (int) ($summary->jumlah_data ?? 0);
        $totalPengeluaran = (int) ($summary->total_pengeluaran ?? 0);
        $jumlahSementara = $rekap->jumlah_sementara === null ? null : (int) $rekap->jumlah_sementara;
        $jumlah = $jumlahData > 0 ? $totalPengeluaran : ($jumlahSementara ?? 0);

        return [
            'id' => (int) $rekap->id,
            'nama' => $rekap->nama,
            'bulan_tahun' => $rekap->bulan_tahun,
            'tanggal_rekap' => $rekap->tanggal_rekap,
            'tanggal_pencairan' => $rekap->tanggal_pencairan,
            'jumlah_sementara' => $jumlahSementara,
            'jumlah' => $jumlah,
            'jumlah_data' => $jumlahData,
            'total_pengeluaran' => $totalPengeluaran,
            'is_jumlah_sementara' => $jumlahData === 0,
            'keterangan' => $rekap->keterangan,
        ];
    }

    private function lpjSummary(array $source, $rekapId, int $totalRab): array
    {
        $query = DB::table($source['lpj_table'])
            ->where('rekap_id', $rekapId);
        $this->applyDetailGenderScope(
            $query,
            $source['lpj_table'],
            $source['lpj_table']
        );

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('pegawai_tipe', $source['pegawai_tipe']);
        }

        $summary = $query
            ->selectRaw('COUNT(*) as jumlah_data, COALESCE(SUM(total), 0) as total_lpj')
            ->first();
        $status = DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', $source['module_key'])
            ->where('rekap_id', $rekapId)
            ->first();

        $jumlahData = (int) ($summary->jumlah_data ?? 0);
        $totalLpj = (int) ($summary->total_lpj ?? 0);

        if ($jumlahData === 0 && $status?->sama_dengan_rab) {
            $totalLpj = (int) ($status->total_lpj ?: $totalRab);
        }

        return [
            'jumlah_data' => $jumlahData,
            'total_lpj' => $totalLpj,
            'selisih' => $totalRab - $totalLpj,
            'sama_dengan_rab' => (bool) ($status?->sama_dengan_rab ?? false),
            'selesai_at' => $status?->selesai_at,
        ];
    }

    private function lpjRows(array $source, $rekapId, ?Request $request = null)
    {
        $select = ['lpj.*'];
        $lpjColumns = Schema::getColumnListing($source['lpj_table']);

        $query = DB::table("{$source['lpj_table']} as lpj")
            ->leftJoin("{$source['rekap_table']} as rekap", 'rekap.id', '=', 'lpj.rekap_id')
            ->where('lpj.rekap_id', $rekapId);
        $this->applyDetailGenderScope($query, $source['lpj_table'], 'lpj');

        if (Schema::hasColumn($source['lpj_table'], 'petugas_id')) {
            $query->leftJoin('users as petugas', function ($join) {
                $join->on('petugas.id', '=', DB::raw('COALESCE(lpj.petugas_id, rekap.petugas_id)'));
            });
            $select[] = 'petugas.name as petugas_nama';
        } else {
            $select[] = DB::raw('NULL as petugas_nama');
        }

        if (Schema::hasColumn($source['lpj_table'], 'pegawai_id')) {
            $query
                ->leftJoin('pegawai', 'pegawai.id', '=', 'lpj.pegawai_id')
                ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
                ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
                ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');

            $select[] = 'pegawai.nama as nama_pegawai';
            $select[] = 'pegawai.kode as kode_pegawai';
            $select[] = 'pegawai.tipe as tipe_pegawai';
            $select[] = 'pegawai.nama as nama_dosen';
            $select[] = 'pegawai.kode as kode_dosen';
            $select[] = 'prodi.nama as nama_prodi_dosen';
            $select[] = 'staff.jabatan as jabatan_staff';
        } else {
            $select[] = DB::raw('NULL as nama_pegawai');
            $select[] = DB::raw('NULL as kode_pegawai');
            $select[] = DB::raw('NULL as tipe_pegawai');
            $select[] = DB::raw('NULL as nama_dosen');
            $select[] = DB::raw('NULL as kode_dosen');
            $select[] = DB::raw('NULL as nama_prodi_dosen');
            $select[] = DB::raw('NULL as jabatan_staff');
        }

        $query->select($select);

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('lpj.pegawai_tipe', $source['pegawai_tipe']);
        }

        $this->applyLpjSearchFilter($query, $source, $request, $lpjColumns);
        $this->applyLpjSorting($query, $source, $request, $lpjColumns);

        if ($request?->has('limit')) {
            $perPage = max(1, min(100, (int) $request->input('limit', 10)));
            $page = max(1, (int) $request->input('page', 1));
            $total = (clone $query)->count();
            $items = $query
                ->forPage($page, $perPage)
                ->get()
                ->map(fn ($row) => $this->normalizeLpjOutputRow($row))
                ->values();

            return new \Illuminate\Pagination\LengthAwarePaginator(
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

        return $query
            ->get()
            ->map(fn ($row) => $this->normalizeLpjOutputRow($row))
            ->values();
    }

    private function normalizeLpjOutputRow($row)
    {
        if (isset($row->lampiran) && is_string($row->lampiran)) {
            $row->lampiran = json_decode($row->lampiran, true) ?: [];
        }

        return $row;
    }

    private function applyLpjSearchFilter($query, array $source, ?Request $request, array $lpjColumns): void
    {
        $search = trim((string) $request?->input('search', ''));

        if ($search === '') {
            return;
        }

        $hasColumn = fn (string $column) => in_array($column, $lpjColumns, true);

        $query->where(function ($q) use ($search, $source, $hasColumn) {
            foreach ([
                'tanggal',
                'kategori_detail',
                'nama_kegiatan',
                'kelompok_anggaran',
                'prioritas',
                'satuan',
                'jenis_pembayaran',
                'keterangan',
            ] as $column) {
                if ($hasColumn($column)) {
                    $q->orWhere("lpj.{$column}", 'LIKE', "%{$search}%");
                }
            }

            foreach (['nominal', 'volume', 'jumlah', 'transport', 'barokah', 'total'] as $column) {
                if ($hasColumn($column)) {
                    $q->orWhere("lpj.{$column}", 'LIKE', "%{$search}%");
                }
            }

            if (Schema::hasColumn($source['lpj_table'], 'petugas_id')) {
                $q->orWhere('petugas.name', 'LIKE', "%{$search}%");
            }

            if (Schema::hasColumn($source['lpj_table'], 'pegawai_id')) {
                $q->orWhere('pegawai.nama', 'LIKE', "%{$search}%")
                    ->orWhere('pegawai.kode', 'LIKE', "%{$search}%")
                    ->orWhere('prodi.nama', 'LIKE', "%{$search}%")
                    ->orWhere('staff.jabatan', 'LIKE', "%{$search}%");
            }
        });
    }

    private function applyLpjSorting($query, array $source, ?Request $request, array $lpjColumns): void
    {
        $hasColumn = fn (string $column) => in_array($column, $lpjColumns, true);
        $sortColumns = [
            'id' => 'lpj.id',
            'tanggal' => $hasColumn('tanggal') ? 'lpj.tanggal' : 'lpj.id',
            'kategori_detail' => $hasColumn('kategori_detail') ? 'lpj.kategori_detail' : 'lpj.id',
            'kelompok_anggaran' => $hasColumn('kelompok_anggaran') ? 'lpj.kelompok_anggaran' : 'lpj.id',
            'uraian' => $hasColumn('nama_kegiatan') ? 'lpj.nama_kegiatan' : 'lpj.id',
            'volume' => $hasColumn('volume') ? 'lpj.volume' : 'lpj.id',
            'satuan' => $hasColumn('satuan') ? 'lpj.satuan' : 'lpj.id',
            'nominal' => $hasColumn('nominal') ? 'lpj.nominal' : 'lpj.id',
            'prioritas' => $hasColumn('prioritas') ? 'lpj.prioritas' : 'lpj.id',
            'jenis_pembayaran' => $hasColumn('jenis_pembayaran') ? 'lpj.jenis_pembayaran' : 'lpj.id',
            'total' => $hasColumn('total') ? 'lpj.total' : 'lpj.id',
            'keterangan' => $hasColumn('keterangan') ? 'lpj.keterangan' : 'lpj.id',
        ];

        if (Schema::hasColumn($source['lpj_table'], 'petugas_id')) {
            $sortColumns['petugas_nama'] = 'petugas.name';
        }

        if (Schema::hasColumn($source['lpj_table'], 'pegawai_id')) {
            $sortColumns['pegawai'] = 'pegawai.nama';
        }

        $sortKey = $request?->input('sort_key', 'id') ?: 'id';
        $sortOrder = $request?->input('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortColumns[$sortKey] ?? 'lpj.id', $sortOrder);
    }

    private function normalizeLpjRow(
        array $source,
        array $item,
        $rekapId,
        array $columns,
        $now,
        int $petugasId
    ): array {
        $row = match ($source['type']) {
            'tatapmuka' => $this->tatapmukaRow($item),
            'kegiatan' => $this->kegiatanRow($item),
            'rumah-tangga' => $this->rumahTanggaRow($item),
            'sarana-prasarana' => $this->saranaPrasaranaRow($item),
            'transportasi' => $this->transportasiRow($item),
            'dosen-bulanan' => $this->dosenBulananRow($item),
            default => throw new \InvalidArgumentException('Unsupported LPJ module type.'),
        };

        $row['rekap_id'] = (int) $rekapId;
        $row['rab_detail_id'] = $this->nullableInt($item['rab_detail_id'] ?? null);
        $row['petugas_id'] = $petugasId;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        if ($source['pegawai_tipe'] && in_array('pegawai_tipe', $columns, true)) {
            $row['pegawai_tipe'] = $source['pegawai_tipe'];
        }

        if (in_array('lampiran', $columns, true) && isset($row['lampiran']) && is_array($row['lampiran'])) {
            $row['lampiran'] = json_encode($row['lampiran']);
        }

        return Arr::only($row, $columns);
    }

    private function applyDetailGenderScope($query, string $table, string $alias): void
    {
        Helper::applyExpenseGenderScope($query, $table, $alias);
    }

    private function tatapmukaRow(array $item): array
    {
        $transportMotor = $this->number($item['transport_motor'] ?? $item['transport'] ?? 0);
        $hariTransportMotor = $this->number($item['hari_transport_motor'] ?? $item['hari'] ?? 0);
        $transportMobil = $this->number($item['transport_mobil'] ?? $item['transport_mobil_tanpa_tol'] ?? 0);
        $hariTransportMobil = $this->number($item['hari_transport_mobil'] ?? $item['hari_transport_mobil_tanpa_tol'] ?? 0);
        $barokahMengajarBiasa = $this->number($item['barokah_mengajar_biasa'] ?? $item['barokah'] ?? 0);
        $barokahMengajarDouble = $this->number($item['barokah_mengajar_double_degree'] ?? 0);
        $jam = $this->number($item['jam'] ?? 0);
        $jamDouble = $this->number($item['jam_mengajar_double_degree'] ?? $jam);
        $barokahUas = $this->number($item['barokah_uas'] ?? 0);
        $jumlahMahasiswaUas = $this->number($item['jumlah_mahasiswa_uas'] ?? 0);
        $barokahSempro = $this->number($item['barokah_sempro'] ?? 0);
        $jamSempro = $this->number($item['jam_sempro'] ?? ($barokahSempro > 0 ? 1 : 0));
        $total = (int) round(
            ($transportMotor * $hariTransportMotor)
            + ($transportMobil * $hariTransportMobil)
            + ($barokahMengajarBiasa * $jam)
            + ($barokahMengajarDouble * $jamDouble)
            + ($barokahUas * $jumlahMahasiswaUas)
            + ($barokahSempro * $jamSempro)
        );

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'pegawai_id' => $this->nullableInt($item['pegawai_id'] ?? null),
            'hari' => (int) round($hariTransportMotor + $hariTransportMobil),
            'hari_transport_motor' => (int) round($hariTransportMotor),
            'hari_transport_mobil' => (int) round($hariTransportMobil),
            'hari_transport_mobil_tol' => 0,
            'hari_transport_mobil_tanpa_tol' => (int) round($hariTransportMobil),
            'jam' => (int) round($jam),
            'jam_mengajar_double_degree' => (int) round($jamDouble),
            'transport' => (int) round($transportMotor + $transportMobil),
            'transport_motor' => (int) round($transportMotor),
            'transport_mobil' => (int) round($transportMobil),
            'transport_mobil_tol' => 0,
            'transport_mobil_tanpa_tol' => (int) round($transportMobil),
            'barokah' => (int) round($barokahMengajarBiasa),
            'barokah_mengajar_biasa' => (int) round($barokahMengajarBiasa),
            'barokah_mengajar_double_degree' => (int) round($barokahMengajarDouble),
            'barokah_uas' => (int) round($barokahUas),
            'jumlah_mahasiswa_uas' => (int) round($jumlahMahasiswaUas),
            'barokah_sempro' => (int) round($barokahSempro),
            'jam_sempro' => (int) round($jamSempro),
            'keterangan_sempro' => $item['keterangan_sempro'] ?? null,
            'total' => $total,
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'CUS BSI',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function kegiatanRow(array $item): array
    {
        $kategoriDetail = ($item['kategori_detail'] ?? 'pegawai') === 'non_pegawai'
            ? 'non_pegawai'
            : 'pegawai';
        $isPegawai = $kategoriDetail === 'pegawai';
        $transport = $isPegawai ? $this->number($item['transport'] ?? 0) : 0;
        $barokah = $isPegawai ? $this->number($item['barokah'] ?? 0) : 0;
        $nominal = $isPegawai ? null : (int) round($this->number($item['nominal'] ?? 0));

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kategori_detail' => $kategoriDetail,
            'pegawai_id' => $isPegawai ? $this->nullableInt($item['pegawai_id'] ?? null) : null,
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Kegiatan',
            'transport' => (int) round($transport),
            'barokah' => (int) round($barokah),
            'nominal' => $nominal,
            'total' => $isPegawai ? (int) round($transport + $barokah) : $nominal,
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? ($isPegawai ? 'CUS BSI' : 'Tunai'),
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function rumahTanggaRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kelompok_anggaran' => trim((string) ($item['kelompok_anggaran'] ?? '')),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Rumah Tangga',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function saranaPrasaranaRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kelompok_anggaran' => trim((string) ($item['kelompok_anggaran'] ?? '')),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Sarana Prasarana',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function transportasiRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'prioritas' => $this->normalizePrioritas($item['prioritas'] ?? 'Sedang'),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Transportasi',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function normalizePrioritas($value): string
    {
        return match (strtolower(trim((string) $value))) {
            'tinggi' => 'Tinggi',
            'rendah' => 'Rendah',
            default => 'Sedang',
        };
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dosenBulananRow(array $item): array
    {
        $tanggal = $item['tanggal'] ?? now()->toDateString();
        $dosenTetap = $this->number($item['barokah_dosen_tetap'] ?? 0);
        $struktural = $this->number($item['barokah_struktural'] ?? 0);

        return [
            ...$this->baseBulananRow($item, $tanggal),
            'hari' => 0,
            'barokah_harian' => 0,
            'barokah_bulanan' => 0,
            'barokah_dosen_tetap' => (int) round($dosenTetap),
            'barokah_struktural' => (int) round($struktural),
            'total' => (int) round($dosenTetap + $struktural),
        ];
    }

    private function baseBulananRow(array $item, string $tanggal): array
    {
        return [
            'tanggal' => $tanggal,
            'bulan' => (int) ($item['bulan'] ?? date('n', strtotime($tanggal))),
            'tahun' => (int) ($item['tahun'] ?? date('Y', strtotime($tanggal))),
            'pegawai_id' => $this->nullableInt($item['pegawai_id'] ?? null),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'CUS BSI',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function upsertStatus(array $source, $rekapId, array $values): void
    {
        DB::table('keuangan_pengeluaran_lpj_rekap_status')->updateOrInsert(
            [
                'module_key' => $source['module_key'],
                'rekap_id' => (int) $rekapId,
            ],
            [
                ...$values,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
