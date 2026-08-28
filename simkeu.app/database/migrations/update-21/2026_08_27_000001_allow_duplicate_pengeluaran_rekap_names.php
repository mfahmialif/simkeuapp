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
        'keuangan_pengeluaran_rumah_tangga_rekap',
        'keuangan_pengeluaran_sarana_prasarana_rekap',
        'keuangan_pengeluaran_transportasi_rekap',
        'keuangan_pengeluaran_umum_rekap',
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'nama')) {
                continue;
            }

            $uniqueNamaIndexes = collect(Schema::getIndexes($tableName))
                ->filter(fn (array $index) => $index['unique']
                    && ! $index['primary']
                    && $index['columns'] === ['nama'])
                ->pluck('name')
                ->all();

            foreach ($uniqueNamaIndexes as $indexName) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rekapTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'nama')) {
                continue;
            }

            $hasUniqueNamaIndex = collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index) => $index['unique']
                    && ! $index['primary']
                    && $index['columns'] === ['nama']);

            if (! $hasUniqueNamaIndex) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unique('nama');
                });
            }
        }
    }
};
