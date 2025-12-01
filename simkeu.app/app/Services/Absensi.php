<?php

namespace App\Services;

class Absensi
{

    public static function table($request)
    {
        $url = "https://absensi.uiidalwa.web.id/api/absensi";

        // ambil semua input request dan ubah jadi query string
        $query = http_build_query($request->all());

        // gabungkan ke URL
        $fullUrl = $url . "?" . $query;

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // pastikan metode GET

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }
}
