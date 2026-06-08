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
    ];

    private array $pengeluaranTables = [
        'keuangan_pengeluaran_dosen' => 'idx_pengeluaran_dosen_rekap_id',
        'keuangan_pengeluaran_dosen_kegiatan' => 'idx_pengeluaran_dosen_kegiatan_rekap_id',
        'keuangan_pengeluaran_pegawai_bulanan' => 'idx_pengeluaran_pegawai_bulanan_rekap_id',
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName) {
            $this->createRekapTable($tableName);
        }

        foreach ($this->pengeluaranTables as $tableName => $indexName) {
            $this->addRekapIdColumn($tableName, $indexName);
        }
    }

    public function down(): void
    {
        foreach ($this->pengeluaranTables as $tableName => $indexName) {
            $this->dropRekapIdColumn($tableName, $indexName);
        }

        foreach (array_reverse($this->rekapTables) as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    private function createRekapTable(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique('nama');
        });
    }

    private function addRekapIdColumn(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'rekap_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->unsignedBigInteger('rekap_id')->nullable()->after('pegawai_id');
            $table->index('rekap_id', $indexName);
        });
    }

    private function dropRekapIdColumn(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'rekap_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
            $table->dropColumn('rekap_id');
        });
    }
};
