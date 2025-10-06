<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('th_akademik_id')->constrained('th_akademik');
            $table->foreignId('th_angkatan_id')->constrained('th_akademik');
            $table->foreignId('prodi_id')->constrained('prodi');
            $table->tinyInteger('double_degree')->nullable();
            $table->foreignId('kelas_id')->constrained('ref');
            $table->foreignId('form_schadule_id')->constrained('form_schadule');
            $table->string('kode')->nullable();
            $table->string('nama')->nullable();
            $table->double('jumlah')->nullable();
            $table->enum('x_sks', ['Y', 'T'])->nullable();
            // $table->foreignId('user_id')->constrained('users');
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_tagihan');
    }
};
