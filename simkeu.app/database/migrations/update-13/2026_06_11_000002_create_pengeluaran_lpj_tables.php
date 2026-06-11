<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $detailTables = [
        'keuangan_pengeluaran_dosen' => 'keuangan_pengeluaran_dosen_lpj',
        'keuangan_pengeluaran_dosen_kegiatan' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
        'keuangan_pengeluaran_pegawai_bulanan' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
    ];

    public function up(): void
    {
        foreach ($this->detailTables as $sourceTable => $lpjTable) {
            if (! Schema::hasTable($sourceTable) || Schema::hasTable($lpjTable)) {
                continue;
            }

            DB::statement("CREATE TABLE `{$lpjTable}` LIKE `{$sourceTable}`");

            Schema::table($lpjTable, function (Blueprint $table) use ($lpjTable) {
                if (! Schema::hasColumn($lpjTable, 'rab_detail_id')) {
                    $table->unsignedBigInteger('rab_detail_id')->nullable()->after('id');
                    $table->index('rab_detail_id', "idx_{$lpjTable}_rab_detail_id");
                }
            });
        }

        if (! Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            Schema::create('keuangan_pengeluaran_lpj_rekap_status', function (Blueprint $table) {
                $table->id();
                $table->string('module_key', 32);
                $table->unsignedBigInteger('rekap_id');
                $table->boolean('sama_dengan_rab')->default(false);
                $table->unsignedBigInteger('total_rab')->default(0);
                $table->unsignedBigInteger('total_lpj')->default(0);
                $table->timestamp('selesai_at')->nullable();
                $table->timestamps();

                $table->unique(['module_key', 'rekap_id'], 'uq_lpj_rekap_status_module_rekap');
                $table->index(['module_key', 'sama_dengan_rab'], 'idx_lpj_rekap_status_module_same');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_lpj_rekap_status');

        foreach (array_reverse($this->detailTables) as $lpjTable) {
            Schema::dropIfExists($lpjTable);
        }
    }
};
