<?php

namespace Tests\Feature;

use App\Services\SiakadPaymentHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiakadPaymentHistoryFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for this isolated test.');
        }

        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->dateTime('tanggal');
            $table->unsignedBigInteger('th_akademik_id');
            $table->unsignedBigInteger('tagihan_id');
            $table->string('nim');
            $table->unsignedInteger('smt');
            $table->unsignedInteger('jml_sks');
            $table->decimal('jumlah', 15, 2);
        });

        Schema::create('keuangan_nota', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_id');
            $table->string('nota');
        });

        Schema::create('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->id();
            $table->boolean('data_test')->default(false);
        });

        Schema::create('keuangan_pembayaran_bsi_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_bsi_id');
            $table->unsignedBigInteger('pembayaran_id')->nullable();
        });
    }

    public function test_history_excludes_payments_from_bsi_test_data(): void
    {
        $productionPaymentId = $this->insertPayment('PAY-PRODUCTION', 10, 250000);
        $testPaymentId = $this->insertPayment('PAY-TEST', 11, 100000);
        $manualPaymentId = $this->insertPayment('PAY-MANUAL', 12, 50000);

        $productionBsiId = DB::table('keuangan_pembayaran_bsi')->insertGetId([
            'data_test' => false,
        ]);
        $testBsiId = DB::table('keuangan_pembayaran_bsi')->insertGetId([
            'data_test' => true,
        ]);

        DB::table('keuangan_pembayaran_bsi_detail')->insert([
            [
                'pembayaran_bsi_id' => $productionBsiId,
                'pembayaran_id' => $productionPaymentId,
            ],
            [
                'pembayaran_bsi_id' => $testBsiId,
                'pembayaran_id' => $testPaymentId,
            ],
        ]);

        DB::table('keuangan_nota')->insert([
            ['pembayaran_id' => $productionPaymentId, 'nota' => 'NOTA-PRODUCTION'],
            ['pembayaran_id' => $testPaymentId, 'nota' => 'NOTA-TEST'],
            ['pembayaran_id' => $manualPaymentId, 'nota' => 'NOTA-MANUAL'],
        ]);

        $result = (new SiakadPaymentHistoryService)->forStudent('20240001');
        $paymentNumbers = collect($result['riwayat'])
            ->flatMap(fn (array $history) => $history['items'])
            ->pluck('nomor')
            ->all();

        $this->assertSame(2, $result['total_transaksi']);
        $this->assertSame(300000.0, $result['total_pembayaran']);
        $this->assertContains('PAY-PRODUCTION', $paymentNumbers);
        $this->assertContains('PAY-MANUAL', $paymentNumbers);
        $this->assertNotContains('PAY-TEST', $paymentNumbers);
    }

    private function insertPayment(string $nomor, int $tagihanId, float $jumlah): int
    {
        return DB::table('keuangan_pembayaran')->insertGetId([
            'nomor' => $nomor,
            'tanggal' => '2026-08-14 10:00:00',
            'th_akademik_id' => 25,
            'tagihan_id' => $tagihanId,
            'nim' => '2024.0001',
            'smt' => 5,
            'jml_sks' => 1,
            'jumlah' => $jumlah,
        ]);
    }
}
