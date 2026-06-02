<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_dosen_kegiatan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_dosen_kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pengeluaran_dosen_kegiatan', 'tanggal')) {
                $table->date('tanggal')->nullable()->after('id');
            }

            if (! Schema::hasColumn('keuangan_pengeluaran_dosen_kegiatan', 'dosen_kode')) {
                $table->string('dosen_kode')->nullable()->after('tanggal');
            }

            $table->index(
                ['dosen_kode', 'tanggal'],
                'idx_pengeluaran_dosen_kegiatan_dosen_tanggal'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_dosen_kegiatan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_dosen_kegiatan', function (Blueprint $table) {
            $table->dropIndex('idx_pengeluaran_dosen_kegiatan_dosen_tanggal');

            if (Schema::hasColumn('keuangan_pengeluaran_dosen_kegiatan', 'dosen_kode')) {
                $table->dropColumn('dosen_kode');
            }

            if (Schema::hasColumn('keuangan_pengeluaran_dosen_kegiatan', 'tanggal')) {
                $table->dropColumn('tanggal');
            }
        });
    }
};
