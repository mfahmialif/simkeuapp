<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('keuangan_catatan_harian');
    }
};
