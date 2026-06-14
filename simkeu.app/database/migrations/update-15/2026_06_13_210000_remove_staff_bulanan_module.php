<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pengeluaran_lpj_rekap_status')) {
            $staffStatuses = DB::table('keuangan_pengeluaran_lpj_rekap_status')
                ->where('module_key', 'staff_bulanan')
                ->get();

            foreach ($staffStatuses as $status) {
                $exists = DB::table('keuangan_pengeluaran_lpj_rekap_status')
                    ->where('module_key', 'dosen_bulanan')
                    ->where('rekap_id', $status->rekap_id)
                    ->exists();

                if ($exists) {
                    DB::table('keuangan_pengeluaran_lpj_rekap_status')
                        ->where('id', $status->id)
                        ->delete();

                    continue;
                }

                DB::table('keuangan_pengeluaran_lpj_rekap_status')
                    ->where('id', $status->id)
                    ->update(['module_key' => 'dosen_bulanan']);
            }
        }
    }

    public function down(): void
    {
        // This module and its data were intentionally removed permanently.
    }
};
