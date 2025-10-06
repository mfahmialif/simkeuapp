<?php
namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Mahasiswa;
use Illuminate\Http\Request;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;
use App\Http\Controllers\Controller;

class PemasukanPengeluaranController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data  = [];
        $putra = (object) [
            "id"       => 8,
            "nama"     => "Laki-laki",
            "kategori" => "Putra",
            "kode"     => "L",
        ];
        $putri = (object) [
            "id"       => 9,
            "nama"     => "Perempuan",
            "kategori" => "Putri",
            "kode"     => "P",
        ];
        $semua = (object) [
            "id"       => "%",
            "nama"     => "%",
            "kategori" => "%",
            "kode"     => "%",
        ];

        $mahasiswaApi = Mahasiswa::all();
        $data['Laki-laki'] = $this->data($putra, $mahasiswaApi);
        $data['Perempuan'] = $this->data($putri, $mahasiswaApi);
        $data['semua']     = $this->data($semua, $mahasiswaApi);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data retrieve successfully',
        ]);
    }

    public function data($jk, $mahasiswaApi)
    {
        $nimList = collect($mahasiswaApi)
            ->filter(fn($m) => str_contains((string) $m->jk_id, "{$jk->id}"))
            ->pluck('nim')
            ->values();

        $pemasukan = 0;
        $nimList->chunk(1000)->each(function ($chunk) use (&$pemasukan) {
            $batch = KeuanganPembayaran::with('tagihan')
                ->whereIn('nim', $chunk)
                ->get();

            foreach ($batch as $t) {
                if ($t->jumlah == $t->nim) {
                    $t->jumlah = optional($t->tagihan)->jumlah ?? 0;
                }
                $pemasukan += (float) $t->jumlah;
            }
        });

        $setoran     = KeuanganSetoran::where('kategori', 'LIKE', "%{$jk->kategori}%")->get();
        $pengeluaran = 0;
        $pending     = 0;
        foreach ($setoran as $s) {
            $status = strtolower((string) $s->status);
            if ($status === 'setuju') {
                $pengeluaran += (float) $s->jumlah;
            }

            if ($status === 'pending') {
                $pending += (float) $s->jumlah;
            }

        }
        return [
            'pemasukan'   => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'pending'     => $pending,
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }
}
