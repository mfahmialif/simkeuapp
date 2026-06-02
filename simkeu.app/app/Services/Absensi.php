<?php

namespace App\Services;

use RuntimeException;

class Absensi
{
    private const DEFAULT_BASE_URL = 'https://absensi.uiidalwa.web.id';

    public static function table($request)
    {
        return self::get('/api/absensi', $request->all(), false);
    }

    public static function users(array $query = [])
    {
        return self::get('/api/users', $query);
    }

    public static function user($id)
    {
        return self::get('/api/users/' . rawurlencode((string) $id));
    }

    private static function get(string $path, array $query = [], bool $throw = true)
    {
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');
        $url = rtrim(config('services.absensi.base_url', self::DEFAULT_BASE_URL), '/') . '/' . ltrim($path, '/');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
        ];
        $token = config('services.absensi.token');
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            if ($throw) {
                throw new RuntimeException('Gagal terhubung ke Web Absensi: ' . $error);
            }

            return null;
        }

        $decoded = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($throw) {
                throw new RuntimeException('Response Web Absensi tidak valid.');
            }

            return null;
        }

        if ($statusCode >= 400 && $throw) {
            $message = data_get($decoded, 'message');
            if (is_array($message) || is_object($message) || ! $message) {
                $message = 'Request Web Absensi gagal.';
            }

            throw new RuntimeException($message);
        }

        return $decoded;
    }
}
