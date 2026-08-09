<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->boolean('auto_transfer_enabled')
                ->default(false)
                ->after('database_failure_mode');
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->boolean('transferred')
                ->default(false)
                ->after('production');
            $table->index(
                ['production', 'data_test', 'status', 'transferred'],
                'bsi_payment_transfer_eligibility_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropIndex('bsi_payment_transfer_eligibility_index');
            $table->dropColumn('transferred');
        });

        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->dropColumn('auto_transfer_enabled');
        });
    }
};
