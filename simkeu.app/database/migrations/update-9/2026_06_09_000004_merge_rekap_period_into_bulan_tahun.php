<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $rekapTables = [
        'keuangan_pengeluaran_dosen_rekap' => [
            'old_index' => 'idx_dosen_rekap_tahun_bulan',
            'new_index' => 'idx_dosen_rekap_bulan_tahun',
        ],
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => [
            'old_index' => 'idx_dosen_kegiatan_rekap_tahun_bulan',
            'new_index' => 'idx_dosen_kegiatan_rekap_bulan_tahun',
        ],
        'keuangan_pengeluaran_dosen_bulanan_rekap' => [
            'old_index' => 'idx_dosen_bulanan_rekap_tahun_bulan',
            'new_index' => 'idx_dosen_bulanan_rekap_bulan_tahun',
        ],
        'keuangan_pengeluaran_staff_bulanan_rekap' => [
            'old_index' => 'idx_staff_bulanan_rekap_tahun_bulan',
            'new_index' => 'idx_staff_bulanan_rekap_bulan_tahun',
        ],
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'bulan_tahun')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->date('bulan_tahun')->nullable()->after('nama');
                });
            }

            if (Schema::hasColumn($tableName, 'bulan') && Schema::hasColumn($tableName, 'tahun')) {
                DB::statement(
                    "UPDATE `{$tableName}`
                    SET `bulan_tahun` = STR_TO_DATE(
                        CONCAT(`tahun`, '-', LPAD(`bulan`, 2, '0'), '-01'),
                        '%Y-%m-%d'
                    )
                    WHERE `bulan_tahun` IS NULL
                        AND `bulan` BETWEEN 1 AND 12
                        AND `tahun` IS NOT NULL"
                );
            }

            DB::statement(
                "UPDATE `{$tableName}`
                SET `bulan_tahun` = DATE_FORMAT(COALESCE(`created_at`, CURRENT_DATE), '%Y-%m-01')
                WHERE `bulan_tahun` IS NULL"
            );

            if ($this->indexExists($tableName, $indexes['old_index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                    $table->dropIndex($indexes['old_index']);
                });
            }

            $oldColumns = array_values(array_filter(
                ['bulan', 'tahun'],
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));

            if ($oldColumns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($oldColumns) {
                    $table->dropColumn($oldColumns);
                });
            }

            if (! $this->indexExists($tableName, $indexes['new_index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                    $table->index('bulan_tahun', $indexes['new_index']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rekapTables as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'bulan')) {
                    $table->unsignedTinyInteger('bulan')->nullable()->after('nama');
                }

                if (! Schema::hasColumn($tableName, 'tahun')) {
                    $table->unsignedSmallInteger('tahun')->nullable()->after('bulan');
                }
            });

            if (Schema::hasColumn($tableName, 'bulan_tahun')) {
                DB::statement(
                    "UPDATE `{$tableName}`
                    SET `bulan` = MONTH(`bulan_tahun`),
                        `tahun` = YEAR(`bulan_tahun`)
                    WHERE `bulan_tahun` IS NOT NULL"
                );
            }

            if ($this->indexExists($tableName, $indexes['new_index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                    $table->dropIndex($indexes['new_index']);
                });
            }

            if (! $this->indexExists($tableName, $indexes['old_index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes) {
                    $table->index(['tahun', 'bulan'], $indexes['old_index']);
                });
            }

            if (Schema::hasColumn($tableName, 'bulan_tahun')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('bulan_tahun');
                });
            }
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return ! empty(DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        ));
    }
};
