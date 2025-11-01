<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Exports\pdf\KwitansiPdf;
use App\Models\KeuanganKamarMhs;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exports\pdf\KwitansiPreviewPdf;
use App\Models\KeuanganJenisPembayaran;
use Illuminate\Support\Facades\Validator;
use App\Models\KeuanganPembayaranTambahan;
use App\Models\KeuanganJenisPembayaranDetail;
use App\Exports\pdf\KwitansiMhsTambahanPdf;

class PembayaranTambahanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganPembayaranTambahan::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('keuangan_pembayaran_tambahan.nomor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran_tambahan.nota', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran_tambahan.nim', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran_tambahan.tagihan', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran_tambahan.jumlah', 'LIKE', "%$request->search%");
            });
        }

        $query->where('keuangan_pembayaran_tambahan.jenis_kelamin', 'LIKE', "%" . Helper::getJenisKelaminUser()->kode . "%");


        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query
            ->select('*');

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pembayaran Tambahan retrieved successfully',
            'jk' => Helper::getJenisKelaminUser()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                'tanggal'             => 'required',
                'th_akademik_1'       => 'required',
                'th_akademik_2'       => 'required',
                'nim'                 => 'required',
                'smt'                 => 'nullable',
                'list_tagihan'        => 'required',
                'list_dibayar'        => 'required',
                'list_jumlah'         => 'required',
                'jenis_pembayaran'    => 'required',
                'jenis_kelamin'       => 'required',
                'prodi'               => 'required',
                'kelas'               => 'required',
                'th_angkatan'         => 'required',
                'nama'                => 'required',
            ]);

            DB::beginTransaction();

            $jk = $dataValidated['jenis_kelamin'];

            $nota = Helper::generateNotaTambahan($dataValidated['tanggal']);

            for ($i = 0; $i < count($dataValidated['list_tagihan']); $i++) {
                $nomor = Helper::generateNumber();
                $data  = KeuanganPembayaranTambahan::where('nomor', $request->nomor)->first();

                if (! $data) {
                    $pembayaran = KeuanganPembayaranTambahan::create([
                        "nota" => $nota,
                        "nomor" => $nomor,

                        "tanggal" => $dataValidated['tanggal'],
                        'th_akademik' => $dataValidated['th_akademik_1'] . '-' . $dataValidated['th_akademik_2'],
                        "nim" => strtoupper($dataValidated['nim']),
                        "nama" => $dataValidated['nama'],
                        "jenis_kelamin" => $dataValidated['jenis_kelamin'],
                        "prodi" => $dataValidated['prodi'],
                        "kelas" => $dataValidated['kelas'],
                        "th_angkatan" => $dataValidated['th_angkatan'],
                        "jenis_pembayaran" => $dataValidated['jenis_pembayaran'],
                        "smt" => $dataValidated['smt'],
                        "tagihan" => $dataValidated['list_tagihan'][$i],
                        "jumlah" => $dataValidated['list_jumlah'][$i],
                        "bayar" => $dataValidated['list_dibayar'][$i],
                        "user_id" => \Auth::user()->id,
                    ]);
                }
            }

            DB::commit();

            $data = [
                "status" => true,
                "code" => 200,
                "id"      => $pembayaran->id,
                "message" => "Berhasil menyimpan data",
                'req' => $request->all()
            ];
            return $data;
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
                'code'  => 422,
                'req'     => $request->all(),
                'asdasd' => 'asdasd'
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            $data = [
                "status" => false,
                "code" => 500,
                "message"    => $th->getMessage(),
            ];
            return $data;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id)
    {
        $data = KeuanganPembayaranTambahan::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak ditemukan.',
                'code'     => 404,
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Berhasil mengambil data.',
            'code'     => 200,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id)
    {
        try {
            $v = Validator::make($request->all(), [
                'tanggal'             => 'required',
                'th_akademik_1'       => 'required',
                'th_akademik_2'       => 'required',
                'nim'                 => 'required',
                'smt'                 => 'nullable',
                'list_tagihan'        => 'required',
                'list_dibayar'        => 'required',
                'list_jumlah'         => 'required',
                'jenis_pembayaran'    => 'required',
                'jenis_kelamin'       => 'required',
                'prodi'               => 'required',
                'kelas'               => 'required',
                'th_angkatan'         => 'required',
                'nama'                => 'required',
            ]);

            if ($v->fails()) {
                return response()->json(['status' => false, 'message' => $v->errors()], 422);
            }

            $pembayaran = KeuanganPembayaranTambahan::find($id);

            if (! $pembayaran) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan.',
                    'code'     => 404,
                ], 404);
            }

            $pembayaran->update([
                "tanggal" => $request->tanggal,
                "tagihan" => $request->list_tagihan,
                'th_akademik' => $request->th_akademik_1 . '-' . $request->th_akademik_2,
                "nim" => strtoupper($request->nim),
                "nama" => $request->nama,
                "jenis_kelamin" => $request->jenis_kelamin,
                "prodi" => $request->prodi,
                "kelas" => $request->kelas,
                "th_angkatan" => $request->th_angkatan,
                "jenis_pembayaran" => $request->jenis_pembayaran,
                "smt" => $request->smt,
                "tagihan" => $request->list_tagihan,
                "jumlah" => $request->list_jumlah,
                "bayar" => $request->list_dibayar,
                "user_id" => \Auth::user()->id,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Berhasil mengupdate data.',
                'code'     => 200,
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
                'asd' => 'asd'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            KeuanganPembayaranTambahan::destroy($id);

            $data = [
                "status" => true,
                "code" => 200,
                "message" => "Berhasil menghapus data",
            ];
            return $data;
        } catch (\Throwable $th) {
            $data = [
                "status" => false,
                "code" => 500,
                "message" => "Gagal mengahapus data",
            ];
            return $data;
        }
    }

    public function kwitansi($id)
    {
        $keuanganPembayaran = KeuanganPembayaranTambahan::findOrFail($id);
        return KwitansiMhsTambahanPdf::pdf($keuanganPembayaran);
    }

    public function kwitansiPreview($id)
    {
        $keuanganPembayaran = KeuanganPembayaranTambahan::findOrFail($id);
        return KwitansiPreviewPdf::pdf($keuanganPembayaran);
    }
}
