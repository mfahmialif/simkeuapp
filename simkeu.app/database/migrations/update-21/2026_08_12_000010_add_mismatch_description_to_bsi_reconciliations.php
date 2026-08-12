<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_reconciliations', function (Blueprint $table) {
            $table->text('mismatch_description')->nullable()->after('match_status');
        });
    }

    public function down(): void
    {
        Schema::table('bsi_reconciliations', function (Blueprint $table) {
            $table->dropColumn('mismatch_description');
        });
    }
};
