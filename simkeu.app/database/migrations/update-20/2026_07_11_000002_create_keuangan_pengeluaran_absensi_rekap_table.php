<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pengeluaran_absensi_rekap')) {
            return;
        }

        Schema::create('keuangan_pengeluaran_absensi_rekap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama');
            $table->date('tanggal_rekap')->nullable();
            $table->date('tanggal_pencairan')->nullable();
            $table->string('bulan_tahun', 10)->nullable();
            $table->bigInteger('jumlah')->default(0);
            $table->bigInteger('jumlah_sementara')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_absensi_rekap');
    }
};
