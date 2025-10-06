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
            $table->integer('user_id');               // NOT NULL
            $table->string('jenis', 255)->nullable(); // NULL
            $table->integer('jenis_tagihan_id');      // NOT NULL
            $table->string('nim', 255);               // NOT NULL
            $table->double('jumlah')->nullable();     // NULL
            $table->integer('th_akademik_id');        // NOT NULL
            $table->date('batas')->nullable();        // NULL
            $table->text('keterangan')->nullable();   // NULL

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_dispensasi_tagihan');
    }
};
