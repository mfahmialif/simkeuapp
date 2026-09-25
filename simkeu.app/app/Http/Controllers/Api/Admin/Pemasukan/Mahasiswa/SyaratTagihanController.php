<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganSyaratTagihan;
use App\Models\KeuanganTagihan;
use App\Services\SyaratTagihanTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyaratTagihanController extends Controller
{
    /**
     * Display a listing of prerequisite rules.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KeuanganSyaratTagihan::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('tagihan_nama', 'LIKE', "%{$search}%")
                    ->orWhere('syarat_nama', 'LIKE', "%{$search}%")
                    ->orWhere('keterangan', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('tipe_syarat') && $request->tipe_syarat !== 'all') {
            $tipe = (string) $request->tipe_syarat;
            if (in_array($tipe, ['with_prereq', 'ada', 'has_prereq'], true)) {
                $query->whereNotNull('syarat_nama')->where('syarat_nama', '!=', '');
            } elseif (in_array($tipe, ['without_prereq', 'bebas', 'no_prereq'], true)) {
                $query->where(function ($q) {
                    $q->whereNull('syarat_nama')->orWhere('syarat_nama', '');
                });
            }
        }

        $sortable = [
            'id' => 'id',
            'tagihan_nama' => 'tagihan_nama',
            'syarat_nama' => 'syarat_nama',
            'is_active' => 'is_active',
            'created_at' => 'created_at',
        ];

        $sortKey = $sortable[$request->input('sort_key')] ?? 'id';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortKey, $sortOrder);

        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $data = $query->paginate($limit);

        $stats = [
            'total' => KeuanganSyaratTagihan::count(),
            'with_prereq' => KeuanganSyaratTagihan::whereNotNull('syarat_nama')->where('syarat_nama', '!=', '')->count(),
            'without_prereq' => KeuanganSyaratTagihan::where(function ($q) {
                $q->whereNull('syarat_nama')->orWhere('syarat_nama', '');
            })->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $data,
            'stats' => $stats,
            'message' => 'Data syarat tagihan berhasil diambil.',
        ]);
    }

    /**
     * Get distinct tagihan names from master tagihan.
     */
    public function tagihanNames(): JsonResponse
    {
        $names = KeuanganTagihan::query()
            ->select('nama')
            ->distinct()
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->orderBy('nama')
            ->pluck('nama')
            ->values();

        return response()->json([
            'status' => true,
            'data' => $names,
        ]);
    }

    /**
     * Get list of tagihan names that have not yet been registered as target rules.
     */
    public function unregistered(?Request $request = null): JsonResponse
    {
        $masterNames = KeuanganTagihan::query()
            ->select('nama')
            ->distinct()
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->whereNull('nim')
            ->pluck('nama')
            ->all();

        $allNames = KeuanganTagihan::query()
            ->select('nama')
            ->distinct()
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->orderBy('nama')
            ->pluck('nama')
            ->all();

        $configuredTargets = KeuanganSyaratTagihan::query()
            ->select('tagihan_nama')
            ->distinct()
            ->pluck('tagihan_nama')
            ->map(fn ($n) => strtolower(trim((string) $n)))
            ->all();

        $unregisteredAll = collect($allNames)->filter(function ($name) use ($configuredTargets) {
            return ! in_array(strtolower(trim((string) $name)), $configuredTargets, true);
        })->values();

        $masterSet = array_flip(array_map('strtolower', array_map('trim', $masterNames)));

        $unregisteredMaster = $unregisteredAll->filter(function ($name) use ($masterSet) {
            return isset($masterSet[strtolower(trim($name))]);
        })->values();

        $unregisteredPerorangan = $unregisteredAll->filter(function ($name) use ($masterSet) {
            return ! isset($masterSet[strtolower(trim($name))]);
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'total_unregistered' => $unregisteredAll->count(),
                'master_count' => $unregisteredMaster->count(),
                'perorangan_count' => $unregisteredPerorangan->count(),
                'regular_count' => $unregisteredMaster->count(),
                'sp_count' => $unregisteredPerorangan->count(),
                'items' => $unregisteredAll,
                'master_items' => $unregisteredMaster,
                'perorangan_items' => $unregisteredPerorangan,
                'regular_items' => $unregisteredMaster,
                'sp_items' => $unregisteredPerorangan,
            ],
        ]);
    }

    /**
     * Store newly created prerequisite rule(s).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tagihan_nama' => 'required|string|max:255',
            'syarat_nama' => 'nullable',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tagihanNama = trim((string) $request->tagihan_nama);
        $rawSyarat = $request->input('syarat_nama');
        $isActive = $request->boolean('is_active', true);
        $keterangan = $request->filled('keterangan') ? trim((string) $request->keterangan) : null;

        // Jika syarat_nama null / kosong: artinya tagihan ini BEBAS tanpa prasyarat
        if (empty($rawSyarat) || (is_array($rawSyarat) && count(array_filter($rawSyarat)) === 0)) {
            $existing = KeuanganSyaratTagihan::where('tagihan_nama', $tagihanNama)
                ->whereNull('syarat_nama')
                ->first();

            if (! $existing) {
                KeuanganSyaratTagihan::create([
                    'tagihan_nama' => $tagihanNama,
                    'syarat_nama' => null,
                    'is_active' => $isActive,
                    'keterangan' => $keterangan ?: 'Bebas tanpa prasyarat (langsung dapat dibayar)',
                ]);
            } else {
                $existing->update([
                    'is_active' => $isActive,
                    'keterangan' => $keterangan ?: $existing->keterangan,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => "Berhasil menyimpan aturan bebas (tanpa prasyarat) untuk {$tagihanNama}.",
            ], 201);
        }

        // Jika diberikan syarat konkret, hapus rule null sebelumnya jika ada
        KeuanganSyaratTagihan::where('tagihan_nama', $tagihanNama)->whereNull('syarat_nama')->delete();

        $syaratList = is_array($rawSyarat) ? $rawSyarat : [$rawSyarat];

        $createdCount = 0;
        foreach ($syaratList as $item) {
            $syarat = trim((string) $item);
            if ($syarat === '' || strcasecmp($tagihanNama, $syarat) === 0) {
                continue;
            }

            KeuanganSyaratTagihan::updateOrCreate(
                [
                    'tagihan_nama' => $tagihanNama,
                    'syarat_nama' => $syarat,
                ],
                [
                    'is_active' => $isActive,
                    'keterangan' => $keterangan,
                ]
            );
            $createdCount++;
        }

        if ($createdCount === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan syarat tidak boleh sama dengan tagihan target.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => "Berhasil menyimpan {$createdCount} aturan syarat tagihan.",
        ], 201);
    }

    /**
     * Update an existing prerequisite rule.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $rule = KeuanganSyaratTagihan::find($id);

        if (! $rule) {
            return response()->json([
                'status' => false,
                'message' => 'Data syarat tagihan tidak ditemukan.',
            ], 404);
        }

        // If only toggling active status
        if ($request->has('is_active') && ! $request->has('tagihan_nama')) {
            $rule->update([
                'is_active' => $request->boolean('is_active'),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Status syarat tagihan berhasil diperbarui.',
                'data' => $rule,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'tagihan_nama' => 'required|string|max:255',
            'syarat_nama' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tagihanNama = trim((string) $request->tagihan_nama);
        $syaratNama = $request->filled('syarat_nama') ? trim((string) $request->syarat_nama) : null;

        if ($syaratNama !== null && strcasecmp($tagihanNama, $syaratNama) === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan syarat tidak boleh sama dengan tagihan target.',
            ], 422);
        }

        $rule->update([
            'tagihan_nama' => $tagihanNama,
            'syarat_nama' => $syaratNama,
            'is_active' => $request->boolean('is_active', true),
            'keterangan' => $request->filled('keterangan') ? trim((string) $request->keterangan) : ($syaratNama === null ? 'Bebas tanpa prasyarat (langsung dapat dibayar)' : null),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Aturan syarat tagihan berhasil diperbarui.',
            'data' => $rule,
        ]);
    }

    /**
     * Delete a prerequisite rule.
     */
    public function destroy($id): JsonResponse
    {
        $rule = KeuanganSyaratTagihan::find($id);

        if (! $rule) {
            return response()->json([
                'status' => false,
                'message' => 'Data syarat tagihan tidak ditemukan.',
            ], 404);
        }

        $rule->delete();

        return response()->json([
            'status' => true,
            'message' => 'Aturan syarat tagihan berhasil dihapus.',
        ]);
    }

    /**
     * Preview template rules.
     */
    public function templatePreview(Request $request, SyaratTagihanTemplateService $service): JsonResponse
    {
        $options = [
            'alur_siklus' => $request->boolean('alur_siklus', true),
            'antar_semester' => $request->boolean('antar_semester', true),
            'spp_bulanan' => $request->boolean('spp_bulanan', true),
            'akhir_wisuda' => $request->boolean('akhir_wisuda', true),
            'include_perorangan' => $request->boolean('include_perorangan', false),
        ];

        return response()->json([
            'status' => true,
            'data' => $service->preview($options),
        ]);
    }

    /**
     * Apply template rules to database.
     */
    public function applyTemplate(Request $request, SyaratTagihanTemplateService $service): JsonResponse
    {
        $options = [
            'alur_siklus' => $request->boolean('alur_siklus', true),
            'antar_semester' => $request->boolean('antar_semester', true),
            'spp_bulanan' => $request->boolean('spp_bulanan', true),
            'akhir_wisuda' => $request->boolean('akhir_wisuda', true),
            'include_perorangan' => $request->boolean('include_perorangan', false),
        ];

        $replaceExisting = $request->boolean('replace_existing', false);
        $result = $service->apply($options, $replaceExisting);

        return response()->json([
            'status' => true,
            'message' => "Berhasil menerapkan {$result['total_applied']} aturan syarat tagihan ({$result['created']} baru, {$result['updated']} diperbarui).",
            'data' => $result,
        ]);
    }

    /**
     * Reset/Delete all rules.
     */
    public function resetAll(): JsonResponse
    {
        $count = KeuanganSyaratTagihan::count();
        KeuanganSyaratTagihan::query()->delete();

        return response()->json([
            'status' => true,
            'message' => "Berhasil mereset {$count} aturan syarat tagihan.",
        ]);
    }
}
