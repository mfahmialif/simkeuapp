<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_jenis_pembayaran_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_pembayaran_id')->constrainer('keuangan_jenis_pembayaran');
            $table->foreignId('pembayaran_id')->constrainer('keuangan_pembayaran');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_jenis_pembayaran_detail');
    }
};
