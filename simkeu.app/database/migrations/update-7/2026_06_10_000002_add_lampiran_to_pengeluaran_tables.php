<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'keuangan_saldo_pengeluaran',
        'keuangan_pengeluaran_dosen',
        'keuangan_pengeluaran_dosen_kegiatan',
        'keuangan_pengeluaran_pegawai_bulanan',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'lampiran')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->json('lampiran')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'lampiran')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('lampiran');
            });
        }
    }
};
