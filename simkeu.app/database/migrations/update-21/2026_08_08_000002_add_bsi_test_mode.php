<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('bsi_integration_settings', 'reconciliation_email')) {
                $table->string('reconciliation_email')->nullable()->after('reconciliation_secret');
            }

            if (! Schema::hasColumn('bsi_integration_settings', 'test_mode')) {
                $table->boolean('test_mode')->default(false)->after('environment');
            }

            if (! Schema::hasColumn('bsi_integration_settings', 'test_nims')) {
                $table->json('test_nims')->nullable()->after('test_mode');
            }
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_pembayaran_bsi', 'data_test')) {
                $table->boolean('data_test')->default(false)->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            if (Schema::hasColumn('keuangan_pembayaran_bsi', 'data_test')) {
                $table->dropIndex(['data_test']);
                $table->dropColumn('data_test');
            }
        });

        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $columns = collect(['test_mode', 'test_nims'])
                ->filter(fn (string $column) => Schema::hasColumn('bsi_integration_settings', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
