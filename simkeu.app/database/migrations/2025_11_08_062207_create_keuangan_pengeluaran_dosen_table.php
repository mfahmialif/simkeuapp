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
        Schema::create('keuangan_pengeluaran_dosen', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->integer('jam');
            $table->integer('hari');
            $table->integer('dosen_kode');
            $table->integer('transport')->nullable();
            $table->integer('barokah')->nullable();
            $table->integer('total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_dosen');
    }
};
