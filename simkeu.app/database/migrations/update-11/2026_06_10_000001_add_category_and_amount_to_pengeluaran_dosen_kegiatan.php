<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keuangan_pengeluaran_dosen_kegiatan', function (Blueprint $table) {
            $table->string('kategori_detail', 20)
                ->default('pegawai')
                ->after('pegawai_id');
            $table->unsignedBigInteger('nominal')
                ->nullable()
                ->after('barokah');
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pengeluaran_dosen_kegiatan', function (Blueprint $table) {
            $table->dropColumn(['kategori_detail', 'nominal']);
        });
    }
};
