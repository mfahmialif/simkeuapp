<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('keuangan_pengeluaran_dosen', 'barokah_uts')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->dropColumn('barokah_uts');
            });
        }

        if (Schema::hasColumn('keuangan_pengeluaran_dosen', 'jumlah_mahasiswa_uts')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->dropColumn('jumlah_mahasiswa_uts');
            });
        }

        Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'jumlah_mahasiswa_uas')) {
                $table->integer('jumlah_mahasiswa_uas')->nullable()->after('barokah_uas');
            }
        });

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('jumlah_mahasiswa_uas')
            ->update([
                'jumlah_mahasiswa_uas' => DB::raw('CASE WHEN COALESCE(barokah_uas, 0) > 0 THEN 1 ELSE 0 END'),
            ]);
    }

    public function down(): void
    {
        Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
            if (Schema::hasColumn('keuangan_pengeluaran_dosen', 'jumlah_mahasiswa_uas')) {
                $table->dropColumn('jumlah_mahasiswa_uas');
            }
        });
    }
};
