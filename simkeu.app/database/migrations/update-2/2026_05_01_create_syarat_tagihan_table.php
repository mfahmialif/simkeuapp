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
        Schema::create('keuangan_syarat_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique(); // Nama tagihan (unique dari keuangan_tagihan)
            $table->integer('smt')->nullable(); // Semester minimum untuk tagihan ini muncul
            $table->text('keterangan')->nullable(); // Catatan tambahan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_syarat_tagihan');
    }
};
