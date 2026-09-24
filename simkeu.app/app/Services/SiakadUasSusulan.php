<?php

namespace App\Services;

use App\Models\ThAkademik;
use Illuminate\Support\Facades\Log;

class SiakadUasSusulan
{
    /**
     * Mendaftarkan UAS Susulan beserta daftar mata kuliah ke SIAKAD.
     * Mengacu pada dokumentasi docs/uassusulan.md (POST /api/uas-susulan).
     *
     * @param array $payload
     * @return object|null
     */
    public static function create(array $payload)
    {
        return self::requestJson('POST', 'uas-susulan', $payload);
    }

    /**
     * Memperbarui pendaftaran UAS Susulan di SIAKAD.
     * Mengacu pada dokumentasi docs/uassusulan.md (PUT /api/uas-susulan/{idOrNim}).
     *
     * @param int|string $idOrNim
     * @param array $payload
     * @return object|null
     */
    public static function update($idOrNim, array $payload)
    {
        return self::requestJson('PUT', 'uas-susulan/' . $idOrNim, $payload);
    }

    /**
     * Menghapus pendaftaran UAS Susulan di SIAKAD.
     * Mengacu pada dokumentasi docs/uassusulan.md (DELETE /api/uas-susulan/{idOrNim}).
     *
     * @param int|string $idOrNim
     * @return object|null
     */
    public static function delete($idOrNim)
    {
        return self::requestJson('DELETE', 'uas-susulan/' . $idOrNim);
    }

    /**
     * Mengambil detail pendaftaran UAS Susulan dari SIAKAD berdasarkan ID atau NIM.
     * Mengacu pada dokumentasi docs/uassusulan.md (GET /api/uas-susulan/{idOrNim}).
     *
     * @param int|string $idOrNim
     * @return object|null
     */
    public static function find($idOrNim)
    {
        return self::requestJson('GET', 'uas-susulan/' . $idOrNim);
    }

    /**
     * Helper praktis untuk mempersiapkan payload dan mendaftarkan UAS Susulan ke SIAKAD.
     *
     * @param string $nim
     * @param array $jadwalKuliahIds
     * @param int|string|null $thAkademikId
     * @param string|null $tanggal
     * @param string|null $keterangan
     * @param bool $allowDuplicate
     * @return object|null
     */
    public static function syncCreate(
        string $nim,
        array $jadwalKuliahIds,
        $thAkademikId = null,
        ?string $tanggal = null,
        ?string $keterangan = null,
        bool $allowDuplicate = false
    ) {
        $nim = trim(strtoupper($nim));
        $mkIds = array_values(array_unique(array_filter(array_map('intval', (array) $jadwalKuliahIds))));

        if (empty($nim) || empty($mkIds)) {
            return null;
        }

        $thAkademikKode = null;
        if ($thAkademikId) {
            $th = ThAkademik::find($thAkademikId);
            $thAkademikKode = $th ? $th->kode : null;
        }

        $payload = [
            'nim'              => $nim,
            'mk'               => $mkIds,
            'th_akademik_id'   => $thAkademikId ? (int) $thAkademikId : null,
            'th_akademik_kode' => $thAkademikKode,
            'tanggal'          => $tanggal ?: date('Y-m-d'),
            'keterangan'       => $keterangan ?: '',
            'allow_duplicate'  => $allowDuplicate,
        ];

        return self::create($payload);
    }

    /**
     * Eksekutor cURL JSON request ke endpoint SIAKAD API.
     *
     * @param string $method
     * @param string $path
     * @param array $data
     * @return object|null
     */
    public static function requestJson(string $method, string $path, array $data = [])
    {
        $method = strtoupper($method);
        $baseUrl = config('simkeu.simkeu_url');
        $apiKey = (string) config('simkeu.simkeu_api_key');

        if (empty($baseUrl)) {
            Log::warning('SiakadUasSusulan: simkeu_url configuration is empty.');
            return (object) [
                'status'  => false,
                'code'    => 500,
                'message' => 'Konfigurasi SIMKEU_URL belum disetel di .env.',
            ];
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        if ($method === 'GET' && !empty($data)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            "apikey: {$apiKey}",
            "x-api-key: {$apiKey}",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error("SiakadUasSusulan cURL Error ({$method} {$url}): {$curlError}");
            return (object) [
                'status'  => false,
                'code'    => $httpCode ?: 500,
                'message' => "Koneksi ke SIAKAD gagal: {$curlError}",
            ];
        }

        $decoded = json_decode($rawResponse);
        if ($decoded === null && !empty($rawResponse)) {
            Log::error("SiakadUasSusulan Invalid JSON ({$method} {$url}, HTTP {$httpCode}): {$rawResponse}");
            return (object) [
                'status'  => false,
                'code'    => $httpCode ?: 500,
                'message' => "Respon dari SIAKAD tidak valid (HTTP {$httpCode}).",
                'raw'     => $rawResponse,
            ];
        }

        return $decoded;
    }
}
