<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiakadPaymentHistoryService
{
    public function forStudent(string $nim): array
    {
        $normalizedNim = BsiPaymentOrderService::customerNumberFromNim($nim);

        $payments = DB::table('keuangan_pembayaran')
            ->leftJoin(
                'keuangan_nota',
                'keuangan_nota.pembayaran_id',
                '=',
                'keuangan_pembayaran.id'
            )
            ->whereRaw(
                "REPLACE(REPLACE(TRIM(keuangan_pembayaran.nim), '.', ''), ' ', '') = ?",
                [$normalizedNim]
            )
            ->select([
                'keuangan_pembayaran.id',
                'keuangan_pembayaran.nomor',
                'keuangan_pembayaran.tanggal',
                'keuangan_pembayaran.th_akademik_id',
                'keuangan_pembayaran.tagihan_id',
                'keuangan_pembayaran.nim',
                'keuangan_pembayaran.smt',
                'keuangan_pembayaran.jml_sks',
                'keuangan_pembayaran.jumlah',
                'keuangan_nota.nota',
            ])
            ->orderByDesc('keuangan_pembayaran.tanggal')
            ->orderByDesc('keuangan_pembayaran.id')
            ->get();

        return $this->bundlePayments($payments, $normalizedNim);
    }

    public function bundlePayments(Collection $payments, string $normalizedNim): array
    {
        $history = $payments
            ->groupBy(fn ($payment) => $payment->nota ?: $payment->nomor)
            ->map(function ($bundle, string $nota) {
                $first = $bundle->first();

                return [
                    'nota' => $nota,
                    'tanggal' => $first->tanggal,
                    'nim' => $first->nim,
                    'total' => round((float) $bundle->sum('jumlah'), 2),
                    'jumlah_item' => $bundle->count(),
                    'items' => $bundle->map(fn ($payment) => [
                        'pembayaran_id' => (int) $payment->id,
                        'nomor' => $payment->nomor,
                        'th_akademik_id' => (int) $payment->th_akademik_id,
                        'tagihan_id' => (int) $payment->tagihan_id,
                        'semester' => (int) $payment->smt,
                        'jumlah_sks' => (int) $payment->jml_sks,
                        'jumlah' => round((float) $payment->jumlah, 2),
                    ])->values()->all(),
                ];
            })
            ->values();

        return [
            'nim' => $normalizedNim,
            'total_transaksi' => $history->count(),
            'total_pembayaran' => round((float) $history->sum('total'), 2),
            'riwayat' => $history->all(),
        ];
    }
}
