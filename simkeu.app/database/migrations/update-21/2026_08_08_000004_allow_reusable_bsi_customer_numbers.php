<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropUnique(['customer_no']);
            $table->index('customer_no');
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropIndex(['customer_no']);
            $table->unique('customer_no');
        });
    }
};
