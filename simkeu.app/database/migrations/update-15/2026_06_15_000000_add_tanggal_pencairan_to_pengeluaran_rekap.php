<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'keuangan_pengeluaran_dosen_rekap' => 'idx_dosen_rekap_tanggal_pencairan',
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => 'idx_dosen_kegiatan_rekap_tgl_cair',
        'keuangan_pengeluaran_dosen_bulanan_rekap' => 'idx_dosen_bulanan_rekap_tgl_cair',
        'keuangan_pengeluaran_rumah_tangga_rekap' => 'idx_rumah_tangga_rekap_tgl_cair',
        'keuangan_pengeluaran_sarana_prasarana_rekap' => 'idx_sarpras_rekap_tgl_cair',
        'keuangan_pengeluaran_transportasi_rekap' => 'idx_transportasi_rekap_tgl_cair',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $indexName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tanggal_pencairan')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->date('tanggal_pencairan')
                    ->nullable()
                    ->after('tanggal_rekap')
                    ->index($indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName => $indexName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tanggal_pencairan')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
                $table->dropColumn('tanggal_pencairan');
            });
        }
    }
};
