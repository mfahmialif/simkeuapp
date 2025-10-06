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
        Schema::create('form_schadule', function (Blueprint $table) {
            $table->id();
            $table->string('kode'); // Kode singkat, misal 'REG','NREG','L'
            $table->string('nama'); // Nama lengkap
            $table->datetime('tgl_mulai')->nullable();
            $table->datetime('tgl_selesai')->nullable();
            $table->string('semester')->nullable();
            $table->enum('aktif', ['Y', 'T'])->default('Y')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_schadule');
    }
};
