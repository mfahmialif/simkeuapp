<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pimpinan;
use App\Services\PimpinanSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PimpinanController extends Controller
{
    public function index(Request $request)
    {
        $query = Pimpinan::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($filter) use ($search) {
                $filter->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('jabatan', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortable = [
            'nama',
            'jabatan',
            'tanggal_awal_menjabat',
            'tanggal_akhir_menjabat',
            'status',
            'created_at',
        ];
        $sortKey = in_array($request->input('sort_key'), $sortable, true)
            ? $request->input('sort_key')
            : 'tanggal_awal_menjabat';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $limit = max(1, min((int) $request->input('limit', 10), 100));
        $data = $query->orderBy($sortKey, $sortOrder)->paginate($limit);
        $data->getCollection()->transform(
            fn (Pimpinan $pimpinan) => PimpinanSignatureService::payload($pimpinan, false)
        );

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data pimpinan berhasil diambil.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $pimpinan = DB::transaction(function () use ($request, $validated) {
            $pimpinan = Pimpinan::create([
                ...$this->recordData($validated),
            ]);

            $this->storeSignature($request, $pimpinan);
            PimpinanSignatureService::deleteLegacySignatureFiles($pimpinan);

            return $pimpinan->fresh();
        });

        return response()->json([
            'status' => true,
            'data' => PimpinanSignatureService::payload($pimpinan, false),
            'message' => 'Data pimpinan berhasil ditambahkan.',
        ], 201);
    }

    public function show(Pimpinan $pimpinan)
    {
        return response()->json([
            'status' => true,
            'data' => PimpinanSignatureService::payload($pimpinan),
            'message' => 'Data pimpinan berhasil diambil.',
        ]);
    }

    public function update(Request $request, Pimpinan $pimpinan)
    {
        $validated = $this->validatePayload($request);

        $pimpinan = DB::transaction(function () use ($request, $validated, $pimpinan) {
            $pimpinan->fill($this->recordData($validated))->save();

            if ($request->boolean('hapus_file_ttd')) {
                $this->deleteSignature($pimpinan);
            }

            $this->storeSignature($request, $pimpinan);
            PimpinanSignatureService::deleteLegacySignatureFiles($pimpinan);

            return $pimpinan->fresh();
        });

        return response()->json([
            'status' => true,
            'data' => PimpinanSignatureService::payload($pimpinan, false),
            'message' => 'Data pimpinan berhasil diperbarui.',
        ]);
    }

    public function destroy(Pimpinan $pimpinan)
    {
        DB::transaction(function () use ($pimpinan) {
            $this->deleteSignature($pimpinan);
            PimpinanSignatureService::deleteLegacySignatureFiles($pimpinan);
            $pimpinan->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Data pimpinan berhasil dihapus.',
        ]);
    }

    public function active()
    {
        $data = PimpinanSignatureService::activeList()
            ->map(fn (Pimpinan $pimpinan) => PimpinanSignatureService::payload($pimpinan, false))
            ->values();

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data pimpinan aktif berhasil diambil.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'mode_ttd' => ['nullable', Rule::in(['file'])],
            'tanggal_awal_menjabat' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir_menjabat' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:tanggal_awal_menjabat',
            ],
            'status' => ['required', Rule::in(['aktif', 'tidak_aktif'])],
            'file_ttd' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:4096'],
            'ttd_drawn' => ['nullable', 'string'],
            'hapus_file_ttd' => ['nullable', 'boolean'],
        ]);
    }

    private function recordData(array $validated): array
    {
        return [
            'nama' => $validated['nama'],
            'jabatan' => $validated['jabatan'],
            'mode_ttd' => 'file',
            'tanggal_awal_menjabat' => $validated['tanggal_awal_menjabat'],
            'tanggal_akhir_menjabat' => $validated['tanggal_akhir_menjabat'] ?: null,
            'status' => $validated['status'],
        ];
    }

    private function storeSignature(Request $request, Pimpinan $pimpinan): void
    {
        $binary = null;
        $extension = 'png';

        if ($request->hasFile('file_ttd')) {
            $file = $request->file('file_ttd');
            $binary = $file->getContent();
            $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        } elseif ($request->filled('ttd_drawn')) {
            if (! preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $request->ttd_drawn, $matches)) {
                throw ValidationException::withMessages([
                    'ttd_drawn' => ['Format gambar tanda tangan tidak valid.'],
                ]);
            }

            $binary = base64_decode($matches[2], true);
            $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];

            if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'ttd_drawn' => ['Gambar tanda tangan tidak valid atau melebihi 4 MB.'],
                ]);
            }
        }

        if ($binary === null) {
            return;
        }

        $imageInfo = @getimagesizefromstring($binary);
        if (! $imageInfo) {
            throw ValidationException::withMessages([
                'file_ttd' => ['File tanda tangan harus berupa gambar yang valid.'],
            ]);
        }

        $this->deleteSignature($pimpinan);
        $path = 'pimpinan/ttd/'.Str::uuid().'.'.$extension;
        $absolutePath = public_path($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (file_put_contents($absolutePath, $binary) === false) {
            throw ValidationException::withMessages([
                'file_ttd' => ['File tanda tangan gagal disimpan.'],
            ]);
        }

        $pimpinan->forceFill(['file_ttd' => $path])->save();
    }

    private function deleteSignature(Pimpinan $pimpinan): void
    {
        if ($pimpinan->file_ttd) {
            PimpinanSignatureService::deleteSignatureFile($pimpinan->file_ttd);
            $pimpinan->forceFill(['file_ttd' => null])->save();
        }
    }
}
