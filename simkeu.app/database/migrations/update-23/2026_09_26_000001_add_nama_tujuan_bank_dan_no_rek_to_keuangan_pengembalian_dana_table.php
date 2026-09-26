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
        if (Schema::hasTable('keuangan_pengembalian_dana')) {
            Schema::table('keuangan_pengembalian_dana', function (Blueprint $table) {
                if (!Schema::hasColumn('keuangan_pengembalian_dana', 'nama_tujuan')) {
                    $table->string('nama_tujuan', 150)->nullable()->after('jenis_pembayaran_id');
                }
                if (!Schema::hasColumn('keuangan_pengembalian_dana', 'nama_bank')) {
                    $table->string('nama_bank', 100)->nullable()->after('nama_tujuan');
                }
                if (!Schema::hasColumn('keuangan_pengembalian_dana', 'no_rek_tujuan')) {
                    $table->string('no_rek_tujuan', 100)->nullable()->after('nama_bank');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('keuangan_pengembalian_dana')) {
            Schema::table('keuangan_pengembalian_dana', function (Blueprint $table) {
                if (Schema::hasColumn('keuangan_pengembalian_dana', 'no_rek_tujuan')) {
                    $table->dropColumn('no_rek_tujuan');
                }
                if (Schema::hasColumn('keuangan_pengembalian_dana', 'nama_bank')) {
                    $table->dropColumn('nama_bank');
                }
                if (Schema::hasColumn('keuangan_pengembalian_dana', 'nama_tujuan')) {
                    $table->dropColumn('nama_tujuan');
                }
            });
        }
    }
};
