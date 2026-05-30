<?php

namespace App\Http\Controllers;

use App\Services\Helper;
use App\Services\Jadwal;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use App\Models\KeuanganTagihan;
use App\Models\KeuanganSetoran;
use App\Models\KeuanganPembayaran;
use App\Services\Wisuda;
use App\Services\SemesterPendek;
use App\Services\TagihanMahasiswa;
use App\Models\KeuanganJenisPembayaranDetail;
use App\Models\KeuanganPembayaranSemesterPendek;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    public function index()
    {
        // dd(Mahasiswa::nim("202385020016"));
        // dd($this->tesInputWisuda());
        // dd($this->syncPembayaranSP());
        // $cekPembayaran = self::cekPembayaran()->getData(true);
        // dd($cekPembayaran);
        // return response()->json($cekPembayaran["data"] ?? []);
        // $search = "202285020123";
        // $tes = SemesterPendek::krs($search);
        // dd($tes);
        // $mahasiswa = Mahasiswa::all(null, null, "ahmad", null, null, [
        //     ['mst_prodi.alias', '=', 'PBA']
        // ]);
        // dd($mahasiswa);
        // $pmbUrl = rtrim(env("pmb_url"), "/") . "/simkeu/pembayaran";
        // $pmbApiKey = env("pmb_api_key");
        // $pmbDataAll = [];
        // $pmbResponse = \Illuminate\Support\Facades\Http::withHeaders([
        //     "apikey" => $pmbApiKey,
        // ])->get($pmbUrl, [
        //     "start_date" => @$startDate,
        //     "end_date" => @$endDate,
        //     "jenjang" => @$jenjang,
        //     "jenis_kelamin" => @$jenisPembayaranKategori,
        //     "jenis_pembayaran" => @$jenisPembayaranNama,
        // ]);
        // dd($pmbResponse->json());
        // $jpModel = \App\Models\KeuanganJenisPembayaran::find(9);
        // if ($jpModel) {
        //     $nama = strtolower(trim($jpModel->nama));
        //     if (strpos($nama, 'deposit') !== false) {
        //         $jenisPembayaranNama = 'deposit';
        //     } elseif (strpos($nama, 'transfer') !== false) {
        //         $jenisPembayaranNama = 'transfer';
        //     } elseif (strpos($nama, 'cash') !== false) {
        //         $jenisPembayaranNama = 'cash';
        //     } elseif (strpos($nama, 'yayasan') !== false) {
        //         $jenisPembayaranNama = 'yayasan';
        //     }
        // }
        // dd($nama, $jenisPembayaranNama);
        // $nim = '202185010011';
        // $cekPelanggaran = Mahasiswa::cekPelanggaran($nim);
        // dd($cekPelanggaran);

        // $dataTagihan = TagihanMahasiswa::tagihan($nim);
        // $hasSkripsi = false;
        // $cekNilai   = [
        //     'status'  => true,
        //     'message' => 'Tanpa cek kelengkapan',
        // ];

        // if (true) {
        //     if (isset($dataTagihan['list_tagihan'])) {
        //         foreach ($dataTagihan['list_tagihan'] as $tagihan) {
        //             if (stripos($tagihan['nama'], 'skripsi') !== false) {
        //                 $hasSkripsi = true;
        //                 break;
        //             }
        //         }
        //     }

        //     if ($hasSkripsi) {
        //         $cekNilai = Mahasiswa::cekNilai($nim);
        //     }
        // }

        return redirect("/");
        // return self::fixPembayaran();
        // return Mahasiswa::all(null, 30, '202585330013', 'mst_mhs.nim', 'asc');
        // $m = Mahasiswa::nim('202385200080');
        // $angkatan = (int) substr($m->th_akademik->kode, 0, 4);
        // dd($angkatan);
        // return TagihanMahasiswa::tagihan('202385200080');

        // return self::syncJkId();

        // $cek = Jadwal::mahasiswa('2025850100022', 24);
        // dd($cek);
        // dd(Mahasiswa::updateStatusMahasiswa('202485010002', 20));
        // $getSemester = Mahasiswa::getSemester(24, 6, 8);
        // $getMahasiswaBySemester = Mahasiswa::getMahasiswaBySemester(24, 6, 8, 1)->data;
        // $mahasiswa = collect($getMahasiswaBySemester->mahasiswa)->pluck('nim')->values();
        // dd($mahasiswa);

        // $pembayaranTanpaJenisPembayaran = KeuanganPembayaran::leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.pembayaran_id')
        //     // ->whereNull('keuangan_jenis_pembayaran_detail.id')
        //     // ->select('keuangan_pembayaran.*', 'keuangan_jenis_pembayaran_detail.id as jenis_pembayaran_id')
        //     ->get();
        // dd($pembayaranTanpaJenisPembayaran);

        // dd($pembayaranTanpaJenisPembayaran);
        // $nota = Helper::generateNota('2025-10-09', 8);
        // dd($nota);
        // Ambil data mahasiswa dari API

        // $mahasiswa = Mahasiswa::nim('["202085030001","202085030001","202085030001","202085030001","202085030001","202485030019","202485030019","202485200131","202485200112","202385200137"]', true);
        // dd($mahasiswa);
        // $mahasiswa = Mahasiswa::nim('202485030019');
        // $mahasiswa = Mahasiswa::all(null, null, null, null, null, [
        //     ['mst_mhs.jk_id', '=', 9]
        // ], ['nim']);

        // dd($mahasiswa);
        // $mahasiswaApi = Mahasiswa::all();

        // $nimList = collect($mahasiswaApi)
        //     ->filter(fn($m) => str_contains((string) $m->jk_id, '8'))
        //     ->pluck('nim')
        //     ->values();

        // $pemasukan = 0;
        // $nimList->chunk(1000)->each(function ($chunk) use (&$pemasukan) {
        //     $batch = KeuanganPembayaran::with('tagihan')
        //         ->whereIn('nim', $chunk)
        //         ->get();

        //     foreach ($batch as $t) {
        //         if ($t->jumlah == $t->nim) {
        //             $t->jumlah = optional($t->tagihan)->jumlah ?? 0;
        //         }
        //         $pemasukan += (float) $t->jumlah;
        //     }
        // });

        // $setoran     = KeuanganSetoran::where('kategori', 'LIKE', "%{$jk->kategori}%")->get();
        // $pengeluaran = 0;
        // $pending     = 0;
        // foreach ($setoran as $s) {
        //     $status = strtolower((string) $s->status);
        //     if ($status === 'setuju') {
        //         $pengeluaran += (float) $s->jumlah;
        //     }

        //     if ($status === 'pending') {
        //         $pending += (float) $s->jumlah;
        //     }

        // }

        // return [
        //     'pemasukan' => $pemasukan,
        //     // 'pengeluaran' => $pengeluaran,
        //     // 'pending'     => $pending,
        // ];
    }

    public static function cekPembayaran()
    {
        try {
            $tagihanUts = KeuanganTagihan::join(
                "th_akademik as tha",
                "tha.id",
                "=",
                "keuangan_tagihan.th_akademik_id",
            )
                ->join(
                    "th_akademik as thangkatan",
                    "thangkatan.id",
                    "=",
                    "keuangan_tagihan.th_angkatan_id",
                )
                ->leftJoin(
                    "prodi",
                    "prodi.id",
                    "=",
                    "keuangan_tagihan.prodi_id",
                )
                ->leftJoin(
                    "form_schadule as form",
                    "form.id",
                    "=",
                    "keuangan_tagihan.form_schadule_id",
                )
                ->where("keuangan_tagihan.nama", "LIKE", "%UTS%")
                ->select(
                    "keuangan_tagihan.id",
                    "keuangan_tagihan.kode",
                    "keuangan_tagihan.nama",
                    "keuangan_tagihan.jumlah",
                    "keuangan_tagihan.nim",
                    "keuangan_tagihan.th_akademik_id",
                    "keuangan_tagihan.th_angkatan_id",
                    "keuangan_tagihan.prodi_id",
                    "keuangan_tagihan.kelas_id",
                    "keuangan_tagihan.form_schadule_id",
                    "tha.kode as th_akademik_kode",
                    "tha.nama as th_akademik_nama",
                    "thangkatan.kode as th_angkatan_kode",
                    "thangkatan.nama as th_angkatan_nama",
                    "prodi.nama as prodi_nama",
                    "prodi.alias as prodi_alias",
                    "form.kode as form_schadule_kode",
                    "form.nama as form_schadule_nama",
                )
                ->get();

            $pembayaranByTagihan = KeuanganPembayaran::leftJoin(
                "th_akademik as thp",
                "thp.id",
                "=",
                "keuangan_pembayaran.th_akademik_id",
            )
                ->leftJoin(
                    "keuangan_nota as kn",
                    "kn.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->leftJoin(
                    "keuangan_jenis_pembayaran_detail as kjpd",
                    "kjpd.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->leftJoin(
                    "keuangan_jenis_pembayaran as kjp",
                    "kjp.id",
                    "=",
                    "kjpd.jenis_pembayaran_id",
                )
                ->leftJoin(
                    "users",
                    "users.id",
                    "=",
                    "keuangan_pembayaran.user_id",
                )
                ->whereIn(
                    "keuangan_pembayaran.tagihan_id",
                    $tagihanUts->pluck("id"),
                )
                ->select(
                    "keuangan_pembayaran.id",
                    "keuangan_pembayaran.nomor",
                    "keuangan_pembayaran.tanggal",
                    "keuangan_pembayaran.th_akademik_id",
                    "thp.kode as th_akademik_kode",
                    "thp.nama as th_akademik_nama",
                    "keuangan_pembayaran.tagihan_id",
                    "keuangan_pembayaran.nim",
                    "keuangan_pembayaran.smt",
                    "keuangan_pembayaran.jml_sks",
                    "keuangan_pembayaran.jumlah",
                    "keuangan_pembayaran.jk_id",
                    "keuangan_pembayaran.user_id",
                    "users.name as petugas_nama",
                    "kjpd.jenis_pembayaran_id",
                    "kjp.nama as jenis_pembayaran_nama",
                    "keuangan_pembayaran.created_at",
                    "keuangan_pembayaran.updated_at",
                )
                ->addSelect(
                    DB::raw(
                        "COALESCE(kn.nota, keuangan_pembayaran.nomor) as nota",
                    ),
                )
                ->orderBy("keuangan_pembayaran.tanggal")
                ->orderBy("keuangan_pembayaran.id")
                ->get()
                ->groupBy("tagihan_id");

            $tagihanTidakSesuai = $tagihanUts
                ->map(function ($tagihan) use ($pembayaranByTagihan) {
                    $semesterTagihan = null;
                    if (
                        preg_match(
                            "/\bsemester\s+(\d+)\b/i",
                            (string) $tagihan->nama,
                            $matches,
                        )
                    ) {
                        $semesterTagihan = (int) $matches[1];
                    }

                    $semesterSeharusnya = TagihanMahasiswa::getSemesterTagihan(
                        $tagihan,
                        $tagihan->th_angkatan_kode,
                    );
                    $masalah = null;

                    if ($semesterTagihan === null) {
                        $masalah = "Semester pada nama tagihan tidak ditemukan";
                    } elseif ($semesterSeharusnya === null) {
                        $masalah =
                            "Kode tahun angkatan atau tahun akademik tidak valid";
                    } else {
                        if ($semesterTagihan === $semesterSeharusnya) {
                            return null;
                        }

                        $masalah = "Semester tagihan tidak sesuai";
                    }

                    return [
                        "id" => $tagihan->id,
                        "kode" => $tagihan->kode,
                        "nama" => $tagihan->nama,
                        "jumlah" => $tagihan->jumlah,
                        "nim" => $tagihan->nim,
                        "th_akademik_id" => $tagihan->th_akademik_id,
                        "th_akademik_kode" => $tagihan->th_akademik_kode,
                        "th_akademik_nama" => $tagihan->th_akademik_nama,
                        "th_angkatan_id" => $tagihan->th_angkatan_id,
                        "th_angkatan_kode" => $tagihan->th_angkatan_kode,
                        "th_angkatan_nama" => $tagihan->th_angkatan_nama,
                        "prodi_id" => $tagihan->prodi_id,
                        "prodi_nama" => $tagihan->prodi_nama,
                        "prodi_alias" => $tagihan->prodi_alias,
                        "kelas_id" => $tagihan->kelas_id,
                        "form_schadule_id" => $tagihan->form_schadule_id,
                        "form_schadule_kode" => $tagihan->form_schadule_kode,
                        "form_schadule_nama" => $tagihan->form_schadule_nama,
                        "semester_tagihan" => $semesterTagihan,
                        "semester_seharusnya" => $semesterSeharusnya,
                        "masalah" => $masalah,
                        "pembayaran" => $pembayaranByTagihan
                            ->get($tagihan->id, collect())
                            ->values(),
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                "status" => true,
                "total" => $tagihanTidakSesuai->count(),
                "data" => $tagihanTidakSesuai,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public static function cekPembayaranUts()
    {
        try {
            $tagihanUtsSemester8 = KeuanganTagihan::join(
                "th_akademik as tha",
                "tha.id",
                "=",
                "keuangan_tagihan.th_akademik_id",
            )
                ->join(
                    "th_akademik as thangkatan",
                    "thangkatan.id",
                    "=",
                    "keuangan_tagihan.th_angkatan_id",
                )
                ->leftJoin(
                    "prodi",
                    "prodi.id",
                    "=",
                    "keuangan_tagihan.prodi_id",
                )
                ->leftJoin(
                    "form_schadule as form",
                    "form.id",
                    "=",
                    "keuangan_tagihan.form_schadule_id",
                )
                ->where("keuangan_tagihan.nama", "LIKE", "%UTS%")
                ->where("keuangan_tagihan.nama", "LIKE", "%SEMESTER 8%")
                ->select(
                    "keuangan_tagihan.id",
                    "keuangan_tagihan.kode",
                    "keuangan_tagihan.nama",
                    "keuangan_tagihan.jumlah",
                    "keuangan_tagihan.nim",
                    "keuangan_tagihan.th_akademik_id",
                    "keuangan_tagihan.th_angkatan_id",
                    "keuangan_tagihan.prodi_id",
                    "keuangan_tagihan.kelas_id",
                    "keuangan_tagihan.form_schadule_id",
                    "tha.kode as th_akademik_kode",
                    "tha.nama as th_akademik_nama",
                    "thangkatan.kode as th_angkatan_kode",
                    "thangkatan.nama as th_angkatan_nama",
                    "prodi.nama as prodi_nama",
                    "prodi.alias as prodi_alias",
                    "form.kode as form_schadule_kode",
                    "form.nama as form_schadule_nama",
                )
                ->get()
                ->filter(function ($tagihan) {
                    return preg_match(
                        "/\bsemester\s+8\b/i",
                        (string) $tagihan->nama,
                    );
                })
                ->values();

            $pembayaranByTagihan = KeuanganPembayaran::leftJoin(
                "th_akademik as thp",
                "thp.id",
                "=",
                "keuangan_pembayaran.th_akademik_id",
            )
                ->leftJoin(
                    "keuangan_nota as kn",
                    "kn.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->leftJoin(
                    "keuangan_jenis_pembayaran_detail as kjpd",
                    "kjpd.pembayaran_id",
                    "=",
                    "keuangan_pembayaran.id",
                )
                ->leftJoin(
                    "keuangan_jenis_pembayaran as kjp",
                    "kjp.id",
                    "=",
                    "kjpd.jenis_pembayaran_id",
                )
                ->leftJoin(
                    "users",
                    "users.id",
                    "=",
                    "keuangan_pembayaran.user_id",
                )
                ->whereIn(
                    "keuangan_pembayaran.tagihan_id",
                    $tagihanUtsSemester8->pluck("id"),
                )
                ->select(
                    "keuangan_pembayaran.id",
                    "keuangan_pembayaran.nomor",
                    "keuangan_pembayaran.tanggal",
                    "keuangan_pembayaran.th_akademik_id",
                    "thp.kode as th_akademik_kode",
                    "thp.nama as th_akademik_nama",
                    "keuangan_pembayaran.tagihan_id",
                    "keuangan_pembayaran.nim",
                    "keuangan_pembayaran.smt",
                    "keuangan_pembayaran.jml_sks",
                    "keuangan_pembayaran.jumlah",
                    "keuangan_pembayaran.jk_id",
                    "keuangan_pembayaran.user_id",
                    "users.name as petugas_nama",
                    "kjpd.jenis_pembayaran_id",
                    "kjp.nama as jenis_pembayaran_nama",
                    "keuangan_pembayaran.created_at",
                    "keuangan_pembayaran.updated_at",
                )
                ->addSelect(
                    DB::raw(
                        "COALESCE(kn.nota, keuangan_pembayaran.nomor) as nota",
                    ),
                )
                ->orderBy("keuangan_pembayaran.tanggal")
                ->orderBy("keuangan_pembayaran.id")
                ->get()
                ->groupBy("tagihan_id");

            $data = $tagihanUtsSemester8
                ->map(function ($tagihan) use ($pembayaranByTagihan) {
                    $pembayaran = $pembayaranByTagihan
                        ->get($tagihan->id, collect())
                        ->values();

                    return [
                        "id" => $tagihan->id,
                        "kode" => $tagihan->kode,
                        "nama" => $tagihan->nama,
                        "jumlah" => $tagihan->jumlah,
                        "nim" => $tagihan->nim,
                        "th_akademik_id" => $tagihan->th_akademik_id,
                        "th_akademik_kode" => $tagihan->th_akademik_kode,
                        "th_akademik_nama" => $tagihan->th_akademik_nama,
                        "th_angkatan_id" => $tagihan->th_angkatan_id,
                        "th_angkatan_kode" => $tagihan->th_angkatan_kode,
                        "th_angkatan_nama" => $tagihan->th_angkatan_nama,
                        "prodi_id" => $tagihan->prodi_id,
                        "prodi_nama" => $tagihan->prodi_nama,
                        "prodi_alias" => $tagihan->prodi_alias,
                        "kelas_id" => $tagihan->kelas_id,
                        "form_schadule_id" => $tagihan->form_schadule_id,
                        "form_schadule_kode" => $tagihan->form_schadule_kode,
                        "form_schadule_nama" => $tagihan->form_schadule_nama,
                        "semester_tagihan" => 8,
                        "total_pembayaran" => $pembayaran->count(),
                        "total_dibayar" => $pembayaran->sum("jumlah"),
                        "pembayaran" => $pembayaran,
                    ];
                })
                ->values();

            return response()->json([
                "status" => true,
                "total_tagihan" => $data->count(),
                "total_pembayaran" => $data->sum("total_pembayaran"),
                "data" => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public static function syncJkId()
    {
        $jkId = [8, 9];
        foreach ($jkId as $item) {
            $nim = collect(
                Mahasiswa::all(null, null, null, null, null, [
                    ["mst_mhs.jk_id", "=", $item],
                ]),
            )
                ->pluck("nim") // pastikan jadi list NIM saja
                ->filter() // buang null/kosong
                ->unique()
                ->values();

            foreach ($nim->chunk(1000) as $chunk) {
                KeuanganPembayaran::whereIn("nim", $chunk->all())->update([
                    "jk_id" => $item,
                ]);
            }
        }
        return response()->json([
            "ok" => true,
            "message" => "Sinkron jk_id selesai (fast join).",
        ]);
    }

    public static function fixPembayaran()
    {
        try {
            //code...
            $pembayaran = KeuanganPembayaran::join(
                "keuangan_tagihan",
                "keuangan_tagihan.id",
                "=",
                "keuangan_pembayaran.tagihan_id",
            )
                ->where("keuangan_pembayaran.tanggal", ">=", "2025-10-29")
                ->where(function ($q) {
                    $q->where(
                        "keuangan_tagihan.nama",
                        "LIKE",
                        "%daftar ulang%",
                    )->orWhere("keuangan_tagihan.nama", "LIKE", "%regist%");
                })
                ->select("keuangan_pembayaran.nim")
                ->distinct()
                ->pluck("nim");

            foreach ($pembayaran as $key => $nim) {
                Mahasiswa::updateStatusMahasiswa($nim, 18);
            }
            return response()->json([
                "status" => true,
                "nim" => $nim,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    public function tesInputWisuda()
    {
        $payload = [
            "nim" => "202385020016",
            "nama" => "Testing Wisudaaa",
            "nama_ayah" => "-",
            "judul" => "Testing Integrasi Wisuda",
            "tanggal_sidang" => now()->toDateString(),
            "tahun_id" => 1,
            "jenis_kelamin" => "Laki-Laki",
            "prodi" => "AS-HK",
            "ukuran_baju" => "M",
            "is_bayar" => Wisuda::TANPA_BAYAR,
        ];
        // dd($payload);

        $response = Wisuda::registrasi($payload);

        return response()->json([
            "status" => true,
            "message" => "Registrasi wisuda sudah dikirim.",
            "payload" => $payload,
            "response" => $response,
        ]);
    }

    public function syncPembayaranSP()
    {
        $summary = [
            "status" => true,
            "message" => "Sync pembayaran semester pendek selesai.",
            "processed" => 0,
            "migrated" => 0,
            "skipped_existing" => 0,
            "skipped_zero_amount" => 0,
            "skipped_no_krs_id" => 0,
            "skipped_no_nim" => 0,
            "skipped_no_tagihan" => 0,
            "errors" => [],
        ];

        $krsNimCache = [];

        KeuanganPembayaranSemesterPendek::orderBy("id")->chunkById(
            100,
            function ($pembayaranSemesterPendek) use (
                &$summary,
                &$krsNimCache,
            ) {
                $this->primeSemesterPendekNimCache(
                    $pembayaranSemesterPendek,
                    $krsNimCache,
                );

                foreach ($pembayaranSemesterPendek as $pembayaranSp) {
                    $summary["processed"]++;

                    try {
                        $krsId = (int) $pembayaranSp->krs_id;
                        if (!$krsId) {
                            $summary["skipped_no_krs_id"]++;
                            continue;
                        }

                        if ((float) $pembayaranSp->jumlah <= 0) {
                            $summary["skipped_zero_amount"]++;
                            continue;
                        }

                        $nim = $this->resolveSemesterPendekNim(
                            $pembayaranSp,
                            $krsNimCache,
                        );
                        if (!$nim) {
                            $summary["skipped_no_nim"]++;
                            continue;
                        }

                        $tagihan = $this->resolveSemesterPendekTagihan(
                            $nim,
                            $krsId,
                        );
                        if (!$tagihan) {
                            $summary["skipped_no_tagihan"]++;
                            continue;
                        }

                        if (
                            $this->findExistingMigratedPembayaran(
                                $pembayaranSp,
                                $tagihan,
                                $nim,
                            )
                        ) {
                            $summary["skipped_existing"]++;
                            continue;
                        }

                        DB::transaction(function () use (
                            $pembayaranSp,
                            $tagihan,
                            $nim,
                            &$summary,
                        ) {
                            $timestamps = [
                                "created_at" => $pembayaranSp->created_at,
                                "updated_at" => $pembayaranSp->updated_at,
                            ];

                            $pembayaran = KeuanganPembayaran::create([
                                "nomor" =>
                                    $pembayaranSp->nomor ?:
                                    Helper::generateNumber(),
                                "tanggal" => $pembayaranSp->tanggal,
                                "th_akademik_id" =>
                                    $tagihan->th_akademik_id ?:
                                    $pembayaranSp->th_akademik_id,
                                "tagihan_id" => $tagihan->id,
                                "nim" => $nim,
                                "smt" => 0,
                                "jml_sks" => 1,
                                "jumlah" => $pembayaranSp->jumlah,
                                "jk_id" => $pembayaranSp->jk_id,
                                "user_id" => $pembayaranSp->user_id,
                            ] + $timestamps);

                            if ($pembayaranSp->jenis_pembayaran_id) {
                                KeuanganJenisPembayaranDetail::firstOrCreate(
                                    [
                                        "pembayaran_id" => $pembayaran->id,
                                    ],
                                    [
                                        "jenis_pembayaran_id" =>
                                            $pembayaranSp->jenis_pembayaran_id,
                                    ] + $timestamps,
                                );
                            }

                            KeuanganNota::firstOrCreate(
                                [
                                    "pembayaran_id" => $pembayaran->id,
                                ],
                                [
                                    "nota" =>
                                        $pembayaranSp->nomor ?:
                                        $pembayaran->nomor,
                                ] + $timestamps,
                            );

                            $summary["migrated"]++;
                        });
                    } catch (\Throwable $th) {
                        $summary["status"] = false;
                        $summary["errors"][] = [
                            "id" => $pembayaranSp->id,
                            "krs_id" => $pembayaranSp->krs_id,
                            "nomor" => $pembayaranSp->nomor,
                            "message" => $th->getMessage(),
                        ];
                    }
                }
            },
        );

        return response()->json($summary);
    }

    private function primeSemesterPendekNimCache(
        $pembayaranSemesterPendek,
        &$krsNimCache,
    ) {
        $krsIds = collect($pembayaranSemesterPendek)
            ->pluck("krs_id")
            ->map(fn($krsId) => (int) $krsId)
            ->filter()
            ->unique()
            ->reject(fn($krsId) => array_key_exists($krsId, $krsNimCache))
            ->values();

        if ($krsIds->isEmpty()) {
            return;
        }

        try {
            $searchKrs = SemesterPendek::searchKrs($krsIds->all());
            $this->fillSemesterPendekNimCache($searchKrs, $krsNimCache);
        } catch (\Throwable $th) {
            //
        }
    }

    private function fillSemesterPendekNimCache($krsData, &$krsNimCache)
    {
        foreach ($this->normalizeList($krsData) as $item) {
            $krsId = (int) $this->pickValue($item, ["id", "krs_id"]);
            $nim = $this->getKrsNim($item);

            if ($krsId && $nim) {
                $krsNimCache[$krsId] = strtoupper(trim((string) $nim));
            }
        }
    }

    private function resolveSemesterPendekNim($pembayaranSp, &$krsNimCache)
    {
        $krsId = (int) $pembayaranSp->krs_id;

        if (array_key_exists($krsId, $krsNimCache)) {
            return $krsNimCache[$krsId];
        }

        $nim = null;

        try {
            $searchKrs = SemesterPendek::searchKrs([$krsId]);
            $krsItem = $this->findKrsItemById($searchKrs, $krsId);
            $nim = $this->getKrsNim($krsItem);

            if (!$nim) {
                $krsDetail = SemesterPendek::krsDetail($krsId);
                $nim = $this->getKrsNim($krsDetail);
            }
        } catch (\Throwable $th) {
            $nim = null;
        }

        if (!$nim) {
            $tagihan = $this->findTagihanSemesterPendekByKrsId($krsId);
            $nim = $tagihan ? $tagihan->nim : null;
        }

        $krsNimCache[$krsId] = $nim ? strtoupper(trim((string) $nim)) : null;

        return $krsNimCache[$krsId];
    }

    private function resolveSemesterPendekTagihan($nim, $krsId)
    {
        return $this->findTagihanSemesterPendekByKrsId($krsId, $nim);
    }

    private function findExistingMigratedPembayaran(
        $pembayaranSp,
        $tagihan,
        $nim,
    ) {
        $query = KeuanganPembayaran::where("tanggal", $pembayaranSp->tanggal)
            ->where("tagihan_id", $tagihan->id)
            ->where("nim", $nim)
            ->where("jumlah", $pembayaranSp->jumlah);

        if ($pembayaranSp->nomor) {
            $query->where("nomor", $pembayaranSp->nomor);
        }

        if ($pembayaranSp->created_at) {
            $query->where("created_at", $pembayaranSp->created_at);
        }

        return $query->first();
    }

    private function findTagihanSemesterPendekByKrsId($krsId, $nim = null)
    {
        $query = KeuanganTagihan::where(
            "nama",
            "LIKE",
            "SEMESTER PENDEK%",
        )->where(function ($q) use ($krsId) {
            $q->where("nama", "LIKE", "% - {$krsId}")
                ->orWhere("nama", "LIKE", "% - {$krsId} - %")
                ->orWhere("nama", "LIKE", "%-{$krsId}")
                ->orWhere("nama", "LIKE", "%-{$krsId}-%");
        });

        if ($nim) {
            $query->where("nim", strtoupper(trim((string) $nim)));
        } else {
            $query->whereNotNull("nim");
        }

        return $query->orderByDesc("id")->first();
    }

    private function findKrsItemById($data, $krsId)
    {
        $items = $this->normalizeList($data);
        $fallback = null;

        foreach ($items as $item) {
            $itemKrsId = (int) $this->pickValue($item, ["id", "krs_id"]);

            if (!$fallback) {
                $fallback = $item;
            }

            if ($itemKrsId === (int) $krsId) {
                return $item;
            }
        }

        return $fallback;
    }

    private function getKrsNim($data)
    {
        return $this->pickValue($data, [
            "nim",
            "mhs_nim",
            "mahasiswa.nim",
            "mhs.nim",
            "mst_mhs.nim",
        ]);
    }

    private function normalizeList($data)
    {
        if ($data instanceof \Illuminate\Support\Collection) {
            return $data->values()->all();
        }

        if (is_array($data)) {
            return array_values($data);
        }

        if (is_object($data)) {
            foreach (["data", "result", "results", "krs"] as $key) {
                $nested = $this->readValue($data, $key);
                if ($nested instanceof \Illuminate\Support\Collection) {
                    return $nested->values()->all();
                }
                if (is_array($nested)) {
                    return array_values($nested);
                }
            }

            if (
                !$this->pickValue($data, ["id", "krs_id", "nim", "mhs_nim"]) &&
                count(get_object_vars($data)) > 0
            ) {
                return array_values(get_object_vars($data));
            }

            return [$data];
        }

        return [];
    }

    private function pickValue($data, $paths)
    {
        foreach ($paths as $path) {
            $value = $this->readValue($data, $path);
            if ($value !== null && $value !== "") {
                return $value;
            }
        }

        return null;
    }

    private function readValue($data, $path)
    {
        $segments = explode(".", $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            if (is_object($current) && isset($current->{$segment})) {
                $current = $current->{$segment};
                continue;
            }

            return null;
        }

        return $current;
    }

    public function tesPembayaranPmb()
    {
        try {
            $input = request();

            // Mengambil URL dan API Key dari .env
            $url = rtrim(env("pmb_url"), "/") . "/simkeu/pembayaran";
            $apiKey = env("pmb_api_key");

            // Melakukan request GET menggunakan HTTP Client Laravel
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                "apikey" => $apiKey,
            ])->get($url, [
                "start_date" => $input->start_date,
                "end_date" => $input->end_date,
            ]);

            return response()->json([
                "status" => true,
                "http_code" => $response->status(),
                "data_dari_pmb" => $response->json(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => "Gagal fetch API: " . $th->getMessage(),
            ]);
        }
    }
}
