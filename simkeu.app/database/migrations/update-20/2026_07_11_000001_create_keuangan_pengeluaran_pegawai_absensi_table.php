<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pengeluaran_pegawai_absensi')) {
            return;
        }

        Schema::create('keuangan_pengeluaran_pegawai_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pegawai_tipe', 20)->nullable();
            $table->unsignedBigInteger('rekap_id')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->date('tanggal');
            $table->integer('total_hari')->default(0);
            $table->decimal('total_jam', 8, 2)->default(0);
            $table->integer('total_barokah')->default(0);
            $table->integer('total')->default(0);
            $table->string('jenis_pembayaran', 50);
            $table->string('bukti_transfer')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('lampiran')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'tanggal'], 'idx_pengeluaran_pegawai_absensi_pegawai_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_pegawai_absensi');
    }
};
