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
        ],
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'pegawai_tipe' => null,
        ],
        'keuangan_pengeluaran_dosen_bulanan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'pegawai_tipe' => 'dosen',
        ],
        'keuangan_pengeluaran_staff_bulanan_rekap' => [
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'pegawai_tipe' => 'staff',
        ],
    ];

    public function up(): void
    {
        foreach ($this->rekapSources as $tableName => $source) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'jumlah_sementara')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('jumlah_sementara')
                        ->nullable()
                        ->after('tanggal_rekap');
                });
            }

            if (! Schema::hasColumn($tableName, 'jumlah')) {
                continue;
            }

            $typeFilter = $source['pegawai_tipe']
                ? "AND `pegawai_tipe` = '{$source['pegawai_tipe']}'"
                : '';

            if (
                Schema::hasTable($source['detail_table'])
                && Schema::hasColumn($source['detail_table'], 'rekap_id')
            ) {
                DB::statement(
                    "UPDATE `{$tableName}` AS rekap
                    LEFT JOIN (
                        SELECT `rekap_id`, COUNT(*) AS `jumlah_data`
                        FROM `{$source['detail_table']}`
                        WHERE `rekap_id` IS NOT NULL {$typeFilter}
                        GROUP BY `rekap_id`
                    ) AS summary ON summary.`rekap_id` = rekap.`id`
                    SET rekap.`jumlah_sementara` = CASE
                        WHEN COALESCE(summary.`jumlah_data`, 0) = 0 THEN rekap.`jumlah`
                        ELSE NULL
                    END"
                );
            } else {
                DB::statement(
                    "UPDATE `{$tableName}`
                    SET `jumlah_sementara` = `jumlah`"
                );
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('jumlah');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->rekapSources as $tableName => $source) {
            if (
                ! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'jumlah_sementara')
            ) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'jumlah')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('jumlah')
                        ->default(0)
                        ->after('tanggal_rekap');
                });
            }

            $typeFilter = $source['pegawai_tipe']
                ? "AND `pegawai_tipe` = '{$source['pegawai_tipe']}'"
                : '';

            if (
                Schema::hasTable($source['detail_table'])
                && Schema::hasColumn($source['detail_table'], 'rekap_id')
                && Schema::hasColumn($source['detail_table'], 'total')
            ) {
                DB::statement(
                    "UPDATE `{$tableName}` AS rekap
                    LEFT JOIN (
                        SELECT
                            `rekap_id`,
                            COUNT(*) AS `jumlah_data`,
                            COALESCE(SUM(`total`), 0) AS `total`
                        FROM `{$source['detail_table']}`
                        WHERE `rekap_id` IS NOT NULL {$typeFilter}
                        GROUP BY `rekap_id`
                    ) AS summary ON summary.`rekap_id` = rekap.`id`
                    SET rekap.`jumlah` = CASE
                        WHEN COALESCE(summary.`jumlah_data`, 0) > 0
                            THEN COALESCE(summary.`total`, 0)
                        ELSE COALESCE(rekap.`jumlah_sementara`, 0)
                    END"
                );
            } else {
                DB::statement(
                    "UPDATE `{$tableName}`
                    SET `jumlah` = COALESCE(`jumlah_sementara`, 0)"
                );
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('jumlah_sementara');
            });
        }
    }
};
