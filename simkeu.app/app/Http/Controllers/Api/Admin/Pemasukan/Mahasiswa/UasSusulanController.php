<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use Carbon\Carbon;
use App\Models\Prodi;
use App\Services\Helper;
use App\Services\Jadwal;
use Illuminate\Http\Request;
use App\Exports\UasSusulanExport;
use App\Models\KeuanganUasSusulan;
use App\Http\Controllers\Controller;
use App\Models\KeuanganUasSusulanMk;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UasSusulanController extends Controller
{
    // GET /keuangan/uas-susulan
    public function index(Request $request)
    {
        $q = KeuanganUasSusulan::query();
        $q->select('keuangan_uas_susulan.*', 'th_akademik.nama as th_akademik_nama', 'th_akademik.kode as th_akademik_kode', 'th_akademik.semester as th_akademik_semester');
        $q->join('th_akademik', 'keuangan_uas_susulan.th_akademik_id', 'th_akademik.id');

        // Pencarian umum
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($x) use ($s) {
                $x->where('nim', 'like', "%{$s}%")
                    ->orWhere('tanggal', 'like', "%{$s}%")
                    ->orWhere('th_akademik.nama', 'like', "%{$s}%")
                    ->orWhere('th_akademik.kode', 'like', "%{$s}%")
                    ->orWhere('th_akademik.semester', 'like', "%{$s}%");
            });
        }

        // Filter spesifik (opsional)
        if ($request->filled('th_akademik_id'))       $q->where('th_akademik_id', $request->th_akademik_id);

        $q = Helper::whereMahasiswaJkChunk($q, 'keuangan_uas_susulan.nim');
        
        // Sorting (whitelist)
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed   = ['id', 'tanggal', 'nim', 'keterangan', 'th_akademik_id', 'created_at', 'updated_at', 'th_akademik_nama', 'th_akademik_kode', 'th_akademik_semester'];
        if (!in_array($sortKey, $allowed, true)) $sortKey = 'id';

        $q->orderBy($sortKey, $sortOrder);

        $data = $q->paginate($request->integer('limit') ?: 10);

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Data uas-susulan berhasil diambil.',
        ]);
    }

    // POST /keuangan/uas-susulan
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'          => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $existing = KeuanganUasSusulan::where('nim', $request->nim)
            ->where('th_akademik_id', $request->th_akademik_id)
            ->first();
        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'Mahasiswa sudah terdaftar UAS Susulan pada tahun akademik ini.',
            ], 422);
        }

        $data = KeuanganUasSusulan::create([
            'tanggal'      => $request->tanggal,
            'nim'          => $request->nim,
            'th_akademik_id' => $request->th_akademik_id,
            'keterangan'   => $request->keterangan,
            'user_id'      => Auth::user()->id,
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik'),
            'message' => 'Uas Susulan berhasil dibuat.',
        ], 201);
    }


    // POST /keuangan/uas-susulan/full
    public function storeFull(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'          => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
            'jadwal_kuliah_id' => 'required|array'
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $existing = KeuanganUasSusulan::where('nim', $request->nim)
            ->where('th_akademik_id', $request->th_akademik_id)
            ->first();
        if ($existing) {
            return response()->json([
                'status'  => false,
                'message' => 'Mahasiswa sudah terdaftar UAS Susulan pada tahun akademik ini.',
            ], 422);
        }

        $data = KeuanganUasSusulan::create([
            'tanggal'      => $request->tanggal,
            'nim'          => $request->nim,
            'th_akademik_id' => $request->th_akademik_id,
            'keterangan'   => $request->keterangan,
            'user_id'      => Auth::user()->id,
        ]);

        foreach ($request->jadwal_kuliah_id as $jadwal_kuliah_id) {
            KeuanganUasSusulanMk::create([
                'uas_susulan_id' => $data->id,
                'jadwal_kuliah_id' => $jadwal_kuliah_id,
                'user_id' => Auth::user()->id,
            ]);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil dibuat.',
        ], 201);
    }


    // GET /keuangan/uas-susulan/{id}
    public function show($id)
    {
        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }
        $data->load('th_akademik', 'uasSusulanMk');

        // Ambil data detail mahasiswa dari SIAKAD
        try {
            $data->mahasiswa = \App\Services\Mahasiswa::nim($data->nim);
        } catch (\Throwable $th) {
            $data->mahasiswa = null;
        }

        // Ambil data jadwal dari jadwal_kuliah_id yang terdaftar pada uas_susulan_mk
        try {
            $jadwalIds = $data->uasSusulanMk->pluck('jadwal_kuliah_id')->filter()->unique()->values()->all();
            
            $jadwalList = !empty($jadwalIds) ? Jadwal::find(json_encode($jadwalIds), true) : [];
            $jadwalMap = [];
            $thIdFromJadwal = null;

            if (is_array($jadwalList)) {
                foreach ($jadwalList as $j) {
                    $jadwalMap[$j->id] = $j;
                    if (!$thIdFromJadwal && isset($j->th_akademik_id)) {
                        $thIdFromJadwal = $j->th_akademik_id;
                    }
                }
            }

            // Cari nilai dari KRS mahasiswa pada th_akademik asal jadwal atau th_akademik_id transaksi
            $targetThId = $thIdFromJadwal ?: $data->th_akademik_id;
            $krsData = Jadwal::mahasiswa($data->nim, $targetThId);
            $krsMap = [];
            if (isset($krsData->data->krs_detail) && is_array($krsData->data->krs_detail)) {
                foreach ($krsData->data->krs_detail as $k) {
                    $krsMap[$k->jadwal_kuliah_id] = $k;
                }
                $data->krs_detail = $krsData->data->krs_detail;
                if (isset($krsData->data->prodi)) {
                    $data->krs_prodi = $krsData->data->prodi;
                }
            }

            // Perkarya setiap uasSusulanMk
            foreach ($data->uasSusulanMk as $mk) {
                $j = $jadwalMap[$mk->jadwal_kuliah_id] ?? null;
                $k = $krsMap[$mk->jadwal_kuliah_id] ?? null;

                $dosenNama = '-';
                if ($j && isset($j->dosen)) {
                    $d = $j->dosen;
                    $dosenNama = trim(($d->gelar_depan ? $d->gelar_depan . ' ' : '') . $d->nama . ($d->gelar_belakang ? ', ' . $d->gelar_belakang : ''));
                } elseif ($k && !empty($k->dosen_nama)) {
                    $dosenNama = $k->dosen_nama;
                }

                $mk->setAttribute('mk_detail', [
                    'jadwal_kuliah_id' => $mk->jadwal_kuliah_id,
                    'kode_mk'          => $j?->kurikulum_matakuliah?->matakuliah?->kode ?? ($k?->kode_mk ?? '-'),
                    'nama_mk'          => $j?->kurikulum_matakuliah?->matakuliah?->nama ?? ($k?->nama_mk ?? "Mata Kuliah #{$mk->jadwal_kuliah_id}"),
                    'sks_mk'           => $j?->kurikulum_matakuliah?->matakuliah?->sks ?? ($k?->sks_mk ?? '-'),
                    'smt_mk'           => $j?->kurikulum_matakuliah?->matakuliah?->smt ?? ($j?->smt ?? ($k?->smt_mk ?? '-')),
                    'dosen_nama'       => $dosenNama,
                    'nilai_akhir'      => $k?->nilai_akhir ?? null,
                    'nilai_huruf'      => $k?->nilai_huruf ?? '',
                    'kelompok'         => $j?->kelompok?->kode ?? ($k?->jadwal_kuliah?->kelompok?->kode ?? '-'),
                ]);
            }

            // Sediakan juga uasSusulanMk di root attribute agar format camelCase maupun snake_case tercover
            $data->setAttribute('uasSusulanMk', $data->uasSusulanMk);
        } catch (\Throwable $th) {
            // Abaikan error jika SIAKAD offline
        }

        return response()->json($data, 200);
    }

    // PUT/PATCH /keuangan/uas-susulan/{id}
    public function update(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'           => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        $data->tanggal      = $request->tanggal;
        $data->nim          = $request->nim;
        $data->th_akademik_id = $request->th_akademik_id;
        $data->keterangan   = $request->keterangan;
        $data->user_id      = Auth::user()->id;
        $data->save();

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil diperbarui.',
        ]);
    }

    // PUT/PATCH /keuangan/uas-susulan/full/{id}
    public function updateFull(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'tanggal'      => 'required|date',
            'nim'           => 'required|string|max:255',
            'th_akademik_id' => 'required|numeric',
            'keterangan'   => 'nullable|string',
            'jadwal_kuliah_id' => 'required|array'
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        $data->tanggal      = $request->tanggal;
        $data->nim          = $request->nim;
        $data->th_akademik_id = $request->th_akademik_id;
        $data->keterangan   = $request->keterangan;
        $data->user_id      = Auth::user()->id;
        $data->save();

        // Hapus semua record yang terkait
        KeuanganUasSusulanMk::where('uas_susulan_id', $id)->delete();

        // Simpan ulang jadwal kuliah yang baru
        foreach ($request->jadwal_kuliah_id as $jadwal_kuliah_id) {
            KeuanganUasSusulanMk::create([
                'uas_susulan_id' => $data->id,
                'jadwal_kuliah_id' => $jadwal_kuliah_id,
                'user_id' => Auth::user()->id,
            ]);
        }

        return response()->json([
            'status'  => true,
            'data'    => $data->load('th_akademik', 'uasSusulanMk'),
            'message' => 'Uas Susulan berhasil diperbarui.',
        ]);
    }


    // DELETE /keuangan/uas-susulan/{id}
    public function destroy($id)
    {
        $userRole = strtolower(Auth::user()->role->name ?? '');
        if (!in_array($userRole, ['admin', 'kabag', 'kabag_pemasukan'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya role Admin dan Kabag yang diizinkan untuk menghapus data UAS Susulan.',
            ], 403);
        }

        $data = KeuanganUasSusulan::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Uas Susulan tidak ditemukan.'], 404);
        }

        DB::transaction(function () use ($data, $id) {
            KeuanganUasSusulanMk::where('uas_susulan_id', $id)->delete();
            $data->delete();
        });

        return response()->json([
            'status'  => true,
            'message' => 'Data UAS Susulan dan mata kuliah terkait berhasil dihapus.',
        ]);
    }

    // DELETE /keuangan/uas-susulan/full/{id}
    public function destroyFull($id)
    {
        return $this->destroy($id);
    }

    public function checkRegistered(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nim'            => 'required|string',
            'th_akademik_id' => 'required|numeric',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()], 422);
        }

        $existing = KeuanganUasSusulan::with(['th_akademik'])
            ->where('nim', $request->nim)
            ->where('th_akademik_id', $request->th_akademik_id)
            ->first();

        return response()->json([
            'status'        => true,
            'is_registered' => (bool) $existing,
            'data'          => $existing,
            'message'       => $existing
                ? 'Mahasiswa sudah terdaftar UAS Susulan pada tahun akademik ini.'
                : 'Mahasiswa belum terdaftar UAS Susulan pada tahun akademik ini.',
        ]);
    }

    public function getJadwalKuliah(Request $request)
    {
        $data = Jadwal::mahasiswa($request->nim, $request->th_akademik_id);
        if (isset($data->data->krs_detail) && is_array($data->data->krs_detail)) {
            $dosenIds = collect($data->data->krs_detail)->pluck('dosen_id')->filter()->unique()->values();
            $dosenMap = \App\Models\Dosen::with('pegawai')->whereIn('id', $dosenIds)->get()->keyBy('id');
            foreach ($data->data->krs_detail as $item) {
                $d = $dosenMap->get($item->dosen_id);
                $item->dosen_nama = $d && $d->pegawai
                    ? trim(($d->gelar_depan ? $d->gelar_depan . ' ' : '') . $d->pegawai->nama . ($d->gelar_belakang ? ', ' . $d->gelar_belakang : ''))
                    : '-';
            }
        }
        return response()->json($data);
    }

    public function excel(Request $request)
    {
        try {
            $dataValidated = $request->validate([
                "tanggal_print" => 'required',
                "prodi_id_print" => 'required',
            ]);

            $data = [
                'message' => 200,
                'data' => $dataValidated,
            ];

            $prodi = 'SEMUA PRODI';

            if($request->prodi_id_print != '*'){
                $prodi = Prodi::find($request->prodi_id_print);
                $prodi = $prodi->alias;
            }

            $tanggal = Carbon::create($request->tanggal_print)->format('d-m-Y');

            return Excel::download(new UasSusulanExport($data), "UAS SUSULAN $tanggal $prodi.xlsx");

        } catch (\Throwable $th) {
            $data = [
                'message' => 500,
                'data' => $th->getMessage(),
                'req' => $request->all(),
            ];
        }
        return $data;

    }
}
