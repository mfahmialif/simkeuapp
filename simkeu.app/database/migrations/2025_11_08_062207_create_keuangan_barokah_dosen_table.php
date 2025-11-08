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
        Schema::create('keuangan_barokah_dosen', function (Blueprint $table) {
            $table->id();
            $table->integer('dosen_id');
            $table->integer('jadwal_id');
            $table->integer('transprot');
            $table->integer('barokah');
            $table->integer('total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_barokah_dosen');
    }
};
