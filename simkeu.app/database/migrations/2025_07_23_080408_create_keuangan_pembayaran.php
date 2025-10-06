<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->datetime('tanggal');
            $table->foreignId('th_akademik_id')->constrained('th_akademik');
            // $table->foreignId('tagihan_id')->constrained('keuangan_tagihan');
            $table->integer('tagihan_id');
            $table->string('nim');
            $table->integer('smt');
            $table->integer('jml_sks');
            $table->double('jumlah');
            // $table->foreignId('user_id')->constrained('users');
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pembayaran');
    }
};
