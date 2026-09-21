<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeuanganUasSusulan;
use App\Services\Jadwal;
use App\Services\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiakadUasSusulanController extends Controller
{
    /**
     * Get list of UAS Susulan with UAS Susulan MK for SIAKAD.
     *
     * Authentication:
     * - Header: X-SIAKAD-API-KEY: <api_key> (or apikey: <api_key>)
     * - Header: Accept: application/json
     *
     * Supported Filters:
     * - nim: string|array (NIM mahasiswa, e.g. '210101001' or '210101001,210101002')
     * - th_akademik_kode: string (Kode tahun akademik, e.g. '20252')
     * - th_akademik_id: int (ID tahun akademik di SIMKEU)
     * - jadwal_kuliah_id: int (ID jadwal kuliah yang disusulkan)
     * - tanggal: string (YYYY-MM-DD)
     * - tanggal_mulai: string (YYYY-MM-DD)
     * - tanggal_akhir: string (YYYY-MM-DD)
     * - search: string (pencarian umum)
     * - limit: int|string (default 20, 0 / 'all' untuk semua data)
     * - page: int (nomor halaman pagination)
     * - sort_by: string (kolom sorting: id, tanggal, nim, th_akademik_id, created_at)
     * - sort_dir: string (asc / desc)
     */
    public function index(Request $request, ?string $nim = null): JsonResponse
    {
        $nimFilter = $nim ?: $request->input('nim');

        $query = KeuanganUasSusulan::query()
            ->with([
                'th_akademik:id,kode,nama,semester,aktif',
                'uasSusulanMk:id,uas_susulan_id,jadwal_kuliah_id,created_at',
            ]);

        // Filter NIM
        if ($nimFilter) {
            if (is_array($nimFilter)) {
                $query->whereIn('nim', array_map('trim', $nimFilter));
            } elseif (str_contains($nimFilter, ',')) {
                $nims = array_filter(array_map('trim', explode(',', $nimFilter)));
                $query->whereIn('nim', $nims);
            } else {
                $query->where('nim', trim($nimFilter));
            }
        }

        // Filter Kode Tahun Akademik
        if ($request->filled('th_akademik_kode')) {
            $kode = trim((string) $request->input('th_akademik_kode'));
            $query->whereHas('th_akademik', function ($q) use ($kode) {
                $q->where('kode', $kode);
            });
        }

        // Filter ID Tahun Akademik
        if ($request->filled('th_akademik_id')) {
            $query->where('th_akademik_id', (int) $request->input('th_akademik_id'));
        }

        // Filter ID Jadwal Kuliah spesifik
        if ($request->filled('jadwal_kuliah_id')) {
            $jadwalId = (int) $request->input('jadwal_kuliah_id');
            $query->whereHas('uasSusulanMk', function ($q) use ($jadwalId) {
                $q->where('jadwal_kuliah_id', $jadwalId);
            });
        }

        // Filter Tanggal
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal', '>=', $request->input('tanggal_mulai'));
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal', '<=', $request->input('tanggal_akhir'));
        }

        // Pencarian umum
        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('nim', 'like', "%{$s}%")
                    ->orWhere('keterangan', 'like', "%{$s}%")
                    ->orWhereHas('th_akademik', function ($tq) use ($s) {
                        $tq->where('nama', 'like', "%{$s}%")
                            ->orWhere('kode', 'like', "%{$s}%");
                    });
            });
        }

        // Sorting
        $sortKey = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortKey, ['id', 'tanggal', 'nim', 'th_akademik_id', 'created_at'], true)) {
            $query->orderBy($sortKey, $sortDir);
        } else {
            $query->latest('id');
        }

        $limitInput = $request->input('limit', 20);
        $isAll = $limitInput === '0' || $limitInput === 0 || $limitInput === 'all';

        if ($isAll) {
            $items = $query->get();
            $formatted = $items->map(fn ($item) => $this->formatUasSusulan($item))->values();

            return response()->json([
                'status' => true,
                'message' => 'Data UAS Susulan berhasil diambil.',
                'total' => $items->count(),
                'data' => $formatted,
            ]);
        }

        $limit = max(1, min(200, (int) $limitInput));
        $paginated = $query->paginate($limit);

        $formatted = collect($paginated->items())
            ->map(fn ($item) => $this->formatUasSusulan($item))
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Data UAS Susulan berhasil diambil.',
            'data' => $formatted,
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Format response object for a single UAS Susulan record.
     */
    public function formatUasSusulan(KeuanganUasSusulan $item): array
    {
        $mkList = $item->uasSusulanMk ?? collect();
        $jadwalKuliahIds = $mkList->pluck('jadwal_kuliah_id')->filter()->values()->all();

        return [
            'id' => $item->id,
            'nim' => $item->nim,
            'tanggal' => $item->tanggal ? (string) $item->tanggal : null,
            'keterangan' => $item->keterangan,
            'th_akademik_id' => $item->th_akademik_id,
            'th_akademik_kode' => $item->th_akademik?->kode,
            'th_akademik_nama' => $item->th_akademik?->nama,
            'th_akademik_semester' => $item->th_akademik?->semester,
            'jadwal_kuliah_ids' => $jadwalKuliahIds,
            'total_mk' => count($jadwalKuliahIds),
            'mata_kuliah' => $mkList->map(fn ($mk) => [
                'id' => $mk->id,
                'uas_susulan_id' => $mk->uas_susulan_id,
                'jadwal_kuliah_id' => $mk->jadwal_kuliah_id,
                'created_at' => $mk->created_at ? $mk->created_at->toISOString() : null,
            ])->values()->all(),
            'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
            'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
        ];
    }

    /**
     * Get list of Peserta Ujian Susulan beserta jumlah MK.
     *
     * URL: GET /peserta
     * Headers:
     * - X-SIAKAD-API-KEY: <api_key> (atau apikey: <api_key>)
     * - Accept: application/json
     *
     * Supported Filters:
     * - nim: string|array
     * - th_akademik_kode: string (e.g. '20251')
     * - th_akademik_id: int
     * - jadwal_kuliah_id: int
     * - tanggal: string (YYYY-MM-DD)
     * - tanggal_mulai: string (YYYY-MM-DD)
     * - tanggal_akhir: string (YYYY-MM-DD)
     * - search: string
     * - limit: int|string (default 20, 0 / 'all' untuk semua data)
     * - page: int
     * - sort_by: string (id, tanggal, nim, th_akademik_id, created_at)
     * - sort_dir: string (asc / desc)
     * - with_mhs: bool (default true, memuat nama & prodi mahasiswa via SIAKAD)
     */
    public function peserta(Request $request): JsonResponse
    {
        $nimFilter = $request->input('nim');

        $query = KeuanganUasSusulan::query()
            ->with([
                'th_akademik:id,kode,nama,semester,aktif',
                'uasSusulanMk:id,uas_susulan_id,jadwal_kuliah_id',
            ]);

        // Filter NIM
        if ($nimFilter) {
            if (is_array($nimFilter)) {
                $query->whereIn('nim', array_map('trim', $nimFilter));
            } elseif (str_contains($nimFilter, ',')) {
                $nims = array_filter(array_map('trim', explode(',', $nimFilter)));
                $query->whereIn('nim', $nims);
            } else {
                $query->where('nim', trim($nimFilter));
            }
        }

        // Filter Kode Tahun Akademik
        if ($request->filled('th_akademik_kode')) {
            $kode = trim((string) $request->input('th_akademik_kode'));
            $query->whereHas('th_akademik', function ($q) use ($kode) {
                $q->where('kode', $kode);
            });
        }

        // Filter ID Tahun Akademik
        if ($request->filled('th_akademik_id')) {
            $query->where('th_akademik_id', (int) $request->input('th_akademik_id'));
        }

        // Filter ID Jadwal Kuliah spesifik
        if ($request->filled('jadwal_kuliah_id')) {
            $jadwalId = (int) $request->input('jadwal_kuliah_id');
            $query->whereHas('uasSusulanMk', function ($q) use ($jadwalId) {
                $q->where('jadwal_kuliah_id', $jadwalId);
            });
        }

        // Filter Tanggal
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal', '>=', $request->input('tanggal_mulai'));
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal', '<=', $request->input('tanggal_akhir'));
        }

        // Pencarian umum
        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('nim', 'like', "%{$s}%")
                    ->orWhere('keterangan', 'like', "%{$s}%")
                    ->orWhereHas('th_akademik', function ($tq) use ($s) {
                        $tq->where('nama', 'like', "%{$s}%")
                            ->orWhere('kode', 'like', "%{$s}%");
                    });
            });
        }

        // Sorting
        $sortKey = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortKey, ['id', 'tanggal', 'nim', 'th_akademik_id', 'created_at'], true)) {
            $query->orderBy($sortKey, $sortDir);
        } else {
            $query->latest('id');
        }

        $limitInput = $request->input('limit', 20);
        $isAll = $limitInput === '0' || $limitInput === 0 || $limitInput === 'all';

        if ($isAll) {
            $items = $query->get();
            $formatted = $this->formatPesertaCollection($items, $request);

            return response()->json([
                'status'  => true,
                'message' => 'Data peserta ujian susulan berhasil diambil.',
                'total'   => $items->count(),
                'data'    => $formatted,
            ]);
        }

        $limit = max(1, min(200, (int) $limitInput));
        $paginated = $query->paginate($limit);

        $formatted = $this->formatPesertaCollection(collect($paginated->items()), $request);

        return response()->json([
            'status'     => true,
            'message'    => 'Data peserta ujian susulan berhasil diambil.',
            'data'       => $formatted,
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Format collection of peserta ujian susulan with batch mahasiswa enrichment.
     */
    protected function formatPesertaCollection($items, Request $request): array
    {
        $mhsMap = [];
        $includeMhs = $request->boolean('with_mhs', true);

        if ($includeMhs && $items->isNotEmpty()) {
            $nims = $items->pluck('nim')->filter()->unique()->values()->all();
            if (!empty($nims)) {
                try {
                    $mhsList = Mahasiswa::nim(json_encode($nims), true);
                    if (is_array($mhsList)) {
                        foreach ($mhsList as $m) {
                            if (isset($m->nim)) {
                                $mhsMap[$m->nim] = $m;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fallback gracefully if SIAKAD service is unavailable
                }
            }
        }

        return $items->map(function ($item) use ($mhsMap) {
            $mhs = $mhsMap[$item->nim] ?? null;
            $jadwalKuliahIds = $item->uasSusulanMk
                ? $item->uasSusulanMk->pluck('jadwal_kuliah_id')->filter()->values()->all()
                : [];
            $totalMk = count($jadwalKuliahIds);

            return [
                'id'                   => $item->id,
                'nim'                  => $item->nim,
                'nama'                 => $mhs?->nama ?? null,
                'prodi'                => $mhs && isset($mhs->prodi) ? [
                    'id'      => $mhs->prodi->id ?? null,
                    'kode'    => $mhs->prodi->kode ?? null,
                    'alias'   => $mhs->prodi->alias ?? null,
                    'nama'    => $mhs->prodi->nama ?? null,
                    'jenjang' => $mhs->prodi->jenjang ?? null,
                ] : null,
                'kelas'                => $mhs?->kelas?->nama ?? null,
                'th_akademik_id'       => $item->th_akademik_id,
                'th_akademik_kode'     => $item->th_akademik?->kode,
                'th_akademik_nama'     => $item->th_akademik?->nama,
                'th_akademik_semester' => $item->th_akademik?->semester,
                'tanggal'              => $item->tanggal ? (string) $item->tanggal : null,
                'keterangan'           => $item->keterangan,
                'jumlah_mk'            => $totalMk,
                'total_mk'             => $totalMk,
                'jadwal_kuliah_ids'    => $jadwalKuliahIds,
                'created_at'           => $item->created_at ? $item->created_at->toISOString() : null,
                'updated_at'           => $item->updated_at ? $item->updated_at->toISOString() : null,
            ];
        })->values()->all();
    }

    /**
     * Get detail data mata kuliah ujian susulan for a specific student (by NIM).
     *
     * URL: GET /peserta/{nim}/detail
     * Headers:
     * - X-SIAKAD-API-KEY: <api_key> (atau apikey: <api_key>)
     * - Accept: application/json
     */
    public function pesertaDetail(Request $request, string $nim): JsonResponse
    {
        $cleanNim = trim($nim);

        $query = KeuanganUasSusulan::query()
            ->where('nim', $cleanNim)
            ->with([
                'th_akademik:id,kode,nama,semester,aktif',
                'uasSusulanMk:id,uas_susulan_id,jadwal_kuliah_id,created_at',
            ]);

        if ($request->filled('th_akademik_kode')) {
            $kode = trim((string) $request->input('th_akademik_kode'));
            $query->whereHas('th_akademik', function ($q) use ($kode) {
                $q->where('kode', $kode);
            });
        }

        if ($request->filled('th_akademik_id')) {
            $query->where('th_akademik_id', (int) $request->input('th_akademik_id'));
        }

        if ($request->filled('uas_susulan_id')) {
            $query->where('id', (int) $request->input('uas_susulan_id'));
        }

        $records = $query->orderBy('id', 'desc')->get();

        if ($records->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => "Data pendaftaran ujian susulan untuk NIM '{$cleanNim}' tidak ditemukan.",
                'data'    => null,
            ], 404);
        }

        // Ambil data profil mahasiswa dari SIAKAD
        $mhs = null;
        try {
            $mhs = Mahasiswa::nim($cleanNim);
        } catch (\Throwable $th) {
            $mhs = null;
        }

        // Kumpulkan seluruh jadwal_kuliah_id yang disusulkan
        $allJadwalIds = $records->flatMap(function ($r) {
            return $r->uasSusulanMk ? $r->uasSusulanMk->pluck('jadwal_kuliah_id') : collect();
        })->filter()->unique()->values()->all();

        // Ambil data jadwal dari SIAKAD
        $jadwalMap = [];
        if (!empty($allJadwalIds)) {
            try {
                $jadwalList = Jadwal::find(json_encode($allJadwalIds), true);
                if (is_array($jadwalList)) {
                    foreach ($jadwalList as $j) {
                        if (isset($j->id)) {
                            $jadwalMap[$j->id] = $j;
                        }
                    }
                }
            } catch (\Throwable $th) {
                // Ignore SIAKAD connection errors
            }
        }

        // Ambil data KRS untuk mendapatkan nilai akhir & dosen pengampu alternatif jika ada
        $krsMaps = [];
        foreach ($records as $r) {
            $thId = $r->th_akademik_id;
            if (!isset($krsMaps[$thId])) {
                $krsMaps[$thId] = [];
                try {
                    $krsData = Jadwal::mahasiswa($cleanNim, $thId);
                    if (isset($krsData->data->krs_detail) && is_array($krsData->data->krs_detail)) {
                        foreach ($krsData->data->krs_detail as $k) {
                            if (isset($k->jadwal_kuliah_id)) {
                                $krsMaps[$thId][$k->jadwal_kuliah_id] = $k;
                            }
                        }
                    }
                } catch (\Throwable $th) {
                    // Ignore KRS errors
                }
            }
        }

        // Format tiap pendaftaran beserta detail mata kuliah
        $pendaftaranList = $records->map(function ($rec) use ($jadwalMap, $krsMaps) {
            $thId = $rec->th_akademik_id;
            $krsMap = $krsMaps[$thId] ?? [];

            $mkDetails = ($rec->uasSusulanMk ?? collect())->map(function ($mk) use ($jadwalMap, $krsMap) {
                $j = $jadwalMap[$mk->jadwal_kuliah_id] ?? null;
                $k = $krsMap[$mk->jadwal_kuliah_id] ?? null;

                $dosenNama = '-';
                if ($j && isset($j->dosen)) {
                    $d = $j->dosen;
                    $dosenNama = trim(($d->gelar_depan ? $d->gelar_depan . ' ' : '') . $d->nama . ($d->gelar_belakang ? ', ' . $d->gelar_belakang : ''));
                } elseif ($k && !empty($k->dosen_nama)) {
                    $dosenNama = $k->dosen_nama;
                }

                $kodeMk = $j?->kurikulum_matakuliah?->matakuliah?->kode ?? ($k?->kode_mk ?? '-');
                $namaMk = $j?->kurikulum_matakuliah?->matakuliah?->nama ?? ($k?->nama_mk ?? "Mata Kuliah #{$mk->jadwal_kuliah_id}");
                $sks = $j?->kurikulum_matakuliah?->matakuliah?->sks ?? ($k?->sks_mk ?? null);
                $smt = $j?->kurikulum_matakuliah?->matakuliah?->smt ?? ($j?->smt ?? ($k?->smt_mk ?? null));
                $kelompok = $j?->kelompok?->nama ?? ($j?->kelompok?->kode ?? ($k?->jadwal_kuliah?->kelompok?->kode ?? '-'));
                $ruang = $j?->ruang_kelas?->nama ?? ($j?->ruang_kelas?->kode ?? '-');
                $hari = $j?->hari?->nama ?? '-';
                $jam = $j?->jamkul?->nama ?? '-';

                return [
                    'id'               => $mk->id,
                    'uas_susulan_id'   => $mk->uas_susulan_id,
                    'jadwal_kuliah_id' => $mk->jadwal_kuliah_id,
                    'kode_mk'          => $kodeMk,
                    'nama_mk'          => $namaMk,
                    'sks'              => $sks,
                    'sks_mk'           => $sks,
                    'smt'              => $smt,
                    'smt_mk'           => $smt,
                    'dosen_nama'       => $dosenNama,
                    'kelompok'         => $kelompok,
                    'ruang'            => $ruang,
                    'hari'             => $hari,
                    'jam'              => $jam,
                    'nilai_akhir'      => $k?->nilai_akhir ?? null,
                    'nilai_huruf'      => $k?->nilai_huruf ?? '',
                    'created_at'       => $mk->created_at ? $mk->created_at->toISOString() : null,
                ];
            })->values()->all();

            $totalMk = count($mkDetails);

            return [
                'id'                   => $rec->id,
                'nim'                  => $rec->nim,
                'tanggal'              => $rec->tanggal ? (string) $rec->tanggal : null,
                'keterangan'           => $rec->keterangan,
                'th_akademik_id'       => $rec->th_akademik_id,
                'th_akademik_kode'     => $rec->th_akademik?->kode,
                'th_akademik_nama'     => $rec->th_akademik?->nama,
                'th_akademik_semester' => $rec->th_akademik?->semester,
                'jumlah_mk'            => $totalMk,
                'total_mk'             => $totalMk,
                'mata_kuliah'          => $mkDetails,
                'created_at'           => $rec->created_at ? $rec->created_at->toISOString() : null,
                'updated_at'           => $rec->updated_at ? $rec->updated_at->toISOString() : null,
            ];
        });

        $current = $pendaftaranList->first();

        return response()->json([
            'status'  => true,
            'message' => 'Detail mata kuliah ujian susulan berhasil diambil.',
            'data'    => [
                'nim'                  => $cleanNim,
                'nama'                 => $mhs?->nama ?? null,
                'prodi'                => $mhs && isset($mhs->prodi) ? [
                    'id'      => $mhs->prodi->id ?? null,
                    'kode'    => $mhs->prodi->kode ?? null,
                    'alias'   => $mhs->prodi->alias ?? null,
                    'nama'    => $mhs->prodi->nama ?? null,
                    'jenjang' => $mhs->prodi->jenjang ?? null,
                ] : null,
                'kelas'                => $mhs?->kelas?->nama ?? null,
                'semester_mahasiswa'   => $mhs?->semester ?? null,
                'id'                   => $current['id'] ?? null,
                'th_akademik_id'       => $current['th_akademik_id'] ?? null,
                'th_akademik_kode'     => $current['th_akademik_kode'] ?? null,
                'th_akademik_nama'     => $current['th_akademik_nama'] ?? null,
                'th_akademik_semester' => $current['th_akademik_semester'] ?? null,
                'tanggal'              => $current['tanggal'] ?? null,
                'keterangan'           => $current['keterangan'] ?? null,
                'jumlah_mk'            => $current['jumlah_mk'] ?? 0,
                'total_mk'             => $current['total_mk'] ?? 0,
                'mata_kuliah'          => $current['mata_kuliah'] ?? [],
                'riwayat_pendaftaran'  => $pendaftaranList->values()->all(),
            ],
        ]);
    }
}

