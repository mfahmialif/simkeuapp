<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('bsi_integration_settings', 'verify_signatures')) {
                $table->boolean('verify_signatures')->default(true)->after('enforce_ip_allowlist');
            }

            if (! Schema::hasColumn('bsi_integration_settings', 'log_payloads')) {
                $table->boolean('log_payloads')->default(true)->after('verify_signatures');
            }

            if (! Schema::hasColumn('bsi_integration_settings', 'serve_test_va')) {
                $table->boolean('serve_test_va')->default(false)->after('log_payloads');
            }

            if (! Schema::hasColumn('bsi_integration_settings', 'database_failure_mode')) {
                $table->string('database_failure_mode', 20)->default('none')->after('serve_test_va');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $columns = collect([
                'verify_signatures',
                'log_payloads',
                'serve_test_va',
                'database_failure_mode',
            ])->filter(fn (string $column) => Schema::hasColumn('bsi_integration_settings', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
