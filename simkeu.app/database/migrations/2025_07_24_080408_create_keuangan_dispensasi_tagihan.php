<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_dispensasi_tagihan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');               // NOT NULL
            $table->string('jenis', 255)->nullable(); // NULL
            $table->unsignedBigInteger('tagihan_id');      // NOT NULL
            $table->string('nim', 255);               // NOT NULL
            $table->double('jumlah')->nullable();     // NULL
            $table->unsignedBigInteger('th_akademik_id');        // NOT NULL
            $table->date('batas')->nullable();        // NULL
            $table->text('keterangan')->nullable();   // NULL
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('th_akademik_id')->references('id')->on('th_akademik')->onDelete('cascade');
            $table->foreign('tagihan_id')->references('id')->on('keuangan_tagihan')->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_dispensasi_tagihan');
    }
};
