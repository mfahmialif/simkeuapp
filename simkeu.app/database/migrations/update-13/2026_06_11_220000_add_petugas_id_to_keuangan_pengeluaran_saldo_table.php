<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_saldo')) {
            return;
        }

        Schema::table('keuangan_pengeluaran_saldo', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pengeluaran_saldo', 'petugas_id')) {
                $table->unsignedBigInteger('petugas_id')->nullable()->after('id');
                $table->index('petugas_id', 'idx_keuangan_pengeluaran_saldo_petugas');
                $table->index(['petugas_id', 'module_key', 'tanggal'], 'idx_keuangan_pengeluaran_saldo_petugas_module_tanggal');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
