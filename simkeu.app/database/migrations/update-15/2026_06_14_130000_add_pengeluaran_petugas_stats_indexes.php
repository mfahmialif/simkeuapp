<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'keuangan_pengeluaran_dosen' => [
            'idx_kpd_petugas_stats' => ['petugas_id', 'tanggal', 'rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_dosen_kegiatan' => [
            'idx_kpdk_petugas_stats' => ['petugas_id', 'tanggal', 'rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_pegawai_bulanan' => [
            'idx_kppb_petugas_tipe_stats' => ['petugas_id', 'pegawai_tipe', 'tanggal', 'rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_rumah_tangga' => [
            'idx_kprt_petugas_stats' => ['petugas_id', 'tanggal', 'rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_sarana_prasarana' => [
            'idx_kpsp_petugas_stats' => ['petugas_id', 'tanggal', 'rekap_id', 'total'],
        ],
        'keuangan_pengeluaran_transportasi' => [
            'idx_kpt_petugas_stats' => ['petugas_id', 'tanggal', 'rekap_id', 'total'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $index => $columns) {
                if ($this->indexExists($table, $index)) {
                    continue;
                }

                $columnSql = collect($columns)
                    ->map(fn ($column) => "`{$column}`")
                    ->implode(', ');

                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnSql})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $index) {
                if (! $this->indexExists($table, $index)) {
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))
            ->isNotEmpty();
    }
};
