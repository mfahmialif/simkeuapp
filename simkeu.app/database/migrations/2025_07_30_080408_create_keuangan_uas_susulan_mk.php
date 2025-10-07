<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_uas_susulan_mk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uas_susulan_id')->constrained('keuangan_uas_susulan_mk');
            $table->integer('jadwal_kuliah_id');
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_uas_susulan');
    }
};
