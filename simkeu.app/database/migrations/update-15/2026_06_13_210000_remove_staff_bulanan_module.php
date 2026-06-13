<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan_lpj')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan_lpj', 'pegawai_tipe')
        ) {
            DB::table('keuangan_pengeluaran_pegawai_bulanan_lpj')
                ->where('pegawai_tipe', 'staff')
                ->delete();
        }

        if (Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            DB::table('keuangan_pengeluaran_lpj_rekap_status')
                ->where('module_key', 'staff_bulanan')
                ->delete();
        }

        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'pegawai_tipe')
        ) {
            DB::table('keuangan_pengeluaran_pegawai_bulanan')
                ->where('pegawai_tipe', 'staff')
                ->delete();
        }

        Schema::dropIfExists('keuangan_pengeluaran_staff_bulanan_rekap');
    }

    public function down(): void
    {
        // This module and its data were intentionally removed permanently.
    }
};
