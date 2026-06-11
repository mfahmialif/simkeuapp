<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

$tables = [
    'keuangan_pengeluaran_dosen',
    'keuangan_pengeluaran_dosen_rekap',
    'keuangan_pengeluaran_dosen_lpj',
    'keuangan_pengeluaran_dosen_kegiatan',
    'keuangan_pengeluaran_dosen_kegiatan_rekap',
    'keuangan_pengeluaran_dosen_kegiatan_lpj',
    'keuangan_pengeluaran_pegawai_bulanan',
    'keuangan_pengeluaran_dosen_bulanan_rekap',
    'keuangan_pengeluaran_staff_bulanan_rekap',
    'keuangan_pengeluaran_pegawai_bulanan_lpj'
];

$userId = User::first()->id ?? null;

if ($userId) {
    foreach ($tables as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->whereNull('petugas_id')->update(['petugas_id' => $userId]);
            echo "Updated $table with user_id $userId\n";
        }
    }
} else {
    echo "No users found!\n";
}
