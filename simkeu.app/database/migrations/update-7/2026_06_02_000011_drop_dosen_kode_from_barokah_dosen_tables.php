<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropDosenKode('keuangan_pengeluaran_dosen');
        $this->dropDosenKode('keuangan_pengeluaran_dosen_kegiatan');
    }

    public function down(): void
    {
        $this->restoreDosenKode('keuangan_pengeluaran_dosen');
        $this->restoreDosenKode('keuangan_pengeluaran_dosen_kegiatan');
    }

    private function dropDosenKode(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'dosen_kode')) {
            return;
        }

        if ($this->indexExists($tableName, 'idx_pengeluaran_dosen_kegiatan_dosen_tanggal')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex('idx_pengeluaran_dosen_kegiatan_dosen_tanggal');
            });
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('dosen_kode');
        });
    }

    private function restoreDosenKode(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'dosen_kode')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->string('dosen_kode')->nullable()->after('pegawai_id');
        });

        if (Schema::hasColumn($tableName, 'pegawai_id')) {
            DB::table($tableName)
                ->join('pegawai', 'pegawai.id', '=', "{$tableName}.pegawai_id")
                ->update(["{$tableName}.dosen_kode" => DB::raw('pegawai.kode')]);
        }

        if ($tableName === 'keuangan_pengeluaran_dosen_kegiatan' && ! $this->indexExists($tableName, 'idx_pengeluaran_dosen_kegiatan_dosen_tanggal')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(
                    ['dosen_kode', 'tanggal'],
                    'idx_pengeluaran_dosen_kegiatan_dosen_tanggal'
                );
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $tableName = str_replace('`', '``', $tableName);

        return ! empty(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]));
    }
};
