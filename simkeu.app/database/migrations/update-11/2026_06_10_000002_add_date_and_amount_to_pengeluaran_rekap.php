<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $rekapSources = [
        'keuangan_pengeluaran_dosen_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'pegawai_tipe' => null,
            'index' => 'idx_dosen_rekap_tanggal',
        ],
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'pegawai_tipe' => null,
            'index' => 'idx_dosen_kegiatan_rekap_tanggal',
        ],
        'keuangan_pengeluaran_dosen_bulanan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'pegawai_tipe' => 'dosen',
            'index' => 'idx_dosen_bulanan_rekap_tanggal',
        ],
        'keuangan_pengeluaran_staff_bulanan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'pegawai_tipe' => 'staff',
            'index' => 'idx_staff_bulanan_rekap_tanggal',
        ],
    ];

    public function up(): void
    {
        foreach ($this->rekapSources as $tableName => $source) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $dateAdded = ! Schema::hasColumn($tableName, 'tanggal_rekap');
            $amountAdded = ! Schema::hasColumn($tableName, 'jumlah');

            if ($dateAdded) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->date('tanggal_rekap')->nullable()->after('bulan_tahun');
                });
            }

            if ($amountAdded) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('jumlah')->default(0)->after('tanggal_rekap');
                });
            }

            if ($dateAdded) {
                DB::statement(
                    "UPDATE `{$tableName}`
                    SET `tanggal_rekap` = DATE(COALESCE(`created_at`, CURRENT_DATE))
                    WHERE `tanggal_rekap` IS NULL"
                );
            }

            if (
                $amountAdded
                && Schema::hasTable($source['detail_table'])
                && Schema::hasColumn($source['detail_table'], 'rekap_id')
                && Schema::hasColumn($source['detail_table'], 'total')
            ) {
                $typeFilter = $source['pegawai_tipe']
                    ? "AND `pegawai_tipe` = '{$source['pegawai_tipe']}'"
                    : '';

                DB::statement(
                    "UPDATE `{$tableName}` AS rekap
                    LEFT JOIN (
                        SELECT `rekap_id`, COALESCE(SUM(`total`), 0) AS `total`
                        FROM `{$source['detail_table']}`
                        WHERE `rekap_id` IS NOT NULL {$typeFilter}
                        GROUP BY `rekap_id`
                    ) AS summary ON summary.`rekap_id` = rekap.`id`
                    SET rekap.`jumlah` = COALESCE(summary.`total`, 0)"
                );
            }

            if (! $this->indexExists($tableName, $source['index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($source) {
                    $table->index('tanggal_rekap', $source['index']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rekapSources as $tableName => $source) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if ($this->indexExists($tableName, $source['index'])) {
                Schema::table($tableName, function (Blueprint $table) use ($source) {
                    $table->dropIndex($source['index']);
                });
            }

            $columns = array_values(array_filter(
                ['tanggal_rekap', 'jumlah'],
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
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
