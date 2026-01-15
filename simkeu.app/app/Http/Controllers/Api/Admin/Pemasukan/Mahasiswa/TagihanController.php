<?php
namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Exports\TagihanTemplateExport;
use App\Imports\TagihanImport;
use App\Models\KeuanganTagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TagihanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = KeuanganTagihan::join('th_akademik as tha', 'tha.id', 'th_akademik_id')
            ->join('th_akademik as tha2', 'tha2.id', 'th_angkatan_id')
            ->join('prodi as prodi', 'prodi.id', 'prodi_id')
            ->join('ref as ref_kelas', 'ref_kelas.id', 'kelas_id')
            ->join('form_schadule as form', 'form.id', 'form_schadule_id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('tha.kode', 'LIKE', "%$request->search%")
                    ->orWhere('tha2.kode', 'LIKE', "%$request->search%")
                    ->orWhere('prodi.nama', 'LIKE', "%$request->search%")
                    ->orWhere('ref_kelas.nama', 'LIKE', "%$request->search%")
                    ->orWhere('form.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.kode', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.jumlah', 'LIKE', "%$request->search%");
            });
        }

        if ($request->th_akademik_id != "") {
            $query->where('keuangan_tagihan.th_akademik_id', $request->th_akademik_id);
        }

        if ($request->th_angkatan_id != "") {
            $query->where('keuangan_tagihan.th_angkatan_id', $request->th_angkatan_id);
        }

        if ($request->prodi_id != "") {
            $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
        }

        if ($request->double_degree != "") {
            if ($request->double_degree == 1) {
                $query->where('keuangan_tagihan.double_degree', $request->double_degree);
            } else {
                $query->where(function ($query) use ($request) {
                    $query->where('keuangan_tagihan.double_degree', $request->double_degree)
                        ->orWhereNull('keuangan_tagihan.double_degree');
                });
            }
        }

        if ($request->kelas_id != "") {
            $query->where('keuangan_tagihan.kelas_id', $request->kelas_id);
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query->select('keuangan_tagihan.*', 'tha.kode as th_akademik_kode', 'tha2.kode as th_angkatan_kode', 'prodi.nama as prodi_nama', 'form.nama as form_nama');

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Tagihan retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'th_akademik_id'   => 'required|exists:th_akademik,id',
            'th_angkatan_id'   => 'required|exists:th_akademik,id',
            'prodi_id'         => 'required|exists:prodi,id',
            'double_degree'    => 'nullable|integer',
            'kelas_id'         => 'required|exists:ref,id',
            'form_schadule_id' => 'required|exists:form_schadule,id',
            'nama'             => 'required|string|max:255',
            'jumlah'           => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ]);
        }

        $check = KeuanganTagihan::where([
            'th_akademik_id'   => $request->th_akademik_id,
            'th_angkatan_id'   => $request->th_angkatan_id,
            'prodi_id'         => $request->prodi_id,
            'kelas_id'         => $request->kelas_id,
            'form_schadule_id' => $request->form_schadule_id,
            'nama'             => $request->nama,
        ])->exists();

        if ($check) {
            return response()->json([
                'status'  => false,
                'message' => 'Jenis Keuangan sudah ada, silahkan edit',
            ]);
        }

        $kode = $request->th_akademik_id . $request->th_angkatan_id . $request->prodi_id . $request->kelas_id . $request->form_schadule_id;

        $data = new KeuanganTagihan();
        $data->fill($request->except(['_token', '_method']));

        $data->kode    = $kode;
        $data->jumlah  = $request->jumlah;
        $data->x_sks   = 'Y';
        $data->user_id = auth()->id();

        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Jenis Keuangan created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = KeuanganTagihan::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Tagihan Not Found',
            ], 404);
        }

        return response()->json($data->load('th_akademik', 'th_angkatan', 'prodi', 'form_schadule', 'kelas'), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Validasi data yang dikirimkan
        $validator = Validator::make($request->all(), [
            'th_akademik_id'   => 'required|exists:th_akademik,id',
            'th_angkatan_id'   => 'required|exists:th_akademik,id',
            'prodi_id'         => 'required|exists:prodi,id',
            'double_degree'    => 'nullable|integer',
            'kelas_id'         => 'required|exists:ref,id',
            'form_schadule_id' => 'required|exists:form_schadule,id',
            'nama'             => 'required|string|max:255',
            'jumlah'           => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $data = KeuanganTagihan::find($id);
        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Tagihan Not Found',
            ], 404);
        }

        $check = KeuanganTagihan::where([
            'id'               => ['!=', $id],
            'th_akademik_id'   => $request->th_akademik_id,
            'th_angkatan_id'   => $request->th_angkatan_id,
            'prodi_id'         => $request->prodi_id,
            'kelas_id'         => $request->kelas_id,
            'form_schadule_id' => $request->form_schadule_id,
            'nama'             => $request->nama,
        ])->exists();

        if ($check) {
            return response()->json([
                'status'  => false,
                'message' => 'Jenis Keuangan sudah ada, silahkan edit',
            ]);
        }

        $kode = $request->th_akademik_id . $request->th_angkatan_id . $request->prodi_id . $request->kelas_id . $request->form_schadule_id;

        $data->fill($request->except(['_token', '_method', 'id']));
        $data->kode    = $kode;
        $data->jumlah  = $request->jumlah;
        $data->x_sks   = 'Y';
        $data->user_id = auth()->id();

        $data->save();

        // Kembalikan response sukses dengan data data yang telah diperbarui
        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Tagihan updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = KeuanganTagihan::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Tagihan Not Found',
            ], 404);
        }

        $data->delete();
        return response()->json([
            'status'  => true,
            'message' => 'Tagihan deleted successfully',
        ]);
    }

    /**
     * Import tagihan from Excel file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors(),
            ], 422);
        }

        try {
            $import = new TagihanImport();
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            $failureMessages = [];
            foreach ($failures as $failure) {
                $failureMessages[] = [
                    'row'     => $failure->row(),
                    'errors'  => $failure->errors(),
                    'values'  => $failure->values(),
                ];
            }

            return response()->json([
                'status'        => true,
                'message'       => 'Import selesai',
                'success_count' => $import->getSuccessCount(),
                'skip_count'    => $import->getSkipCount(),
                'skip_reasons'  => $import->getSkipReasons(),
                'failures'      => $failureMessages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Gagal import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download Excel template for import.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadTemplate()
    {
        return Excel::download(new TagihanTemplateExport, 'template_tagihan.xlsx');
    }
}

