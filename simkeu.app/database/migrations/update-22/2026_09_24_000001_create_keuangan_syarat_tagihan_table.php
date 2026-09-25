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
        Schema::create('keuangan_syarat_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('tagihan_nama');
            $table->string('syarat_nama')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['tagihan_nama', 'is_active']);
            $table->unique(['tagihan_nama', 'syarat_nama']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_syarat_tagihan');
    }
};
