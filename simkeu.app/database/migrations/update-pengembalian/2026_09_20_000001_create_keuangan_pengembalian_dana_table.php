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
        Schema::create('keuangan_pengembalian_dana', function (Blueprint $table) {
            $table->id();
            $table->decimal('nominal', 15, 2);
            $table->dateTime('tanggal');
            $table->unsignedBigInteger('petugas_id');
            $table->string('file_bukti_masuk');
            $table->string('file_bukti_keluar')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('petugas_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengembalian_dana');
    }
};
