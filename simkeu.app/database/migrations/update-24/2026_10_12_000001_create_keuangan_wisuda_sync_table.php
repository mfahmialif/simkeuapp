<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('keuangan_wisuda_sync')) {
            Schema::create('keuangan_wisuda_sync', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pembayaran_id')->nullable()->index();
                $table->string('nim')->index();
                $table->integer('tahun_id')->nullable();
                $table->string('tahun_nama')->nullable();
                $table->string('ukuran_baju', 10)->nullable()->default('L');
                $table->text('judul')->nullable();
                $table->date('tanggal_sidang')->nullable();
                $table->string('status', 30)->default('synced')->index(); // synced, failed, pending
                $table->text('message')->nullable();
                $table->string('no_unik', 50)->nullable();
                $table->json('payload')->nullable();
                $table->json('response')->nullable();
                $table->unsignedBigInteger('synced_by')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index(['nim', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_wisuda_sync');
    }
};
