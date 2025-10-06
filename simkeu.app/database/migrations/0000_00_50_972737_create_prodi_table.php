<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('konim')->nullable();
            $table->string('alias')->nullable();
            $table->string('nama');
            $table->enum('aktif', ['Y', 'N'])->default('Y');
            $table->string('jenjang')->nullable();
            $table->string('nidn_kepala')->nullable();
            $table->string('nama_kepala')->nullable();
            $table->string('akreditasi')->nullable();
            $table->string('color', 10)->nullable();
            $table->integer('max_sks_skripsi')->default(0);
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodi');
    }
};
