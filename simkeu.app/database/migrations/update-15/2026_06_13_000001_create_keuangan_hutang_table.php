<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_hutang')) {
            return;
        }

        Schema::create('keuangan_hutang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            $table->string('pemberi_pinjaman', 150);
            $table->date('tanggal');
            $table->enum('tipe', ['hutang', 'pelunasan']);
            $table->unsignedBigInteger('nominal');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['petugas_id', 'tanggal'], 'idx_keuangan_hutang_petugas_tanggal');
            $table->index(['petugas_id', 'pemberi_pinjaman'], 'idx_keuangan_hutang_petugas_pemberi');
            $table->index('tipe', 'idx_keuangan_hutang_tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_hutang');
    }
};
