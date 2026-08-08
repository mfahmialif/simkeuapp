<?php

namespace Tests\Feature;

use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BsiStandalonePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for the isolated BSI test.');
        }

        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('sumber')->nullable();
        });
        Schema::create('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->id();
            $table->boolean('data_test')->default(false);
            $table->timestamps();
        });
        Schema::create('keuangan_pembayaran_bsi_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_bsi_id');
            $table->timestamps();
        });
    }

    public function test_deleting_test_payment_only_removes_bsi_data(): void
    {
        $ledgerId = DB::table('keuangan_pembayaran')->insertGetId(['sumber' => 'manual']);
        $payment = KeuanganPembayaranBsi::create(['data_test' => true]);
        DB::table('keuangan_pembayaran_bsi_detail')->insert([
            'pembayaran_bsi_id' => $payment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new BsiPaymentService)->deleteTestPayment($payment);

        $this->assertDatabaseMissing('keuangan_pembayaran_bsi', ['id' => $payment->id]);
        $this->assertDatabaseMissing('keuangan_pembayaran_bsi_detail', [
            'pembayaran_bsi_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('keuangan_pembayaran', ['id' => $ledgerId]);
    }
}
