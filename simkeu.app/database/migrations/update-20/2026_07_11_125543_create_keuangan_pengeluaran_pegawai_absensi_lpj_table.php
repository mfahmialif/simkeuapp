<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $sourceTable = 'keuangan_pengeluaran_pegawai_absensi';
    private string $lpjTable = 'keuangan_pengeluaran_pegawai_absensi_lpj';

    public function up(): void
    {
        if (! Schema::hasTable($this->sourceTable) || Schema::hasTable($this->lpjTable)) {
            return;
        }

        DB::statement("CREATE TABLE `{$this->lpjTable}` LIKE `{$this->sourceTable}`");

        Schema::table($this->lpjTable, function (Blueprint $table) {
            if (! Schema::hasColumn($this->lpjTable, 'rab_detail_id')) {
                $table->unsignedBigInteger('rab_detail_id')->nullable()->after('id');
                $table->index('rab_detail_id', "idx_{$this->lpjTable}_rab_detail_id");
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->lpjTable);
    }
};
