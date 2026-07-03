<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_piutang')) {
            Schema::create('keuangan_piutang', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pegawai_id');
                $table->date('tanggal');
                $table->unsignedBigInteger('nominal');
                $table->unsignedBigInteger('default_cicilan')->default(0);
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('pegawai_id', 'idx_keuangan_piutang_pegawai');
                $table->index('tanggal', 'idx_keuangan_piutang_tanggal');

                $table->foreign('pegawai_id')
                    ->references('id')
                    ->on('pegawai')
                    ->restrictOnDelete();
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('keuangan_piutang_pembayaran')) {
            Schema::create('keuangan_piutang_pembayaran', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('piutang_id');
                $table->date('tanggal');
                $table->unsignedBigInteger('nominal');
                $table->enum('jenis', ['potong_gaji', 'bayar_langsung']);
                $table->unsignedBigInteger('pengeluaran_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('piutang_id', 'idx_keu_piutang_bayar_piutang');
                $table->index('pengeluaran_id', 'idx_keu_piutang_bayar_pengeluaran');
                $table->index('tanggal', 'idx_keu_piutang_bayar_tanggal');
                $table->index(['piutang_id', 'tanggal'], 'idx_keu_piutang_bayar_piutang_tanggal');

                $table->foreign('piutang_id')
                    ->references('id')
                    ->on('keuangan_piutang')
                    ->restrictOnDelete();
                $table->foreign('pengeluaran_id')
                    ->references('id')
                    ->on('keuangan_pengeluaran_pegawai_bulanan')
                    ->cascadeOnDelete();
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_piutang_pembayaran');
        Schema::dropIfExists('keuangan_piutang');
    }
};
