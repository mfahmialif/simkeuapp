<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $rekapTables = [
        'keuangan_pengeluaran_dosen_rekap' => 'idx_dosen_rekap_cetak_rab',
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => 'idx_dosen_kegiatan_rekap_cetak_rab',
        'keuangan_pengeluaran_dosen_bulanan_rekap' => 'idx_dosen_bulanan_rekap_cetak_rab',
        'keuangan_pengeluaran_rumah_tangga_rekap' => 'idx_rumah_tangga_rekap_cetak_rab',
        'keuangan_pengeluaran_sarana_prasarana_rekap' => 'idx_sarpras_rekap_cetak_rab',
        'keuangan_pengeluaran_transportasi_rekap' => 'idx_transportasi_rekap_cetak_rab',
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName => $indexName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'cetak_rab')) {
                continue;
            }

            $afterColumn = Schema::hasColumn($tableName, 'tanggal_pencairan')
                ? 'tanggal_pencairan'
                : 'tanggal_rekap';

            Schema::table($tableName, function (Blueprint $table) use ($afterColumn, $indexName) {
                $table->boolean('cetak_rab')
                    ->default(false)
                    ->after($afterColumn)
                    ->index($indexName);
            });
        }

        if (! Schema::hasTable('keuangan_cetak_rab')) {
            Schema::create('keuangan_cetak_rab', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal_cetak')->index('idx_keuangan_cetak_rab_tanggal');
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keuangan_cetak_rab_detail')) {
            Schema::create('keuangan_cetak_rab_detail', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cetak_rab_id')
                    ->constrained('keuangan_cetak_rab')
                    ->cascadeOnDelete();
                $table->string('module_key', 32);
                $table->unsignedBigInteger('rekap_id');
                $table->timestamps();

                $table->unique(['cetak_rab_id', 'module_key', 'rekap_id'], 'uq_cetak_rab_detail_batch_rekap');
                $table->index(['module_key', 'rekap_id'], 'idx_cetak_rab_detail_module_rekap');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_cetak_rab_detail');
        Schema::dropIfExists('keuangan_cetak_rab');

        foreach (array_keys($this->rekapTables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'cetak_rab')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('cetak_rab');
            });
        }
    }
};
