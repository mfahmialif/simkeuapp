<?php

namespace App\Services;

class Jadwal
{
    /**
     * Get mahasiswa by nim
     * @param int $offset offset, default null
     * @param int $limit limit, default null
     * @param string $search search mahasiswa, default null
     * @param string $order order mahasiswa
     * @param string $dir dir mahasiswa, default null asc or desc
     * @param array $where where mahasiswa
     * @return array as data mahasiswa
     */
    public static function all($offset = null, $limit = null, $search = null, $order = null, $dir = null, $where = null, $pluck = null)
    {
        $post = [
            'offset' => $offset,
            'limit' => $limit,
            'search' => $search,
            'order' => $order,
            'dir' => $dir,
            'where' => $where != null ? json_encode($where) : null,
            'pluck' => $pluck != null ? json_encode($pluck) : null,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",

        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response->data;
    }

    public static function find($id, $whereIn = null)
    {
        // if ($whereIn) {
        // $id = json_encode($id);
        // }
        // dd(json_decode($id));

        $post = [
            'id' => $id,
            'whereIn' => $whereIn
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal/find";
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

    public static function table($request)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal/table";
        $post = $request->all();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",

        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }


    /**
     * Get jadwal for table
     * @param int $offset offset, default null
     * @param int $limit limit, default null
     * @param string $search search jadwal, default null
     * @param string $order order jadwal
     * @param string $dir dir mahasiswa, default null asc or desc
     * @param array $where where mahasiswa
     * @return array as data mahasiswa
     */
    public static function mahasiswa($nim, $thAkademikId)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal/mahasiswa";
        $post = [
            'nim' => $nim,
            'th_akademik_id' => $thAkademikId
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",

        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }

    /**
     * Get jadwal for table
     * @param int $offset offset, default null
     * @param int $limit limit, default null
     * @param string $search search jadwal, default null
     * @param string $order order jadwal
     * @param string $dir dir mahasiswa, default null asc or desc
     * @param array $where where mahasiswa
     * @return array as data mahasiswa
     */
    public static function dosen($nim, $thAkademikId)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal/dosen";
        $post = [
            'nim' => $nim,
            'th_akademik_id' => $thAkademikId
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",

        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }

    public static function dosenTable($request)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "jadwal/dosenTable";
        $post = $request->all();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",

        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);
        return $response;
    }
}
