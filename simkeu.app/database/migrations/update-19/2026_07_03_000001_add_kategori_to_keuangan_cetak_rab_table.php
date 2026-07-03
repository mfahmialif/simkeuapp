<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || Schema::hasColumn('keuangan_cetak_rab', 'kategori')) {
            return;
        }

        Schema::table('keuangan_cetak_rab', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('tanggal_cetak');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_cetak_rab') || ! Schema::hasColumn('keuangan_cetak_rab', 'kategori')) {
            return;
        }

        Schema::table('keuangan_cetak_rab', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
