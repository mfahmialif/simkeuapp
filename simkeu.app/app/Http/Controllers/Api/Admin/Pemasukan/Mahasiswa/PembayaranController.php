<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Exports\pdf\KwitansiPdf;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exports\pdf\KwitansiPreviewPdf;
use App\Models\KeuanganJenisPembayaran;
use Illuminate\Support\Facades\Validator;
use App\Models\KeuanganJenisPembayaranDetail;

class PembayaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganPembayaran::join('th_akademik', 'th_akademik.id', '=', 'keuangan_pembayaran.th_akademik_id')
            ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
            ->leftJoin('keuangan_nota', 'keuangan_nota.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('th_akademik.kode', 'LIKE', "%$request->search%")
                    ->orWhere('th_akademik.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.nomor', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.nim', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.jumlah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_nota.nota', 'LIKE', "%$request->search%");
            });
        }

        $query->where('keuangan_pembayaran.jk_id', 'LIKE', "%" . Helper::getJenisKelaminUser()->id . "%");

        if ($request->filled('th_akademik_id')) {
            $query->where('keuangan_pembayaran.th_akademik_id', $request->th_akademik_id);
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query
            ->select('keuangan_pembayaran.*', 'th_akademik.kode as th_akademik_kode', 'keuangan_tagihan.nama as keuangan_tagihan_nama', 'keuangan_jenis_pembayaran.nama as keuangan_jenis_pembayaran_nama')
            ->addSelect(DB::raw(
                "COALESCE(keuangan_nota.nota, keuangan_pembayaran.nomor) AS nota"
            ));

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Tagihan retrieved successfully',
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
                'tahun_akademik'      => 'required',
                'nim'                 => 'required',
                'semester'            => 'nullable',
                'list_tagihan_id'     => 'required',
                'list_tagihan'        => 'required',
                'list_dibayar'        => 'required',
                'list_deposit'        => 'required',
                'jenis_pembayaran'    => 'required',
                'dipakai_deposit_mhs' => 'nullable',
                'kamar_id'            => 'nullable',
                'jk_id'               => 'required'
            ]);

            DB::beginTransaction();

            $jk = Helper::getJenisKelaminUser();

            $nota = Helper::generateNota($dataValidated['tanggal'], $request->jk_id);

            for ($i = 0; $i < count($dataValidated['list_tagihan']); $i++) {
                $nomor = Helper::generateNumber();
                $data  = KeuanganPembayaran::where('nomor', $request->nomor)->first();

                if (! $data) {

                    if ($dataValidated['list_dibayar'][$i] != 0) {
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $dataValidated['list_tagihan_id'][$i],
                            "nim"            => strtoupper($dataValidated['nim']),
                            "jumlah"         => $dataValidated['list_dibayar'][$i],
                            "smt"            => $dataValidated['semester'],
                            "jml_sks"        => 1,
                            "jk_id"          => $request->jk_id,
                            "user_id"        => Auth::user()->id,
                        ]);

                        KeuanganJenisPembayaranDetail::create([
                            'jenis_pembayaran_id' => $dataValidated['jenis_pembayaran'],
                            'pembayaran_id'       => $pembayaran->id,
                        ]);

                        KeuanganNota::create([
                            'nota'          => $nota,
                            'pembayaran_id' => $pembayaran->id,
                        ]);

                        if (
                            stripos(strtolower($pembayaran->tagihan->nama), 'daftar ulang') !== false ||
                            stripos(strtolower($pembayaran->tagihan->nama), 'regist') !== false
                        ) {
                            // // update mahasiswa jadi aktif
                            // $mhs = Mahasiswa::where('nim', $dataValidated['nim'])->first();
                            // if ($mhs) {
                            //     $mhs->status_id = 18; //status Aktif
                            //     $mhs->user_id   = Auth::user()->id;
                            //     $mhs->save();
                            // }
                        }
                    }

                    // insert data deposit jika bukan 0, auto jenis pembayaran deposit
                    if ($dataValidated['list_deposit'][$i] != 0) {
                        $jenisPembayaran = KeuanganJenisPembayaran::where([
                            ['nama', 'LIKE', "%deposit%"],
                            ['kategori', 'LIKE', "%$jk->kategori%"],
                        ])->first();
                        $nomor      = Helper::generateNumber();
                        $pembayaran = KeuanganPembayaran::create([
                            "th_akademik_id" => $dataValidated['tahun_akademik'],
                            "nomor"          => $nomor,
                            "tanggal"        => $dataValidated['tanggal'],
                            "tagihan_id"     => $dataValidated['list_tagihan_id'][$i],
                            "nim"            => strtoupper($dataValidated['nim']),
                            "jumlah"         => $dataValidated['list_deposit'][$i],
                            "smt"            => $dataValidated['semester'],
                            "jml_sks"        => 1,
                            "jk_id"          => $request->jk_id,
                            "user_id"        => Auth::user()->id,
                        ]);

                        KeuanganJenisPembayaranDetail::create([
                            'jenis_pembayaran_id' => $jenisPembayaran->id,
                            'pembayaran_id'       => $pembayaran->id,
                        ]);

                        KeuanganNota::create([
                            'nota'          => $nota,
                            'pembayaran_id' => $pembayaran->id,
                        ]);

                        if (
                            stripos(strtolower($pembayaran->tagihan->nama), 'daftar ulang') !== false ||
                            stripos(strtolower($pembayaran->tagihan->nama), 'regist') !== false
                        ) {
                        }
                    }
                }
            }

            // catatan-deposit
            if ($dataValidated['dipakai_deposit_mhs'] != "") {
                $deposit = KeuanganDeposit::where('nim', $dataValidated['nim'])->first();
                if ($deposit != null) {
                    $jumlahDepositBaru = $deposit->jumlah - $dataValidated['dipakai_deposit_mhs'];
                    $deposit->update([
                        'jumlah' => $jumlahDepositBaru,
                    ]);
                }
            }

            // Banat Tambah Kamar
            if ($request->has('kamar_id')) {
                if ($dataValidated['kamar_id'] != null) {
                    if ($jk->kategori == '%' || $jk->kategori == 'Putri') {
                        $kamarMhs = KeuanganKamarMhs::where('nim', $dataValidated['nim'])->first();
                        if (! $kamarMhs) {
                            $kamarMhs = new KeuanganKamarMhs();
                        }
                        $kamarMhs->nim      = $dataValidated['nim'];
                        $kamarMhs->kamar_id = $dataValidated['kamar_id'];
                        $kamarMhs->save();
                    }
                }
            }
            // End Banat Tambah Kamar

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
        $data = KeuanganPembayaran::find($id);

        if (! $data) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak ditemukan.',
                'code'     => 404,
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'tagihan', 'keuanganNota', 'jenisPembayaranDetail', 'user'),
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
                'tanggal'      => 'required|date',
                'th_akademik_id' => 'required|integer',
                'jumlah'       => 'required|numeric',
            ]);

            if ($v->fails()) {
                return response()->json(['status' => false, 'message' => $v->errors()], 422);
            }

            $pembayaran = KeuanganPembayaran::find($id);

            if (! $pembayaran) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan.',
                    'code'     => 404,
                ], 404);
            }

            $pembayaran->update([
                'tanggal'        => $request->input('tanggal'),
                'th_akademik_id' => $request->input('th_akademik_id'),
                'jumlah'         => $request->input('jumlah'),
                'user_id'        => Auth::user()->id,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Berhasil mengupdate data.',
                'code'     => 200,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            KeuanganPembayaran::destroy($id);

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
        $keuanganPembayaran = KeuanganPembayaran::findOrFail($id);
        return KwitansiPdf::pdf($keuanganPembayaran);
    }

    public function kwitansiPreview($id)
    {
        $keuanganPembayaran = KeuanganPembayaran::findOrFail($id);
        return KwitansiPreviewPdf::pdf($keuanganPembayaran);
    }
}
