<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\SiakadUasSusulanController;
use App\Services\BsiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiSettingController extends Controller
{
    public function index(BsiSettingsService $service): JsonResponse
    {
        $settings = $service->settings();
        $baseUrl = url('/api/v1/integrations/siakad');

        return response()->json([
            'status' => true,
            'data' => [
                'siakad_api_key_configured' => filled($settings->siakad_api_key_hash),
                'siakad_api_key_hint' => $settings->siakad_api_key_hint,
                'base_url' => $baseUrl,
                'header_keys' => [
                    'primary' => 'X-SIAKAD-API-KEY',
                    'alternatives' => ['apikey', 'X-API-KEY', 'Authorization: Bearer <API_KEY>'],
                ],
                'endpoints' => [
                    [
                        'name' => 'Peserta Ujian Susulan & Jumlah MK',
                        'method' => 'GET',
                        'path' => '/uas-susulan/peserta',
                        'full_url' => "{$baseUrl}/uas-susulan/peserta",
                        'alias_url' => url('/api/siakad/uas-susulan/peserta'),
                        'description' => 'Mengambil daftar peserta pendaftaran ujian susulan beserta nama mahasiswa, prodi, dan jumlah total mata kuliah (jumlah_mk) yang disusulkan.',
                        'parameters' => [
                            ['name' => 'nim', 'type' => 'string', 'required' => false, 'description' => 'Filter NIM mahasiswa (contoh: 210101001 atau koma dipisah: 210101001,210101002)'],
                            ['name' => 'th_akademik_kode', 'type' => 'string', 'required' => false, 'description' => 'Filter kode tahun akademik SIMKEU (contoh: 20251)'],
                            ['name' => 'th_akademik_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter ID tahun akademik di database SIMKEU'],
                            ['name' => 'jadwal_kuliah_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter spesifik ID jadwal kuliah'],
                            ['name' => 'tanggal', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal pendaftaran (YYYY-MM-DD)'],
                            ['name' => 'tanggal_mulai', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal awal rentang (YYYY-MM-DD)'],
                            ['name' => 'tanggal_akhir', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal akhir rentang (YYYY-MM-DD)'],
                            ['name' => 'search', 'type' => 'string', 'required' => false, 'description' => 'Pencarian umum (NIM, keterangan, th akademik)'],
                            ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Jumlah data per halaman (default 20, isi 0 atau all untuk semua data)'],
                            ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Nomor halaman pagination (default 1)'],
                            ['name' => 'sort_by', 'type' => 'string', 'required' => false, 'description' => 'Kolom sorting (id, tanggal, nim, th_akademik_id, created_at)'],
                            ['name' => 'sort_dir', 'type' => 'string', 'required' => false, 'description' => 'Arah sorting (asc atau desc, default desc)'],
                        ],
                    ],
                    [
                        'name' => 'Detail MK Ujian Susulan by NIM',
                        'method' => 'GET',
                        'path' => '/uas-susulan/peserta/{nim}/detail',
                        'full_url' => "{$baseUrl}/uas-susulan/peserta/{nim}/detail",
                        'alias_url' => url('/api/siakad/uas-susulan/peserta/{nim}/detail'),
                        'description' => 'Mengambil detail lengkap seluruh mata kuliah ujian susulan yang didaftarkan oleh peserta tertentu (nama MK, kode MK, SKS, dosen pengampu, ruang, hari, jam, kelompok, nilai).',
                        'parameters' => [
                            ['name' => 'nim', 'type' => 'string (URL path)', 'required' => true, 'description' => 'NIM mahasiswa pada URL path (contoh: 202585010009)'],
                            ['name' => 'th_akademik_kode', 'type' => 'string (query)', 'required' => false, 'description' => 'Filter kode tahun akademik SIMKEU (contoh: 20251)'],
                            ['name' => 'th_akademik_id', 'type' => 'integer (query)', 'required' => false, 'description' => 'Filter ID tahun akademik'],
                            ['name' => 'uas_susulan_id', 'type' => 'integer (query)', 'required' => false, 'description' => 'Filter ID transaksi pendaftaran spesifik'],
                        ],
                    ],
                    [
                        'name' => 'Jadwal Kuliah Terdaftar UAS Susulan',
                        'method' => 'GET',
                        'path' => '/uas-susulan/jadwal-kuliah',
                        'full_url' => "{$baseUrl}/uas-susulan/jadwal-kuliah",
                        'alias_url' => url('/api/siakad/uas-susulan/jadwal-kuliah'),
                        'description' => 'Mengambil seluruh ID jadwal kuliah (jadwal_kuliah_id) dan detail mata kuliah yang terdaftar pada UAS susulan beserta jumlah dan daftar peserta.',
                        'parameters' => [
                            ['name' => 'th_akademik_kode', 'type' => 'string', 'required' => false, 'description' => 'Filter kode tahun akademik SIMKEU (contoh: 20251)'],
                            ['name' => 'th_akademik_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter ID tahun akademik'],
                            ['name' => 'jadwal_kuliah_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter ID jadwal kuliah spesifik'],
                            ['name' => 'nim', 'type' => 'string', 'required' => false, 'description' => 'Filter berdasarkan NIM mahasiswa peserta'],
                            ['name' => 'tanggal', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal pendaftaran (YYYY-MM-DD)'],
                            ['name' => 'tanggal_mulai', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal awal rentang (YYYY-MM-DD)'],
                            ['name' => 'tanggal_akhir', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal akhir rentang (YYYY-MM-DD)'],
                            ['name' => 'only_ids', 'type' => 'boolean', 'required' => false, 'description' => 'Kirim 1/true jika hanya membutuhkan array jadwal_kuliah_ids ringkas'],
                            ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Jumlah data per halaman (default 0 / semua data)'],
                            ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Nomor halaman pagination (default 1)'],
                        ],
                    ],
                    [
                        'name' => 'UAS Susulan & MK (Legacy)',
                        'method' => 'GET',
                        'path' => '/uas-susulan',
                        'full_url' => "{$baseUrl}/uas-susulan",
                        'alias_url' => url('/api/siakad/uas-susulan'),
                        'description' => 'Mengambil daftar pendaftaran UAS Susulan beserta ID mata kuliah (jadwal_kuliah_id) yang disusulkan.',
                        'parameters' => [
                            ['name' => 'nim', 'type' => 'string', 'required' => false, 'description' => 'Filter NIM mahasiswa (contoh: 210101001 atau 210101001,210101002)'],
                            ['name' => 'th_akademik_kode', 'type' => 'string', 'required' => false, 'description' => 'Filter kode tahun akademik SIMKEU (contoh: 20252)'],
                            ['name' => 'th_akademik_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter ID tahun akademik di database SIMKEU'],
                            ['name' => 'jadwal_kuliah_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter spesifik ID jadwal kuliah'],
                            ['name' => 'tanggal', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal pendaftaran (YYYY-MM-DD)'],
                            ['name' => 'tanggal_mulai', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal awal rentang (YYYY-MM-DD)'],
                            ['name' => 'tanggal_akhir', 'type' => 'date', 'required' => false, 'description' => 'Filter tanggal akhir rentang (YYYY-MM-DD)'],
                            ['name' => 'search', 'type' => 'string', 'required' => false, 'description' => 'Pencarian umum (NIM, keterangan, th akademik)'],
                            ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Jumlah data per halaman (default 20, isi 0 atau all untuk semua data)'],
                            ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Nomor halaman pagination (default 1)'],
                        ],
                    ],
                    [
                        'name' => 'UAS Susulan by NIM (Legacy)',
                        'method' => 'GET',
                        'path' => '/uas-susulan/{nim}',
                        'full_url' => "{$baseUrl}/uas-susulan/{nim}",
                        'alias_url' => url('/api/siakad/uas-susulan/{nim}'),
                        'description' => 'Shortcut pengambilan data UAS Susulan untuk satu mahasiswa spesifik berdasarkan parameter URL NIM.',
                        'parameters' => [
                            ['name' => 'nim', 'type' => 'string (URL path)', 'required' => true, 'description' => 'NIM mahasiswa pada URL path'],
                            ['name' => 'th_akademik_kode', 'type' => 'string (query)', 'required' => false, 'description' => 'Filter kode tahun akademik'],
                        ],
                    ],
                    [
                        'name' => 'BSI Tagihan Mahasiswa',
                        'method' => 'GET',
                        'path' => '/bsi/bills/{nim}',
                        'full_url' => "{$baseUrl}/bsi/bills/{nim}",
                        'description' => 'Mengambil daftar tagihan mahasiswa yang siap dibayarkan via integrasi BSI Virtual Account.',
                        'parameters' => [
                            ['name' => 'nim', 'type' => 'string (URL path)', 'required' => true, 'description' => 'NIM mahasiswa'],
                        ],
                    ],
                    [
                        'name' => 'BSI Metode Pembayaran',
                        'method' => 'GET',
                        'path' => '/bsi/payment-methods',
                        'full_url' => "{$baseUrl}/bsi/payment-methods",
                        'description' => 'Mengambil daftar saluran/metode Virtual Account BSI yang aktif.',
                        'parameters' => [],
                    ],
                    [
                        'name' => 'BSI Buat Payment Order',
                        'method' => 'POST',
                        'path' => '/bsi/payment-orders',
                        'full_url' => "{$baseUrl}/bsi/payment-orders",
                        'description' => 'Menerbitkan Virtual Account BSI untuk tagihan yang dipilih mahasiswa dari SIAKAD.',
                        'parameters' => [],
                    ],
                ],
            ],
        ]);
    }

    public function rotateSiakadKey(Request $request, BsiSettingsService $service): JsonResponse
    {
        $settings = $service->settings();
        $plain = $service->rotateSiakadKey($settings, $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'API key SIAKAD berhasil dibuat/dirotasi. Salin sekarang karena tidak akan ditampilkan lagi.',
            'data' => [
                'api_key' => $plain,
                'hint' => substr($plain, -8),
            ],
        ]);
    }

    public function uasSusulanPreview(Request $request, SiakadUasSusulanController $siakadController): JsonResponse
    {
        return $siakadController->index($request);
    }

    public function pesertaPreview(Request $request, SiakadUasSusulanController $siakadController): JsonResponse
    {
        return $siakadController->peserta($request);
    }

    public function pesertaDetailPreview(Request $request, string $nim, SiakadUasSusulanController $siakadController): JsonResponse
    {
        return $siakadController->pesertaDetail($request, $nim);
    }

    public function jadwalKuliahPreview(Request $request, SiakadUasSusulanController $siakadController): JsonResponse
    {
        return $siakadController->jadwalKuliah($request);
    }
}
