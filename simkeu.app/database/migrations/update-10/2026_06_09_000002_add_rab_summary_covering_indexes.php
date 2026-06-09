<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'keuangan_pengeluaran_dosen' => [
            'name' => 'idx_pengeluaran_dosen_rekap_total',
            'columns' => ['rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_dosen_kegiatan' => [
            'name' => 'idx_pengeluaran_dosen_kegiatan_rekap_total',
            'columns' => ['rekap_id', 'total'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $definition) {
            if (
                ! Schema::hasTable($tableName)
                || $this->indexExists($tableName, $definition['name'])
            ) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($definition) {
                $table->index($definition['columns'], $definition['name']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $definition) {
            if (
                ! Schema::hasTable($tableName)
                || ! $this->indexExists($tableName, $definition['name'])
            ) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($definition) {
                $table->dropIndex($definition['name']);
            });
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
