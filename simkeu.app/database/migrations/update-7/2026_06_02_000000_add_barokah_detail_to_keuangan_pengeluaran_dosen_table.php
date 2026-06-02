<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) {
            $table->integer('barokah_mengajar_biasa')->nullable()->after('barokah');
            $table->integer('barokah_mengajar_double_degree')->nullable()->after('barokah_mengajar_biasa');
            $table->integer('barokah_uas')->nullable()->after('barokah_mengajar_double_degree');
            $table->integer('jumlah_mahasiswa_uas')->nullable()->after('barokah_uas');
            $table->integer('barokah_sempro')->nullable()->after('jumlah_mahasiswa_uas');
            $table->string('jenis_pembayaran', 50)->nullable()->after('total');
            $table->string('bukti_transfer')->nullable()->after('jenis_pembayaran');
            $table->text('keterangan')->nullable()->after('bukti_transfer');
        });

        DB::table('keuangan_pengeluaran_dosen')
            ->whereNull('barokah_mengajar_biasa')
            ->update([
                'barokah_mengajar_biasa' => DB::raw('barokah'),
                'barokah_mengajar_double_degree' => 0,
                'barokah_uas' => 0,
                'jumlah_mahasiswa_uas' => 0,
                'barokah_sempro' => 0,
                'jenis_pembayaran' => 'CUS BSI',
            ]);
    }

    public function down(): void
    {
        $columns = array_filter([
            'barokah_mengajar_biasa',
            'barokah_mengajar_double_degree',
            'barokah_uas',
            'jumlah_mahasiswa_uas',
            'barokah_sempro',
            'jenis_pembayaran',
            'bukti_transfer',
            'keterangan',
        ], fn ($column) => Schema::hasColumn('keuangan_pengeluaran_dosen', $column));

        if ($columns) {
            Schema::table('keuangan_pengeluaran_dosen', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
