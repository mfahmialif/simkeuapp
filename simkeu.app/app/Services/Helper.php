<?php

namespace App\Services;

use App\Models\ThAkademik;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use App\Models\KeuanganSetting;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use App\Models\KeuanganPembayaranTambahan;

class Helper
{
    public static function penyebut($nilai)
    {
        $nilai = abs($nilai);
        $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        $temp = "";
        if ($nilai < 12) {
            $temp = " " . $huruf[$nilai];
        } else if ($nilai < 20) {
            $temp = Helper::penyebut($nilai - 10) . " belas";
        } else if ($nilai < 100) {
            $temp = Helper::penyebut($nilai / 10) . " puluh" . Helper::penyebut($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " seratus" . Helper::penyebut($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = Helper::penyebut($nilai / 100) . " ratus" . Helper::penyebut($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " seribu" . Helper::penyebut($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = Helper::penyebut($nilai / 1000) . "ribu" . Helper::penyebut($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = Helper::penyebut($nilai / 1000000) . " juta" . Helper::penyebut($nilai % 1000000);
        } else if ($nilai < 1000000000000) {
            $temp = Helper::penyebut($nilai / 1000000000) . " milyar" . Helper::penyebut(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = Helper::penyebut($nilai / 1000000000000) . " trilyun" . Helper::penyebut(fmod($nilai, 1000000000000));
        }
        return $temp;
    }

    public static function terbilang($nilai)
    {
        if ($nilai < 0) {
            $hasil = "minus " . trim(Helper::penyebut($nilai));
        } else {
            $hasil = trim(Helper::penyebut($nilai));
        }
        return $hasil;
    }

    public static function formatNumber($angka)
    {
        return number_format($angka, 0, ",", ".");
    }

    public static function semester($th_masuk, $npm = null)
    {
        // $npm = null;
        $thAktif = ThAkademik::Aktif()->first();
        $smt = $thAktif->semester;
        $cuti = 0; //App\kemahasiswaan\CutiMahasiswa::where('npm',$npm)->count();

        $thA = substr($thAktif->kode, 0, 4);
        $thM = substr($th_masuk, 0, 4);

        $smtM = substr($thAktif->kode, 4, 1);

        if ((int) $thA >= $thM) {
            $th = $thA - $thM;
            $hasil = ($th * 2) + $smtM;
        } else {
            $hasil = 0; //$thAktif->kode.'-'.$th_masuk;
        }

        return $hasil;
    }

    public static function generateNumber()
    {
        $th = date('Y');
        $th .= uniqid();
        // $row = KeuanganPembayaran::select(DB::raw('right(nomor,7) as nomor_akhir'))
        //     ->whereYear('tanggal', $th)->orderBy('nomor', 'DESC')->limit(1)->first();

        //     $uniqid = uniqid();
        // if (isset($row)) {
        //     $akhir = (int) $row->nomor_akhir + 1;
        //     $return = $th . sprintf("%07s$uniqid", $akhir);
        // } else {
        //     $return = $th . '0000001';
        // }
        return $th;
    }

    public static function generateNotaBanat($tanggal)
    {
        $notaStart = KeuanganSetting::where('slug', 'nota')->first();
        $number = sprintf("%05d", $notaStart->value);
        $notaBaru = $notaStart->value + 1;
        $notaStart->value = $notaBaru;
        $notaStart->save();

        $cek = KeuanganNota::where('nota', "$tanggal-$number")->first();
        if ($cek) {
            return [
                'status' => true,
                'number' => $number
            ];
        } else {
            return [
                'status' => false,
                'number' => $number
            ];
        }
    }

    // $d = date('Y-m-d', strtotime($tanggal));
    // $tanggalNota = date('dmy', strtotime($tanggal));
    // $nim = Mahasiswa::all(null, null, null, null, null, [
    //     ['mst_mhs.jk_id', '=', $jkId]
    // ], ['nim']);

    // $nimList = $nim; // hasil API: array of NIM (sudah di-pluck & terfilter jk)

    // $query = KeuanganPembayaran::query()
    //     ->leftJoin('keuangan_nota as kn', 'kn.pembayaran_id', '=', 'keuangan_pembayaran.id')
    //     ->whereDate('keuangan_pembayaran.tanggal', $d);

    // $query->where(function ($q) use ($nimList) {
    //     foreach (array_chunk($nimList, 1000) as $chunk) {
    //         $q->orWhereIn('keuangan_pembayaran.nim', $chunk);
    //     }
    // });

    // $transaksiTagihan = $query->orderByDesc('kn.nota')->first();
    public static function generateNota($tanggal, $jkId)
    {

        $d = date('Y-m-d', strtotime($tanggal));
        $tanggalNota = date('dmy', strtotime($tanggal));
        $notas = KeuanganPembayaran::leftJoin('keuangan_nota as kn', 'kn.pembayaran_id', 'keuangan_pembayaran.id')
            ->where('keuangan_pembayaran.jk_id', $jkId)
            ->whereDate('tanggal', $d)
            ->where('kn.nota', 'like', "$tanggalNota-%")
            ->pluck('kn.nota');
        $jk = 0;
        if ($jkId == 9) {
            // $notaBanat = Helper::generateNotaBanat($tanggalNota);
            // while ($notaBanat['status']) {
            //     $notaBanat = Helper::generateNotaBanat($tanggalNota);
            // }
            // $number = $notaBanat['number'];
            $jk = "P";
        }

        if ($jkId == 8) {
            $jk = "L";
        }
        $number = Helper::nextNotaNumber($notas);

        $uniq = Helper::randomNumber();
        return "$tanggalNota-$number-$jk-$uniq";
    }
    public static function generateNumberTambahan()
    {
        $th = date('Y');
        $th .= uniqid();
        // $row = KeuanganPembayaran::select(DB::raw('right(nomor,7) as nomor_akhir'))
        //     ->whereYear('tanggal', $th)->orderBy('nomor', 'DESC')->limit(1)->first();

        //     $uniqid = uniqid();
        // if (isset($row)) {
        //     $akhir = (int) $row->nomor_akhir + 1;
        //     $return = $th . sprintf("%07s$uniqid", $akhir);
        // } else {
        //     $return = $th . '0000001';
        // }
        return $th;
    }

    public static function generateNotaTambahan($tanggal)
    {
        $d = date('Y-m-d', strtotime($tanggal));
        $tanggalNota = date('dmy', strtotime($tanggal));
        $notas = KeuanganPembayaranTambahan::whereDate('tanggal', $d)
            ->where('nota', 'like', "$tanggalNota-%")
            ->pluck('nota');

        $number = Helper::nextNotaNumber($notas);

        $uniq = Helper::randomNumber();
        return "$tanggalNota-$number-$uniq";
    }

    public static function randomNumber()
    {
        // Generate a unique 4-digit value
        $uniqueValue = substr(uniqid(), -4);

        // Ensure it's exactly 4 digits
        $uniqueValue = str_pad($uniqueValue, 4, '0', STR_PAD_LEFT);
        $uniqueValue = strtoupper($uniqueValue);
        return $uniqueValue;
    }
    /**
     * Generate nota khusus Semester Pendek (tabel keuangan_pembayaran_semester_pendek)
     */
    public static function generateNotaSp($tanggal, $jkId)
    {
        $d = date('Y-m-d', strtotime($tanggal));
        $tanggalNota = date('dmy', strtotime($tanggal));

        $notas = \App\Models\KeuanganPembayaranSemesterPendek::where('jk_id', $jkId)
            ->whereDate('tanggal', $d)
            ->where('nomor', 'like', "%$tanggalNota-%")
            ->pluck('nomor');

        $jk = 0;
        if ($jkId == 9) {
            $jk = "P";
        }
        if ($jkId == 8) {
            $jk = "L";
        }

        $number = Helper::nextNotaNumber($notas);

        $uniq = Helper::randomNumber();
        return "$tanggalNota-$number-$jk-$uniq";
    }

    private static function nextNotaNumber(iterable $notas): string
    {
        $max = 0;

        foreach ($notas as $nota) {
            if (preg_match('/^(?:SP-)?\d{6}-(\d{5})(?:-|$)/', (string) $nota, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return sprintf("%05d", $max + 1);
    }

    /**
     * return id jenis_kelamin dari tabel ref
     */
    public static function getJenisKelaminUser()
    {
        $putra = (object) [
            "id" => 8,
            "nama" => "Laki-Laki",
            "kategori" => "Putra",
            "kode" => "L",
        ];
        $putri = (object) [
            "id" => 9,
            "nama" => "Perempuan",
            "kategori" => "Putri",
            "kode" => "P",
        ];
        $semua = (object) [
            "id" => "%",
            "nama" => "%",
            "kategori" => "%",
            "kode" => "%",
        ];

        $user = auth()->user();
        $jenisKelamin = $user->jenis_kelamin != null ? $user->jenis_kelamin : 'Laki-laki';

        if ($user->role_id == 1) { // admin bisa switch scope sistem dari navbar
            $scope = strtolower((string) (
                request()->header('X-Simkeu-Jk-Scope')
                ?? request()->input('jk_scope')
                ?? request()->input('jenis_kelamin_scope')
                ?? 'semua'
            ));

            if (in_array($scope, ['putra', 'laki-laki', 'laki', 'l', '8'])) {
                return $putra;
            }

            if (in_array($scope, ['putri', 'perempuan', 'p', '9'])) {
                return $putri;
            }

            return $semua;
        }

        if ($jenisKelamin == 'Perempuan') {
            return $putri;
        }

        return $putra;
    }

    public static function activeGenderScope(): ?string
    {
        $scope = self::getJenisKelaminUser();

        return match ($scope->kategori) {
            'Putra' => 'Laki-laki',
            'Putri' => 'Perempuan',
            default => null,
        };
    }

    public static function pengeluaranPetugasRoles(?string $module): array
    {
        $moduleKey = str_replace('-', '_', strtolower((string) $module));

        $roles = [
            'umum' => self::pengeluaranUmumPetugasRoles(),
            'rumah_tangga' => ['rumahtangga'],
            'sarana_prasarana' => ['sarpras'],
            'sarpras' => ['sarpras'],
            'transportasi' => ['transportasi'],
            'dosen' => ['barokahdosen_tatapmuka'],
            'tatap_muka' => ['barokahdosen_tatapmuka'],
            'dosen_tatapmuka' => ['barokahdosen_tatapmuka'],
            'dosen_tatap_muka' => ['barokahdosen_tatapmuka'],
            'kegiatan' => ['barokahdosen_kegiatan'],
            'dosen_kegiatan' => ['barokahdosen_kegiatan'],
            'bulanan' => ['barokahdosen_bulanan'],
            'dosen_bulanan' => ['barokahdosen_bulanan'],
            'rab' => [
                'rumahtangga',
                'sarpras',
                'transportasi',
                'barokahdosen_tatapmuka',
                'barokahdosen_kegiatan',
                'barokahdosen_bulanan',
            ],
        ];

        return $roles[$moduleKey] ?? $roles['rab'];
    }

    public static function pengeluaranUmumPetugasRoles(): array
    {
        return [
            'kabag_pengeluaran',
            'barokahdosen_bulanan',
            'barokahdosen_tatapmuka',
            'barokahdosen_kegiatan',
            'sarpras',
            'rumahtangga',
            'transportasi',
        ];
    }

    public static function applyGenderScope($query, string $column = 'jenis_kelamin'): void
    {
        $gender = self::activeGenderScope();

        if ($gender !== null) {
            $query->where($column, $gender);
        }
    }

    public static function applyRelatedGenderScope(
        $query,
        string $foreignKeyColumn,
        string $relatedTable,
        string $relatedKeyColumn = 'id',
        string $relatedGenderColumn = 'jenis_kelamin'
    ): void {
        $gender = self::activeGenderScope();

        if ($gender === null) {
            return;
        }

        $query->whereExists(function ($relatedQuery) use (
            $foreignKeyColumn,
            $relatedTable,
            $relatedKeyColumn,
            $relatedGenderColumn,
            $gender
        ) {
            $relatedQuery
                ->selectRaw('1')
                ->from($relatedTable)
                ->whereColumn(
                    "{$relatedTable}.{$relatedKeyColumn}",
                    $foreignKeyColumn
                )
                ->where("{$relatedTable}.{$relatedGenderColumn}", $gender);
        });
    }

    public static function applyNullableRelatedGenderScope(
        $query,
        string $foreignKeyColumn,
        string $relatedTable,
        string $relatedKeyColumn = 'id',
        string $relatedGenderColumn = 'jenis_kelamin'
    ): void {
        $gender = self::activeGenderScope();

        if ($gender === null) {
            return;
        }

        $query->where(function ($scopeQuery) use (
            $foreignKeyColumn,
            $relatedTable,
            $relatedKeyColumn,
            $relatedGenderColumn,
            $gender
        ) {
            $scopeQuery
                ->whereNull($foreignKeyColumn)
                ->orWhereExists(function ($relatedQuery) use (
                    $foreignKeyColumn,
                    $relatedTable,
                    $relatedKeyColumn,
                    $relatedGenderColumn,
                    $gender
                ) {
                    $relatedQuery
                        ->selectRaw('1')
                        ->from($relatedTable)
                        ->whereColumn(
                            "{$relatedTable}.{$relatedKeyColumn}",
                            $foreignKeyColumn
                        )
                        ->where("{$relatedTable}.{$relatedGenderColumn}", $gender);
                });
        });
    }

    public static function genderMatchesActiveScope(?string $gender): bool
    {
        $activeGender = self::activeGenderScope();

        return $activeGender === null
            || strcasecmp(trim((string) $gender), $activeGender) === 0;
    }

    public static function activeGenderExpensePetugasIds(): array
    {
        $gender = self::activeGenderScope();

        if ($gender === null) {
            return [];
        }

        $cacheKey = 'simkeu_expense_petugas_ids_'.md5($gender);
        $request = app()->bound('request') ? request() : null;

        if ($request && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $ids = DB::table('users')
            ->join('role', 'role.id', '=', 'users.role_id')
            ->where('users.jenis_kelamin', $gender)
            ->whereIn('role.name', self::pengeluaranPetugasRoles('rab'))
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($request) {
            $request->attributes->set($cacheKey, $ids);
        }

        return $ids;
    }

    public static function applyExpenseGenderScope(
        $query,
        string $table,
        ?string $alias = null
    ): void {
        if (self::activeGenderScope() === null) {
            return;
        }

        $prefix = $alias ?? $table;

        if (Schema::hasColumn($table, 'petugas_id')) {
            $petugasIds = self::activeGenderExpensePetugasIds();

            if ($petugasIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIntegerInRaw("{$prefix}.petugas_id", $petugasIds);
        }
    }

    public static function isAdmin()
    {
        if (auth()->user()->level_id == 1) {
            return true;
        } else {
            return false;
        }
    }

    public static function tempMahasiswaJk()
    {
        $nimList = collect(Mahasiswa::all(null, null, null, null, null, [
            ['mst_mhs.jk_id', '=', Helper::getJenisKelaminUser()->id]
        ]))
            ->pluck('nim')        // pastikan jadi list NIM saja
            ->filter()            // buang null/kosong
            ->unique()
            ->values();

        $collation = 'utf8mb4_unicode_ci';

        DB::statement("
            CREATE TEMPORARY TABLE tmp_nims (
                nim VARCHAR(32) COLLATE {$collation} PRIMARY KEY
            ) ENGINE=Memory
            DEFAULT CHARSET=utf8mb4
            COLLATE={$collation}
        ");
        collect($nimList)->chunk(1000)->each(function ($chunk) {
            DB::table('tmp_nims')->insert($chunk->map(fn($n) => ['nim' => $n])->all());
        });
    
    }
    public static function whereMahasiswaJkTemp($query, $column)
    {
        $nimList = collect(Mahasiswa::all(null, null, null, null, null, [
            ['mst_mhs.jk_id', '=', Helper::getJenisKelaminUser()->id]
        ]))
            ->pluck('nim')        // pastikan jadi list NIM saja
            ->filter()            // buang null/kosong
            ->unique()
            ->values();

        $collation = 'utf8mb4_unicode_ci';

        DB::statement("
            CREATE TEMPORARY TABLE tmp_nims (
                nim VARCHAR(32) COLLATE {$collation} PRIMARY KEY
            ) ENGINE=Memory
            DEFAULT CHARSET=utf8mb4
            COLLATE={$collation}
        ");
        collect($nimList)->chunk(1000)->each(function ($chunk) {
            DB::table('tmp_nims')->insert($chunk->map(fn($n) => ['nim' => $n])->all());
        });

        $query->join('tmp_nims', 'tmp_nims.nim', '=', $column);
        return $query;
    }

    public static function whereMahasiswaJkChunk($query, $column)
    {
        $nimList = collect(Mahasiswa::all(null, null, null, null, null, [
            ['mst_mhs.jk_id', '=', Helper::getJenisKelaminUser()->id]
        ]))
            ->pluck('nim')        // pastikan jadi list NIM saja
            ->filter()            // buang null/kosong
            ->unique()
            ->values()
            ->toArray();

        if (!empty($nimList)) {
            $chunks = array_chunk($nimList, 1000);
            $query->where(function ($q) use ($chunks, $column) {
                foreach ($chunks as $chunk) {
                    $q->orWhereIn($column, $chunk);
                }
            });
        }

        return $query;
    }
}
