<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return;
        }

        Schema::create('keuangan_pengeluaran_saldo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('petugas_id')->nullable();
            $table->string('module_key', 32);
            $table->date('tanggal');
            $table->enum('tipe', ['masuk', 'keluar'])->default('masuk');
            $table->unsignedBigInteger('nominal');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('petugas_id', 'idx_keuangan_pengeluaran_saldo_petugas');
            $table->index(['petugas_id', 'module_key', 'tanggal'], 'idx_keuangan_pengeluaran_saldo_petugas_module_tanggal');
            $table->index(['module_key', 'tanggal'], 'idx_keuangan_pengeluaran_saldo_module_tanggal');
            $table->index('tipe', 'idx_keuangan_pengeluaran_saldo_tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_saldo');
    }
};
