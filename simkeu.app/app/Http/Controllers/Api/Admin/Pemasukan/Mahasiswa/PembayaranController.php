<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\KeuanganJenisPembayaran;
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
            ->leftJoin('keuangan_nota', 'keuangan_nota.pembayaran_id', '=', 'keuangan_pembayaran.id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('th_akademik.kode', 'LIKE', "%$request->search%")
                    ->orWhere('th_akademik.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.nim', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_pembayaran.jumlah', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_nota.nota', 'LIKE', "%$request->search%");
            });
        }

        if ($request->filled('th_akademik_id')) {
            $query->where('keuangan_pembayaran.th_akademik_id', $request->th_akademik_id);
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query
            ->select('keuangan_pembayaran.*', 'th_akademik.kode as th_akademik_kode', 'keuangan_tagihan.nama as keuangan_tagihan_nama')
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
                'jk_id' => 'required'
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
                "message" => 200,
                "data"    => "Berhasil menyimpan data",
                "id"      => $pembayaran->id,
            ];
            return $data;
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 422,
                'errors'  => $e->errors(),
                'req'     => $request->all(),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            $data = [
                "message" => 500,
                "data"    => $th->getMessage(),
            ];
            return $data;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
