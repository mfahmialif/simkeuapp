<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_motor')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('hari_transport_motor')->nullable()->after('hari');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'hari_transport_mobil')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('hari_transport_mobil')->nullable()->after('hari_transport_motor');
            });
        }

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('hari_transport_motor')
            ->update([
                'hari_transport_motor' => DB::raw('COALESCE(hari, 0)'),
            ]);

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('hari_transport_mobil')
            ->update([
                'hari_transport_mobil' => 0,
            ]);
    }

    public function down(): void
    {
        $columns = array_filter([
            'hari_transport_motor',
            'hari_transport_mobil',
        ], fn ($column) => Schema::hasColumn('keuangan_pengeluaran_dosen', $column));

        if ($columns) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
