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

        if (! Schema::hasColumn('keuangan_hutang', 'pemberi_pinjaman')) {
            Schema::table('keuangan_hutang', function (Blueprint $table) {
                $table->string('pemberi_pinjaman', 150)->nullable()->after('petugas_id');
            });
        }

        DB::table('keuangan_hutang')
            ->whereNull('pemberi_pinjaman')
            ->update(['pemberi_pinjaman' => 'Tidak diketahui']);

        if (! $this->indexExists('keuangan_hutang', 'idx_keuangan_hutang_petugas_pemberi')) {
            Schema::table('keuangan_hutang', function (Blueprint $table) {
                $table->index(['petugas_id', 'pemberi_pinjaman'], 'idx_keuangan_hutang_petugas_pemberi');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_hutang') || ! Schema::hasColumn('keuangan_hutang', 'pemberi_pinjaman')) {
            return;
        }

        Schema::table('keuangan_hutang', function (Blueprint $table) {
            if ($this->indexExists('keuangan_hutang', 'idx_keuangan_hutang_petugas_pemberi')) {
                $table->dropIndex('idx_keuangan_hutang_petugas_pemberi');
            }
            $table->dropColumn('pemberi_pinjaman');
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
