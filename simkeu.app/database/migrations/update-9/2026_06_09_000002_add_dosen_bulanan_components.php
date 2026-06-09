<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'barokah_dosen_tetap')) {
                $table->integer('barokah_dosen_tetap')->default(0)->after('barokah_bulanan');
            }

            if (! Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'barokah_struktural')) {
                $table->integer('barokah_struktural')->default(0)->after('barokah_dosen_tetap');
            }
        });

        DB::table('keuangan_pengeluaran_pegawai_bulanan')
            ->where('barokah_dosen_tetap', 0)
            ->where('barokah_struktural', 0)
            ->whereIn('pegawai_id', function ($query) {
                $query->select('id')
                    ->from('pegawai')
                    ->where('tipe', 'dosen');
            })
            ->update([
                'barokah_dosen_tetap' => DB::raw('total'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            if (Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'barokah_struktural')) {
                $table->dropColumn('barokah_struktural');
            }

            if (Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'barokah_dosen_tetap')) {
                $table->dropColumn('barokah_dosen_tetap');
            }
        });
    }
};
