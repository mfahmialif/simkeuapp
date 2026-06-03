<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')) {
            return;
        }

        Schema::create('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->date('tanggal');
            $table->integer('hari')->nullable();
            $table->integer('barokah_harian')->nullable();
            $table->integer('barokah_bulanan')->nullable();
            $table->integer('total')->default(0);
            $table->string('jenis_pembayaran', 50);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'tanggal'], 'idx_pengeluaran_pegawai_bulanan_pegawai_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_pegawai_bulanan');
    }
};
