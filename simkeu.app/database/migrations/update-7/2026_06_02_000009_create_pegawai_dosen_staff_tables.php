<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->enum('tipe', ['dosen', 'staff']);
            $table->string('kode')->unique();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('hp')->nullable();
            $table->string('nomer_rekening')->nullable();
            $table->string('bank')->nullable();
            $table->enum('status', ['aktif', 'tidak aktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::create('dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->unique()->constrained('pegawai')->cascadeOnDelete();
            $table->string('kode')->nullable()->unique();
            $table->string('nidn')->nullable()->unique();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->foreignId('prodi_id')->nullable()->constrained('prodi')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->unique()->constrained('pegawai')->cascadeOnDelete();
            $table->string('jabatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
        Schema::dropIfExists('dosen');
        Schema::dropIfExists('pegawai');
    }
};
