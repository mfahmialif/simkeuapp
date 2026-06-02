<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPegawaiId('keuangan_pengeluaran_dosen');
        $this->addPegawaiId('keuangan_pengeluaran_dosen_kegiatan');

        $this->backfillPegawaiId('keuangan_pengeluaran_dosen');
        $this->backfillPegawaiId('keuangan_pengeluaran_dosen_kegiatan');
    }

    public function down(): void
    {
        $this->dropPegawaiId('keuangan_pengeluaran_dosen', 'idx_pengeluaran_dosen_pegawai_tanggal');
        $this->dropPegawaiId('keuangan_pengeluaran_dosen_kegiatan', 'idx_pengeluaran_dosen_kegiatan_pegawai_tanggal');
    }

    private function addPegawaiId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'pegawai_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreignId('pegawai_id')->nullable()->after('id')->constrained('pegawai')->nullOnDelete();
        });

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $indexName = $tableName === 'keuangan_pengeluaran_dosen'
                ? 'idx_pengeluaran_dosen_pegawai_tanggal'
                : 'idx_pengeluaran_dosen_kegiatan_pegawai_tanggal';

            $table->index(['pegawai_id', 'tanggal'], $indexName);
        });
    }

    private function backfillPegawaiId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'pegawai_id') || ! Schema::hasColumn($tableName, 'dosen_kode')) {
            return;
        }

        DB::table($tableName)
            ->join('pegawai', function ($join) use ($tableName) {
                $join->where('pegawai.tipe', 'dosen')
                    ->whereRaw("CAST(pegawai.kode AS CHAR) = CAST({$tableName}.dosen_kode AS CHAR)");
            })
            ->whereNull("{$tableName}.pegawai_id")
            ->update(["{$tableName}.pegawai_id" => DB::raw('pegawai.id')]);
    }

    private function dropPegawaiId(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'pegawai_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
            $table->dropConstrainedForeignId('pegawai_id');
        });
    }
};
