<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('keuangan_pemasukan_umum')) {
            if (!Schema::hasColumn('keuangan_pemasukan_umum', 'no_transaksi')) {
                Schema::table('keuangan_pemasukan_umum', function (Blueprint $table) {
                    $table->string('no_transaksi', 40)->nullable()->after('id')->index('idx_pemasukan_umum_no_transaksi');
                });
            }

            // Update existing records
            $records = DB::table('keuangan_pemasukan_umum')->orderBy('id')->get();
            $grouped = [];
            foreach ($records as $rec) {
                $date = $rec->tanggal ? Carbon::parse($rec->tanggal) : Carbon::now();
                $key = $date->format('Y-m');
                if (!isset($grouped[$key])) {
                    $grouped[$key] = 1;
                } else {
                    $grouped[$key]++;
                }
                $seq = $grouped[$key];
                $noTransaksi = sprintf('%04d/PU/%s/%s', $seq, $date->format('m'), $date->format('Y'));
                DB::table('keuangan_pemasukan_umum')->where('id', $rec->id)->update([
                    'no_transaksi' => $noTransaksi,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('keuangan_pemasukan_umum') && Schema::hasColumn('keuangan_pemasukan_umum', 'no_transaksi')) {
            Schema::table('keuangan_pemasukan_umum', function (Blueprint $table) {
                $table->dropIndex('idx_pemasukan_umum_no_transaksi');
                $table->dropColumn('no_transaksi');
            });
        }
    }
};
