<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $tables = [
        'keuangan_pengeluaran_dosen',
        'keuangan_pengeluaran_dosen_rekap',
        'keuangan_pengeluaran_dosen_lpj',
        'keuangan_pengeluaran_dosen_kegiatan',
        'keuangan_pengeluaran_dosen_kegiatan_rekap',
        'keuangan_pengeluaran_dosen_kegiatan_lpj',
        'keuangan_pengeluaran_pegawai_bulanan',
        'keuangan_pengeluaran_dosen_bulanan_rekap',
        'keuangan_pengeluaran_staff_bulanan_rekap',
        'keuangan_pengeluaran_pegawai_bulanan_lpj',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleTatapMuka = \Illuminate\Support\Facades\DB::table('role')->where('name', 'barokahdosen_tatapmuka')->first()->id ?? 0;
        $roleKegiatan = \Illuminate\Support\Facades\DB::table('role')->where('name', 'barokahdosen_kegiatan')->first()->id ?? 0;
        $roleBulanan = \Illuminate\Support\Facades\DB::table('role')->where('name', 'barokahdosen_bulanan')->first()->id ?? 0;

        $userTatapMuka = \Illuminate\Support\Facades\DB::table('users')->where('role_id', $roleTatapMuka)->first()->id ?? null;
        $userKegiatan = \Illuminate\Support\Facades\DB::table('users')->where('role_id', $roleKegiatan)->first()->id ?? null;
        $userBulanan = \Illuminate\Support\Facades\DB::table('users')->where('role_id', $roleBulanan)->first()->id ?? null;

        foreach ($this->tables as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                });

                if (in_array($table, [
                    'keuangan_pengeluaran_dosen',
                    'keuangan_pengeluaran_dosen_rekap',
                    'keuangan_pengeluaran_dosen_lpj'
                ])) {
                    \Illuminate\Support\Facades\DB::table($table)->update(['petugas_id' => $userTatapMuka]);
                } elseif (in_array($table, [
                    'keuangan_pengeluaran_dosen_kegiatan',
                    'keuangan_pengeluaran_dosen_kegiatan_rekap',
                    'keuangan_pengeluaran_dosen_kegiatan_lpj',
                    'keuangan_pengeluaran_staff_bulanan_rekap'
                ])) {
                    \Illuminate\Support\Facades\DB::table($table)->update(['petugas_id' => $userKegiatan]);
                } elseif (in_array($table, [
                    'keuangan_pengeluaran_dosen_bulanan_rekap'
                ])) {
                    \Illuminate\Support\Facades\DB::table($table)->update(['petugas_id' => $userBulanan]);
                } elseif ($table === 'keuangan_pengeluaran_pegawai_bulanan' || $table === 'keuangan_pengeluaran_pegawai_bulanan_lpj') {
                    \Illuminate\Support\Facades\DB::table($table)->where('pegawai_tipe', 'dosen')->update(['petugas_id' => $userBulanan]);
                    \Illuminate\Support\Facades\DB::table($table)->where('pegawai_tipe', 'staff')->update(['petugas_id' => $userKegiatan]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['petugas_id']);
                    $table->dropColumn('petugas_id');
                });
            }
        }
    }
};
