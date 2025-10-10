<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_pembayaran_tambahan', function (Blueprint $table) {
            $table->id();
            $table->string('nota')->nullable();
            $table->string('nomor', 20)->nullable();
            $table->dateTime('tanggal');
            $table->string('th_akademik')->nullable();
            $table->string('tagihan');
            $table->string('nim', 20);
            $table->string('nama');
            $table->string('jenis_kelamin')->nullable();
            $table->string('prodi')->nullable();
            $table->string('kelas')->nullable();
            $table->string('th_angkatan')->nullable();
            $table->string('jenis_pembayaran')->nullable();
            $table->string('smt')->nullable();
            $table->integer('jml_sks')->nullable();
            $table->double('jumlah')->nullable();
            $table->string('bayar')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pembayaran_tambahan');
    }
};
