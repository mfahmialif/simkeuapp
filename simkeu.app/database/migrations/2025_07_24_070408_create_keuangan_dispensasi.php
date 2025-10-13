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
            $table->unsignedBigInteger('th_akademik_id');
            $table->string('nim', 255)->nullable();
            $table->text('keterangan')->nullable;
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('th_akademik_id')->references('id')->on('th_akademik')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_dispensasi');
    }
};
