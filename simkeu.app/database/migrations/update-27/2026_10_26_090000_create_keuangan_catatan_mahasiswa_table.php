<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_catatan_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('nim');
            $table->unsignedBigInteger('th_akademik_id')->nullable();
            $table->unsignedBigInteger('pembayaran_id')->nullable();
            $table->unsignedBigInteger('tagihan_id')->nullable();
            $table->date('tanggal')->nullable();
            $table->text('catatan')->nullable(); // Di DB tidak wajib (nullable)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('nim');
            $table->index('th_akademik_id');
            $table->index('pembayaran_id');
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_catatan_mahasiswa');
    }
};
