<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('keuangan_pengembalian_dana', function (Blueprint $table) {
            $table->string('no_transaksi', 50)->nullable()->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keuangan_pengembalian_dana', function (Blueprint $table) {
            $table->dropIndex(['no_transaksi']);
            $table->dropColumn('no_transaksi');
        });
    }
};
