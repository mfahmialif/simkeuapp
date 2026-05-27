<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Ref;
use App\Models\Prodi;
use App\Models\ThAkademik;
use App\Models\FormSchadule;
use App\Models\KeuanganTagihan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\KeuanganDispensasi;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

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
        if ($apiKeyResponse = $this->validateSimkeuv2ApiKey($request)) {
            return $apiKeyResponse;
        }

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
        if ($apiKeyResponse = $this->validateSimkeuv2ApiKey($request)) {
            return $apiKeyResponse;
        }

        try {
            $validated = $request->validate([
                'id'   => 'nullable|integer|min:1',
                'nim'  => 'nullable|string|max:255',
                'nama' => 'nullable|string|max:255',
            ]);

            $id = $validated['id'] ?? null;
            $nim = $request->filled('nim') ? strtoupper(trim($validated['nim'])) : null;
            $nama = $request->filled('nama') ? trim($validated['nama']) : null;

            if (! $id && blank($nim) && blank($nama)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Minimal kirim salah satu filter: id, nim, atau nama.',
                ], 422);
            }

            $query = KeuanganTagihan::whereNotNull('nim')
                ->where('nim', '!=', '');

            if ($id) {
                $query->where('id', $id);
            }

            if (! blank($nim)) {
                $query->where('nim', $nim);
            }

            if (! blank($nama)) {
                $query->where('nama', $nama);
            }

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

    private function validateSimkeuv2ApiKey(Request $request): ?JsonResponse
    {
        $configuredKey = config('simkeu.simkeuv2_api_key');

        if (blank($configuredKey)) {
            return response()->json([
                'status'  => false,
                'message' => 'SIMKEUV2_API_KEY belum dikonfigurasi.',
            ], 500);
        }

        $providedKey = $request->header('apikey')
            ?? $request->header('x-api-key')
            ?? $request->header('x-simkeuv2-api-key')
            ?? $request->header('simkeuv2-api-key');

        if (! $providedKey || ! hash_equals((string) $configuredKey, (string) $providedKey)) {
            return response()->json([
                'status'  => false,
                'message' => 'API key tidak valid.',
            ], 401);
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
}
