<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_setting_akhir_laporan', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedTinyInteger('tanggal_akhir');
            $table->string('jenis_pembayaran_key', 50)->default('all');
            $table->unsignedBigInteger('jenis_pembayaran_id')->nullable();
            $table->string('jenis_pembayaran_nama', 100)->default('Semua Jenis Pembayaran');
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['tahun', 'bulan'], 'idx_sal_tahun_bulan');
            $table->index('jenis_pembayaran_key', 'idx_sal_jp_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_setting_akhir_laporan');
    }
};
