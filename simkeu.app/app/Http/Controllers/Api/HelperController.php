<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Ref;
use App\Models\Prodi;
use App\Models\ThAkademik;
use App\Models\FormSchadule;
use App\Models\KeuanganNota;
use App\Models\KeuanganTagihan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\KeuanganDispensasi;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganJenisPembayaran;
use App\Services\Helper as SimkeuHelper;
use App\Services\Mahasiswa;
use App\Services\TagihanMahasiswa;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\KeuanganJenisPembayaranDetail;
use Illuminate\Support\Str;

class HelperController extends Controller
{
    /**
     * Get ENUM values from a specific table column.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEnumValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table'         => 'required|string',
            'column'        => 'required|string',
            'delete_column' => 'nullable|array',
        ]);

        $table        = $validated['table'];
        $column       = $validated['column'];
        $deleteColumn = $validated['delete_column'] ?? [];

        $columnInfo = DB::select("SHOW COLUMNS FROM `$table` WHERE Field = ?", [$column]);

        if (empty($columnInfo)) {
            return response()->json([
                'message' => 'Column not found.',
            ], 404);
        }

        $type = $columnInfo[0]->Type;

        if (! preg_match('/^enum\((.*)\)$/', $type, $matches)) {
            return response()->json([
                'message' => 'The specified column is not an ENUM type.',
            ], 400);
        }

        $enum = array_map(fn($value) => trim($value, "'"), explode(',', $matches[1]));

        if (! empty($deleteColumn)) {
            $enum = array_values(array_filter($enum, fn($val) => ! in_array($val, $deleteColumn)));
        }

        return response()->json($enum);
    }

    public function createTagihanPerorangan(Request $request): JsonResponse
    {
        try {
            if ($request->filled('form_schedule_kode') && ! $request->filled('form_schadule_kode')) {
                $request->merge(['form_schadule_kode' => $request->input('form_schedule_kode')]);
            }

            $validated = $request->validate([
                'nim'                 => 'required|string|max:255',
                'th_akademik_kode'    => 'required|string|max:255',
                'th_angkatan_kode'    => 'required|string|max:255',
                'prodi_alias'         => 'required|string|max:255',
                'double_degree'       => 'nullable|integer',
                'form_schadule_kode'  => 'required|string|max:255',
                'nama'                => 'required|string|max:255',
                'jumlah'              => 'required|numeric|min:0',
            ]);

            $refs = [
                'th_akademik_kode' => ThAkademik::where('kode', trim($validated['th_akademik_kode']))->first(),
                'th_angkatan_kode' => ThAkademik::where('kode', trim($validated['th_angkatan_kode']))->first(),
                'prodi_alias' => Prodi::where('alias', trim($validated['prodi_alias']))->first(),
                'kelas_id' => Ref::where('table', 'Kelas')->find(6),
                'form_schadule_kode' => FormSchadule::where('kode', trim($validated['form_schadule_kode']))->first(),
            ];

            $refLabels = [
                'th_akademik_kode' => 'tahun akademik',
                'th_angkatan_kode' => 'tahun angkatan',
                'prodi_alias' => 'prodi',
                'kelas_id' => 'kelas',
                'form_schadule_kode' => 'form schadule',
            ];

            $missingRefs = collect($refs)
                ->filter(fn($ref) => ! $ref)
                ->map(fn($_, $field) => [
                    $field === 'kelas_id'
                        ? 'Kelas default ID 6 tidak ditemukan.'
                        : "Kode {$validated[$field]} untuk {$refLabels[$field]} tidak ditemukan.",
                ])
                ->toArray();

            if (! empty($missingRefs)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Kode referensi tidak ditemukan.',
                    'errors'  => $missingRefs,
                ], 422);
            }

            $nim = strtoupper(trim($validated['nim']));
            $doubleDegree = $validated['double_degree'] ?? null;

            $duplicateQuery = KeuanganTagihan::where('nim', $nim)
                ->where('th_akademik_id', $refs['th_akademik_kode']->id)
                ->where('th_angkatan_id', $refs['th_angkatan_kode']->id)
                ->where('prodi_id', $refs['prodi_alias']->id)
                ->where('kelas_id', $refs['kelas_id']->id)
                ->where('form_schadule_id', $refs['form_schadule_kode']->id)
                ->where('nama', $validated['nama']);

            if ($doubleDegree === null) {
                $duplicateQuery->whereNull('double_degree');
            } else {
                $duplicateQuery->where('double_degree', $doubleDegree);
            }

            $kode = $refs['th_akademik_kode']->id
                . $refs['th_angkatan_kode']->id
                . $refs['prodi_alias']->id
                . $refs['kelas_id']->id
                . $refs['form_schadule_kode']->id;

            $duplicate = $duplicateQuery->first();

            if ($duplicate) {
                $duplicate->update([
                    'kode'    => $kode,
                    'jumlah'  => $validated['jumlah'],
                    'x_sks'   => 'Y',
                    'user_id' => null,
                ]);

                return response()->json([
                    'status'  => true,
                    'message' => 'Tagihan Perorangan updated successfully.',
                    'data'    => $duplicate->load('th_akademik', 'th_angkatan', 'prodi', 'form_schadule', 'kelas'),
                ]);
            }

            $data = KeuanganTagihan::create([
                'nim'              => $nim,
                'th_akademik_id'   => $refs['th_akademik_kode']->id,
                'th_angkatan_id'   => $refs['th_angkatan_kode']->id,
                'prodi_id'         => $refs['prodi_alias']->id,
                'double_degree'    => $doubleDegree,
                'kelas_id'         => $refs['kelas_id']->id,
                'form_schadule_id' => $refs['form_schadule_kode']->id,
                'kode'             => $kode,
                'nama'             => $validated['nama'],
                'jumlah'           => $validated['jumlah'],
                'x_sks'            => 'Y',
                'user_id'          => null,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Tagihan Perorangan created successfully.',
                'data'    => $data->load('th_akademik', 'th_angkatan', 'prodi', 'form_schadule', 'kelas'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    public function deleteTagihanPerorangan(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nim'  => 'required|string|max:255',
                'nama' => 'required|string|max:255',
            ]);

            $nim = strtoupper(trim($validated['nim']));
            $nama = trim($validated['nama']);

            if (blank($nim) || blank($nama)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Filter nim dan nama wajib diisi.',
                ], 422);
            }

            $query = KeuanganTagihan::whereNotNull('nim')
                ->where('nim', '!=', '')
                ->where('nim', $nim)
                ->where('nama', $nama);

            $tagihan = $query->get();

            if ($tagihan->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Tagihan Perorangan tidak ditemukan.',
                ], 404);
            }

            $ids = $tagihan->pluck('id')->values();
            $deletedCount = 0;

            DB::transaction(function () use ($ids, &$deletedCount) {
                $deletedCount = KeuanganTagihan::whereIn('id', $ids)->delete();
            });

            return response()->json([
                'status'        => true,
                'message'       => 'Tagihan Perorangan deleted successfully.',
                'deleted_count' => $deletedCount,
                'deleted_ids'   => $ids,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    /**
     * Contoh curl:
     * curl -X POST "{BASE_URL}/api/helper/pembayaran-wisuda" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "apikey: ISI_API_KEY_KAMU" \
     *   -d '{
     *     "nim": "2021001001",
     *     "jenis_pembayaran": "transfer",
     *     "th_akademik_kode": "20252",
     *     "tanggal": "2026-05-30 12:00:00",
     *     "jumlah": 500000
     *   }'
     */
    public function createPembayaranWisuda(Request $request): JsonResponse
    {
        try {
            $dataValidated = $request->validate([
                'nim'              => 'required|string|max:255',
                'jenis_pembayaran' => 'required|string|max:255',
                'th_akademik_kode' => 'required|string|max:255',
                'tanggal'          => 'required|date',
                'jumlah'           => 'nullable|numeric|min:0',
            ]);

            $nim = strtoupper(trim($dataValidated['nim']));
            $tanggalTransaksi = Carbon::parse($dataValidated['tanggal']);
            $tanggal = $tanggalTransaksi->toDateTimeString();
            $createdAt = $tanggalTransaksi->copy();

            $mahasiswa = $this->resolveMahasiswa($nim);
            if (! $mahasiswa) {
                return response()->json([
                    'status'  => false,
                    'code'    => 404,
                    'message' => 'Mahasiswa tidak ditemukan.',
                    'data'    => [
                        'nim' => $nim,
                    ],
                ], 404);
            }

            $thAkademik = ThAkademik::where('kode', trim($dataValidated['th_akademik_kode']))->first();
            if (! $thAkademik) {
                return response()->json([
                    'status'  => false,
                    'code'    => 404,
                    'message' => 'Tahun akademik tidak ditemukan.',
                ], 404);
            }

            $semester = $this->resolveSemesterMahasiswa($nim, $thAkademik->kode);
            if ($semester === null) {
                return response()->json([
                    'status'  => false,
                    'code'    => 422,
                    'message' => 'Semester mahasiswa tidak bisa dihitung dari NIM dan tahun akademik.',
                ], 422);
            }

            $jenisKelamin = $this->resolveJenisKelaminMahasiswa($mahasiswa);
            if (! $jenisKelamin) {
                return response()->json([
                    'status'  => false,
                    'code'    => 422,
                    'message' => 'Jenis kelamin mahasiswa tidak ditemukan dari data mahasiswa.',
                ], 422);
            }

            $tagihan = $this->resolveTagihanWisuda($nim);
            if (! $tagihan) {
                return response()->json([
                    'status'  => false,
                    'code'    => 404,
                    'message' => 'Tagihan wisuda mahasiswa tidak ditemukan pada daftar tagihan.',
                ], 404);
            }

            $jenisPembayaran = $this->resolveJenisPembayaranWisuda(
                $dataValidated['jenis_pembayaran'],
                $jenisKelamin['kategori']
            );

            if (! $jenisPembayaran) {
                return response()->json([
                    'status'  => false,
                    'code'    => 404,
                    'message' => 'Jenis pembayaran tidak ditemukan untuk kategori ' . $jenisKelamin['kategori'] . '.',
                ], 404);
            }

            $jumlahInput = array_key_exists('jumlah', $dataValidated)
                ? (float) $dataValidated['jumlah']
                : null;

            $pembayaranExisting = $this->resolvePembayaranWisudaExisting(
                $nim,
                $tagihan,
                $tanggalTransaksi,
                $jumlahInput
            );

            if ($pembayaranExisting->isNotEmpty()) {
                $jumlah = $jumlahInput ?? (float) $pembayaranExisting->first()->jumlah;

                if ($jumlah <= 0) {
                    return response()->json([
                        'status'  => false,
                        'code'    => 422,
                        'message' => 'Jumlah pembayaran harus lebih dari 0.',
                    ], 422);
                }

                $pembayaran = DB::transaction(function () use (
                    $jenisKelamin,
                    $jenisPembayaran,
                    $jumlah,
                    $nim,
                    $pembayaranExisting,
                    $semester,
                    $tagihan,
                    $thAkademik,
                    $tanggal,
                    $createdAt
                ) {
                    $pembayaranIds = $pembayaranExisting->pluck('id');

                    KeuanganJenisPembayaranDetail::whereIn('pembayaran_id', $pembayaranIds)->delete();

                    foreach ($pembayaranExisting as $pembayaran) {
                        $pembayaran->update([
                            'th_akademik_id' => $thAkademik->id,
                            'tanggal'        => $tanggal,
                            'tagihan_id'     => $tagihan->id,
                            'nim'            => $nim,
                            'jumlah'         => $jumlah,
                            'smt'            => $semester,
                            'jml_sks'        => 1,
                            'jk_id'          => $jenisKelamin['id'],
                            'user_id'        => 8,
                        ]);

                        KeuanganJenisPembayaranDetail::create([
                            'jenis_pembayaran_id' => $jenisPembayaran->id,
                            'pembayaran_id'       => $pembayaran->id,
                            'created_at'          => $createdAt,
                            'updated_at'          => $createdAt,
                        ]);
                    }

                    return $pembayaranExisting->first()->refresh();
                });

                return response()->json([
                    'status'  => true,
                    'code'    => 200,
                    'updated' => true,
                    'message' => 'Pembayaran wisuda sudah ada, data berhasil diperbarui.',
                    'data'    => [
                        'id'                    => $pembayaran->id,
                        'updated_count'         => $pembayaranExisting->count(),
                        'nim'                   => $nim,
                        'mahasiswa_nama'        => data_get($mahasiswa, 'nama'),
                        'th_akademik_id'        => $thAkademik->id,
                        'th_akademik_kode'      => $thAkademik->kode,
                        'tagihan_id'            => $tagihan->id,
                        'tagihan_nama'          => $tagihan->nama,
                        'jumlah'                => $pembayaran->jumlah,
                        'semester'              => $semester,
                        'created_at'            => optional($pembayaran->created_at)->toDateTimeString(),
                        'jenis_kelamin'         => $jenisKelamin['nama'],
                        'jenis_pembayaran_id'   => $jenisPembayaran->id,
                        'jenis_pembayaran_nama' => $jenisPembayaran->nama,
                    ],
                ]);
            }

            $sisaTagihan = data_get($tagihan, 'sisa');
            if ($sisaTagihan === null) {
                $sisaTagihan = TagihanMahasiswa::getSisaTagihan($nim, $tagihan->id);
            }
            $sisaTagihan = (float) $sisaTagihan;

            if ($sisaTagihan <= 0) {
                return response()->json([
                    'status'  => false,
                    'code'    => 422,
                    'message' => 'Tagihan wisuda sudah lunas.',
                ], 422);
            }

            $jumlah = array_key_exists('jumlah', $dataValidated)
                ? (float) $dataValidated['jumlah']
                : $sisaTagihan;

            if ($jumlah <= 0) {
                return response()->json([
                    'status'  => false,
                    'code'    => 422,
                    'message' => 'Jumlah pembayaran harus lebih dari 0.',
                ], 422);
            }

            if ($jumlah > $sisaTagihan) {
                return response()->json([
                    'status'  => false,
                    'code'    => 422,
                    'message' => 'Jumlah pembayaran melebihi sisa tagihan wisuda.',
                    'data'    => [
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ], 422);
            }

            $pembayaran = DB::transaction(function () use (
                $jenisKelamin,
                $jenisPembayaran,
                $jumlah,
                $nim,
                $semester,
                $tagihan,
                $thAkademik,
                $tanggal,
                $createdAt
            ) {
                $pembayaran = KeuanganPembayaran::create([
                    'th_akademik_id' => $thAkademik->id,
                    'nomor'          => SimkeuHelper::generateNumber(),
                    'tanggal'        => $tanggal,
                    'tagihan_id'     => $tagihan->id,
                    'nim'            => $nim,
                    'jumlah'         => $jumlah,
                    'smt'            => $semester,
                    'jml_sks'        => 1,
                    'jk_id'          => $jenisKelamin['id'],
                    'user_id'        => 8,
                    'created_at'     => $createdAt,
                    'updated_at'     => $createdAt,
                ]);

                KeuanganJenisPembayaranDetail::create([
                    'jenis_pembayaran_id' => $jenisPembayaran->id,
                    'pembayaran_id'       => $pembayaran->id,
                    'created_at'          => $createdAt,
                    'updated_at'          => $createdAt,
                ]);

                KeuanganNota::create([
                    'nota'          => SimkeuHelper::generateNota($tanggal, $jenisKelamin['id']),
                    'pembayaran_id' => $pembayaran->id,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
                ]);

                return $pembayaran;
            });

            return response()->json([
                'status'  => true,
                'code'    => 201,
                'message' => 'Pembayaran wisuda berhasil disimpan.',
                'data'    => [
                    'id'                    => $pembayaran->id,
                    'nim'                   => $nim,
                    'mahasiswa_nama'        => data_get($mahasiswa, 'nama'),
                    'th_akademik_id'        => $thAkademik->id,
                    'th_akademik_kode'      => $thAkademik->kode,
                    'tagihan_id'            => $tagihan->id,
                    'tagihan_nama'          => $tagihan->nama,
                    'jumlah'                => $pembayaran->jumlah,
                    'semester'              => $semester,
                    'created_at'            => optional($pembayaran->created_at)->toDateTimeString(),
                    'jenis_kelamin'         => $jenisKelamin['nama'],
                    'jenis_pembayaran_id'   => $jenisPembayaran->id,
                    'jenis_pembayaran_nama' => $jenisPembayaran->nama,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    /**
     * Contoh curl:
     * curl -X GET "{BASE_URL}/api/helper/pembayaran-wisuda?nim=2021001001" \
     *   -H "Accept: application/json" \
     *   -H "apikey: ISI_API_KEY_KAMU"
     */
    public function getDataPembayaranWisuda(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nim' => 'required|string|max:255',
            ]);

            $nim = strtoupper(trim($validated['nim']));

            $pembayaran = KeuanganPembayaran::with([
                'tagihan',
                'th_akademik',
                'keuanganNota',
                'jenisPembayaranDetail.jenisPembayaran',
            ])
                ->whereRaw('UPPER(nim) = ?', [$nim])
                ->whereHas('tagihan', function ($query) {
                    $query->where('nama', 'LIKE', '%wisuda%');
                })
                ->orderByDesc('tanggal')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Data pembayaran wisuda berhasil diambil.',
                'data' => $pembayaran->map(function (KeuanganPembayaran $item) {
                    return [
                        'id' => $item->id,
                        'nim' => $item->nim,
                        'nomor' => $item->nomor,
                        'tanggal' => $item->tanggal,
                        'jumlah' => $item->jumlah,
                        'semester' => $item->smt,
                        'created_at' => optional($item->created_at)->toDateTimeString(),
                        'updated_at' => optional($item->updated_at)->toDateTimeString(),
                        'th_akademik' => [
                            'id' => $item->th_akademik?->id,
                            'kode' => $item->th_akademik?->kode,
                            'nama' => $item->th_akademik?->nama,
                            'semester' => $item->th_akademik?->semester,
                        ],
                        'tagihan' => [
                            'id' => $item->tagihan?->id,
                            'nama' => $item->tagihan?->nama,
                            'jumlah' => $item->tagihan?->jumlah,
                        ],
                        'jenis_pembayaran' => [
                            'id' => $item->jenisPembayaranDetail?->jenisPembayaran?->id,
                            'nama' => $item->jenisPembayaranDetail?->jenisPembayaran?->nama,
                        ],
                        'nota' => $item->keuanganNota?->nota,
                    ];
                })->values(),
                'summary' => [
                    'nim' => $nim,
                    'total_data' => $pembayaran->count(),
                    'total_jumlah' => $pembayaran->sum('jumlah'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    private function resolveTagihanWisuda(string $nim): ?KeuanganTagihan
    {
        $dataTagihan = TagihanMahasiswa::tagihan($nim);
        $listTagihan = $dataTagihan['list_tagihan'] ?? [];

        return collect($listTagihan)
            ->first(function ($tagihan) {
                return stripos((string) data_get($tagihan, 'nama'), 'wisuda') !== false;
            });
    }

    private function resolvePembayaranWisudaExisting(string $nim, KeuanganTagihan $tagihan, Carbon $tanggalTransaksi, ?float $jumlah)
    {
        $tanggal = $tanggalTransaksi->toDateTimeString();
        $tanggalDate = $tanggalTransaksi->toDateString();

        return KeuanganPembayaran::where('nim', $nim)
            ->where(function ($query) use ($tagihan, $jumlah) {
                $query->where('tagihan_id', $tagihan->id)
                    ->orWhereHas('tagihan', function ($tagihanQuery) {
                        $tagihanQuery->where('nama', 'LIKE', '%wisuda%');
                    });

                if ($jumlah !== null) {
                    $query->orWhere('jumlah', $jumlah);
                }
            })
            ->where(function ($query) use ($tanggal, $tanggalDate) {
                $query->where('tanggal', $tanggal)
                    ->orWhere('created_at', $tanggal)
                    ->orWhereDate('tanggal', $tanggalDate)
                    ->orWhereDate('created_at', $tanggalDate);
            })
            ->get();
    }

    private function resolveJenisPembayaranWisuda(string $nama, string $kategori): ?KeuanganJenisPembayaran
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }

        $namaLower = Str::lower(preg_replace('/\s+/', ' ', $nama));
        $queryByKategori = function () use ($kategori) {
            return KeuanganJenisPembayaran::where('kategori', 'LIKE', '%' . $kategori . '%');
        };

        $jenisPembayaran = $queryByKategori()
            ->whereRaw('LOWER(TRIM(nama)) = ?', [$namaLower])
            ->first();

        if ($jenisPembayaran) {
            return $jenisPembayaran;
        }

        foreach ($this->resolveKeywordJenisPembayaranWisuda($namaLower) as $keyword) {
            $jenisPembayaran = $queryByKategori()
                ->whereRaw('LOWER(nama) LIKE ?', ['%' . $keyword . '%'])
                ->first();

            if ($jenisPembayaran) {
                return $jenisPembayaran;
            }
        }

        return $queryByKategori()
            ->whereRaw('LOWER(nama) LIKE ?', ['%' . $namaLower . '%'])
            ->first();
    }

    private function resolveKeywordJenisPembayaranWisuda(string $namaLower): array
    {
        if (str_contains($namaLower, 'transfer')) {
            return ['transfer'];
        }

        if (str_contains($namaLower, 'tunai') || str_contains($namaLower, 'cash')) {
            return ['cash', 'tunai'];
        }

        if (str_contains($namaLower, 'lain') || str_contains($namaLower, 'yayasan')) {
            return ['yayasan'];
        }

        if (str_contains($namaLower, 'deposit')) {
            return ['deposit'];
        }

        return [];
    }

    private function resolveSemesterMahasiswa(string $nim, string $thAkademikKode): ?int
    {
        $tahunMasuk = (int) substr($nim, 0, 4);
        $tahunAkademik = (int) substr($thAkademikKode, 0, 4);
        $semesterAkademik = (int) substr($thAkademikKode, -1);

        if ($tahunMasuk <= 0 || $tahunAkademik <= 0 || ! in_array($semesterAkademik, [1, 2], true)) {
            return null;
        }

        $semester = (($tahunAkademik - $tahunMasuk) * 2) + $semesterAkademik;

        return $semester > 0 ? $semester : null;
    }

    private function resolveMahasiswa(string $nim)
    {
        $mahasiswa = Mahasiswa::nim($nim);

        if (! $mahasiswa) {
            return null;
        }

        if (is_array($mahasiswa)) {
            if (empty($mahasiswa)) {
                return null;
            }

            if (array_key_exists('nim', $mahasiswa)) {
                return $mahasiswa;
            }

            return collect($mahasiswa)->first(function ($item) use ($nim) {
                return strtoupper((string) data_get($item, 'nim')) === $nim;
            }) ?: collect($mahasiswa)->first();
        }

        if (! data_get($mahasiswa, 'nim')) {
            return null;
        }

        return $mahasiswa;
    }

    private function resolveJenisKelaminMahasiswa($mahasiswa): ?array
    {
        $jkId = (int) data_get($mahasiswa, 'jk_id', data_get($mahasiswa, 'jk.id'));
        $jenisKelamin = $this->formatJenisKelaminMahasiswa($jkId);

        if ($jenisKelamin) {
            return $jenisKelamin;
        }

        $candidates = [
            data_get($mahasiswa, 'jk.kode'),
            data_get($mahasiswa, 'jk.nama'),
            data_get($mahasiswa, 'jk.kategori'),
            data_get($mahasiswa, 'jenis_kelamin'),
            data_get($mahasiswa, 'gender'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = Str::lower(trim((string) $candidate));
            $normalized = str_replace([' ', '_'], '-', $normalized);

            if (in_array($normalized, ['l', 'laki', 'laki-laki', 'putra', 'pria', 'male'], true)) {
                return $this->formatJenisKelaminMahasiswa(8);
            }

            if (in_array($normalized, ['p', 'perempuan', 'putri', 'wanita', 'female'], true)) {
                return $this->formatJenisKelaminMahasiswa(9);
            }
        }

        return null;
    }

    private function formatJenisKelaminMahasiswa(int $jkId): ?array
    {
        if ($jkId === 8) {
            return [
                'id'       => 8,
                'nama'     => 'Laki-Laki',
                'kategori' => 'Putra',
            ];
        }

        if ($jkId === 9) {
            return [
                'id'       => 9,
                'nama'     => 'Perempuan',
                'kategori' => 'Putri',
            ];
        }

        return null;
    }

    public function cekPembayaran(Request $request)
    {
        try {
            $request->validate([
                'nim' => 'required',
                'th_akademik_id' => 'required'
            ]);

            $nim = $request->nim;
            $th_akademik_id = $request->th_akademik_id;

            $kodeFormKrs = ['KRS-1', 'KRS-2'];

            $bayar = KeuanganPembayaran::with('tagihan.form_schadule')
                ->where('nim', $nim)
                ->where('jumlah', '>', 0)
                ->whereHas('tagihan', function ($query) use ($th_akademik_id, $kodeFormKrs) {
                    $query->where('th_akademik_id', $th_akademik_id)
                        ->whereHas('form_schadule', function ($formQuery) use ($kodeFormKrs) {
                            $formQuery->whereIn('kode', $kodeFormKrs);
                        });
                })
                ->first();

            if ($bayar) {
                $return = [
                    'status' => true,
                    'message' => 'Pembayaran ' . $bayar->tagihan->nama . ' Nomor ' . $bayar->nomor . ' Tanggal ' . Carbon::parse($bayar->tanggal)->format('d-m-Y'),
                ];
            } else {
                $dispensasi = KeuanganDispensasi::where('th_akademik_id', $th_akademik_id)
                    ->where('nim', $nim)->first();
                if ($dispensasi) {
                    $return = [
                        'status' => true,
                        'message' => ' Tanggal Dispensasi ' . $dispensasi->created_at,
                    ];
                } else {
                    $return = [
                        'status' => false,
                        'message' => 'null',
                    ];
                }
            }
            return $return;
        } catch (\Illuminate\Validation\ValidationException $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
            ], 500);
        }
    }

    public function cekPembayaranUas(Request $request)
    {
        try {
            $request->validate([
                'nim' => 'required',
                'th_akademik_kode' => 'required'
            ]);

            $getThAkademik = ThAkademik::where('kode', $request->th_akademik_kode)->first();
            $tahunAkademik = substr($getThAkademik->kode, 0, 4); // 2024
            $semester = substr($getThAkademik->kode, -1);   // 1 / 2
            $thAkademikNama = str_replace("/", "-", $getThAkademik->nama);

            $nimList = collect($request->nim)
                ->map(fn($nim) => "SELECT '{$nim}' AS nim")
                ->implode(" UNION ALL ");

            $data = DB::table(DB::raw("({$nimList}) AS mhs"))
                ->select('mhs.nim')
                ->addSelect([

                    // HITUNG SEMESTER
                    DB::raw("
                        (
                            ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                            + {$semester}
                        ) AS semester_mhs
                    "),

                    // STATUS PEMBAYARAN UAS
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_pembayaran kp
                            JOIN keuangan_tagihan kt
                                ON kt.id = kp.tagihan_id
                            WHERE kp.nim = mhs.nim
                            AND UPPER(kt.nama) = CONCAT(
                                'UAS SEMESTER ',
                                (
                                    ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                                    + {$semester}
                                )
                            )
                        ) AS status_pembayaran_uas
                    "),

                    // STATUS DISPENSASI TAGIHAN UAS
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_dispensasi_tagihan kdt
                            JOIN keuangan_tagihan kt
                                ON kt.id = kdt.jenis_tagihan_id
                            WHERE kdt.nim = mhs.nim
                            AND UPPER(kt.nama) = CONCAT(
                                'UAS SEMESTER ',
                                (
                                    ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                                    + {$semester}
                                )
                            )
                        ) AS status_uas_dispensasi_tagihan
                    "),

                    // STATUS DISPENSASI UAS
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_dispensasi_uas kdu
                            WHERE kdu.nim = mhs.nim
                            AND kdu.th_akademik_id = {$getThAkademik->id}
                        ) AS status_dispensasi_uas
                    "),

                    // STATUS PEMBAYARAN TAMBAHAN
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_pembayaran_tambahan kpt
                            WHERE kpt.nim = mhs.nim
                            AND kpt.th_akademik = '{$thAkademikNama}'
                            AND kpt.smt = '{$getThAkademik->semester}'
                            AND UPPER(kpt.tagihan) LIKE 'UAS%'
                        ) AS status_pembayaran_tambahan_uas
                    "),
                ])
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'success',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
            ], 500);
        }
    }

    public function cekPembayaranUTS(Request $request)
    {
        try {
            $request->validate([
                'nim' => 'required',
                'th_akademik_kode' => 'required'
            ]);

            $getThAkademik = ThAkademik::where('kode', $request->th_akademik_kode)->first();
            $tahunAkademik = substr($getThAkademik->kode, 0, 4); // 2024
            $semester = substr($getThAkademik->kode, -1);   // 1 / 2
            $thAkademikNama = str_replace("/", "-", $getThAkademik->nama);

            $nimList = collect($request->nim)
                ->map(fn($nim) => "SELECT '{$nim}' AS nim")
                ->implode(" UNION ALL ");

            $data = DB::table(DB::raw("({$nimList}) AS mhs"))
                ->select('mhs.nim')
                ->addSelect([

                    // HITUNG SEMESTER
                    DB::raw("
                        (
                            ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                            + {$semester}
                        ) AS semester_mhs
                    "),

                    // STATUS PEMBAYARAN UTS
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_pembayaran kp
                            JOIN keuangan_tagihan kt
                                ON kt.id = kp.tagihan_id
                            WHERE kp.nim = mhs.nim
                            AND UPPER(kt.nama) = CONCAT(
                                'UTS SEMESTER ',
                                (
                                    ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                                    + {$semester}
                                )
                            )
                        ) AS status_pembayaran_uts
                    "),

                    // STATUS DISPENSASI TAGIHAN UTS
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_dispensasi_tagihan kdt
                            JOIN keuangan_tagihan kt
                                ON kt.id = kdt.jenis_tagihan_id
                            WHERE kdt.nim = mhs.nim
                            AND UPPER(kt.nama) = CONCAT(
                                'UTS SEMESTER ',
                                (
                                    ({$tahunAkademik} - LEFT(mhs.nim, 4)) * 2
                                    + {$semester}
                                )
                            )
                        ) AS status_uts_dispensasi_tagihan
                    "),

                    // STATUS PEMBAYARAN TAMBAHAN
                    DB::raw("
                        EXISTS (
                            SELECT 1
                            FROM keuangan_pembayaran_tambahan kpt
                            WHERE kpt.nim = mhs.nim
                            AND kpt.th_akademik = '{$thAkademikNama}'
                            AND kpt.smt = '{$getThAkademik->semester}'
                            AND UPPER(kpt.tagihan) LIKE 'UTS%'
                        ) AS status_pembayaran_tambahan_uts
                    "),
                ])
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'success',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 422,
                'error' => implode(' ', collect($e->errors())->flatten()->toArray()),
                'req' => $request->all()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage(),
                'code'     => 500,
            ], 500);
        }
    }

    public function petugasPembayaran(Request $request)
    {
        try {
            $jkUser = \App\Services\Helper::getJenisKelaminUser();

            $query = \App\Models\User::whereHas('role', function ($q) {
                $q->whereIn('name', ['kabag', 'staff']);
            });

            // If not "semua" (admin), filter by the logged-in user's gender
            if ($jkUser->nama !== '%') {
                $query->where('jenis_kelamin', $jkUser->nama);
            }

            // Also we can optionally filter by name if search is needed, but for dropdown we just get all
            $users = $query->orderBy('name', 'asc')->get(['id', 'name', 'jenis_kelamin']);

            return response()->json([
                'status' => true,
                'data' => $users
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function petugasPengeluaran(Request $request)
    {
        try {
            $roleNames = $this->pengeluaranPetugasRoles(
                $request->input('module', $request->input('module_key'))
            );

            $query = \App\Models\User::with('role:id,name')
                ->whereHas('role', fn ($role) => $role->whereIn('name', $roleNames));

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('jenis_kelamin', 'LIKE', "%{$search}%");
                });
            }

            $users = $query
                ->orderBy('name')
                ->get(['id', 'name', 'jenis_kelamin', 'role_id'])
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'jenis_kelamin' => $user->jenis_kelamin,
                    'role_name' => $user->role?->name,
                ])
                ->values();

            return response()->json([
                'status' => true,
                'data' => $users,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    private function pengeluaranPetugasRoles(?string $module): array
    {
        $moduleKey = str_replace('-', '_', strtolower((string) $module));

        $roles = [
            'umum' => ['rumahtangga'],
            'dosen' => ['barokahdosen_tatapmuka'],
            'tatap_muka' => ['barokahdosen_tatapmuka'],
            'dosen_tatapmuka' => ['barokahdosen_tatapmuka'],
            'dosen_tatap_muka' => ['barokahdosen_tatapmuka'],
            'kegiatan' => ['barokahdosen_kegiatan'],
            'dosen_kegiatan' => ['barokahdosen_kegiatan'],
            'staff_bulanan' => ['barokahdosen_kegiatan'],
            'dosen_bulanan' => ['barokahdosen_bulanan'],
            'rab' => [
                'barokahdosen_tatapmuka',
                'barokahdosen_kegiatan',
                'barokahdosen_bulanan',
            ],
        ];

        if (array_key_exists($moduleKey, $roles)) {
            return $roles[$moduleKey];
        }

        return $roles['rab'];
    }
}
