<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'bulan')) {
                $table->unsignedTinyInteger('bulan')->nullable()->after('pegawai_id');
            }

            if (! Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'tahun')) {
                $table->unsignedSmallInteger('tahun')->nullable()->after('bulan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            if (Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'tahun')) {
                $table->dropColumn('tahun');
            }

            if (Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'bulan')) {
                $table->dropColumn('bulan');
            }
        });
    }
};
