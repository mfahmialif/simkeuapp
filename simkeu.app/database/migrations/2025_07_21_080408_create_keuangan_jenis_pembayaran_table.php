<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_jenis_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nomer_rekening')->nullable();
            $table->string('kategori')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_jenis_pembayaran');
    }
};
