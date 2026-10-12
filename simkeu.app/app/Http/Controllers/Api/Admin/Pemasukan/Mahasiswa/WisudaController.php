<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganWisudaSync;
use App\Models\ThAkademik;
use App\Models\Prodi;
use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Services\Wisuda;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WisudaController extends Controller
{
    /**
     * Map prodi alias SIMKEU -> Web Wisuda
     */
    private const PRODI_ALIAS_MAP = [
        'SKI' => 'SPI',
    ];

    /**
     * Display a listing of wisuda payments with synchronization status.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = (int) ($request->input('limit', 10));
            $sortKey = $request->input('sort_key', 'id');
            $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $baseQuery = $this->buildBaseQuery($request);

            // Filter status_sinkron ('all', 'synced', 'not_synced')
            $statusSinkron = $request->input('status_sinkron', 'all');
            if ($statusSinkron !== 'all') {
                $syncedNims = $this->getAllSyncedNims();
                if ($statusSinkron === 'synced') {
                    $baseQuery->whereIn('keuangan_pembayaran.nim', $syncedNims);
                } elseif ($statusSinkron === 'not_synced') {
                    $baseQuery->whereNotIn('keuangan_pembayaran.nim', $syncedNims);
                }
            }

            // Allowed sort columns
            $sortColumns = [
                'id' => 'keuangan_pembayaran.id',
                'tanggal' => 'keuangan_pembayaran.tanggal',
                'jumlah' => 'keuangan_pembayaran.jumlah',
                'nim' => 'keuangan_pembayaran.nim',
                'nomor' => 'keuangan_pembayaran.nomor',
                'created_at' => 'keuangan_pembayaran.created_at',
            ];
            $sortColumn = $sortColumns[$sortKey] ?? 'keuangan_pembayaran.id';
            $baseQuery->orderBy($sortColumn, $sortOrder);

            $paginated = $baseQuery->paginate($limit);

            // Collect all NIMs for batch student lookup & sync check
            $nimList = $paginated->getCollection()->pluck('nim')->unique()->filter()->values()->toArray();
            $syncedNimsMap = $this->getSyncedNimsMap($nimList);

            // Fetch student profiles from SIAKAD
            $mhsMap = [];
            foreach ($nimList as $nim) {
                $mhs = Mahasiswa::nim($nim);
                if ($mhs) {
                    $mhsMap[$nim] = $mhs;
                }
            }

            $items = $paginated->getCollection()->map(function ($item) use ($mhsMap, $syncedNimsMap) {
                $nim = strtoupper(trim((string) $item->nim));
                $mhs = $mhsMap[$nim] ?? null;

                $syncRecord = $syncedNimsMap[$nim] ?? null;
                $isSynced = $syncRecord !== null && ($syncRecord['is_synced'] ?? false);

                return [
                    'id' => $item->id,
                    'nomor' => $item->nomor,
                    'nota' => $item->nota ?? $item->nomor,
                    'tanggal' => $item->tanggal ? Carbon::parse($item->tanggal)->format('Y-m-d') : null,
                    'tanggal_formatted' => $item->tanggal ? Carbon::parse($item->tanggal)->translatedFormat('d M Y') : '-',
                    'nim' => $nim,
                    'nama_mahasiswa' => $mhs?->nama ?? $item->nama_mahasiswa ?? '-',
                    'jenis_kelamin' => $this->resolveGenderName($mhs?->jk?->nama ?? $mhs?->jenis_kelamin ?? ($item->jk_id == 9 ? 'Perempuan' : 'Laki-Laki')),
                    'prodi' => [
                        'id' => $mhs?->prodi?->id ?? $item->prodi_id ?? null,
                        'nama' => $mhs?->prodi?->nama ?? $item->prodi_nama ?? '-',
                        'alias' => $mhs?->prodi?->alias ?? $item->prodi_alias ?? '-',
                        'jenjang' => $mhs?->prodi?->jenjang ?? $item->prodi_jenjang ?? '-',
                    ],
                    'tagihan' => [
                        'id' => $item->tagihan_id,
                        'nama' => $item->tagihan_nama ?? 'WISUDA',
                        'th_akademik_kode' => $item->th_akademik_kode ?? '-',
                        'th_akademik_nama' => $item->th_akademik_nama ?? '-',
                    ],
                    'jumlah' => (float) $item->jumlah,
                    'mata_uang' => [
                        'id' => $item->mata_uang_id ?? null,
                        'kode' => $item->mata_uang_kode ?? 'IDR',
                        'simbol' => $item->mata_uang_simbol ?? 'Rp',
                    ],
                    'jenis_pembayaran' => [
                        'id' => $item->jenis_pembayaran_id ?? null,
                        'nama' => $item->jenis_pembayaran_nama ?? 'CASH',
                    ],
                    'petugas' => [
                        'id' => $item->user_id,
                        'name' => $item->petugas_nama ?? '-',
                    ],
                    'is_synced' => $isSynced,
                    'sync_info' => $syncRecord,
                    'mahasiswa' => $mhs,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'current_page' => $paginated->currentPage(),
                    'data' => $items,
                    'first_page_url' => $paginated->url(1),
                    'from' => $paginated->firstItem(),
                    'last_page' => $paginated->lastPage(),
                    'last_page_url' => $paginated->url($paginated->lastPage()),
                    'next_page_url' => $paginated->nextPageUrl(),
                    'path' => $paginated->path(),
                    'per_page' => $paginated->perPage(),
                    'prev_page_url' => $paginated->previousPageUrl(),
                    'to' => $paginated->lastItem(),
                    'total' => $paginated->total(),
                ],
                'message' => 'Data pembayaran wisuda berhasil diambil',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil data pembayaran wisuda: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for wisuda payments (Total, Sudah Sinkron, Belum Sinkron, Total Nominal).
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $baseQuery = $this->buildBaseQuery($request);

            // Ambil semua NIM yang bayar wisuda sesuai filter dasar
            $pembayaranRows = (clone $baseQuery)->get(['keuangan_pembayaran.id', 'keuangan_pembayaran.nim', 'keuangan_pembayaran.jumlah']);
            $totalTransaksi = $pembayaranRows->count();
            $totalNominal = (float) $pembayaranRows->sum('jumlah');

            $uniqueNims = $pembayaranRows->pluck('nim')->unique()->filter()->values()->toArray();
            $totalMahasiswa = count($uniqueNims);

            // Cek status sinkron
            $syncedNims = $this->getAllSyncedNims();
            $syncedNimsLookup = array_flip($syncedNims);

            $sudahSinkronCount = 0;
            $belumSinkronCount = 0;
            $nominalSudahSinkron = 0;
            $nominalBelumSinkron = 0;

            foreach ($pembayaranRows as $row) {
                $nim = strtoupper(trim((string) $row->nim));
                if (isset($syncedNimsLookup[$nim])) {
                    $sudahSinkronCount++;
                    $nominalSudahSinkron += (float) $row->jumlah;
                } else {
                    $belumSinkronCount++;
                    $nominalBelumSinkron += (float) $row->jumlah;
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => [
                        'count' => $totalTransaksi,
                        'amount' => $totalNominal,
                        'total_mahasiswa' => $totalMahasiswa,
                    ],
                    'synced' => [
                        'count' => $sudahSinkronCount,
                        'amount' => $nominalSudahSinkron,
                    ],
                    'not_synced' => [
                        'count' => $belumSinkronCount,
                        'amount' => $nominalBelumSinkron,
                    ],
                ],
                'message' => 'Statistik pembayaran wisuda berhasil diambil',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil statistik: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of academic years for graduation (Tahun Wisuda) from Web Wisuda.
     */
    public function tahun(): JsonResponse
    {
        try {
            $response = Wisuda::tahun();

            $tahunList = [];
            if (is_object($response) && isset($response->data) && is_array($response->data)) {
                $tahunList = $response->data;
            } elseif (is_array($response)) {
                $tahunList = $response;
            } else {
                // Fallback ke DB wisuda jika ada
                if ($this->hasWisudaDbConnection()) {
                    $tahunList = DB::connection('wisuda')->table('tahun')->orderBy('id', 'desc')->get()->toArray();
                }
            }

            return response()->json([
                'status' => true,
                'data' => $tahunList,
                'message' => 'Data tahun wisuda berhasil diambil',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil data tahun wisuda: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronize a single student payment to Web Wisuda.
     */
    public function sinkronkan(Request $request): JsonResponse
    {
        $request->validate([
            'nim' => 'required|string',
            'pembayaran_id' => 'nullable|integer',
            'tahun_id' => 'nullable|integer',
            'ukuran_baju' => 'nullable|string',
            'nama_ayah' => 'nullable|string',
            'judul' => 'nullable|string',
            'tanggal_sidang' => 'nullable|date',
        ]);

        $nim = strtoupper(trim($request->nim));

        $pembayaran = null;
        if ($request->pembayaran_id) {
            $pembayaran = KeuanganPembayaran::find($request->pembayaran_id);
        }
        if (!$pembayaran) {
            $pembayaran = KeuanganPembayaran::whereRaw('UPPER(nim) = ?', [$nim])
                ->whereHas('tagihan', fn ($q) => $q->where('nama', 'LIKE', '%wisuda%'))
                ->latest('id')
                ->first();
        }

        $result = $this->processSyncStudent($nim, $pembayaran, $request->all());

        if ($result['success']) {
            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? null,
        ], 422);
    }

    /**
     * Batch synchronize students to Web Wisuda.
     */
    public function sinkronkanBatch(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'required|in:selected,all_unsynced',
            'pembayaran_ids' => 'nullable|array',
            'pembayaran_ids.*' => 'integer',
            'nims' => 'nullable|array',
            'nims.*' => 'string',
            'tahun_id' => 'nullable|integer',
            'ukuran_baju' => 'nullable|string',
        ]);

        $mode = $request->input('mode');
        $nimsToSync = [];

        if ($mode === 'selected') {
            if ($request->filled('nims')) {
                $nimsToSync = collect($request->nims)->map(fn ($n) => strtoupper(trim((string) $n)))->filter()->unique()->values()->toArray();
            } elseif ($request->filled('pembayaran_ids')) {
                $nimsToSync = KeuanganPembayaran::whereIn('id', $request->pembayaran_ids)
                    ->pluck('nim')
                    ->map(fn ($n) => strtoupper(trim((string) $n)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }
        } elseif ($mode === 'all_unsynced') {
            $allWisudaNims = KeuanganPembayaran::whereHas('tagihan', fn ($q) => $q->where('nama', 'LIKE', '%wisuda%'))
                ->pluck('nim')
                ->map(fn ($n) => strtoupper(trim((string) $n)))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $syncedNims = $this->getAllSyncedNims();
            $nimsToSync = array_values(array_diff($allWisudaNims, $syncedNims));
        }

        if (empty($nimsToSync)) {
            return response()->json([
                'status' => true,
                'message' => 'Tidak ada mahasiswa yang perlu disinkronkan.',
                'data' => [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'logs' => [],
                ],
            ]);
        }

        $successCount = 0;
        $failedCount = 0;
        $logs = [];

        foreach ($nimsToSync as $nim) {
            $pembayaran = KeuanganPembayaran::whereRaw('UPPER(nim) = ?', [$nim])
                ->whereHas('tagihan', fn ($q) => $q->where('nama', 'LIKE', '%wisuda%'))
                ->latest('id')
                ->first();

            $res = $this->processSyncStudent($nim, $pembayaran, $request->only(['tahun_id', 'ukuran_baju']));

            $logs[] = [
                'nim' => $nim,
                'success' => $res['success'],
                'message' => $res['message'],
            ];

            if ($res['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Proses sinkronisasi selesai: {$successCount} berhasil, {$failedCount} gagal.",
            'data' => [
                'total' => count($nimsToSync),
                'success' => $successCount,
                'failed' => $failedCount,
                'logs' => $logs,
            ],
        ]);
    }

    /**
     * Process student synchronization to Web Wisuda.
     */
    private function processSyncStudent(string $nim, ?KeuanganPembayaran $pembayaran, array $options = []): array
    {
        try {
            $nim = strtoupper(trim($nim));
            $mhs = Mahasiswa::nim($nim);

            if (!$mhs) {
                return [
                    'success' => false,
                    'message' => "Data mahasiswa dengan NIM {$nim} tidak ditemukan di SIAKAD.",
                ];
            }

            // Resolve target tahun_id
            $tahunId = $options['tahun_id'] ?? null;
            if (!$tahunId) {
                $tahunId = $this->resolveActiveTahunId();
            }

            if (!$tahunId) {
                return [
                    'success' => false,
                    'message' => 'Tahun wisuda aktif tidak ditemukan. Silakan pilih tahun wisuda tujuan.',
                ];
            }

            // Resolve prodi alias
            $rawProdiAlias = strtoupper(trim((string) ($mhs->prodi?->alias ?? '')));
            $prodiAlias = self::PRODI_ALIAS_MAP[$rawProdiAlias] ?? $rawProdiAlias;

            if (empty($prodiAlias)) {
                return [
                    'success' => false,
                    'message' => "Prodi mahasiswa {$nim} belum memiliki alias yang valid.",
                ];
            }

            // Resolve gender in Web Wisuda format ('Laki-Laki' or 'Perempuan')
            $rawJk = $mhs->jk?->nama ?? $mhs->jenis_kelamin ?? ($pembayaran?->jk_id == 9 ? 'Perempuan' : 'Laki-Laki');
            $jenisKelamin = (stripos((string) $rawJk, 'perempuan') !== false || ($mhs->jk_id ?? 0) == 9) ? 'Perempuan' : 'Laki-Laki';

            $ukuranBaju = $options['ukuran_baju'] ?? 'L';
            if (!in_array($ukuranBaju, Wisuda::UKURAN_BAJU, true)) {
                $ukuranBaju = 'L';
            }

            $namaAyah = $options['nama_ayah'] ?? ($mhs->nama_ayah ?: '-');
            $judul = $options['judul'] ?? ($options['judul_skripsi'] ?? '-');
            $tanggalSidang = $options['tanggal_sidang'] ?? null;

            $payload = [
                'nim' => $nim,
                'nama' => (string) $mhs->nama,
                'nama_ayah' => (string) $namaAyah,
                'judul' => (string) $judul,
                'tanggal_sidang' => $tanggalSidang,
                'tahun_id' => (int) $tahunId,
                'jenis_kelamin' => $jenisKelamin,
                'prodi' => $prodiAlias,
                'ukuran_baju' => $ukuranBaju,
                'is_bayar' => false,
            ];

            // Send to Web Wisuda API
            $response = Wisuda::registrasi($payload);

            if ($response === null) {
                $this->recordSyncFailure($nim, $pembayaran, $payload, 'Tidak ada response dari API Web Wisuda.');
                return [
                    'success' => false,
                    'message' => 'Tidak ada response dari API Web Wisuda (koneksi timeout atau gagal).',
                ];
            }

            if (is_object($response) && isset($response->status) && $response->status === false) {
                $errorMessage = $response->message ?? 'Registrasi ke Web Wisuda ditolak.';
                if (isset($response->errors)) {
                    $errorMessage .= ' (' . json_encode($response->errors) . ')';
                }
                $this->recordSyncFailure($nim, $pembayaran, $payload, $errorMessage, $response);
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => $response->errors ?? null,
                ];
            }

            // Success! Save to local tracking table
            $noUnik = data_get($response, 'data.no_unik') ?? substr($nim, -6);
            $syncRecord = KeuanganWisudaSync::updateOrCreate(
                ['nim' => $nim],
                [
                    'pembayaran_id' => $pembayaran?->id,
                    'tahun_id' => $tahunId,
                    'ukuran_baju' => $ukuranBaju,
                    'judul' => $judul,
                    'tanggal_sidang' => $tanggalSidang,
                    'status' => 'synced',
                    'message' => data_get($response, 'message') ?? 'Berhasil registrasi peserta wisuda',
                    'no_unik' => $noUnik,
                    'payload' => $payload,
                    'response' => (array) $response,
                    'synced_by' => Auth::id() ?? 8,
                    'synced_at' => now(),
                ]
            );

            // If local wisuda DB connection exists, also ensure it's written/verified locally
            $this->ensureLocalWisudaDbSynced($nim, $payload, $noUnik);

            return [
                'success' => true,
                'message' => "Mahasiswa {$nim} ({$mhs->nama}) berhasil disinkronkan ke Web Wisuda.",
                'data' => [
                    'nim' => $nim,
                    'nama' => $mhs->nama,
                    'no_unik' => $noUnik,
                    'sync' => $syncRecord,
                    'api_response' => $response,
                ],
            ];
        } catch (\Throwable $th) {
            $this->recordSyncFailure($nim, $pembayaran, [], $th->getMessage());
            return [
                'success' => false,
                'message' => 'Error sinkronisasi: ' . $th->getMessage(),
            ];
        }
    }

    /**
     * Record failure in KeuanganWisudaSync
     */
    private function recordSyncFailure(string $nim, ?KeuanganPembayaran $pembayaran, array $payload, string $message, $response = null): void
    {
        try {
            KeuanganWisudaSync::updateOrCreate(
                ['nim' => $nim],
                [
                    'pembayaran_id' => $pembayaran?->id,
                    'status' => 'failed',
                    'message' => $message,
                    'payload' => $payload,
                    'response' => $response ? (array) $response : null,
                    'synced_by' => Auth::id() ?? 8,
                    'synced_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat kegagalan sinkron wisuda: ' . $e->getMessage());
        }
    }

    /**
     * If local wisuda DB connection exists, ensure Peserta record exists in local DB.
     */
    private function ensureLocalWisudaDbSynced(string $nim, array $payload, ?string $noUnik): void
    {
        if (!$this->hasWisudaDbConnection()) {
            return;
        }

        try {
            $prodi = DB::connection('wisuda')->table('prodi')->where('alias', $payload['prodi'])->first();
            if (!$prodi) {
                return;
            }

            $user = DB::connection('wisuda')->table('users')->where('username', $nim)->first();
            $userId = $user?->id;

            if (!$userId) {
                $userId = DB::connection('wisuda')->table('users')->insertGetId([
                    'username' => $nim,
                    'nama' => $payload['nama'],
                    'jenis_kelamin' => $payload['jenis_kelamin'],
                    'role_id' => 2,
                    'no_unik' => $noUnik ?? substr($nim, -6),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::connection('wisuda')->table('users')->where('id', $userId)->update([
                    'nama' => $payload['nama'],
                    'jenis_kelamin' => $payload['jenis_kelamin'],
                    'updated_at' => now(),
                ]);
            }

            $peserta = DB::connection('wisuda')->table('peserta')->where('nim', $nim)->first();
            if (!$peserta) {
                DB::connection('wisuda')->table('peserta')->insert([
                    'nim' => $nim,
                    'nama' => $payload['nama'],
                    'nama_ayah' => $payload['nama_ayah'] ?? '-',
                    'judul' => $payload['judul'] ?? '-',
                    'tanggal_sidang' => $payload['tanggal_sidang'] ?? null,
                    'prodi_id' => $prodi->id,
                    'status_id' => 1,
                    'user_id' => $userId,
                    'tahun_id' => $payload['tahun_id'],
                    'tanggal_daftar' => now(),
                    'ukuran_baju' => $payload['ukuran_baju'] ?? 'L',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::connection('wisuda')->table('peserta')->where('id', $peserta->id)->update([
                    'nama' => $payload['nama'],
                    'prodi_id' => $prodi->id,
                    'tahun_id' => $payload['tahun_id'],
                    'ukuran_baju' => $payload['ukuran_baju'] ?? 'L',
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Local wisuda DB sync skipped: ' . $e->getMessage());
        }
    }

    /**
     * Resolve active tahun ID from Web Wisuda API or local DB
     */
    private function resolveActiveTahunId(): ?int
    {
        try {
            $tahunResponse = Wisuda::tahun();
            if (is_object($tahunResponse) && isset($tahunResponse->data) && is_array($tahunResponse->data)) {
                foreach ($tahunResponse->data as $t) {
                    if (isset($t->status) && $t->status === 'Y') {
                        return (int) $t->id;
                    }
                }
                if (!empty($tahunResponse->data)) {
                    return (int) $tahunResponse->data[0]->id;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($this->hasWisudaDbConnection()) {
            try {
                $t = DB::connection('wisuda')->table('tahun')->where('status', 'Y')->first();
                if ($t) {
                    return (int) $t->id;
                }
                $t2 = DB::connection('wisuda')->table('tahun')->latest('id')->first();
                if ($t2) {
                    return (int) $t2->id;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 3; // default fallback
    }

    /**
     * Get list of all NIMs that have been synced to Web Wisuda.
     */
    private function getAllSyncedNims(): array
    {
        $nims = [];

        // 1. From local sync tracking table
        $localNims = KeuanganWisudaSync::where('status', 'synced')->pluck('nim')->toArray();
        foreach ($localNims as $n) {
            $cleaned = strtoupper(trim((string) $n));
            if ($cleaned !== '') {
                $nims[$cleaned] = true;
            }
        }

        // 2. From wisuda database connection if accessible
        if ($this->hasWisudaDbConnection()) {
            try {
                $dbNims = DB::connection('wisuda')->table('peserta')->pluck('nim')->toArray();
                foreach ($dbNims as $n) {
                    $cleaned = strtoupper(trim((string) $n));
                    if ($cleaned !== '') {
                        $nims[$cleaned] = true;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if DB connection fails
            }
        }

        return array_keys($nims);
    }

    /**
     * Map NIM list to their sync info
     */
    private function getSyncedNimsMap(array $nimList): array
    {
        if (empty($nimList)) {
            return [];
        }

        $nimListClean = array_values(array_filter(array_map(fn ($n) => strtoupper(trim((string) $n)), $nimList)));
        $map = [];

        // 1. Check local sync table
        $syncRecords = KeuanganWisudaSync::whereIn('nim', $nimListClean)->get()->keyBy('nim');
        foreach ($syncRecords as $nim => $rec) {
            $map[$nim] = [
                'is_synced' => $rec->status === 'synced',
                'status' => $rec->status,
                'no_unik' => $rec->no_unik,
                'tahun_id' => $rec->tahun_id,
                'ukuran_baju' => $rec->ukuran_baju,
                'synced_at' => $rec->synced_at ? Carbon::parse($rec->synced_at)->translatedFormat('d M Y, H:i') : null,
                'message' => $rec->message,
            ];
        }

        // 2. Check wisuda database connection
        if ($this->hasWisudaDbConnection()) {
            try {
                $pesertaRows = DB::connection('wisuda')->table('peserta')
                    ->leftJoin('tahun', 'tahun.id', '=', 'peserta.tahun_id')
                    ->whereIn('peserta.nim', $nimListClean)
                    ->get(['peserta.nim', 'peserta.ukuran_baju', 'peserta.tahun_id', 'tahun.nama as tahun_nama', 'peserta.created_at'])
                    ->keyBy('nim');

                foreach ($pesertaRows as $nim => $row) {
                    $uNim = strtoupper(trim((string) $nim));
                    if (!isset($map[$uNim]) || !$map[$uNim]['is_synced']) {
                        $map[$uNim] = [
                            'is_synced' => true,
                            'status' => 'synced',
                            'no_unik' => substr($uNim, -6),
                            'tahun_id' => $row->tahun_id,
                            'tahun_nama' => $row->tahun_nama,
                            'ukuran_baju' => $row->ukuran_baju,
                            'synced_at' => $row->created_at ? Carbon::parse($row->created_at)->translatedFormat('d M Y, H:i') : null,
                            'message' => 'Terdaftar di Web Wisuda',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return $map;
    }

    /**
     * Check if wisuda DB connection is available
     */
    private function hasWisudaDbConnection(): bool
    {
        try {
            DB::connection('wisuda')->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Build base query for wisuda payments with all relational joins.
     */
    private function buildBaseQuery(Request $request)
    {
        $jkUser = Helper::getJenisKelaminUser();

        $query = KeuanganPembayaran::query()
            ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
            ->leftJoin('th_akademik', 'th_akademik.id', '=', 'keuangan_pembayaran.th_akademik_id')
            ->leftJoin('mata_uang', 'mata_uang.id', '=', 'keuangan_tagihan.mata_uang_id')
            ->leftJoin('keuangan_nota', 'keuangan_nota.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
            ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
            ->leftJoin('users', 'users.id', '=', 'keuangan_pembayaran.user_id')
            ->leftJoin('prodi', 'prodi.id', '=', 'keuangan_tagihan.prodi_id')
            ->where('keuangan_tagihan.nama', 'LIKE', '%wisuda%');

        // Scope by user gender (Laki-laki / Perempuan)
        if ($jkUser && $jkUser->id !== '%') {
            $query->where('keuangan_pembayaran.jk_id', $jkUser->id);
        }

        // Filter search (NIM, nomor, nota, tagihan, user)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('keuangan_pembayaran.nim', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_pembayaran.nomor', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_nota.nota', 'LIKE', "%{$search}%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        // Filter tahun akademik
        if ($request->filled('th_akademik_id') && $request->th_akademik_id !== 'all') {
            $query->where('keuangan_pembayaran.th_akademik_id', $request->th_akademik_id);
        }

        // Filter prodi
        if ($request->filled('prodi_id') && $request->prodi_id !== 'all') {
            if ($request->prodi_id === 'sarjana') {
                $query->where('prodi.jenjang', 'S1');
            } elseif ($request->prodi_id === 'pasca') {
                $query->where('prodi.jenjang', '!=', 'S1');
            } else {
                $query->where('keuangan_tagihan.prodi_id', $request->prodi_id);
            }
        }

        // Filter jenis pembayaran (metode)
        if ($request->filled('jenis_pembayaran_id') && $request->jenis_pembayaran_id !== 'all') {
            $query->where('keuangan_jenis_pembayaran_detail.jenis_pembayaran_id', $request->jenis_pembayaran_id);
        }

        // Filter user / petugas
        if ($request->filled('user_id') && $request->user_id !== 'all') {
            $query->where('keuangan_pembayaran.user_id', $request->user_id);
        }

        // Filter tanggal
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('keuangan_pembayaran.tanggal', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('keuangan_pembayaran.tanggal', '<=', $request->tanggal_akhir);
        }

        $query->select([
            'keuangan_pembayaran.*',
            'keuangan_tagihan.nama as tagihan_nama',
            'keuangan_tagihan.prodi_id',
            'prodi.nama as prodi_nama',
            'prodi.alias as prodi_alias',
            'prodi.jenjang as prodi_jenjang',
            'th_akademik.kode as th_akademik_kode',
            'th_akademik.nama as th_akademik_nama',
            'keuangan_jenis_pembayaran.id as jenis_pembayaran_id',
            'keuangan_jenis_pembayaran.nama as jenis_pembayaran_nama',
            'users.name as petugas_nama',
            'mata_uang.id as mata_uang_id',
            'mata_uang.kode as mata_uang_kode',
            'mata_uang.simbol as mata_uang_simbol',
            DB::raw("COALESCE(keuangan_nota.nota, keuangan_pembayaran.nomor) AS nota"),
        ]);

        return $query;
    }

    private function resolveGenderName($val): string
    {
        $s = strtolower((string) $val);
        if (str_contains($s, 'perempuan') || $s === 'p') {
            return 'Perempuan';
        }
        return 'Laki-Laki';
    }
}
