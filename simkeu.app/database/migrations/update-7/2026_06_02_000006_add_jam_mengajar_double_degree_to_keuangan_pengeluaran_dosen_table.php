<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'jam_mengajar_double_degree')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('jam_mengajar_double_degree')->nullable()->after('jam');
            });
        }

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('jam_mengajar_double_degree')
            ->update([
                'jam_mengajar_double_degree' => DB::raw(
                    'CASE WHEN COALESCE(barokah_mengajar_double_degree, 0) > 0 THEN COALESCE(jam, 0) ELSE 0 END'
                ),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('keuangan_pengeluaran_dosen', 'jam_mengajar_double_degree')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->dropColumn('jam_mengajar_double_degree');
            });
        }
    }
};
