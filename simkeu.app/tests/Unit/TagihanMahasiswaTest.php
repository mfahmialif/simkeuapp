<?php

namespace Tests\Unit;

use App\Services\TagihanMahasiswa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TagihanMahasiswaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for this isolated test.');
        }

        Schema::create('keuangan_tagihan', function (Blueprint $table) {
            $table->id();
            $table->decimal('jumlah', 15, 2);
        });

        Schema::create('idn_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tagihan_id');
            $table->string('bill_key');
            $table->decimal('total_bill_amount', 15, 2);
        });

        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tagihan_id');
            $table->string('nim');
            $table->decimal('jumlah', 15, 2);
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

    public function test_sisa_tagihan_ignores_test_bsi_ledger_but_counts_production_payment(): void
    {
        $tagihanId = DB::table('keuangan_tagihan')->insertGetId(['jumlah' => 250000]);

        $productionLedgerId = DB::table('keuangan_pembayaran')->insertGetId([
            'tagihan_id' => $tagihanId,
            'nim' => '20200101',
            'jumlah' => 50000,
        ]);
        $testLedgerId = DB::table('keuangan_pembayaran')->insertGetId([
            'tagihan_id' => $tagihanId,
            'nim' => '20200101',
            'jumlah' => 10000,
        ]);

        $productionBsiId = DB::table('keuangan_pembayaran_bsi')->insertGetId([
            'data_test' => false,
        ]);
        $testBsiId = DB::table('keuangan_pembayaran_bsi')->insertGetId([
            'data_test' => true,
        ]);

        DB::table('keuangan_pembayaran_bsi_detail')->insert([
            [
                'pembayaran_bsi_id' => $productionBsiId,
                'pembayaran_id' => $productionLedgerId,
            ],
            [
                'pembayaran_bsi_id' => $testBsiId,
                'pembayaran_id' => $testLedgerId,
            ],
        ]);

        $this->assertSame(200000.0, TagihanMahasiswa::getSisaTagihan('20200101', $tagihanId));
    }
}
