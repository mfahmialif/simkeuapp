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
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Group by tagihan_nama so 1 target tagihan displays all its prerequisites
        $query->selectRaw(
            'tagihan_nama, MIN(id) as id, MAX(is_active) as is_active, MAX(keterangan) as keterangan, MAX(created_at) as created_at, MAX(CASE WHEN syarat_nama IS NOT NULL AND syarat_nama != \'\' THEN 1 ELSE 0 END) as has_prereq'
        )->groupBy('tagihan_nama');

        if ($request->filled('tipe_syarat') && $request->tipe_syarat !== 'all') {
            $tipe = (string) $request->tipe_syarat;
            if (in_array($tipe, ['with_prereq', 'ada', 'has_prereq'], true)) {
                $query->havingRaw('MAX(CASE WHEN syarat_nama IS NOT NULL AND syarat_nama != \'\' THEN 1 ELSE 0 END) = 1');
            } elseif (in_array($tipe, ['without_prereq', 'bebas', 'no_prereq'], true)) {
                $query->havingRaw('MAX(CASE WHEN syarat_nama IS NOT NULL AND syarat_nama != \'\' THEN 1 ELSE 0 END) = 0');
            }
        }

        $sortable = [
            'id' => 'MIN(id)',
            'tagihan_nama' => 'tagihan_nama',
            'is_active' => 'MAX(is_active)',
            'created_at' => 'MAX(created_at)',
        ];

        $sortKeyInput = (string) $request->input('sort_key', 'id');
        $sortKey = $sortable[$sortKeyInput] ?? 'MIN(id)';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (str_contains($sortKey, '(')) {
            $query->orderByRaw("{$sortKey} {$sortOrder}");
        } else {
            $query->orderBy($sortKey, $sortOrder);
        }

        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $paginator = $query->paginate($limit);

        // Fetch all related prerequisites for target items on the current page
        $targetNames = collect($paginator->items())->pluck('tagihan_nama')->all();
        $relatedRules = !empty($targetNames)
            ? KeuanganSyaratTagihan::whereIn('tagihan_nama', $targetNames)->get()
            : collect();

        $transformedItems = collect($paginator->items())->map(function ($row) use ($relatedRules) {
            $matching = $relatedRules->filter(fn ($r) => strcasecmp($r->tagihan_nama, $row->tagihan_nama) === 0);
            $syaratList = $matching->pluck('syarat_nama')
                ->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
                ->unique()
                ->values()
                ->all();

            return [
                'id' => (int) $row->id,
                'tagihan_nama' => $row->tagihan_nama,
                'syarat_nama' => $syaratList,
                'syarat_string' => count($syaratList) > 0 ? implode(', ', $syaratList) : null,
                'syarat_count' => count($syaratList),
                'is_active' => (bool) $row->is_active,
                'keterangan' => $row->keterangan,
                'created_at' => $row->created_at,
            ];
        });

        $paginator->setCollection($transformedItems);

        $stats = [
            'total' => KeuanganSyaratTagihan::distinct('tagihan_nama')->count('tagihan_nama'),
            'with_prereq' => KeuanganSyaratTagihan::whereNotNull('syarat_nama')
                ->where('syarat_nama', '!=', '')
                ->distinct('tagihan_nama')
                ->count('tagihan_nama'),
            'without_prereq' => KeuanganSyaratTagihan::where(function ($q) {
                    $q->whereNull('syarat_nama')->orWhere('syarat_nama', '');
                })
                ->whereNotIn('tagihan_nama', function ($sub) {
                    $sub->select('tagihan_nama')
                        ->from('keuangan_syarat_tagihan')
                        ->whereNotNull('syarat_nama')
                        ->where('syarat_nama', '!=', '');
                })
                ->distinct('tagihan_nama')
                ->count('tagihan_nama'),
        ];

        return response()->json([
            'status' => true,
            'data' => $paginator,
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

        // Clean existing rules for this target tagihan
        KeuanganSyaratTagihan::where('tagihan_nama', $tagihanNama)->delete();

        // Convert rawSyarat to array of cleaned strings
        $syaratList = [];
        if (is_array($rawSyarat)) {
            foreach ($rawSyarat as $s) {
                $trimmed = trim((string) $s);
                if ($trimmed !== '' && strcasecmp($trimmed, $tagihanNama) !== 0) {
                    $syaratList[] = $trimmed;
                }
            }
        } elseif (!empty($rawSyarat)) {
            $trimmed = trim((string) $rawSyarat);
            if ($trimmed !== '' && strcasecmp($trimmed, $tagihanNama) !== 0) {
                $syaratList[] = $trimmed;
            }
        }
        $syaratList = array_values(array_unique($syaratList));

        // Jika syarat_nama null / kosong: artinya tagihan ini BEBAS tanpa prasyarat
        if (empty($syaratList)) {
            KeuanganSyaratTagihan::create([
                'tagihan_nama' => $tagihanNama,
                'syarat_nama' => null,
                'is_active' => $isActive,
                'keterangan' => $keterangan ?: 'Bebas tanpa prasyarat (langsung dapat dibayar)',
            ]);

            return response()->json([
                'status' => true,
                'message' => "Berhasil menyimpan aturan bebas (tanpa prasyarat) untuk {$tagihanNama}.",
            ], 201);
        }

        foreach ($syaratList as $syarat) {
            KeuanganSyaratTagihan::create([
                'tagihan_nama' => $tagihanNama,
                'syarat_nama' => $syarat,
                'is_active' => $isActive,
                'keterangan' => $keterangan,
            ]);
        }

        $count = count($syaratList);
        return response()->json([
            'status' => true,
            'message' => "Berhasil menyimpan aturan untuk {$tagihanNama} dengan {$count} prasyarat.",
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

        $oldTargetNama = $rule->tagihan_nama;

        // If only toggling active status
        if ($request->has('is_active') && ! $request->has('tagihan_nama')) {
            $isActive = $request->boolean('is_active');
            KeuanganSyaratTagihan::where('tagihan_nama', $oldTargetNama)->update([
                'is_active' => $isActive,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Status syarat tagihan berhasil diperbarui.',
            ]);
        }

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

        $newTargetNama = trim((string) $request->tagihan_nama);
        $rawSyarat = $request->input('syarat_nama');
        $isActive = $request->boolean('is_active', true);
        $keterangan = $request->filled('keterangan') ? trim((string) $request->keterangan) : null;

        // Clean previous target rules
        KeuanganSyaratTagihan::where('tagihan_nama', $oldTargetNama)->delete();
        if (strcasecmp($newTargetNama, $oldTargetNama) !== 0) {
            KeuanganSyaratTagihan::where('tagihan_nama', $newTargetNama)->delete();
        }

        // Convert rawSyarat to array of cleaned strings
        $syaratList = [];
        if (is_array($rawSyarat)) {
            foreach ($rawSyarat as $s) {
                $trimmed = trim((string) $s);
                if ($trimmed !== '' && strcasecmp($trimmed, $newTargetNama) !== 0) {
                    $syaratList[] = $trimmed;
                }
            }
        } elseif (!empty($rawSyarat)) {
            $trimmed = trim((string) $rawSyarat);
            if ($trimmed !== '' && strcasecmp($trimmed, $newTargetNama) !== 0) {
                $syaratList[] = $trimmed;
            }
        }
        $syaratList = array_values(array_unique($syaratList));

        if (empty($syaratList)) {
            KeuanganSyaratTagihan::create([
                'tagihan_nama' => $newTargetNama,
                'syarat_nama' => null,
                'is_active' => $isActive,
                'keterangan' => $keterangan ?: 'Bebas tanpa prasyarat (langsung dapat dibayar)',
            ]);

            return response()->json([
                'status' => true,
                'message' => "Aturan untuk {$newTargetNama} diperbarui menjadi bebas tanpa prasyarat.",
            ]);
        }

        foreach ($syaratList as $syarat) {
            KeuanganSyaratTagihan::create([
                'tagihan_nama' => $newTargetNama,
                'syarat_nama' => $syarat,
                'is_active' => $isActive,
                'keterangan' => $keterangan,
            ]);
        }

        $count = count($syaratList);
        return response()->json([
            'status' => true,
            'message' => "Aturan syarat tagihan untuk {$newTargetNama} berhasil diperbarui ({$count} prasyarat).",
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

        $targetNama = $rule->tagihan_nama;
        KeuanganSyaratTagihan::where('tagihan_nama', $targetNama)->delete();

        return response()->json([
            'status' => true,
            'message' => "Seluruh aturan syarat tagihan untuk {$targetNama} berhasil dihapus.",
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
