<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'keuangan_pengeluaran_dosen' => [
            'idx_pengeluaran_dosen_tanggal' => ['tanggal'],
        ],
        'keuangan_pengeluaran_dosen_kegiatan' => [
            'idx_pengeluaran_dosen_kegiatan_tanggal' => ['tanggal'],
        ],
        'keuangan_pengeluaran_pegawai_bulanan' => [
            'idx_pengeluaran_pegawai_bulanan_tanggal' => ['tanggal'],
            'idx_pengeluaran_pegawai_bulanan_pegawai_periode' => ['pegawai_id', 'tahun', 'bulan'],
            'idx_pengeluaran_pegawai_bulanan_tipe_stats' => ['pegawai_tipe', 'tanggal', 'rekap_id', 'total'],
            'idx_pengeluaran_pegawai_bulanan_tipe_rekap' => ['pegawai_tipe', 'rekap_id', 'total'],
            'idx_pengeluaran_pegawai_bulanan_tipe_periode' => ['pegawai_tipe', 'tahun', 'bulan'],
        ],
    ];

    public function up(): void
    {
        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            && ! Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'pegawai_tipe')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
                $table->string('pegawai_tipe', 10)->nullable()->after('pegawai_id');
            });

            DB::statement(
                'UPDATE keuangan_pengeluaran_pegawai_bulanan AS detail
                INNER JOIN pegawai ON pegawai.id = detail.pegawai_id
                SET detail.pegawai_tipe = pegawai.tipe
                WHERE detail.pegawai_tipe IS NULL'
            );
        }

        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if ($this->indexExists($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        }

    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $indexName) {
                if (! $this->indexExists($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'pegawai_tipe')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
                $table->dropColumn('pegawai_tipe');
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
