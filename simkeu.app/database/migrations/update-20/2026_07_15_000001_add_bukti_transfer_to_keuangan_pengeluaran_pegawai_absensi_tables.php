<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_absensi')
            && ! Schema::hasColumn('keuangan_pengeluaran_pegawai_absensi', 'bukti_transfer')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_absensi', function (Blueprint $table) {
                $table->string('bukti_transfer')->nullable()->after('jenis_pembayaran');
            });
        }

        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_absensi_lpj')
            && ! Schema::hasColumn('keuangan_pengeluaran_pegawai_absensi_lpj', 'bukti_transfer')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_absensi_lpj', function (Blueprint $table) {
                $table->string('bukti_transfer')->nullable()->after('jenis_pembayaran');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_absensi')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_absensi', 'bukti_transfer')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_absensi', function (Blueprint $table) {
                $table->dropColumn('bukti_transfer');
            });
        }

        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_absensi_lpj')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_absensi_lpj', 'bukti_transfer')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_absensi_lpj', function (Blueprint $table) {
                $table->dropColumn('bukti_transfer');
            });
        }
    }
};
