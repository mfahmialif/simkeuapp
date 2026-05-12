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
        Schema::dropIfExists('keuangan_syarat_tagihan');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('keuangan_syarat_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->integer('smt')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
};
