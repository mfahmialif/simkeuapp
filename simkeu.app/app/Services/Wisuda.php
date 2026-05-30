<?php

namespace App\Services;

use InvalidArgumentException;

class Wisuda
{
    public const BAYAR = true;
    public const TANPA_BAYAR = false;

    public const JENIS_PEMBAYARAN = [
        'Transfer Bank',
        'Tunai',
        'Lain-lain',
    ];

    public const JENIS_PEMBAYARAN_MAP = [
        'transfer' => 'Transfer Bank',
        'transfer bank' => 'Transfer Bank',
        'cash' => 'Tunai',
        'tunai' => 'Tunai',
        'yayasan' => 'Lain-lain',
        'deposit' => 'Lain-lain',
        'lain-lain' => 'Lain-lain',
        'lain lain' => 'Lain-lain',
        'lainnya' => 'Lain-lain',
    ];

    public const UKURAN_BAJU = [
        'S',
        'M',
        'L',
        'XL',
        'XXL',
        'XXXL',
    ];

    public static function tahun()
    {
        return self::requestJson('GET', 'tahun');
    }

    public static function registrasi(array $data)
    {
        $data = self::prepareRegistrasi($data);

        self::validateRegistrasi($data);

        return self::requestJson('POST', 'siswa/registrasi', $data);
    }

    public static function translateJenisPembayaran($jenisPembayaran): ?string
    {
        if (is_array($jenisPembayaran)) {
            $jenisPembayaran = $jenisPembayaran['nama'] ?? null;
        }

        if (is_object($jenisPembayaran)) {
            $jenisPembayaran = $jenisPembayaran->nama ?? null;
        }

        if ($jenisPembayaran === null || trim((string) $jenisPembayaran) === '') {
            return null;
        }

        $jenisPembayaran = trim((string) $jenisPembayaran);
        $key = strtolower(preg_replace('/\s+/', ' ', $jenisPembayaran));

        if (isset(self::JENIS_PEMBAYARAN_MAP[$key])) {
            return self::JENIS_PEMBAYARAN_MAP[$key];
        }

        if (str_contains($key, 'transfer')) {
            return 'Transfer Bank';
        }

        if (str_contains($key, 'cash') || str_contains($key, 'tunai')) {
            return 'Tunai';
        }

        if (str_contains($key, 'yayasan') || str_contains($key, 'deposit') || str_contains($key, 'lain')) {
            return 'Lain-lain';
        }

        return $jenisPembayaran;
    }

    public static function editPembayaran(array $data)
    {
        return self::requestJson('PUT', 'siswa/edit-pembayaran', $data);
    }

    public static function hapusPembayaran(array $data)
    {
        return self::requestJson('DELETE', 'siswa/hapus-pembayaran', $data);
    }

    public static function cekWisuda(array $query = [])
    {
        return self::requestJson('GET', 'peserta/cekWisuda', $query, false);
    }

    private static function prepareRegistrasi(array $data): array
    {
        $data['is_bayar'] = self::TANPA_BAYAR;
        unset($data['jenis_pembayaran'], $data['jumlah'], $data['keterangan']);

        return $data;
    }

    private static function validateRegistrasi(array $data): void
    {
        if (isset($data['ukuran_baju']) && ! in_array($data['ukuran_baju'], self::UKURAN_BAJU, true)) {
            throw new InvalidArgumentException(
                'ukuran_baju harus salah satu: ' . implode(', ', self::UKURAN_BAJU)
            );
        }

        $isBayar = filter_var($data['is_bayar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isBayar && (! isset($data['jenis_pembayaran']) || ! in_array($data['jenis_pembayaran'], self::JENIS_PEMBAYARAN, true))) {
            throw new InvalidArgumentException(
                'jenis_pembayaran harus salah satu: ' . implode(', ', self::JENIS_PEMBAYARAN)
            );
        }
    }

    private static function requestJson(string $method, string $path, array $data = [], bool $withApiKey = true)
    {
        $method = strtoupper($method);
        $apiKey = (string) config('simkeu.wisuda_api_key');
        $url = rtrim((string) config('simkeu.wisuda_url'), '/') . '/' . ltrim($path, '/');

        if ($method === 'GET' && $data) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            'Accept: application/json',
        ];

        if ($withApiKey) {
            $headers[] = "apikey: $apiKey";
        }

        if ($method !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}
