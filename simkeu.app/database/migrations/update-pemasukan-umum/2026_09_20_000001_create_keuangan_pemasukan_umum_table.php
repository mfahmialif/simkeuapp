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
        if (!Schema::hasTable('keuangan_pemasukan_umum')) {
            Schema::create('keuangan_pemasukan_umum', function (Blueprint $table) {
                $table->id();
                $table->dateTime('tanggal')->index('idx_pemasukan_umum_tanggal');
                $table->decimal('nominal', 15, 2)->index('idx_pemasukan_umum_nominal');
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('petugas_id')->index('idx_pemasukan_umum_petugas');
                $table->json('lampiran')->nullable();
                $table->timestamps();

                $table->foreign('petugas_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_pemasukan_umum');
    }
};
