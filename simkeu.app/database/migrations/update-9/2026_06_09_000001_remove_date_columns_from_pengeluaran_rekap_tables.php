<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $rekapTables = [
        'keuangan_pengeluaran_dosen_rekap',
        'keuangan_pengeluaran_dosen_kegiatan_rekap',
        'keuangan_pengeluaran_dosen_bulanan_rekap',
        'keuangan_pengeluaran_staff_bulanan_rekap',
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_filter(
                ['tanggal_mulai', 'tanggal_akhir'],
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));

            if ($columns === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->rekapTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'tanggal_mulai')) {
                    $table->date('tanggal_mulai')->nullable()->after('nama');
                }

                if (! Schema::hasColumn($tableName, 'tanggal_akhir')) {
                    $table->date('tanggal_akhir')->nullable()->after('tanggal_mulai');
                }
            });
        }
    }
};
