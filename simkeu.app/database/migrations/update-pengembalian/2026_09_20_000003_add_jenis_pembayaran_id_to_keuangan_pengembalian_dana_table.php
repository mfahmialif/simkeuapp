<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
                if (!Schema::hasColumn('keuangan_pengembalian_dana', 'jenis_pembayaran_id')) {
                    $table->unsignedBigInteger('jenis_pembayaran_id')->nullable()->after('petugas_id')->index('idx_pd_jenis_pembayaran');

                    if (Schema::hasTable('keuangan_jenis_pembayaran')) {
                        $table->foreign('jenis_pembayaran_id', 'fk_pd_jenis_pembayaran')
                            ->references('id')
                            ->on('keuangan_jenis_pembayaran')
                            ->nullOnDelete();
                    }
                }
            });

            // Set default cash payment method for existing records
            $defaultJp = DB::table('keuangan_jenis_pembayaran')
                ->where('nama', 'LIKE', '%cash%')
                ->orWhere('nama', 'LIKE', '%tunai%')
                ->value('id');

            if ($defaultJp) {
                DB::table('keuangan_pengembalian_dana')
                    ->whereNull('jenis_pembayaran_id')
                    ->update(['jenis_pembayaran_id' => $defaultJp]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('keuangan_pengembalian_dana')) {
            Schema::table('keuangan_pengembalian_dana', function (Blueprint $table) {
                if (Schema::hasColumn('keuangan_pengembalian_dana', 'jenis_pembayaran_id')) {
                    $table->dropForeign('fk_pd_jenis_pembayaran');
                    $table->dropColumn('jenis_pembayaran_id');
                }
            });
        }
    }
};
