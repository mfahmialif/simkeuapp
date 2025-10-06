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
        Schema::create('ref', function (Blueprint $table) {
            $table->id();
            $table->string('table');       // Nama kelompok referensi, misal 'Kelas', 'Agama', dst
            $table->string('kode');        // Kode singkat, misal 'REG','NREG','L'
            $table->string('nama');        // Nama lengkap
            $table->string('param')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref');
    }
};
