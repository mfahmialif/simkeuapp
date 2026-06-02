<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_pengeluaran_dosen_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kegiatan');
            $table->integer('transport')->nullable();
            $table->integer('barokah')->nullable();
            $table->integer('total');
            $table->string('jenis_pembayaran', 50);
            $table->string('bukti_transfer')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_dosen_kegiatan');
    }
};
