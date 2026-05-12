<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AktifkanMahasiswaController extends Controller
{
    private const ACTIVE_STATUS_ID = 18;
    private const FORM_SCHADULE_CODES = ['KRS-1', 'KRS-2'];
    private const MAX_BATCH_SIZE = 25;

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'th_akademik_id' => ['required', 'integer', 'exists:th_akademik,id'],
        ]);

        $rows = $this->mergeEligibilityRows(
            $this->eligiblePaymentRows((int) $validated['th_akademik_id']),
            $this->eligibleDispensasiRows((int) $validated['th_akademik_id'])
        );

        return response()->json([
            'status'  => true,
            'data'    => $rows,
            'summary' => [
                'total_mahasiswa' => $rows->count(),
                'form_schadule'   => self::FORM_SCHADULE_CODES,
                'status_id'       => self::ACTIVE_STATUS_ID,
            ],
            'message' => 'Data mahasiswa siap ditampilkan.',
        ]);
    }

    public function activate(Request $request)
    {
        $validated = $request->validate([
            'th_akademik_id' => ['required', 'integer', 'exists:th_akademik,id'],
            'nims'           => ['required', 'array', 'min:1', 'max:' . self::MAX_BATCH_SIZE],
            'nims.*'         => ['required', 'string'],
        ]);

        $nims = collect($validated['nims'])
            ->map(fn ($nim) => strtoupper(trim((string) $nim)))
            ->filter()
            ->unique()
            ->values();

        if ($nims->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'NIM tidak valid.',
            ], 422);
        }

        $eligibleNims = $this->eligibleNims((int) $validated['th_akademik_id'], $nims->all());

        $results = [
            'success' => [],
            'failed'  => [],
            'skipped' => [],
        ];

        foreach ($nims as $nim) {
            if (! $eligibleNims->contains($nim)) {
                $results['skipped'][] = [
                    'nim'     => $nim,
                    'message' => 'Tidak ditemukan pembayaran KRS-1/KRS-2 atau dispensasi pada tahun akademik yang dipilih.',
                ];
                continue;
            }

            $response = Mahasiswa::updateStatusMahasiswa($nim, self::ACTIVE_STATUS_ID);

            if ($this->isSuccessfulResponse($response)) {
                $results['success'][] = [
                    'nim'     => $nim,
                    'message' => $this->responseMessage($response) ?? 'Status mahasiswa berhasil diaktifkan.',
                ];
                continue;
            }

            $results['failed'][] = [
                'nim'     => $nim,
                'message' => $this->responseMessage($response) ?? 'Gagal mengaktifkan status mahasiswa.',
            ];
        }

        return response()->json([
            'status'  => count($results['failed']) === 0,
            'data'    => $results,
            'summary' => [
                'success' => count($results['success']),
                'failed'  => count($results['failed']),
                'skipped' => count($results['skipped']),
            ],
            'message' => 'Batch aktifasi mahasiswa selesai diproses.',
        ]);
    }

    private function eligiblePaymentBaseQuery(int $thAkademikId)
    {
        return DB::table('keuangan_pembayaran as kp')
            ->join('keuangan_tagihan as kt', 'kt.id', '=', 'kp.tagihan_id')
            ->join('form_schadule as fs', 'fs.id', '=', 'kt.form_schadule_id')
            ->leftJoin('prodi as p', 'p.id', '=', 'kt.prodi_id')
            ->where('kt.th_akademik_id', $thAkademikId)
            ->whereIn('fs.kode', self::FORM_SCHADULE_CODES)
            ->where('kp.jumlah', '>', 0)
            ->whereNotNull('kp.nim')
            ->where('kp.nim', '!=', '');
    }

    private function eligiblePaymentRows(int $thAkademikId)
    {
        return $this->eligiblePaymentBaseQuery($thAkademikId)
            ->select([
                'kp.nim',
                DB::raw('COUNT(DISTINCT kp.id) as jumlah_pembayaran'),
                DB::raw('SUM(kp.jumlah) as total_bayar'),
                DB::raw('MAX(kp.tanggal) as tanggal_terakhir'),
                DB::raw('GROUP_CONCAT(DISTINCT fs.kode) as form_schadule_kode'),
                DB::raw('GROUP_CONCAT(DISTINCT kt.nama) as tagihan'),
                DB::raw('GROUP_CONCAT(DISTINCT p.nama) as prodi'),
                DB::raw("'Pembayaran KRS' as sumber"),
            ])
            ->groupBy('kp.nim')
            ->get()
            ->map(function ($row) {
                return [
                    'nim'                => strtoupper(trim($row->nim)),
                    'prodi'              => $row->prodi,
                    'sumber'             => $row->sumber,
                    'jumlah_pembayaran'  => (int) $row->jumlah_pembayaran,
                    'total_bayar'        => (float) $row->total_bayar,
                    'tanggal_terakhir'   => $row->tanggal_terakhir,
                    'form_schadule_kode' => $row->form_schadule_kode,
                    'tagihan'            => $row->tagihan,
                ];
            });
    }

    private function eligibleDispensasiRows(int $thAkademikId)
    {
        return DB::table('keuangan_dispensasi as kd')
            ->leftJoin('users as u', 'u.username', '=', 'kd.nim')
            ->leftJoin('prodi as p', 'p.id', '=', 'u.prodi_id')
            ->where('kd.th_akademik_id', $thAkademikId)
            ->whereNotNull('kd.nim')
            ->where('kd.nim', '!=', '')
            ->select([
                'kd.nim',
                DB::raw('COUNT(DISTINCT kd.id) as jumlah_dispensasi'),
                DB::raw('MAX(kd.created_at) as tanggal_terakhir'),
                DB::raw('GROUP_CONCAT(DISTINCT kd.keterangan) as keterangan'),
                DB::raw('GROUP_CONCAT(DISTINCT p.nama) as prodi'),
                DB::raw("'Dispensasi' as sumber"),
            ])
            ->groupBy('kd.nim')
            ->get()
            ->map(function ($row) {
                return [
                    'nim'                => strtoupper(trim($row->nim)),
                    'prodi'              => $row->prodi,
                    'sumber'             => $row->sumber,
                    'jumlah_pembayaran'  => (int) $row->jumlah_dispensasi,
                    'total_bayar'        => 0,
                    'tanggal_terakhir'   => $row->tanggal_terakhir,
                    'form_schadule_kode' => 'DISPENSASI',
                    'tagihan'            => $row->keterangan ?: 'Dispensasi',
                ];
            });
    }

    private function mergeEligibilityRows($paymentRows, $dispensasiRows)
    {
        return $paymentRows
            ->concat($dispensasiRows)
            ->groupBy('nim')
            ->map(function ($rows, $nim) {
                return [
                    'nim'                => $nim,
                    'prodi'              => $this->uniqueCsv($rows, 'prodi'),
                    'sumber'             => $this->uniqueCsv($rows, 'sumber'),
                    'jumlah_pembayaran'  => (int) $rows->sum('jumlah_pembayaran'),
                    'total_bayar'        => (float) $rows->sum('total_bayar'),
                    'tanggal_terakhir'   => $rows->pluck('tanggal_terakhir')->filter()->max(),
                    'form_schadule_kode' => $this->uniqueCsv($rows, 'form_schadule_kode'),
                    'tagihan'            => $this->uniqueCsv($rows, 'tagihan'),
                ];
            })
            ->sortBy('nim')
            ->values();
    }

    private function eligibleNims(int $thAkademikId, array $nims)
    {
        $paymentNims = $this->eligiblePaymentBaseQuery($thAkademikId)
            ->whereIn('kp.nim', $nims)
            ->pluck('kp.nim');

        $dispensasiNims = DB::table('keuangan_dispensasi as kd')
            ->where('kd.th_akademik_id', $thAkademikId)
            ->whereIn('kd.nim', $nims)
            ->pluck('kd.nim');

        return $paymentNims
            ->concat($dispensasiNims)
            ->map(fn ($nim) => strtoupper($nim))
            ->unique()
            ->values();
    }

    private function uniqueCsv($rows, string $key): string
    {
        return $rows
            ->pluck($key)
            ->filter()
            ->flatMap(fn ($value) => array_filter(array_map('trim', explode(',', $value))))
            ->unique()
            ->implode(', ');
    }

    private function isSuccessfulResponse($response): bool
    {
        if (! $response) {
            return false;
        }

        foreach (['status', 'success'] as $property) {
            if (is_object($response) && property_exists($response, $property)) {
                $value = $response->{$property};

                if (is_bool($value)) {
                    return $value;
                }

                if (is_numeric($value)) {
                    return (int) $value === 1;
                }

                return ! in_array(strtolower((string) $value), ['false', 'failed', 'error', '0'], true);
            }
        }

        return true;
    }

    private function responseMessage($response): ?string
    {
        if (! is_object($response)) {
            return null;
        }

        foreach (['message', 'pesan'] as $property) {
            if (property_exists($response, $property) && $response->{$property}) {
                return is_string($response->{$property})
                    ? $response->{$property}
                    : json_encode($response->{$property});
            }
        }

        return null;
    }
}
