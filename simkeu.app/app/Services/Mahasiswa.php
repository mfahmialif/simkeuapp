<?php

namespace App\Services;

class Mahasiswa
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
        $url = config('simkeu.simkeu_url') . "mahasiswa";
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

    public static function find($id)
    {
        $post = [
            'id' => $id,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/id";
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

    public static function nim($nim, $whereIn = null)
    {
        // if ($whereIn) {
        // $nim = json_encode($nim);
        // }
        // dd(json_decode($nim));

        $post = [
            'nim' => $nim,
            'whereIn' => $whereIn
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/searchNim";
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

    public static function count($offset = null, $limit = null, $search = null, $order = null, $dir = null, $where = null)
    {
        $post = [
            'offset' => $offset,
            'limit' => $limit,
            'search' => $search,
            'order' => $order,
            'dir' => $dir,
            'where' => $where != null ? json_encode($where) : null,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/count";
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

    /**
     * Get mahasiswa for kkn
     * @param int $offset offset, default null
     * @param int $limit limit, default null
     * @param string $search search mahasiswa, default null
     * @param string $order order mahasiswa
     * @param string $dir dir mahasiswa, default null asc or desc
     * @param array $where where mahasiswa
     * @return array as data mahasiswa
     */
    public static function kkn($offset = null, $limit = null, $search = null, $order = null, $dir = null, $where = null)
    {
        $post = [
            'offset' => $offset,
            'limit' => $limit,
            'search' => $search,
            'order' => $order,
            'dir' => $dir,
            'where' => $where != null ? json_encode($where) : null,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/kkn";
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

    /**
     * Get mahasiswa for table
     * @param int $offset offset, default null
     * @param int $limit limit, default null
     * @param string $search search mahasiswa, default null
     * @param string $order order mahasiswa
     * @param string $dir dir mahasiswa, default null asc or desc
     * @param array $where where mahasiswa
     * @return array as data mahasiswa
     */
    public static function table($request)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/table";
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

    public static function getSemester($thAkademikId, $prodiId, $jkId)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/getSemester";
        $post = [
            'prodi_id' => $prodiId,
            'jk_id' => $jkId,
            'th_akademik_id' => $thAkademikId,
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

    public static function getMahasiswaBySemester($thAkademikId, $prodiId, $jkId, $semester)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/getMahasiswaBySemester";
        $post = [
            'prodi_id' => $prodiId,
            'jk_id' => $jkId,
            'th_akademik_id' => $thAkademikId,
            'semester' => $semester,
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

    public static function updateStatusMahasiswa($nim, $statusId)
    {
        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/updateStatusMahasiswa";
        $post = [
            'nim' => $nim,
            'status_id' => $statusId,
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

    public static function cekNilai($nim)
    {

        $post = [
            'nim' => $nim,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/cekNilai";
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

    public static function cekPelanggaran($nim)
    {

        $post = [
            'nim' => $nim,
        ];

        $apiKey = config('simkeu.simkeu_api_key');
        $url = config('simkeu.simkeu_url') . "mahasiswa/cekPelanggaran";
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
