<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'jam_sempro')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('jam_sempro')->nullable()->after('barokah_sempro');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'keterangan_sempro')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->string('keterangan_sempro')->nullable()->after('jam_sempro');
            });
        }

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('jam_sempro')
            ->update([
                'jam_sempro' => DB::raw('CASE WHEN COALESCE(barokah_sempro, 0) > 0 THEN 1 ELSE 0 END'),
            ]);

        if (
            Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_mobil_tol')
            && Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_mobil_tanpa_tol')
        ) {
            DB::table('keuangan_pengeluaran_dosen')
                ->update([
                    'transport_mobil' => DB::raw('CASE WHEN COALESCE(transport_mobil, 0) > 0 THEN transport_mobil ELSE COALESCE(transport_mobil_tol, 0) + COALESCE(transport_mobil_tanpa_tol, 0) END'),
                    'transport_mobil_tol' => 0,
                    'transport_mobil_tanpa_tol' => DB::raw('CASE WHEN COALESCE(transport_mobil, 0) > 0 THEN transport_mobil ELSE COALESCE(transport_mobil_tol, 0) + COALESCE(transport_mobil_tanpa_tol, 0) END'),
                ]);
        }

        if (
            Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_mobil_tol')
            && Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_mobil_tanpa_tol')
        ) {
            DB::table('keuangan_pengeluaran_dosen')
                ->update([
                    'hari_transport_mobil' => DB::raw('CASE WHEN COALESCE(hari_transport_mobil, 0) > 0 THEN hari_transport_mobil ELSE COALESCE(hari_transport_mobil_tol, 0) + COALESCE(hari_transport_mobil_tanpa_tol, 0) END'),
                    'hari_transport_mobil_tol' => 0,
                    'hari_transport_mobil_tanpa_tol' => DB::raw('CASE WHEN COALESCE(hari_transport_mobil, 0) > 0 THEN hari_transport_mobil ELSE COALESCE(hari_transport_mobil_tol, 0) + COALESCE(hari_transport_mobil_tanpa_tol, 0) END'),
                ]);
        }
    }

    public function down(): void
    {
        $columns = array_filter([
            'keterangan_sempro',
            'jam_sempro',
        ], fn ($column) => Schema::hasColumn('keuangan_pengeluaran_dosen', $column));

        if ($columns) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
