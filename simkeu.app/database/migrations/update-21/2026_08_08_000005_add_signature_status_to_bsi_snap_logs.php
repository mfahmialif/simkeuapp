<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_snap_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('bsi_snap_logs', 'signature_valid')) {
                $table->boolean('signature_valid')->nullable()->after('outcome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bsi_snap_logs', function (Blueprint $table) {
            if (Schema::hasColumn('bsi_snap_logs', 'signature_valid')) {
                $table->dropColumn('signature_valid');
            }
        });
    }
};
