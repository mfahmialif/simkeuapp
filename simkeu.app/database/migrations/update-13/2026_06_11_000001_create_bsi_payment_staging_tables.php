<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keuangan_jenis_pembayaran', function (Blueprint $table) {
            $table->boolean('is_manual')->default(true)->after('keterangan');
        });

        Schema::table('keuangan_pembayaran', function (Blueprint $table) {
            $table->string('sumber', 30)->nullable()->after('user_id')->index();
        });

        Schema::create('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->nullable()->unique();
            $table->string('request_id')->unique();
            $table->string('request_hash', 64);
            $table->string('nim')->index();
            $table->string('nama_mahasiswa')->nullable();
            $table->unsignedBigInteger('jk_id')->nullable()->index();
            $table->foreignId('jenis_pembayaran_id')
                ->constrained('keuangan_jenis_pembayaran');
            $table->string('va_number')->index();
            $table->string('bank_reference')->nullable()->index();
            $table->decimal('total', 15, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('expired_at')->index();
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

            $table->index(['nim', 'status']);
            $table->index(['status', 'expired_at']);
        });

        Schema::create('keuangan_pembayaran_bsi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_bsi_id')
                ->constrained('keuangan_pembayaran_bsi')
                ->cascadeOnDelete();
            $table->foreignId('tagihan_id')->constrained('keuangan_tagihan');
            $table->foreignId('th_akademik_id')->constrained('th_akademik');
            $table->string('tagihan_nama');
            $table->decimal('jumlah_tagihan', 15, 2);
            $table->decimal('sisa_awal', 15, 2);
            $table->decimal('jumlah', 15, 2);
            $table->string('cara_bayar', 20);
            $table->unsignedInteger('urutan')->default(1);
            $table->foreignId('pembayaran_id')
                ->nullable()
                ->constrained('keuangan_pembayaran')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['pembayaran_bsi_id', 'tagihan_id'], 'bsi_detail_transaction_bill_unique');
            $table->index(['tagihan_id', 'pembayaran_bsi_id'], 'bsi_detail_bill_transaction_index');
        });

        Schema::create('keuangan_pembayaran_bsi_callback', function (Blueprint $table) {
            $table->id();
            $table->string('callback_id')->unique();
            $table->foreignId('pembayaran_bsi_id')
                ->nullable()
                ->constrained('keuangan_pembayaran_bsi')
                ->nullOnDelete();
            $table->string('request_id')->nullable()->index();
            $table->string('va_number')->nullable()->index();
            $table->string('bank_reference')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('bank_status', 40);
            $table->string('process_status', 30)->default('received')->index();
            $table->text('message')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->json('payload');
            $table->timestamps();
        });

        foreach (['Putra', 'Putri'] as $kategori) {
            DB::table('keuangan_jenis_pembayaran')->updateOrInsert(
                [
                    'nama' => 'VA BSI - '.$kategori,
                    'kategori' => $kategori,
                ],
                [
                    'nomer_rekening' => null,
                    'keterangan' => 'Pembayaran Virtual Account BSI. Tidak untuk input pembayaran manual.',
                    'is_manual' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pembayaran_bsi_callback');
        Schema::dropIfExists('keuangan_pembayaran_bsi_detail');
        Schema::dropIfExists('keuangan_pembayaran_bsi');

        Schema::table('keuangan_pembayaran', function (Blueprint $table) {
            $table->dropIndex(['sumber']);
            $table->dropColumn('sumber');
        });

        Schema::table('keuangan_jenis_pembayaran', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
