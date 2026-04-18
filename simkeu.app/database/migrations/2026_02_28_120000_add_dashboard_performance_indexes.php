<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index for JOIN keuangan_pembayaran.tagihan_id → keuangan_tagihan.id
        Schema::table('keuangan_pembayaran', function (Blueprint $table) {
            $table->index('tagihan_id', 'idx_pembayaran_tagihan_id');
            $table->index('tanggal', 'idx_pembayaran_tanggal');
            $table->index('jk_id', 'idx_pembayaran_jk_id');
        });

        // Index for statistic() query date filters
        Schema::table('keuangan_saldo_pemasukan', function (Blueprint $table) {
            $table->index('tanggal', 'idx_saldo_pemasukan_tanggal');
        });

        Schema::table('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->index('tanggal', 'idx_saldo_pengeluaran_tanggal');
        });

        // Index for financeOverview LIKE query on nama + filter columns
        Schema::table('keuangan_tagihan', function (Blueprint $table) {
            $table->index('nama', 'idx_tagihan_nama');
            $table->index(['th_akademik_id', 'prodi_id'], 'idx_tagihan_th_prodi');
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran', function (Blueprint $table) {
            $table->dropIndex('idx_pembayaran_tagihan_id');
            $table->dropIndex('idx_pembayaran_tanggal');
            $table->dropIndex('idx_pembayaran_jk_id');
        });

        Schema::table('keuangan_saldo_pemasukan', function (Blueprint $table) {
            $table->dropIndex('idx_saldo_pemasukan_tanggal');
        });

        Schema::table('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->dropIndex('idx_saldo_pengeluaran_tanggal');
        });

        Schema::table('keuangan_tagihan', function (Blueprint $table) {
            $table->dropIndex('idx_tagihan_nama');
            $table->dropIndex('idx_tagihan_th_prodi');
        });
    }
};
