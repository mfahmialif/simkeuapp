<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Exports\TagihanExport;
use App\Http\Controllers\Controller;
use App\Models\KeuanganTagihan;
use App\Models\MataUang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TagihanPeroranganController extends Controller
{
    public function index(Request $request)
    {
        $query = KeuanganTagihan::join('th_akademik as tha', 'tha.id', 'th_akademik_id')
            ->join('th_akademik as tha2', 'tha2.id', 'th_angkatan_id')
            ->join('prodi as prodi', 'prodi.id', 'prodi_id')
            ->join('ref as ref_kelas', 'ref_kelas.id', 'kelas_id')
            ->join('form_schadule as form', 'form.id', 'form_schadule_id')
            ->leftJoin('mata_uang as mata_uang', 'mata_uang.id', 'keuangan_tagihan.mata_uang_id')
            ->whereNotNull('keuangan_tagihan.nim');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('keuangan_tagihan.nim', 'LIKE', "%$request->search%")
                    ->orWhere('tha.kode', 'LIKE', "%$request->search%")
                    ->orWhere('tha2.kode', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('ref_kelas.nama', 'LIKE', "%$request->search%")
                    ->orWhere('form.nama', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.kode', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.nama', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.simbol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.kode', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.jumlah', 'LIKE', "%$request->search%");
            });
        }

        if ($request->th_akademik_id != '') {
            $query->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
        }

        if ($request->th_angkatan_id != '') {
            $query->where('keuangan_tagihan.th_angkatan_id', $request->th_angkatan_id);
        }

        if ($request->prodi_id != '') {
            $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
        }

        if ($request->has('double_degree') && $request->double_degree !== '') {
            if ($request->double_degree == 1) {
                $query->where('keuangan_tagihan.double_degree', $request->double_degree);
            } else {
                $query->where(function ($query) use ($request) {
                    $query->where('keuangan_tagihan.double_degree', $request->double_degree)
                        ->orWhereNull('keuangan_tagihan.double_degree');
                });
            }
        }

        if ($request->kelas_id != '') {
            $query->where('keuangan_tagihan.kelas_id', $request->kelas_id);
        }

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        if ($sortKey === 'id') {
            $sortKey = 'keuangan_tagihan.id';
        }

        $query->orderBy($sortKey, $sortOrder);
        $query->select(
            'keuangan_tagihan.*',
            'tha.kode as th_akademik_kode',
            'tha2.kode as th_angkatan_kode',
            'prodi.nama as prodi_nama',
            'form.nama as form_nama',
            'mata_uang.kode as mata_uang_kode',
            'mata_uang.nama as mata_uang_nama',
            'mata_uang.simbol as mata_uang_simbol'
        );

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Tagihan Perorangan retrieved successfully',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = KeuanganTagihan::join('th_akademik as tha', 'tha.id', 'th_akademik_id')
            ->join('th_akademik as tha2', 'tha2.id', 'th_angkatan_id')
            ->join('prodi as prodi', 'prodi.id', 'prodi_id')
            ->join('ref as ref_kelas', 'ref_kelas.id', 'kelas_id')
            ->join('form_schadule as form', 'form.id', 'form_schadule_id')
            ->leftJoin('mata_uang as mata_uang', 'mata_uang.id', 'keuangan_tagihan.mata_uang_id')
            ->whereNotNull('keuangan_tagihan.nim');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('keuangan_tagihan.nim', 'LIKE', "%$request->search%")
                    ->orWhere('tha.kode', 'LIKE', "%$request->search%")
                    ->orWhere('tha2.kode', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('ref_kelas.nama', 'LIKE', "%$request->search%")
                    ->orWhere('form.nama', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.kode', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.nama', 'LIKE', "%$request->search%")
                    ->orWhere('mata_uang.simbol', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.kode', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.jumlah', 'LIKE', "%$request->search%");
            });
        }

        if ($request->th_akademik_id != '') {
            $query->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
        }

        if ($request->th_angkatan_id != '') {
            $query->where('keuangan_tagihan.th_angkatan_id', $request->th_angkatan_id);
        }

        if ($request->prodi_id != '') {
            $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
        }

        if ($request->has('double_degree') && $request->double_degree !== '') {
            if ($request->double_degree == 1) {
                $query->where('keuangan_tagihan.double_degree', $request->double_degree);
            } else {
                $query->where(function ($query) use ($request) {
                    $query->where('keuangan_tagihan.double_degree', $request->double_degree)
                        ->orWhereNull('keuangan_tagihan.double_degree');
                });
            }
        }

        if ($request->kelas_id != '') {
            $query->where('keuangan_tagihan.kelas_id', $request->kelas_id);
        }

        $data = $query
            ->orderBy('keuangan_tagihan.id', 'desc')
            ->select(
                'keuangan_tagihan.nim',
                'tha.kode as tahun_akademik',
                'tha2.kode as tahun_angkatan',
                'prodi.nama as prodi',
                'keuangan_tagihan.double_degree',
                'ref_kelas.nama as kelas',
                'form.nama as formulir',
                'keuangan_tagihan.kode as kode_tagihan',
                'keuangan_tagihan.nama as nama_tagihan',
                'keuangan_tagihan.mata_uang_id',
                'mata_uang.kode as mata_uang_kode',
                'mata_uang.simbol as mata_uang_simbol',
                'keuangan_tagihan.jumlah'
            )
            ->get()
            ->values()
            ->map(function ($item, $index) {
                return [
                    $index + 1,
                    $item->nim,
                    $item->tahun_akademik,
                    $item->tahun_angkatan,
                    $item->prodi,
                    $item->double_degree ? 'Ya' : 'Tidak',
                    $item->kelas,
                    $item->formulir,
                    $item->kode_tagihan,
                    $item->nama_tagihan,
                    $item->mata_uang_id,
                    $item->mata_uang_kode,
                    $item->mata_uang_simbol,
                    $item->jumlah,
                ];
            })
            ->all();

        return Excel::download(new TagihanExport($data, true), 'Tagihan Perorangan.xlsx');
    }

    public function store(Request $request)
    {
        $tagihan = $request->input('tagihan');

        if (! is_array($tagihan)) {
            $tagihan = [[
                'nama' => $request->input('nama'),
                'jumlah' => $request->input('jumlah'),
                'mata_uang_id' => $request->input('mata_uang_id'),
            ]];
        }

        $tagihan = collect($tagihan)
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'nama' => trim((string) ($item['nama'] ?? '')),
                    'jumlah' => $item['jumlah'] ?? null,
                    'mata_uang_id' => $this->resolveMataUangValue($item['mata_uang_id'] ?? null),
                ];
            })
            ->values()
            ->all();

        $request->merge([
            'tagihan' => $tagihan,
        ]);

        $validator = Validator::make($request->all(), [
            'nim' => 'required|string|max:255',
            'th_akademik_id' => 'required|exists:th_akademik,id',
            'th_angkatan_id' => 'required|exists:th_akademik,id',
            'prodi_id' => 'required|exists:prodi,id',
            'double_degree' => 'nullable|integer',
            'kelas_id' => 'required|exists:ref,id',
            'form_schadule_id' => 'required|exists:form_schadule,id',
            'tagihan' => 'required|array|min:1|max:50',
            'tagihan.*.nama' => 'required|string|max:255',
            'tagihan.*.jumlah' => 'required|numeric',
            'tagihan.*.mata_uang_id' => 'required|exists:mata_uang,id',
        ]);

        $validator->after(function ($validator) use ($tagihan) {
            $namaTagihan = [];

            foreach ($tagihan as $index => $item) {
                $normalizedName = mb_strtolower($item['nama']);

                if (isset($namaTagihan[$normalizedName])) {
                    $validator->errors()->add(
                        "tagihan.$index.nama",
                        'Nama tagihan tidak boleh sama dalam satu input.'
                    );
                }

                $namaTagihan[$normalizedName] = true;
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $nim = strtoupper($request->nim);

        $scope = [
            'nim' => $nim,
            'th_akademik_id' => $request->th_akademik_id,
            'th_angkatan_id' => $request->th_angkatan_id,
            'prodi_id' => $request->prodi_id,
            'double_degree' => $request->double_degree,
            'kelas_id' => $request->kelas_id,
            'form_schadule_id' => $request->form_schadule_id,
        ];

        $existingNames = KeuanganTagihan::where($scope)
            ->whereIn('nama', collect($tagihan)->pluck('nama'))
            ->pluck('nama')
            ->all();

        if (count($existingNames) > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan Perorangan sudah ada: '.implode(', ', $existingNames),
            ], 422);
        }

        $kode = $request->th_akademik_id.$request->th_angkatan_id.$request->prodi_id.$request->kelas_id.$request->form_schadule_id;

        $commonData = array_merge($scope, [
            'kode' => $kode,
            'x_sks' => 'Y',
            'user_id' => auth()->id(),
        ]);

        $data = DB::transaction(function () use ($commonData, $tagihan) {
            return collect($tagihan)->map(function ($item) use ($commonData) {
                return KeuanganTagihan::create(array_merge($commonData, $item));
            });
        });

        return response()->json([
            'status' => true,
            'data' => $data,
            'created_count' => $data->count(),
            'message' => $data->count().' Tagihan Perorangan berhasil dibuat',
        ], 201);
    }

    public function show($id)
    {
        $data = KeuanganTagihan::whereNotNull('nim')->find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan Perorangan Not Found',
            ], 404);
        }

        return response()->json($data->load('th_akademik', 'th_angkatan', 'prodi', 'form_schadule', 'kelas', 'mata_uang'), 200);
    }

    public function update(Request $request, $id)
    {
        $request->merge([
            'mata_uang_id' => $this->resolveMataUangId($request),
        ]);

        $validator = Validator::make($request->all(), [
            'nim' => 'required|string|max:255',
            'th_akademik_id' => 'required|exists:th_akademik,id',
            'th_angkatan_id' => 'required|exists:th_akademik,id',
            'prodi_id' => 'required|exists:prodi,id',
            'double_degree' => 'nullable|integer',
            'kelas_id' => 'required|exists:ref,id',
            'form_schadule_id' => 'required|exists:form_schadule,id',
            'nama' => 'required|string|max:255',
            'jumlah' => 'required|numeric',
            'mata_uang_id' => 'required|exists:mata_uang,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganTagihan::whereNotNull('nim')->find($id);
        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan Perorangan Not Found',
            ], 404);
        }

        $nim = strtoupper($request->nim);

        $check = KeuanganTagihan::where('id', '!=', $id)
            ->where([
                'nim' => $nim,
                'th_akademik_id' => $request->th_akademik_id,
                'th_angkatan_id' => $request->th_angkatan_id,
                'prodi_id' => $request->prodi_id,
                'double_degree' => $request->double_degree,
                'kelas_id' => $request->kelas_id,
                'form_schadule_id' => $request->form_schadule_id,
                'nama' => $request->nama,
            ])->exists();

        if ($check) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan Perorangan sudah ada, silahkan edit',
            ]);
        }

        $kode = $request->th_akademik_id.$request->th_angkatan_id.$request->prodi_id.$request->kelas_id.$request->form_schadule_id;

        $data->fill($request->except(['_token', '_method', 'id']));
        $data->kode = $kode;
        $data->nim = $nim;
        $data->jumlah = $request->jumlah;
        $data->x_sks = 'Y';
        $data->user_id = auth()->id();
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Tagihan Perorangan updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganTagihan::whereNotNull('nim')->find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan Perorangan Not Found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Tagihan Perorangan deleted successfully',
        ]);
    }

    private function resolveMataUangId(Request $request): ?int
    {
        return $this->resolveMataUangValue($request->input('mata_uang_id'));
    }

    private function resolveMataUangValue($value): ?int
    {
        if (is_numeric($value) && MataUang::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        if ($value !== null && $value !== '') {
            return MataUang::where('kode', strtoupper((string) $value))->value('id');
        }

        return MataUang::where('kode', 'IDR')->value('id')
            ?? MataUang::where('aktif', true)->orderBy('kode')->value('id');
    }
}
