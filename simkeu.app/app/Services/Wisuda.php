<?php

namespace App\Services;

class Wisuda
{
    public static function registrasi(array $data)
    {
        return self::postJson('siswa/registrasi', $data);
    }

    private static function postJson($path, array $data)
    {
        $apiKey = config('simkeu.wisuda_api_key');
        $url = rtrim((string) config('simkeu.wisuda_url'), '/') . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            "apikey: $apiKey",
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}
