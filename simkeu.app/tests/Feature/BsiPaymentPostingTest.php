<?php

namespace Tests\Feature;

use App\Models\KeuanganPembayaranBsi;
use App\Services\BsiPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BsiPaymentPostingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for the isolated BSI posting test.');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('th_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->timestamps();
        });

        Schema::create('keuangan_jenis_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kategori');
            $table->boolean('is_manual')->default(true);
            $table->timestamps();
        });

        Schema::create('keuangan_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nim')->nullable();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->dateTime('tanggal');
            $table->unsignedBigInteger('th_akademik_id');
            $table->unsignedBigInteger('tagihan_id');
            $table->string('nim');
            $table->integer('smt');
            $table->integer('jml_sks');
            $table->decimal('jumlah', 15, 2);
            $table->unsignedBigInteger('jk_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('sumber')->nullable();
            $table->timestamps();
        });

        Schema::create('keuangan_jenis_pembayaran_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jenis_pembayaran_id');
            $table->unsignedBigInteger('pembayaran_id');
            $table->timestamps();
        });

        Schema::create('keuangan_nota', function (Blueprint $table) {
            $table->id();
            $table->string('nota');
            $table->unsignedBigInteger('pembayaran_id');
            $table->timestamps();
        });

        Schema::create('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->string('request_id');
            $table->string('request_hash', 64);
            $table->string('nim');
            $table->string('nama_mahasiswa')->nullable();
            $table->unsignedBigInteger('jk_id');
            $table->unsignedBigInteger('jenis_pembayaran_id');
            $table->string('va_number');
            $table->string('bank_reference')->nullable();
            $table->decimal('total', 15, 2);
            $table->string('status');
            $table->dateTime('expired_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();
        });

        Schema::create('keuangan_pembayaran_bsi_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_bsi_id');
            $table->unsignedBigInteger('tagihan_id');
            $table->unsignedBigInteger('th_akademik_id');
            $table->string('tagihan_nama');
            $table->decimal('jumlah_tagihan', 15, 2);
            $table->decimal('sisa_awal', 15, 2);
            $table->decimal('jumlah', 15, 2);
            $table->string('cara_bayar');
            $table->unsignedInteger('urutan');
            $table->unsignedBigInteger('pembayaran_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_posts_a_paid_staging_transaction_once(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $academicYearId = DB::table('th_akademik')->insertGetId([
            'kode' => '20252',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $paymentTypeId = DB::table('keuangan_jenis_pembayaran')->insertGetId([
            'nama' => 'VA BSI',
            'kategori' => 'Putra',
            'is_manual' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $billId = DB::table('keuangan_tagihan')->insertGetId([
            'nim' => null,
            'nama' => 'UKT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = KeuanganPembayaranBsi::create([
            'nomor' => 'BSI-20260611-00000001',
            'request_id' => 'REQ-1',
            'request_hash' => str_repeat('a', 64),
            'nim' => '20240001',
            'nama_mahasiswa' => 'Mahasiswa',
            'jk_id' => 8,
            'jenis_pembayaran_id' => $paymentTypeId,
            'va_number' => '900001',
            'total' => 150000,
            'status' => 'paid',
            'expired_at' => now()->addHour(),
            'paid_at' => '2026-06-11 09:30:00',
        ]);
        $detail = $payment->details()->create([
            'tagihan_id' => $billId,
            'th_akademik_id' => $academicYearId,
            'tagihan_nama' => 'UKT',
            'jumlah_tagihan' => 500000,
            'sisa_awal' => 500000,
            'jumlah' => 150000,
            'cara_bayar' => 'cicilan',
            'urutan' => 1,
        ]);

        $service = new BsiPaymentService;
        $posted = $service->postPayment($payment, $userId);
        $service->postPayment($posted, $userId);

        $this->assertSame('posted', $posted->status);
        $this->assertDatabaseCount('keuangan_pembayaran', 1);
        $this->assertDatabaseHas('keuangan_pembayaran', [
            'nomor' => 'BSI-20260611-00000001-01',
            'nim' => '20240001',
            'jumlah' => 150000,
            'smt' => 4,
            'sumber' => 'bsi',
        ]);
        $this->assertDatabaseHas('keuangan_jenis_pembayaran_detail', [
            'jenis_pembayaran_id' => $paymentTypeId,
        ]);
        $this->assertDatabaseCount('keuangan_nota', 1);
        $this->assertNotNull($detail->refresh()->pembayaran_id);
    }
}
