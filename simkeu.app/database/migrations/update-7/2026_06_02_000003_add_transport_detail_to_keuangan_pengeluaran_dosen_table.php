<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_motor')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('transport_motor')->nullable()->after('transport');
            });
        }

        if (! Schema::hasColumn('keuangan_pengeluaran_dosen', 'transport_mobil')) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
                $table->integer('transport_mobil')->nullable()->after('transport_motor');
            });
        }

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('transport_motor')
            ->update([
                'transport_motor' => DB::raw('COALESCE(transport, 0)'),
            ]);

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('transport_mobil')
            ->update([
                'transport_mobil' => 0,
            ]);
    }

    public function down(): void
    {
        $columns = array_filter([
            'transport_motor',
            'transport_mobil',
        ], fn ($column) => Schema::hasColumn('keuangan_pengeluaran_dosen', $column));

        if ($columns) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
