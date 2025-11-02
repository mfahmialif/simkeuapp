<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\KeuanganDispensasi;
use App\Models\KeuanganPembayaran;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

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
}
