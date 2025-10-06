<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_kamar_mhs', function (Blueprint $table) {
            $table->id();
            $table->string('nim');
            $table->foreignId('kamar_id')->constrained('keuangan_kamar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_kamar_mhs');
    }
};
