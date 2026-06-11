<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop child tables first (foreign key dependencies)
        Schema::dropIfExists('keuangan_catatan_harian');
        Schema::dropIfExists('keuangan_saldo_pengeluaran');
        Schema::dropIfExists('keuangan_saldo_pemasukan');

        // Drop parent table
        Schema::dropIfExists('keuangan_saldo');
    }

    public function down(): void
    {
        // Recreate parent table
        Schema::create('keuangan_saldo', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->double('saldo', 20, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // Recreate child tables
        Schema::create('keuangan_saldo_pemasukan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_id')->constrained('keuangan_saldo');
            $table->double('jumlah');
            $table->date('tanggal');
            $table->text('keterangan');
            $table->timestamps();
        });

        Schema::create('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_id')->constrained('keuangan_saldo');
            $table->double('jumlah');
            $table->date('tanggal');
            $table->text('keterangan');
            $table->timestamps();
        });

        Schema::create('keuangan_catatan_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_id')->constrained('keuangan_saldo');
            $table->enum('tipe', ['pemasukan', 'pengeluaran']);
            $table->double('jumlah', 20, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
};
