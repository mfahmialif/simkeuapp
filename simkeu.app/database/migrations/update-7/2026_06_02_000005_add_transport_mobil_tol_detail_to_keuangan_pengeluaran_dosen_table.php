<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_mobil_tol')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('hari_transport_mobil_tol')->nullable()->after('hari_transport_mobil');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_mobil_tanpa_tol')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('hari_transport_mobil_tanpa_tol')->nullable()->after('hari_transport_mobil_tol');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_mobil_tol')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('transport_mobil_tol')->nullable()->after('transport_mobil');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_mobil_tanpa_tol')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('transport_mobil_tanpa_tol')->nullable()->after('transport_mobil_tol');
            });
        }

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('hari_transport_mobil_tol')
            ->update(['hari_transport_mobil_tol' => 0]);

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('hari_transport_mobil_tanpa_tol')
            ->update([
                'hari_transport_mobil_tanpa_tol' => DB::raw('COALESCE(hari_transport_mobil, 0)'),
            ]);

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('transport_mobil_tol')
            ->update(['transport_mobil_tol' => 0]);

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('transport_mobil_tanpa_tol')
            ->update([
                'transport_mobil_tanpa_tol' => DB::raw('COALESCE(transport_mobil, 0)'),
            ]);
    }

    public function down(): void
    {
        $columns = array_filter([
            'hari_transport_mobil_tol',
            'hari_transport_mobil_tanpa_tol',
            'transport_mobil_tol',
            'transport_mobil_tanpa_tol',
        ], fn ($column) => Schema::hasColumn('keuangan_pengeluaran_dosen', $column));

        if ($columns) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
