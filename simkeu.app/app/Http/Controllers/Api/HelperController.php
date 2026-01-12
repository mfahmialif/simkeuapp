<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\ThAkademik;
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


    public function cekPembayaran(Request $request)
    {
        try {
            $request->validate([
                'nim' => 'required',
                'th_akademik_id' => 'required'
            ]);

            $nim = $request->nim;
            $th_akademik_id = $request->th_akademik_id;

            $krs1 = 'krs-1';
            $krs2 = 'krs-2';

            $bayar = KeuanganPembayaran::where('th_akademik_id', $th_akademik_id)
                ->where('nim', $nim)->first();

            if ($bayar) {
                $kode_form = $bayar->tagihan->form_schadule->kode;
                if ((strtolower($kode_form) == strtolower($krs1)) || (strtolower($kode_form) == strtolower($krs2))) {
                    $return = [
                        'status' => true,
                        'message' => 'Pembayaran ' . $bayar->tagihan->nama . ' Nomor ' . $bayar->nomor . ' Tanggal ' . Carbon::parse($bayar->tanggal)->format('d-m-Y'),
                    ];
                } else {
                    $return = [
                        'status' => false,
                        'message' => 'null',
                    ];
                }
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
}
