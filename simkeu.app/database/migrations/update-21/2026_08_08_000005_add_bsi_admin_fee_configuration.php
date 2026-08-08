<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->string('admin_fee_bearer', 20)
                ->default('institution')
                ->after('payment_expiry_minutes');
            $table->decimal('admin_fee_amount', 15, 2)
                ->default(2500)
                ->after('admin_fee_bearer');
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->string('admin_fee_bearer', 20)
                ->default('institution')
                ->after('total');
            $table->decimal('admin_fee_amount', 15, 2)
                ->default(2500)
                ->after('admin_fee_bearer');
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropColumn(['admin_fee_bearer', 'admin_fee_amount']);
        });

        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->dropColumn(['admin_fee_bearer', 'admin_fee_amount']);
        });
    }
};
