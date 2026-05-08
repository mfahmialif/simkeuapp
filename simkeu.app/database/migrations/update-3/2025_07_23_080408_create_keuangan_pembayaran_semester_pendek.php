<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_pembayaran_semester_pendek', function (Blueprint $table) {
            $table->id();
            $table->string('nomor');
            $table->datetime('tanggal');
            $table->foreignId('th_akademik_id')->constrained('th_akademik');
            $table->integer('periode_id')->nullable();
            $table->integer('krs_id');
            $table->double('jumlah');
            $table->integer('jk_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pembayaran_semester_pendek');
    }
};
