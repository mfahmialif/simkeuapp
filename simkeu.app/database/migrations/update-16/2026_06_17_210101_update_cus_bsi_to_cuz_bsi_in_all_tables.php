<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_kegiatan',
            'keuangan_pengeluaran_pegawai_bulanan',
            'keuangan_pengeluaran_rumah_tangga',
            'keuangan_pengeluaran_transportasi',
            'keuangan_pengeluaran_sarana_prasarana',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->where('jenis_pembayaran', 'CUS BSI')
                ->update(['jenis_pembayaran' => 'CUZ BSI']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'keuangan_pengeluaran_dosen',
            'keuangan_pengeluaran_dosen_kegiatan',
            'keuangan_pengeluaran_pegawai_bulanan',
            'keuangan_pengeluaran_rumah_tangga',
            'keuangan_pengeluaran_transportasi',
            'keuangan_pengeluaran_sarana_prasarana',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->where('jenis_pembayaran', 'CUZ BSI')
                ->update(['jenis_pembayaran' => 'CUS BSI']);
        }
    }
};
