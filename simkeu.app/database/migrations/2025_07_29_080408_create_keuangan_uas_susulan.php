<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_uas_susulan', function (Blueprint $table) {
            $table->id();
            $table->integer('th_akademik_id');
            $table->date('tanggal');
            $table->string('nim');
            $table->text('keterangan')->nullable();
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_uas_susulan');
    }
};
