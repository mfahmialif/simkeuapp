<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WebAbsensiService
{
    /**
     * Get and validate Web Absensi configuration.
     */
    protected function getConfig(): array
    {
        $apiKey = config('services.absensi.api_key', env('ABSENSI_API_KEY'));
        $secretKey = config('services.absensi.secret_key', env('ABSENSI_SECRET_KEY'));
        $baseUrl = rtrim(config('services.absensi.base_url', env('ABSENSI_BASE_URL', 'https://absensi.uiidalwa.web.id')), '/');

        if (empty($apiKey) || empty($secretKey)) {
            throw new \Exception('Konfigurasi ABSENSI_API_KEY dan ABSENSI_SECRET_KEY belum diatur di file .env server.');
        }

        return [
            'apiKey' => $apiKey,
            'secretKey' => $secretKey,
            'baseUrl' => $baseUrl,
        ];
    }

    /**
     * Generate HMAC SHA256 Signature headers for cURL / Http request.
     */
    protected function getSignedHeaders(string $path, string $secretKey, string $apiKey, int $timestamp = null): array
    {
        $timestamp = $timestamp ?: time();
        $stringToSign = strtoupper('GET') . ":" . $path . ":" . $timestamp;
        $signature = hash_hmac("sha256", $stringToSign, $secretKey);

        return [
            'X-Api-Key' => $apiKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Helper to resolve departemen_id from request (either by departemen_id directly or by mapping departemen name).
     */
    protected function resolveDepartemenId(Request $request): ?int
    {
        $id = $request->input('departemen_id');
        if ($id !== null && $id !== '') {
            return (int) $id;
        }

        $name = $request->input('departemen');
        if ($name !== null && trim($name) !== '') {
            $map = [
                'dosen' => 1,
                'staff' => 2,
                'admin' => 3,
            ];
            $key = strtolower(trim($name));
            return $map[$key] ?? null;
        }

        return null;
    }

    /**
     * Fetch paginated daily logs (Rekap Harian / Detail Log) from Web Absensi.
     */
    public function fetchHarian(Request $request): array
    {
        $cfg = $this->getConfig();
        $mode = $request->input('mode', 'bulan_tahun');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 15);
        $search = $request->input('search');
        $departemenId = $this->resolveDepartemenId($request);

        $path = "api/client/v1/absensi";
        $url = "{$cfg['baseUrl']}/{$path}";

        $params = [
            'page' => $page,
            'limit' => $limit,
            'per_page' => $limit,
        ];

        if ($mode === 'bulan_tahun') {
            $params['mode'] = 'bulan_tahun';
            if ($bulan) $params['bulan'] = $bulan;
            if ($tahun) $params['tahun'] = $tahun;
        } elseif ($mode === 'range') {
            $params['mode'] = 'range';
            if ($startDate) $params['start_date'] = $startDate;
            if ($endDate) $params['end_date'] = $endDate;
        }

        if ($search) {
            $params['search'] = $search;
        }

        if ($departemenId !== null) {
            $params['departemen_id'] = $departemenId;
        }

        $headers = $this->getSignedHeaders($path, $cfg['secretKey'], $cfg['apiKey']);
        $response = Http::withHeaders($headers)->get($url, $params);
        $json = $response->json();

        if (! $response->successful() || ! ($json['status'] ?? false)) {
            throw new \Exception($json['message'] ?? $response->body() ?? 'Gagal mengambil data dari Web Absensi.');
        }

        return $json;
    }

    /**
     * Fetch all daily logs (unlimited limit) from Web Absensi (for Excel/PDF export).
     */
    public function fetchHarianAll(Request $request): array
    {
        $cfg = $this->getConfig();
        $mode = $request->input('mode', 'bulan_tahun');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');
        $departemenId = $this->resolveDepartemenId($request);

        $path = "api/client/v1/absensi";
        $url = "{$cfg['baseUrl']}/{$path}";

        $params = [
            'page' => 1,
            'limit' => 100000,
            'per_page' => 100000,
        ];

        if ($mode === 'bulan_tahun') {
            $params['mode'] = 'bulan_tahun';
            if ($bulan) $params['bulan'] = $bulan;
            if ($tahun) $params['tahun'] = $tahun;
        } elseif ($mode === 'range') {
            $params['mode'] = 'range';
            if ($startDate) $params['start_date'] = $startDate;
            if ($endDate) $params['end_date'] = $endDate;
        }

        if ($search) {
            $params['search'] = $search;
        }

        if ($departemenId !== null) {
            $params['departemen_id'] = $departemenId;
        }

        $headers = $this->getSignedHeaders($path, $cfg['secretKey'], $cfg['apiKey']);
        $response = Http::withHeaders($headers)->get($url, $params);
        $json = $response->json();

        if (! $response->successful() || ! ($json['status'] ?? false)) {
            throw new \Exception($json['message'] ?? $response->body() ?? 'Gagal mengambil data dari Web Absensi.');
        }

        return [
            'data' => $json['data'] ?? [],
            'periode' => $json['periode'] ?? [],
        ];
    }

    /**
     * Fetch complete grouped recap (Rekap Total) from Web Absensi (with caching & multi-page pooling).
     */
    public function fetchRekap(Request $request): array
    {
        $cfg = $this->getConfig();
        $mode = $request->input('mode', 'bulan_tahun');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');
        $departemenId = $this->resolveDepartemenId($request);

        $path = "api/client/v1/absensi/rekap";
        $url = "{$cfg['baseUrl']}/{$path}";

        $baseParams = [
            'mode' => $mode,
            'per_page' => 500,
        ];

        if ($mode === 'bulan_tahun') {
            if ($bulan) $baseParams['bulan'] = $bulan;
            if ($tahun) $baseParams['tahun'] = $tahun;
        } elseif ($mode === 'range') {
            if ($startDate) $baseParams['start_date'] = $startDate;
            if ($endDate) $baseParams['end_date'] = $endDate;
        }

        if ($departemenId !== null) {
            $baseParams['departemen_id'] = $departemenId;
        }

        $cacheKey = 'web_absensi_rekap_' . md5(json_encode($baseParams));
        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
        } else {
            $headers = $this->getSignedHeaders($path, $cfg['secretKey'], $cfg['apiKey']);
            $queryParamsPage1 = array_merge($baseParams, ['page' => 1]);
            $responsePage1 = Http::withHeaders($headers)->get($url, $queryParamsPage1);

            if (!$responsePage1->successful()) {
                $errorData = $responsePage1->json();
                $errorMsg = $errorData['message'] ?? $responsePage1->body() ?? 'Gagal mengambil data dari Web Absensi.';
                throw new \Exception('Web Absensi Error: ' . $errorMsg);
            }

            $jsonPage1 = $responsePage1->json();
            if (!($jsonPage1['status'] ?? false)) {
                throw new \Exception($jsonPage1['message'] ?? 'Gagal mengambil rekap absensi.');
            }

            $allData = is_array($jsonPage1['data'] ?? null) ? $jsonPage1['data'] : [];
            $periodeData = $jsonPage1['periode'] ?? null;
            $pagination = $jsonPage1['pagination'] ?? null;
            $lastPage = $pagination['last_page'] ?? 1;
            $maxPages = 20;

            if ($lastPage > 1) {
                $limitLastPage = min($lastPage, $maxPages);
                try {
                    $poolResponses = Http::pool(function (Pool $pool) use ($url, $baseParams, $path, $cfg, $limitLastPage) {
                        $requests = [];
                        for ($p = 2; $p <= $limitLastPage; $p++) {
                            $t = time();
                            $h = $this->getSignedHeaders($path, $cfg['secretKey'], $cfg['apiKey'], $t);
                            $params = array_merge($baseParams, ['page' => $p]);
                            $requests[] = $pool->withHeaders($h)->get($url, $params);
                        }
                        return $requests;
                    });

                    foreach ($poolResponses as $resp) {
                        if ($resp instanceof \Illuminate\Http\Client\Response && $resp->successful()) {
                            $json = $resp->json();
                            if (($json['status'] ?? false) && is_array($json['data'] ?? null)) {
                                $allData = array_merge($allData, $json['data']);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue with collected data if pooling fails
                }
            }

            $result = [
                'status' => true,
                'message' => 'Rekap absensi berhasil diambil (' . count($allData) . ' data)',
                'periode' => $periodeData,
                'data' => $allData,
            ];

            Cache::put($cacheKey, $result, 60);
        }

        // Apply local department filtering if a custom department name was provided that didn't map to 1/2/3
        if ($request->filled('departemen')) {
            $deptKeyword = strtolower(trim($request->input('departemen')));
            $filteredData = array_filter($result['data'] ?? [], function ($item) use ($deptKeyword) {
                $dept = strtolower($item['user']['departemen'] ?? '');
                return $dept === $deptKeyword;
            });
            $result['data'] = array_values($filteredData);
            $result['message'] = 'Rekap absensi berhasil diambil (' . count($result['data']) . ' data)';
        }

        // Apply search filtering in PHP if requested
        if ($search && trim($search) !== '') {
            $keyword = strtolower(trim($search));
            $filteredData = array_filter($result['data'] ?? [], function ($item) use ($keyword) {
                $kode = strtolower($item['user']['kode'] ?? $item['kode_user'] ?? '');
                $nama = strtolower($item['user']['name'] ?? $item['user']['nama'] ?? '');
                $dept = strtolower($item['user']['departemen'] ?? '');
                return str_contains($kode, $keyword) || str_contains($nama, $keyword) || str_contains($dept, $keyword);
            });
            $result['data'] = array_values($filteredData);
            $result['message'] = 'Rekap absensi berhasil diambil (' . count($result['data']) . ' data)';
        }

        // Sort ascending by name
        usort($result['data'], function ($a, $b) {
            $nameA = strtolower($a['user']['name'] ?? $a['user']['nama'] ?? '');
            $nameB = strtolower($b['user']['name'] ?? $b['user']['nama'] ?? '');
            return $nameA <=> $nameB;
        });

        return $result;
    }
}
