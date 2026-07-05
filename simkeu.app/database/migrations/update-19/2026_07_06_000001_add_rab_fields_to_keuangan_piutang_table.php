<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'idx_piutang_rab_cetak_tanggal_id' => ['cetak_rab', 'tanggal', 'id'],
        'idx_piutang_rab_pegawai_tanggal_id' => ['pegawai_id', 'tanggal', 'id'],
        'idx_piutang_rab_tgl_cair' => ['tanggal_pencairan'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('keuangan_piutang')) {
            return;
        }

        if (! Schema::hasColumn('keuangan_piutang', 'tanggal_pencairan')) {
            Schema::table('keuangan_piutang', function (Blueprint $table) {
                $table->date('tanggal_pencairan')->nullable()->after('tanggal');
            });
        }

        if (! Schema::hasColumn('keuangan_piutang', 'cetak_rab')) {
            Schema::table('keuangan_piutang', function (Blueprint $table) {
                $table->boolean('cetak_rab')->default(false)->after('tanggal_pencairan');
            });
        }

        Schema::table('keuangan_piutang', function (Blueprint $table) {
            foreach ($this->indexes as $name => $columns) {
                if (! $this->indexExists('keuangan_piutang', $name)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_piutang')) {
            return;
        }

        Schema::table('keuangan_piutang', function (Blueprint $table) {
            foreach (array_keys($this->indexes) as $name) {
                if ($this->indexExists('keuangan_piutang', $name)) {
                    $table->dropIndex($name);
                }
            }
        });

        Schema::table('keuangan_piutang', function (Blueprint $table) {
            if (Schema::hasColumn('keuangan_piutang', 'cetak_rab')) {
                $table->dropColumn('cetak_rab');
            }

            if (Schema::hasColumn('keuangan_piutang', 'tanggal_pencairan')) {
                $table->dropColumn('tanggal_pencairan');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($item) => ($item['name'] ?? null) === $index);
    }
};
