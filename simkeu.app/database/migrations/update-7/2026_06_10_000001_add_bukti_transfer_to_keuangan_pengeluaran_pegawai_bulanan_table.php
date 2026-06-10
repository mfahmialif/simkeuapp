<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            || Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'bukti_transfer')
        ) {
            return;
        }

        Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
            $table->string('bukti_transfer')->nullable()->after('jenis_pembayaran');
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            && Schema::hasColumn('keuangan_pengeluaran_pegawai_bulanan', 'bukti_transfer')
        ) {
            Schema::table('keuangan_pengeluaran_pegawai_bulanan', function (Blueprint $table) {
                $table->dropColumn('bukti_transfer');
            });
        }
    }
};
