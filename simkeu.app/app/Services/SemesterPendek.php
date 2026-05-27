<?php

namespace App\Services;

use App\Models\KeuanganDispensasiTagihan;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganTagihan;

class SemesterPendek
{
    public static function krs($search)
    {
        $post = [
            'search' => $search,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "semester-pendek/krs";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return isset($response->data) ? $response->data : null;
    }

    public static function periode()
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "semester-pendek/periode";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return isset($response->data) ? $response->data : null;
    }

    public static function krsDetail($id)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "semester-pendek/krs/" . $id;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return isset($response->data) ? $response->data : null;
    }

    public static function searchKrs($krsIds)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $krsJson = json_encode(array_map('intval', $krsIds));
        $post = [
            'krs_id' => $krsJson,
            'whereIn' => true,
        ];
        $url = config('simkeu.simkeu_url') . "semester-pendek/searchKrs";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return isset($response->data) ? $response->data : [];
    }

    public static function updatePembayaranKrs($id, $sudahBayar)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        // send PUT request to api.semester-pendek.updatePembayaranKrs
        // The URL should be updatePembayaranKrs/{id}?sudah_bayar={sudahBayar}
        $url = config('simkeu.simkeu_url') . "semester-pendek/updatePembayaranKrs/" . $id . "?sudah_bayar=" . $sudahBayar;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); // If params are needed in body
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }

    public static function syncTagihanIds($tagihanIds, $nim): void
    {
        collect((array) $tagihanIds)
            ->filter()
            ->unique()
            ->each(function ($tagihanId) use ($nim) {
                $tagihan = KeuanganTagihan::find($tagihanId);
                self::syncTagihan($tagihan, $nim);
            });
    }

    public static function syncFromPembayaran(?KeuanganPembayaran $pembayaran): void
    {
        if (! $pembayaran) {
            return;
        }

        $pembayaran->loadMissing('tagihan');
        self::syncTagihan($pembayaran->tagihan, $pembayaran->nim);
    }

    public static function syncFromDispensasi(?KeuanganDispensasiTagihan $dispensasi): void
    {
        if (! $dispensasi) {
            return;
        }

        self::syncTagihanIds([$dispensasi->jenis_tagihan_id], $dispensasi->nim);
    }

    public static function syncTagihan($tagihan, $nim): void
    {
        if (! $tagihan || empty(trim((string) $tagihan->nim))) {
            return;
        }

        $krsId = self::parseKrsIdFromTagihanName($tagihan->nama);
        if (! $krsId) {
            return;
        }

        $nim = strtoupper(trim((string) ($tagihan->nim ?: $nim)));
        $totalSudahBayar = KeuanganPembayaran::where('tagihan_id', $tagihan->id)
            ->where('nim', $nim)
            ->sum('jumlah');
        $totalDispensasiAktif = KeuanganDispensasiTagihan::where('jenis_tagihan_id', $tagihan->id)
            ->where('nim', $nim)
            ->where(function ($query) {
                $query->whereNull('jenis')
                    ->orWhere('jenis', '!=', 'Non Beasiswa');
            })
            ->sum('jumlah');

        self::updatePembayaranKrs($krsId, $totalSudahBayar + $totalDispensasiAktif);
    }

    public static function parseKrsIdFromTagihanName($namaTagihan): ?int
    {
        $parts = array_map('trim', explode(' - ', (string) $namaTagihan));

        if (count($parts) < 3 || strtoupper($parts[0]) !== 'SEMESTER PENDEK') {
            return null;
        }

        $krsId = end($parts);
        if (! preg_match('/^\d+$/', (string) $krsId)) {
            return null;
        }

        return (int) $krsId;
    }
}
