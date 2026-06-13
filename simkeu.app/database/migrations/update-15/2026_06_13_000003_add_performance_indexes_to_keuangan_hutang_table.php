<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_hutang')) {
            return;
        }

        Schema::table('keuangan_hutang', function (Blueprint $table) {
            if (! $this->indexExists('keuangan_hutang', 'idx_keuangan_hutang_tanggal_id')) {
                $table->index(['tanggal', 'id'], 'idx_keuangan_hutang_tanggal_id');
            }

            if (! $this->indexExists('keuangan_hutang', 'idx_keuangan_hutang_tipe_tanggal')) {
                $table->index(['tipe', 'tanggal'], 'idx_keuangan_hutang_tipe_tanggal');
            }

            if (! $this->indexExists('keuangan_hutang', 'idx_keuangan_hutang_pemberi')) {
                $table->index('pemberi_pinjaman', 'idx_keuangan_hutang_pemberi');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_hutang')) {
            return;
        }

        Schema::table('keuangan_hutang', function (Blueprint $table) {
            foreach ([
                'idx_keuangan_hutang_tanggal_id',
                'idx_keuangan_hutang_tipe_tanggal',
                'idx_keuangan_hutang_pemberi',
            ] as $index) {
                if ($this->indexExists('keuangan_hutang', $index)) {
                    $table->dropIndex($index);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
