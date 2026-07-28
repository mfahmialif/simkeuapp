<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'keuangan_pengeluaran_dosen_kegiatan',
        'keuangan_pengeluaran_dosen_kegiatan_lpj',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'nama')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $afterColumn = Schema::hasColumn($tableName, 'kategori_detail')
                    ? 'kategori_detail'
                    : 'nama_kegiatan';

                $table->string('nama')->nullable()->after($afterColumn);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'nama')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('nama');
            });
        }
    }
};
