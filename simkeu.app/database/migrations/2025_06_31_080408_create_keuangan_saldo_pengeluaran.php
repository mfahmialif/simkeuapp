<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saldo_id')->constrained('keuangan_saldo');
            $table->double('jumlah');
            $table->date('tanggal');
            $table->text('keterangan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_saldo_pengeluaran');
    }
};
