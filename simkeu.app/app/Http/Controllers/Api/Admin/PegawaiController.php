<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\PegawaiExport;
use App\Http\Controllers\Controller;
use App\Models\Dosen as DosenModel;
use App\Models\Pegawai;
use App\Models\Prodi;
use App\Models\Staff as StaffModel;
use App\Services\Absensi;
use App\Services\Dosen as SiakadDosen;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $query = Pegawai::with(['dosen.prodi', 'staff']);

        $this->applyFilters($query, $request);

        $sortable = ['id', 'nama', 'kode', 'tipe', 'jenis_kelamin', 'status', 'created_at', 'updated_at'];
        $sortKey = in_array($request->input('sort_key'), $sortable) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        $limit = (int) $request->get('limit', 10);
        if ($limit === 0) {
            $items = $query->get();
            $data = [
                'current_page' => 1,
                'data' => $items,
                'first_page_url' => null,
                'from' => $items->isEmpty() ? null : 1,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $items->count(),
                'prev_page_url' => null,
                'to' => $items->count(),
                'total' => $items->count(),
            ];
        } else {
            $data = $query->paginate($limit);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $this->stats($request),
            'message' => 'Pegawai retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        [$validator, $payload] = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $pegawai = DB::transaction(function () use ($payload) {
            $pegawai = Pegawai::create($this->pegawaiPayload($payload));
            $this->syncDetail($pegawai, $payload);

            return $pegawai->load(['dosen.prodi', 'staff']);
        });

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai created successfully',
        ], 201);
    }

    public function show($id)
    {
        $pegawai = Pegawai::with(['dosen.prodi', 'staff'])->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai retrieved successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $pegawai = Pegawai::with(['dosen', 'staff'])->find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        [$validator, $payload] = $this->validator($request, $pegawai);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $pegawai = DB::transaction(function () use ($pegawai, $payload) {
            $pegawai->fill($this->pegawaiPayload($payload));
            $pegawai->save();

            $this->syncDetail($pegawai, $payload);

            return $pegawai->load(['dosen.prodi', 'staff']);
        });

        return response()->json([
            'status' => true,
            'data' => $pegawai,
            'message' => 'Pegawai updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $pegawai = Pegawai::find($id);

        if (! $pegawai) {
            return response()->json([
                'status' => false,
                'message' => 'Pegawai Not Found',
            ], 404);
        }

        $pegawai->delete();

        return response()->json([
            'status' => true,
            'message' => 'Pegawai deleted successfully',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Pegawai::with(['dosen.prodi', 'staff']);
        $this->applyFilters($query, $request);

        $sortable = ['id', 'nama', 'kode', 'tipe', 'jenis_kelamin', 'status', 'created_at', 'updated_at'];
        $sortKey = in_array($request->input('sort_key'), $sortable, true) ? $request->input('sort_key') : 'id';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $data = $query->orderBy($sortKey, $sortOrder)->get();

        return Excel::download(new PegawaiExport($data, $request->input('tipe')), $this->pegawaiExportFileName($request));
    }

    public function importExcel(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'tipe' => ['nullable', Rule::in(['dosen', 'staff'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $fixedTipe = $request->input('tipe');
        $rows = $this->pegawaiImportRows($request->file('file'));

        if (! $rows) {
            return response()->json([
                'status' => false,
                'message' => 'File import tidak memiliki data.',
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $payload = $this->pegawaiImportPayload($row['data'], $fixedTipe);

            if (! $payload) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: kolom nama, kode, dan tipe wajib diisi.";
                continue;
            }

            if ($fixedTipe && $payload['tipe'] !== $fixedTipe) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: tipe harus {$fixedTipe}.";
                continue;
            }

            $pegawai = $this->findPegawaiForImport($row['data'], $payload['kode']);
            [$rowValidator] = $this->validator(new Request($payload), $pegawai);

            if ($rowValidator->fails()) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: " . collect($rowValidator->errors()->all())->implode('; ');
                continue;
            }

            $payload = $rowValidator->validated();
            $wasUpdate = (bool) $pegawai;

            try {
                DB::transaction(function () use ($pegawai, $payload) {
                    if ($pegawai) {
                        $pegawai->fill($this->pegawaiPayload($payload));
                        $pegawai->save();
                    } else {
                        $pegawai = Pegawai::create($this->pegawaiPayload($payload));
                    }

                    $this->syncDetail($pegawai, $payload);
                });

                if ($wasUpdate) {
                    $updated++;
                } else {
                    $created++;
                }
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = "Baris {$row['number']}: gagal disimpan ({$exception->getMessage()}).";
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 20),
                'error_count' => count($errors),
            ],
            'message' => "Import selesai. {$created} data baru, {$updated} data diperbarui, {$skipped} dilewati.",
        ]);
    }

    public function syncDosenSiakad(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $batchSize = max(50, min((int) $request->input('batch_size', 200), 1000));
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        $dosenSiakad = $this->siakadDosenSources();

        if ($ids->isNotEmpty()) {
            $selected = array_fill_keys($ids->all(), true);
            $dosenSiakad = $dosenSiakad->filter(function ($source) use ($selected) {
                $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);

                return $kode && isset($selected[(string) $kode]);
            })->values();
        }

        if ($dosenSiakad->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Data dosen SIAKAD kosong atau tidak dapat diambil.',
            ], 422);
        }

        $prodiMap = $this->prodiMap();
        $seenKode = [];
        $seenNidn = [];
        $seenEmail = [];
        $existingNidn = DosenModel::whereNotNull('nidn')
            ->where('nidn', '!=', '')
            ->pluck('kode', 'nidn')
            ->all();
        $existingEmail = Pegawai::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['kode', 'email'])
            ->mapWithKeys(fn ($pegawai) => [mb_strtolower($pegawai->email) => $pegawai->kode])
            ->all();

        $rows = [];
        $skipped = 0;

        foreach ($dosenSiakad as $source) {
            $mapped = $this->mapSiakadDosen($source, $prodiMap);

            if (! $mapped) {
                $skipped++;
                continue;
            }

            $kode = $mapped['pegawai']['kode'];
            if (isset($seenKode[$kode])) {
                $skipped++;
                continue;
            }

            $nidn = $mapped['dosen']['nidn'];
            if ($nidn && (isset($seenNidn[$nidn]) || (isset($existingNidn[$nidn]) && $existingNidn[$nidn] !== $kode))) {
                $mapped['dosen']['nidn'] = null;
            }

            if ($mapped['dosen']['nidn']) {
                $seenNidn[$mapped['dosen']['nidn']] = true;
            }

            $emailKey = $mapped['pegawai']['email'] ? mb_strtolower($mapped['pegawai']['email']) : null;
            if ($emailKey && (isset($seenEmail[$emailKey]) || (isset($existingEmail[$emailKey]) && $existingEmail[$emailKey] !== $kode))) {
                $mapped['pegawai']['email'] = null;
            }

            if ($mapped['pegawai']['email']) {
                $seenEmail[mb_strtolower($mapped['pegawai']['email'])] = true;
            }

            $seenKode[$kode] = true;
            $rows[] = $mapped;
        }

        $created = 0;
        $updated = 0;
        $synced = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$created, &$updated, &$synced) {
                $now = now();
                $codes = array_column(array_column($chunk, 'pegawai'), 'kode');
                $existingPegawai = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');

                $pegawaiRows = array_map(function ($row) use ($now) {
                    return array_merge($this->pegawaiPayload($row['pegawai']), [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }, $chunk);

                Pegawai::upsert(
                    $pegawaiRows,
                    ['kode'],
                    $this->pegawaiUpsertUpdateColumns()
                );

                $pegawaiIds = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');
                DB::table('staff')->whereIn('pegawai_id', $pegawaiIds->values())->delete();
                DosenModel::whereIn('kode', $codes)
                    ->whereNotIn('pegawai_id', $pegawaiIds->values())
                    ->delete();

                $dosenRows = [];
                foreach ($chunk as $row) {
                    $pegawaiId = $pegawaiIds[$row['pegawai']['kode']] ?? null;
                    if (! $pegawaiId) {
                        continue;
                    }

                    $dosenRows[] = array_merge($row['dosen'], [
                        'pegawai_id' => $pegawaiId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($dosenRows) {
                    DosenModel::upsert(
                        $dosenRows,
                        ['pegawai_id'],
                        [
                            'kode',
                            'nidn',
                            'gelar_depan',
                            'gelar_belakang',
                            'prodi_id',
                            'updated_at',
                        ]
                    );
                }

                $chunkCreated = collect($codes)->reject(fn ($kode) => $existingPegawai->has($kode))->count();
                $created += $chunkCreated;
                $updated += count($codes) - $chunkCreated;
                $synced += count($codes);
            });
        }

        return response()->json([
            'status' => true,
            'data' => [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'batch_size' => $batchSize,
            ],
            'message' => "Sinkronisasi dosen SIAKAD selesai. {$synced} data diproses.",
        ]);
    }

    public function previewDosenSiakad(Request $request)
    {
        try {
            $page = max((int) $request->input('page', 1), 1);
            $perPage = max(1, min((int) $request->input('per_page', $request->input('limit', 10)), 100));
            $sources = $this->filteredSiakadDosenSources($request);
            $total = $sources->count();
            $items = $sources
                ->forPage($page, $perPage)
                ->values();
            $codes = $items
                ->map(fn ($item) => $this->sourceValue($item, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']))
                ->filter()
                ->map(fn ($kode) => (string) $kode)
                ->unique()
                ->values();
            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->get(['id', 'kode', 'tipe'])
                ->keyBy('kode');

            return response()->json([
                'status' => true,
                'data' => [
                    'current_page' => $page,
                    'data' => $items
                        ->map(fn ($item) => $this->siakadDosenPreviewRow($item, $existing))
                        ->filter()
                        ->values()
                        ->all(),
                    'from' => $total ? (($page - 1) * $perPage) + 1 : null,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'to' => min($page * $perPage, $total),
                    'total' => $total,
                ],
                'message' => 'Data dosen SIAKAD berhasil diambil.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil data dosen SIAKAD.',
            ], 422);
        }
    }

    public function dosenSiakadIds(Request $request)
    {
        try {
            $sources = $this->filteredSiakadDosenSources($request);
            $codes = $sources
                ->map(fn ($item) => $this->sourceValue($item, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']))
                ->filter()
                ->map(fn ($kode) => (string) $kode)
                ->unique()
                ->values();
            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->pluck('tipe', 'kode');
            $skippedConflict = 0;
            $ids = [];

            foreach ($codes as $kode) {
                if ($existing->has($kode) && $existing[$kode] !== 'dosen') {
                    $skippedConflict++;
                    continue;
                }

                $ids[] = $kode;
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'ids' => $ids,
                    'total' => count($ids),
                    'skipped_conflict' => $skippedConflict,
                ],
                'message' => count($ids) . ' data dosen SIAKAD dipilih.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil ID dosen SIAKAD.',
            ], 422);
        }
    }

    public function previewStaffAbsensi(Request $request)
    {
        try {
            $page = max((int) $request->input('page', 1), 1);
            $perPage = max(1, min((int) $request->input('per_page', $request->input('limit', 10)), 100));
            $query = $this->staffAbsensiQuery($request, $page, $perPage);

            $payload = $this->absensiPaginatorPayload(Absensi::users($query), $page, $perPage);
            $codes = collect($payload['data'])
                ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            $existing = Pegawai::query()
                ->whereIn('kode', $codes)
                ->get(['id', 'kode', 'tipe'])
                ->keyBy('kode');

            $payload['data'] = collect($payload['data'])
                ->map(fn ($item) => $this->absensiPreviewRow($item, $existing))
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'status' => true,
                'data' => $payload,
                'message' => 'Data staff Web Absensi berhasil diambil.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil data staff Web Absensi.',
            ], 422);
        }
    }

    public function staffAbsensiIds(Request $request)
    {
        try {
            $ids = [];
            $page = 1;
            $perPage = 100;
            $skippedConflict = 0;
            $maxPages = 500;
            $seenPageSignatures = [];

            do {
                $payload = $this->absensiPaginatorPayload(
                    Absensi::users($this->staffAbsensiQuery($request, $page, $perPage)),
                    $page,
                    $perPage
                );
                $items = collect($payload['data']);

                if ($items->isEmpty()) {
                    break;
                }

                $signature = $this->absensiItemsSignature($items);
                if ($signature && isset($seenPageSignatures[$signature])) {
                    break;
                }

                if ($signature) {
                    $seenPageSignatures[$signature] = true;
                }

                $codes = $items
                    ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();
                $existing = Pegawai::query()
                    ->whereIn('kode', $codes)
                    ->pluck('tipe', 'kode');

                foreach ($codes as $kode) {
                    if ($existing->has($kode) && $existing[$kode] !== 'staff') {
                        $skippedConflict++;
                        continue;
                    }

                    $ids[$kode] = true;
                }

                $page++;
            } while ($page <= (int) $payload['last_page'] && $page <= $maxPages);

            $ids = array_keys($ids);

            return response()->json([
                'status' => true,
                'data' => [
                    'ids' => $ids,
                    'total' => count($ids),
                    'skipped_conflict' => $skippedConflict,
                ],
                'message' => count($ids) . ' data staff Web Absensi dipilih.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal mengambil ID staff Web Absensi.',
            ], 422);
        }
    }

    public function syncStaffAbsensi(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'batch_size' => ['nullable', 'integer', 'min:50', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $batchSize = max(50, min((int) $request->input('batch_size', 200), 1000));
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Tidak ada data staff Web Absensi yang dipilih.',
            ], 422);
        }

        $existingTipe = Pegawai::query()
            ->whereIn('kode', $ids)
            ->pluck('tipe', 'kode')
            ->all();
        $existingEmail = Pegawai::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['kode', 'email'])
            ->mapWithKeys(fn ($pegawai) => [mb_strtolower($pegawai->email) => $pegawai->kode])
            ->all();

        [$sources, $failedFetches] = $this->absensiStaffSourcesForSync($ids->all());
        $rows = [];
        $seenKode = [];
        $seenEmail = [];
        $skipped = $failedFetches;

        foreach ($sources as $source) {
            $departemenId = $this->sourceValue($source, ['departemen_id', 'department_id', 'departemen.id', 'department.id']);
            if ($departemenId && $departemenId !== '2') {
                $skipped++;
                continue;
            }

            $mapped = $this->mapAbsensiStaff($source);
            if (! $mapped) {
                $skipped++;
                continue;
            }

            $kode = $mapped['pegawai']['kode'];
            if (isset($seenKode[$kode])) {
                $skipped++;
                continue;
            }

            if (isset($existingTipe[$kode]) && $existingTipe[$kode] !== 'staff') {
                $skipped++;
                continue;
            }

            $emailKey = $mapped['pegawai']['email'] ? mb_strtolower($mapped['pegawai']['email']) : null;
            if ($emailKey && (isset($seenEmail[$emailKey]) || (isset($existingEmail[$emailKey]) && $existingEmail[$emailKey] !== $kode))) {
                $mapped['pegawai']['email'] = null;
            }

            if ($mapped['pegawai']['email']) {
                $seenEmail[mb_strtolower($mapped['pegawai']['email'])] = true;
            }

            $seenKode[$kode] = true;
            $rows[] = $mapped;
        }

        if (! $rows) {
            return response()->json([
                'status' => false,
                'data' => [
                    'synced' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => $skipped,
                    'failed_fetches' => $failedFetches,
                    'batch_size' => $batchSize,
                ],
                'message' => 'Tidak ada data staff Web Absensi yang dapat disinkronkan.',
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $synced = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, &$created, &$updated, &$synced) {
                $now = now();
                $codes = array_column(array_column($chunk, 'pegawai'), 'kode');
                $existingPegawai = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');

                $pegawaiRows = array_map(function ($row) use ($now) {
                    return array_merge($this->pegawaiPayload($row['pegawai']), [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }, $chunk);

                Pegawai::upsert(
                    $pegawaiRows,
                    ['kode'],
                    $this->pegawaiUpsertUpdateColumns()
                );

                $pegawaiIds = Pegawai::whereIn('kode', $codes)->pluck('id', 'kode');
                DosenModel::whereIn('pegawai_id', $pegawaiIds->values())->delete();

                $staffRows = [];
                foreach ($chunk as $row) {
                    $pegawaiId = $pegawaiIds[$row['pegawai']['kode']] ?? null;
                    if (! $pegawaiId) {
                        continue;
                    }

                    $staffRows[] = array_merge($row['staff'], [
                        'pegawai_id' => $pegawaiId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($staffRows) {
                    StaffModel::upsert(
                        $staffRows,
                        ['pegawai_id'],
                        [
                            'jabatan',
                            'updated_at',
                        ]
                    );
                }

                $chunkCreated = collect($codes)->reject(fn ($kode) => $existingPegawai->has($kode))->count();
                $created += $chunkCreated;
                $updated += count($codes) - $chunkCreated;
                $synced += count($codes);
            });
        }

        return response()->json([
            'status' => true,
            'data' => [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed_fetches' => $failedFetches,
                'batch_size' => $batchSize,
            ],
            'message' => "Sinkronisasi staff Web Absensi selesai. {$synced} data diproses.",
        ]);
    }

    private function pegawaiExportFileName(Request $request): string
    {
        return match ($request->input('tipe')) {
            'dosen' => 'Data Dosen.xlsx',
            'staff' => 'Data Staff.xlsx',
            default => 'Data Pegawai.xlsx',
        };
    }

    private function pegawaiImportRows($file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);

        if (! $rawRows) {
            return [];
        }

        $headings = array_shift($rawRows);
        $columnKeys = [];

        foreach ($headings as $column => $heading) {
            $key = $this->normalizeImportHeading($heading);
            if ($key) {
                $columnKeys[$column] = $key;
            }
        }

        if (! $columnKeys) {
            return [];
        }

        $rows = [];
        $rowNumber = 2;

        foreach ($rawRows as $rawRow) {
            $row = [];

            foreach ($columnKeys as $column => $key) {
                $row[$key] = $rawRow[$column] ?? null;
            }

            if (! $this->isImportRowEmpty($row)) {
                $rows[] = [
                    'number' => $rowNumber,
                    'data' => $row,
                ];
            }

            $rowNumber++;
        }

        return $rows;
    }

    private function pegawaiImportPayload(array $row, ?string $fixedTipe): ?array
    {
        $tipe = $this->normalizeTipe($this->importValue($row, ['tipe', 'type'])) ?: $fixedTipe;
        $kode = $this->importValue($row, ['kode', 'kode_pegawai', 'niy', 'nip']);
        $nama = $this->importValue($row, ['nama', 'nama_pegawai', 'name']);

        if (! $tipe || ! $kode || ! $nama) {
            return null;
        }

        $payload = [
            'nama' => $nama,
            'jenis_kelamin' => $this->normalizeJenisKelamin($this->importValue($row, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
            'tipe' => $tipe,
            'kode' => $kode,
            'tempat_lahir' => $this->nullableImportValue($row, ['tempat_lahir', 'tmp_lahir']),
            'tanggal_lahir' => $this->normalizeImportDate($this->importValue($row, ['tanggal_lahir', 'tgl_lahir'])),
            'alamat' => $this->nullableImportValue($row, ['alamat', 'alamat_lengkap']),
            'email' => $this->normalizeEmail($this->importValue($row, ['email'])),
            'hp' => $this->nullableImportValue($row, ['hp', 'no_hp', 'telepon', 'telp']),
            'nomer_rekening' => $this->nullableImportValue($row, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
            'nama_pemilik_rekening' => $this->nullableImportValue($row, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
            'bank' => $this->nullableImportValue($row, ['bank', 'nama_bank']),
            'status' => $this->normalizeStatus($this->importValue($row, ['status', 'aktif'])),
        ];

        if ($tipe === 'dosen') {
            $payload += [
                'dosen_kode' => $this->nullableImportValue($row, ['dosen_kode', 'kode_dosen']) ?: $kode,
                'nidn' => $this->nullableImportValue($row, ['nidn']),
                'gelar_depan' => $this->nullableImportValue($row, ['gelar_depan', 'gelar_dpn']),
                'gelar_belakang' => $this->nullableImportValue($row, ['gelar_belakang', 'gelar_blk', 'gelar']),
                'prodi_id' => $this->resolveImportProdiId($row),
            ];
        }

        if ($tipe === 'staff') {
            $payload['jabatan'] = $this->nullableImportValue($row, ['jabatan', 'position', 'posisi']);
        }

        return $payload;
    }

    private function findPegawaiForImport(array $row, string $kode): ?Pegawai
    {
        $id = $this->importValue($row, ['id', 'pegawai_id', 'id_pegawai']);

        if (is_numeric($id)) {
            $pegawai = Pegawai::with(['dosen', 'staff'])->find((int) $id);
            if ($pegawai) {
                return $pegawai;
            }
        }

        return Pegawai::with(['dosen', 'staff'])->where('kode', $kode)->first();
    }

    private function resolveImportProdiId(array $row): ?int
    {
        $prodiId = $this->importValue($row, ['prodi_id', 'id_prodi']);
        if (is_numeric($prodiId)) {
            return (int) $prodiId;
        }

        $lookup = $this->normalizeLookupKey($this->importValue($row, ['prodi_kode', 'kode_prodi', 'prodi_nama', 'nama_prodi', 'prodi']));
        if (! $lookup) {
            return null;
        }

        return $this->prodiMap()[$lookup] ?? null;
    }

    private function normalizeImportHeading($heading): ?string
    {
        $normalized = mb_strtolower(trim((string) $heading));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);
        $normalized = trim($normalized, '_');

        $aliases = [
            'pegawai_id' => 'id',
            'id_pegawai' => 'id',
            'pegawai_tipe' => 'tipe',
            'pegawai_type' => 'tipe',
            'pegawai_kode' => 'kode',
            'kode_pegawai' => 'kode',
            'pegawai_nama' => 'nama',
            'nama_pegawai' => 'nama',
            'pegawai_jenis_kelamin' => 'jenis_kelamin',
            'pegawai_status' => 'status',
            'pegawai_tempat_lahir' => 'tempat_lahir',
            'pegawai_tanggal_lahir' => 'tanggal_lahir',
            'pegawai_alamat' => 'alamat',
            'pegawai_email' => 'email',
            'pegawai_hp' => 'hp',
            'pegawai_no_hp' => 'hp',
            'pegawai_telepon' => 'hp',
            'pegawai_telp' => 'hp',
            'pegawai_nomer_rekening' => 'nomer_rekening',
            'pegawai_nomor_rekening' => 'nomer_rekening',
            'pegawai_no_rekening' => 'nomer_rekening',
            'pegawai_rekening' => 'nomer_rekening',
            'nomor_rekening' => 'nomer_rekening',
            'no_rekening' => 'nomer_rekening',
            'rekening' => 'nomer_rekening',
            'pegawai_nama_pemilik_rekening' => 'nama_pemilik_rekening',
            'pegawai_nama_rekening' => 'nama_pemilik_rekening',
            'pegawai_atas_nama_rekening' => 'nama_pemilik_rekening',
            'pegawai_atas_nama' => 'nama_pemilik_rekening',
            'nama_rekening' => 'nama_pemilik_rekening',
            'atas_nama_rekening' => 'nama_pemilik_rekening',
            'atas_nama' => 'nama_pemilik_rekening',
            'pegawai_bank' => 'bank',
            'pegawai_nama_bank' => 'bank',
            'kode_dosen' => 'dosen_kode',
            'dosen_nidn' => 'nidn',
            'dosen_gelar_depan' => 'gelar_depan',
            'dosen_gelar_dpn' => 'gelar_depan',
            'dosen_gelar_belakang' => 'gelar_belakang',
            'dosen_gelar_blk' => 'gelar_belakang',
            'dosen_gelar' => 'gelar_belakang',
            'dosen_prodi_id' => 'prodi_id',
            'dosen_id_prodi' => 'prodi_id',
            'dosen_prodi_kode' => 'prodi_kode',
            'dosen_kode_prodi' => 'prodi_kode',
            'dosen_prodi_nama' => 'prodi_nama',
            'dosen_nama_prodi' => 'prodi_nama',
            'dosen_prodi' => 'prodi_nama',
            'kode_prodi' => 'prodi_kode',
            'nama_prodi' => 'prodi_nama',
            'prodi' => 'prodi_nama',
            'staff_jabatan' => 'jabatan',
            'jabatan_staff' => 'jabatan',
            'position' => 'jabatan',
            'posisi' => 'jabatan',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function importValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function nullableImportValue(array $row, array $keys): ?string
    {
        return $this->importValue($row, $keys) ?: null;
    }

    private function normalizeTipe(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['dosen', 'lecturer'], true)) {
            return 'dosen';
        }

        if (in_array($normalized, ['staff', 'staf', 'pegawai'], true)) {
            return 'staff';
        }

        return null;
    }

    private function normalizeImportDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->normalizeDate($value);
    }

    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));

            $query->where(function ($q) use ($term) {
                $q->where('nama', 'LIKE', "%{$term}%")
                    ->orWhere('kode', 'LIKE', "%{$term}%")
                    ->orWhere('tempat_lahir', 'LIKE', "%{$term}%")
                    ->orWhere('alamat', 'LIKE', "%{$term}%")
                    ->orWhere('email', 'LIKE', "%{$term}%")
                    ->orWhere('hp', 'LIKE', "%{$term}%")
                    ->orWhere('nomer_rekening', 'LIKE', "%{$term}%")
                    ->orWhere('bank', 'LIKE', "%{$term}%")
                    ->orWhereHas('dosen', function ($dosen) use ($term) {
                        $dosen->where('kode', 'LIKE', "%{$term}%")
                            ->orWhere('nidn', 'LIKE', "%{$term}%")
                            ->orWhere('gelar_depan', 'LIKE', "%{$term}%")
                            ->orWhere('gelar_belakang', 'LIKE', "%{$term}%")
                            ->orWhereHas('prodi', function ($prodi) use ($term) {
                                $prodi->where('nama', 'LIKE', "%{$term}%")
                                    ->orWhere('kode', 'LIKE', "%{$term}%");
                            });
                    })
                    ->orWhereHas('staff', function ($staff) use ($term) {
                        $staff->where('jabatan', 'LIKE', "%{$term}%");
                    });

                if ($this->hasNamaPemilikRekeningColumn()) {
                    $q->orWhere('nama_pemilik_rekening', 'LIKE', "%{$term}%");
                }
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->input('tipe'));
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('prodi_id')) {
            $query->whereHas('dosen', function ($dosen) use ($request) {
                $dosen->where('prodi_id', $request->input('prodi_id'));
            });
        }
    }

    private function stats(Request $request): array
    {
        $query = Pegawai::query();
        $this->applyFilters($query, $request);

        return [
            'total' => (clone $query)->count(),
            'dosen' => (clone $query)->where('tipe', 'dosen')->count(),
            'staff' => (clone $query)->where('tipe', 'staff')->count(),
            'aktif' => (clone $query)->where('status', 'aktif')->count(),
            'tidak_aktif' => (clone $query)->where('status', 'tidak aktif')->count(),
        ];
    }

    private function validator(Request $request, ?Pegawai $pegawai = null): array
    {
        $payload = $request->all();

        if (($payload['tipe'] ?? null) === 'dosen' && empty($payload['dosen_kode'])) {
            $payload['dosen_kode'] = $payload['kode'] ?? null;
        }

        $pegawaiId = $pegawai?->id;
        $dosenId = $pegawai?->dosen?->id;

        $validator = Validator::make($payload, [
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'tipe' => ['required', Rule::in(['dosen', 'staff'])],
            'kode' => ['required', 'string', 'max:255', Rule::unique('pegawai', 'kode')->ignore($pegawaiId)],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('pegawai', 'email')->ignore($pegawaiId)],
            'hp' => ['nullable', 'string', 'max:255'],
            'nomer_rekening' => ['nullable', 'string', 'max:255'],
            'nama_pemilik_rekening' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'tidak aktif'])],
            'dosen_kode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dosen', 'kode')->ignore($dosenId),
            ],
            'nidn' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('dosen', 'nidn')->ignore($dosenId),
            ],
            'gelar_depan' => ['nullable', 'string', 'max:255'],
            'gelar_belakang' => ['nullable', 'string', 'max:255'],
            'prodi_id' => ['nullable', 'exists:prodi,id'],
            'jabatan' => [
                Rule::requiredIf(($payload['tipe'] ?? null) === 'staff'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return [$validator, $payload];
    }

    private function pegawaiPayload(array $payload): array
    {
        $columns = [
            'nama',
            'jenis_kelamin',
            'tipe',
            'kode',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'email',
            'hp',
            'nomer_rekening',
            'bank',
            'status',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            $columns[] = 'nama_pemilik_rekening';
        }

        return Arr::only($payload, $columns);
    }

    private function pegawaiUpsertUpdateColumns(): array
    {
        $columns = [
            'nama',
            'jenis_kelamin',
            'tipe',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'email',
            'hp',
            'nomer_rekening',
            'bank',
            'status',
            'updated_at',
        ];

        if ($this->hasNamaPemilikRekeningColumn()) {
            array_splice($columns, 9, 0, ['nama_pemilik_rekening']);
        }

        return $columns;
    }

    private function hasNamaPemilikRekeningColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('pegawai', 'nama_pemilik_rekening');
        }

        return $hasColumn;
    }

    private function syncDetail(Pegawai $pegawai, array $payload): void
    {
        if ($payload['tipe'] === 'dosen') {
            $pegawai->staff()->delete();
            $pegawai->dosen()->updateOrCreate(
                ['pegawai_id' => $pegawai->id],
                [
                    'kode' => $payload['dosen_kode'] ?? $payload['kode'],
                    'nidn' => $payload['nidn'] ?? null,
                    'gelar_depan' => $payload['gelar_depan'] ?? null,
                    'gelar_belakang' => $payload['gelar_belakang'] ?? null,
                    'prodi_id' => $payload['prodi_id'] ?? null,
                ]
            );

            return;
        }

        $pegawai->dosen()->delete();
        $pegawai->staff()->updateOrCreate(
            ['pegawai_id' => $pegawai->id],
            ['jabatan' => $payload['jabatan'] ?? null]
        );
    }

    private function mapSiakadDosen($source, array $prodiMap): ?array
    {
        $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);
        $nama = $this->sourceValue($source, ['nama', 'nama_dosen', 'name']);

        if (! $kode || ! $nama) {
            return null;
        }

        $prodiName = $this->sourceValue($source, [
            'nama_prodi',
            'prodi_nama',
            'program_studi',
            'prodi',
            'prodi.nama',
            'prodi.alias',
            'prodi.kode',
        ]);
        $prodiKey = $this->normalizeLookupKey($prodiName);
        $nidn = $this->sourceValue($source, ['nidn', 'nidn_dosen']);

        return [
            'pegawai' => [
                'nama' => $nama,
                'jenis_kelamin' => $this->normalizeJenisKelamin($this->sourceValue($source, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
                'tipe' => 'dosen',
                'kode' => $kode,
                'tempat_lahir' => $this->sourceValue($source, ['tempat_lahir', 'tmp_lahir']),
                'tanggal_lahir' => $this->normalizeDate($this->sourceValue($source, ['tanggal_lahir', 'tgl_lahir', 'lahir_tanggal'])),
                'alamat' => $this->sourceValue($source, ['alamat', 'alamat_lengkap']),
                'email' => $this->normalizeEmail($this->sourceValue($source, ['email', 'email_dosen'])),
                'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'telepon', 'telp']),
                'nomer_rekening' => $this->sourceValue($source, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
                'nama_pemilik_rekening' => $this->sourceValue($source, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
                'bank' => $this->sourceValue($source, ['bank', 'nama_bank']),
                'status' => $this->normalizeStatus($this->sourceValue($source, ['status', 'aktif'])),
            ],
            'dosen' => [
                'kode' => $kode,
                'nidn' => $nidn ?: null,
                'gelar_depan' => $this->sourceValue($source, ['gelar_depan', 'gelar_dpn']),
                'gelar_belakang' => $this->sourceValue($source, ['gelar_belakang', 'gelar_blk', 'gelar']),
                'prodi_id' => $prodiKey ? ($prodiMap[$prodiKey] ?? null) : null,
            ],
        ];
    }

    private function siakadDosenSources()
    {
        return collect(SiakadDosen::all() ?? []);
    }

    private function filteredSiakadDosenSources(Request $request)
    {
        $sources = $this->siakadDosenSources();

        if ($request->filled('search')) {
            $term = mb_strtolower(trim((string) $request->input('search')));
            $sources = $sources->filter(function ($source) use ($term) {
                $haystacks = [
                    $this->sourceValue($source, ['nama', 'nama_dosen', 'name']),
                    $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']),
                    $this->sourceValue($source, ['nidn', 'nidn_dosen']),
                    $this->sourceValue($source, ['email', 'email_dosen']),
                    $this->sourceValue($source, ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode']),
                ];

                return collect($haystacks)
                    ->filter()
                    ->contains(fn ($value) => str_contains(mb_strtolower($value), $term));
            });
        }

        $sortKey = $request->input('order_by', $request->input('sort_key', 'nama'));
        $sortOrder = $request->input('order_dir', $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortMap = [
            'id' => ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip'],
            'kode' => ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip'],
            'nama' => ['nama', 'nama_dosen', 'name'],
            'name' => ['nama', 'nama_dosen', 'name'],
            'nidn' => ['nidn', 'nidn_dosen'],
            'prodi' => ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode'],
        ];
        $keys = $sortMap[$sortKey] ?? $sortMap['nama'];
        $sources = $sources->sortBy(
            fn ($source) => mb_strtolower($this->sourceValue($source, $keys) ?? ''),
            SORT_NATURAL,
            $sortOrder === 'desc'
        );

        return $sources->values();
    }

    private function siakadDosenPreviewRow($source, $existing): ?array
    {
        $kode = $this->sourceValue($source, ['kode', 'kode_dosen', 'dosen_kode', 'niy', 'nip']);
        $nama = $this->sourceValue($source, ['nama', 'nama_dosen', 'name']);

        if (! $kode || ! $nama) {
            return null;
        }

        $kode = (string) $kode;
        $pegawai = $existing->get($kode);
        $existingTipe = $pegawai?->tipe;

        return [
            'id' => $kode,
            'kode' => $kode,
            'nama' => $nama,
            'nidn' => $this->sourceValue($source, ['nidn', 'nidn_dosen']),
            'email' => $this->sourceValue($source, ['email', 'email_dosen']),
            'prodi' => $this->sourceValue($source, ['nama_prodi', 'prodi_nama', 'program_studi', 'prodi', 'prodi.nama', 'prodi.alias', 'prodi.kode']) ?: '-',
            'exists' => (bool) $pegawai,
            'existing_pegawai_id' => $pegawai?->id,
            'existing_tipe' => $existingTipe,
            'can_sync' => ! $pegawai || $existingTipe === 'dosen',
        ];
    }

    private function absensiOrderBy($key): string
    {
        $map = [
            'id' => 'id',
            'kode' => 'id',
            'nama' => 'name',
            'name' => 'name',
            'role' => 'role',
            'departemen' => 'departemen',
            'created_at' => 'created_at',
        ];

        return $map[trim((string) $key)] ?? 'name';
    }

    private function staffAbsensiQuery(Request $request, int $page, int $perPage): array
    {
        return [
            'departemen_id' => 2,
            'page' => $page,
            'per_page' => $perPage,
            'search' => $request->input('search'),
            'order_by' => $this->absensiOrderBy($request->input('order_by', $request->input('sort_key', 'name'))),
            'order_dir' => $request->input('order_dir', $request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function absensiStaffSourcesForSync(array $ids): array
    {
        $wanted = collect($ids)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($wanted->count() <= 10) {
            return $this->absensiStaffDetailSourcesForSync($wanted->all());
        }

        $remaining = array_fill_keys($wanted->all(), true);
        $sources = [];
        $page = 1;
        $perPage = 100;
        $maxPages = 500;
        $seenPageSignatures = [];

        do {
            $payload = $this->absensiPaginatorPayload(
                Absensi::users([
                    'departemen_id' => 2,
                    'page' => $page,
                    'per_page' => $perPage,
                    'order_by' => 'id',
                    'order_dir' => 'asc',
                ]),
                $page,
                $perPage
            );
            $items = collect($payload['data']);

            if ($items->isEmpty()) {
                break;
            }

            $signature = $this->absensiItemsSignature($items);
            if ($signature && isset($seenPageSignatures[$signature])) {
                break;
            }

            if ($signature) {
                $seenPageSignatures[$signature] = true;
            }

            foreach ($items as $source) {
                $kode = $this->sourceValue($source, ['id', 'user_id']);
                if (! $kode) {
                    continue;
                }

                $kode = (string) $kode;
                if (! isset($remaining[$kode]) || isset($sources[$kode])) {
                    continue;
                }

                $sources[$kode] = $source;
                unset($remaining[$kode]);
            }

            if (! $remaining) {
                break;
            }

            $page++;
        } while ($page <= (int) $payload['last_page'] && $page <= $maxPages);

        return [array_values($sources), count($remaining)];
    }

    private function absensiStaffDetailSourcesForSync(array $ids): array
    {
        $sources = [];
        $failedFetches = 0;

        foreach ($ids as $id) {
            try {
                $sources[] = $this->absensiUserPayload(Absensi::user($id));
            } catch (\Throwable) {
                $failedFetches++;
            }
        }

        return [$sources, $failedFetches];
    }

    private function absensiItemsSignature($items): ?string
    {
        $signature = collect($items)
            ->map(fn ($item) => $this->sourceValue($item, ['id', 'user_id']))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->implode('|');

        return $signature !== '' ? $signature : null;
    }

    private function absensiPaginatorPayload($response, int $page, int $perPage): array
    {
        $payload = $this->toArray($response);
        $container = $this->absensiPageContainer($payload);
        $items = $this->absensiPageItems($container);
        $metaSources = $this->absensiMetaSources($payload, $container);

        $normalizedPerPage = max(1, (int) ($this->firstDataValue($metaSources, [
            'per_page',
            'perPage',
            'limit',
            'page_size',
            'pageSize',
            'items_per_page',
        ]) ?? $perPage));
        $currentPage = max(1, (int) ($this->firstDataValue($metaSources, [
            'current_page',
            'currentPage',
            'page',
        ]) ?? $page));
        $totalValue = $this->firstDataValue($metaSources, [
            'total',
            'total_data',
            'totalData',
            'total_records',
            'totalRecords',
            'recordsTotal',
            'recordsFiltered',
            'filtered',
            'total_count',
            'totalCount',
            'jumlah_data',
            'jumlahData',
        ]);
        $lastPageValue = $this->firstDataValue($metaSources, [
            'last_page',
            'lastPage',
            'total_page',
            'total_pages',
            'totalPages',
            'pages',
        ]);

        $hasExplicitTotal = $totalValue !== null;
        $total = $hasExplicitTotal ? (int) $totalValue : (($currentPage - 1) * $normalizedPerPage) + count($items);
        $lastPage = $lastPageValue
            ? max(1, (int) $lastPageValue)
            : max(1, (int) ceil($total / $normalizedPerPage));

        if (! $hasExplicitTotal) {
            if ($lastPageValue) {
                $total = max($total, $lastPage * $normalizedPerPage);
            } elseif ($this->absensiHasNextPage($metaSources, $currentPage, $lastPage) || count($items) >= $normalizedPerPage) {
                $total = max($total, ($currentPage * $normalizedPerPage) + 1);
                $lastPage = max($lastPage, $currentPage + 1);
            }
        }

        $from = $this->firstDataValue($metaSources, ['from']);
        $to = $this->firstDataValue($metaSources, ['to']);

        return [
            'current_page' => $currentPage,
            'data' => $items,
            'from' => $from ?? (count($items) ? (($currentPage - 1) * $normalizedPerPage) + 1 : null),
            'last_page' => $lastPage,
            'per_page' => $normalizedPerPage,
            'to' => $to ?? (count($items) ? (($currentPage - 1) * $normalizedPerPage) + count($items) : null),
            'total' => $total,
        ];
    }

    private function absensiPageContainer(array $payload): array
    {
        if ($payload && $this->isList($payload)) {
            return ['data' => $payload];
        }

        foreach ([$payload, data_get($payload, 'data'), data_get($payload, 'result'), data_get($payload, 'response')] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($this->absensiPageItems($candidate) || $this->hasAnyDataKey($candidate, ['data', 'users', 'items', 'results', 'records'])) {
                return $candidate;
            }
        }

        return $payload;
    }

    private function absensiPageItems(array $container): array
    {
        if ($container && $this->isList($container)) {
            return $container;
        }

        foreach (['data', 'users', 'items', 'results', 'records'] as $key) {
            $value = data_get($container, $key);
            if (! is_array($value)) {
                continue;
            }

            if ($this->isList($value)) {
                return $value;
            }

            $nested = $this->absensiPageItems($value);
            if ($nested || $this->hasAnyDataKey($value, ['data', 'users', 'items', 'results', 'records'])) {
                return $nested;
            }
        }

        return [];
    }

    private function absensiMetaSources(array $payload, array $container): array
    {
        return array_values(array_filter([
            data_get($payload, 'meta'),
            data_get($payload, 'pagination'),
            data_get($payload, 'links'),
            data_get($container, 'meta'),
            data_get($container, 'pagination'),
            data_get($container, 'links'),
            $container,
            $payload,
        ], fn ($source) => is_array($source)));
    }

    private function absensiHasNextPage(array $sources, int $currentPage, int $lastPage): bool
    {
        if ($lastPage > $currentPage) {
            return true;
        }

        $next = $this->firstDataValue($sources, [
            'next',
            'next_page',
            'nextPage',
            'next_page_url',
            'has_more',
            'hasMore',
        ]);

        if (is_bool($next)) {
            return $next;
        }

        if (is_numeric($next)) {
            return (int) $next > 0;
        }

        return is_string($next) && trim($next) !== '';
    }

    private function firstDataValue(array $sources, array $keys)
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                $value = data_get($source, $key);

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function hasAnyDataKey(array $source, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }

        return false;
    }

    private function absensiPreviewRow($source, $existing): ?array
    {
        $kode = $this->sourceValue($source, ['id', 'user_id']);
        $nama = $this->sourceValue($source, ['name', 'nama', 'username']);

        if (! $kode || ! $nama) {
            return null;
        }

        $kode = (string) $kode;
        $pegawai = $existing->get($kode);
        $existingTipe = $pegawai?->tipe;

        return [
            'id' => $kode,
            'kode' => $kode,
            'nama' => $nama,
            'email' => $this->sourceValue($source, ['email', 'email_user']),
            'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'phone', 'phone_number', 'telepon', 'telp']),
            'role' => $this->sourceValue($source, ['role.name', 'role.nama', 'role_name', 'nama_role']) ?: '-',
            'departemen' => $this->sourceValue($source, ['departemen.name', 'departemen.nama', 'departemen_name', 'nama_departemen', 'department.name', 'department.nama']) ?: '-',
            'jabatan' => $this->absensiJabatan($source),
            'exists' => (bool) $pegawai,
            'existing_pegawai_id' => $pegawai?->id,
            'existing_tipe' => $existingTipe,
            'can_sync' => ! $pegawai || $existingTipe === 'staff',
        ];
    }

    private function absensiUserPayload($response): array
    {
        $payload = $this->toArray($response);
        $data = data_get($payload, 'data');

        if (is_array($data) && isset($data['data']) && is_array($data['data']) && ! $this->isList($data['data'])) {
            return $data['data'];
        }

        if (is_array($data) && ! $this->isList($data)) {
            return $data;
        }

        return $payload;
    }

    private function mapAbsensiStaff(array $source): ?array
    {
        $kode = $this->sourceValue($source, ['id', 'user_id']);
        $nama = $this->sourceValue($source, ['name', 'nama', 'username']);

        if (! $kode || ! $nama) {
            return null;
        }

        return [
            'pegawai' => [
                'nama' => $nama,
                'jenis_kelamin' => $this->normalizeJenisKelamin($this->sourceValue($source, ['jenis_kelamin', 'kelamin', 'jk', 'gender'])),
                'tipe' => 'staff',
                'kode' => (string) $kode,
                'tempat_lahir' => $this->sourceValue($source, ['tempat_lahir', 'tmp_lahir', 'birth_place']),
                'tanggal_lahir' => $this->normalizeDate($this->sourceValue($source, ['tanggal_lahir', 'tgl_lahir', 'birth_date', 'date_of_birth'])),
                'alamat' => $this->sourceValue($source, ['alamat', 'alamat_lengkap', 'address']),
                'email' => $this->normalizeEmail($this->sourceValue($source, ['email', 'email_user'])),
                'hp' => $this->sourceValue($source, ['hp', 'no_hp', 'phone', 'phone_number', 'telepon', 'telp']),
                'nomer_rekening' => $this->sourceValue($source, ['nomer_rekening', 'nomor_rekening', 'no_rekening', 'rekening']),
                'nama_pemilik_rekening' => $this->sourceValue($source, ['nama_pemilik_rekening', 'nama_rekening', 'atas_nama_rekening', 'atas_nama']),
                'bank' => $this->sourceValue($source, ['bank', 'nama_bank']),
                'status' => $this->normalizeStatus($this->sourceValue($source, ['status', 'aktif', 'is_active'])),
            ],
            'staff' => [
                'jabatan' => $this->absensiJabatan($source),
            ],
        ];
    }

    private function absensiJabatan($source): ?string
    {
        return $this->sourceValue($source, [
            'jabatan',
            'position',
            'posisi',
            'role.name',
            'role.nama',
            'role_name',
            'nama_role',
            'departemen.name',
            'departemen.nama',
            'departemen_name',
            'nama_departemen',
            'department.name',
            'department.nama',
        ]);
    }

    private function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        return [];
    }

    private function isList(array $value): bool
    {
        return array_values($value) === $value;
    }

    private function prodiMap(): array
    {
        $map = [];

        Prodi::query()
            ->select(['id', 'kode', 'alias', 'nama'])
            ->get()
            ->each(function ($prodi) use (&$map) {
                foreach ([$prodi->kode, $prodi->alias, $prodi->nama] as $value) {
                    $key = $this->normalizeLookupKey($value);
                    if ($key) {
                        $map[$key] = $prodi->id;
                    }
                }
            });

        return $map;
    }

    private function sourceValue($source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizeLookupKey(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));

        return $normalized !== '' ? mb_strtolower($normalized) : null;
    }

    private function normalizeJenisKelamin(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['p', 'perempuan', 'wanita', 'female'], true)) {
            return 'Perempuan';
        }

        return 'Laki-laki';
    }

    private function normalizeStatus(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['n', 'nonaktif', 'tidak aktif', 'inactive', '0'], true)) {
            return 'tidak aktif';
        }

        return 'aktif';
    }

    private function normalizeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeEmail(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $email = mb_strtolower(trim($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
