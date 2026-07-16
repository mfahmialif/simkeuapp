<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('pegawai')
            && ! Schema::hasColumn('pegawai', 'status_absensi')
        ) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->string('status_absensi', 20)->nullable()->default('aktif')->after('status');
            });

            DB::table('pegawai')
                ->whereNull('status_absensi')
                ->update(['status_absensi' => 'aktif']);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('pegawai')
            && Schema::hasColumn('pegawai', 'status_absensi')
        ) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->dropColumn('status_absensi');
            });
        }
    }
};
