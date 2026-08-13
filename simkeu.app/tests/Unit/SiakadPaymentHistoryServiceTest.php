<?php

namespace Tests\Unit;

use App\Services\SiakadPaymentHistoryService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SiakadPaymentHistoryServiceTest extends TestCase
{
    public function test_history_bundles_official_payment_rows_by_nota(): void
    {
        $payments = new Collection([
            $this->payment(2, 'PAY-002', '130826-00001-L-123', '2026-08-13 09:00:00', 12, 100000),
            $this->payment(1, 'PAY-001', '130826-00001-L-123', '2026-08-13 09:00:00', 10, 250000),
            $this->payment(3, 'PAY-003', '010726-00002-L-456', '2026-07-01 08:00:00', 15, 75000),
        ]);

        $result = (new SiakadPaymentHistoryService)->bundlePayments($payments, '20240001');

        $this->assertSame('20240001', $result['nim']);
        $this->assertSame(2, $result['total_transaksi']);
        $this->assertSame(425000.0, $result['total_pembayaran']);
        $this->assertSame('130826-00001-L-123', $result['riwayat'][0]['nota']);
        $this->assertSame(350000.0, $result['riwayat'][0]['total']);
        $this->assertSame(2, $result['riwayat'][0]['jumlah_item']);
        $this->assertSame([2, 1], array_column(
            $result['riwayat'][0]['items'],
            'pembayaran_id'
        ));
        $this->assertSame('010726-00002-L-456', $result['riwayat'][1]['nota']);
    }

    public function test_payment_without_nota_becomes_its_own_bundle(): void
    {
        $payments = new Collection([
            $this->payment(1, 'PAY-TANPA-NOTA', null, '2026-08-13 09:00:00', 10, 50000),
        ]);

        $result = (new SiakadPaymentHistoryService)->bundlePayments($payments, '20240001');

        $this->assertSame(1, $result['total_transaksi']);
        $this->assertSame('PAY-TANPA-NOTA', $result['riwayat'][0]['nota']);
        $this->assertSame(1, $result['riwayat'][0]['items'][0]['pembayaran_id']);
    }

    private function payment(
        int $id,
        string $nomor,
        ?string $nota,
        string $tanggal,
        int $tagihanId,
        float $jumlah,
    ): object {
        return (object) [
            'id' => $id,
            'nomor' => $nomor,
            'nota' => $nota,
            'tanggal' => $tanggal,
            'th_akademik_id' => 25,
            'tagihan_id' => $tagihanId,
            'nim' => '2024.0001',
            'smt' => 5,
            'jml_sks' => 1,
            'jumlah' => $jumlah,
        ];
    }
}
