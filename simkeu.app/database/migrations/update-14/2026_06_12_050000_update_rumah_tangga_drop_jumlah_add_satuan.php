<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keuangan_pengeluaran_rumah_tangga', function (Blueprint $table) {
            $table->dropColumn('jumlah');
            $table->string('satuan')->nullable()->after('volume');
        });

        Schema::table('keuangan_pengeluaran_rumah_tangga_lpj', function (Blueprint $table) {
            $table->dropColumn('jumlah');
            $table->string('satuan')->nullable()->after('volume');
        });
    }

    public function down(): void
    {
        Schema::table('keuangan_pengeluaran_rumah_tangga', function (Blueprint $table) {
            $table->dropColumn('satuan');
            $table->unsignedInteger('jumlah')->nullable()->after('nominal');
        });

        Schema::table('keuangan_pengeluaran_rumah_tangga_lpj', function (Blueprint $table) {
            $table->dropColumn('satuan');
            $table->unsignedInteger('jumlah')->nullable()->after('nominal');
        });
    }
};
