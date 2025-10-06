<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_dispensasi', function (Blueprint $table) {
            $table->id();
            $table->integer('th_akademik_id');
            $table->string('nim', 255)->nullable();
            $table->text('keterangan')->nullable;
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_dispensasi');
    }
};
