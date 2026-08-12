<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->string('payment_mode', 10)
                ->default('open')
                ->after('payment_expiry_minutes');
        });

        DB::table('bsi_integration_settings')->update(['payment_mode' => 'open']);
    }

    public function down(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
