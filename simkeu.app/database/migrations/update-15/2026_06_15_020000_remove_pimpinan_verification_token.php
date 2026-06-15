<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pimpinan') && Schema::hasColumn('pimpinan', 'mode_ttd')) {
            DB::table('pimpinan')
                ->where('mode_ttd', 'qr')
                ->update(['mode_ttd' => 'file']);
        }

        if (Schema::hasTable('pimpinan') && Schema::hasColumn('pimpinan', 'verification_token')) {
            Schema::table('pimpinan', function (Blueprint $table) {
                $table->dropUnique(['verification_token']);
                $table->dropColumn('verification_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pimpinan') && ! Schema::hasColumn('pimpinan', 'verification_token')) {
            Schema::table('pimpinan', function (Blueprint $table) {
                $table->uuid('verification_token')->nullable()->unique()->after('status');
            });
        }
    }
};
