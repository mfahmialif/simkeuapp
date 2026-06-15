<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pimpinan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jabatan');
            $table->string('file_ttd')->nullable();
            $table->string('mode_ttd', 10)->default('file');
            $table->date('tanggal_awal_menjabat');
            $table->date('tanggal_akhir_menjabat')->nullable();
            $table->string('status', 20)->default('tidak_aktif');
            $table->timestamps();

            $table->index(
                ['status', 'tanggal_awal_menjabat', 'tanggal_akhir_menjabat'],
                'pimpinan_status_periode_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pimpinan');
    }
};
