<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pegawai') || Schema::hasColumn('pegawai', 'nama_pemilik_rekening')) {
            return;
        }

        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nama_pemilik_rekening')->nullable()->after('nomer_rekening');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pegawai') || ! Schema::hasColumn('pegawai', 'nama_pemilik_rekening')) {
            return;
        }

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('nama_pemilik_rekening');
        });
    }
};
