<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mata_uang')) {
            Schema::create('mata_uang', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 10)->unique();
                $table->string('nama', 100)->nullable();
                $table->string('simbol', 10)->nullable();
                $table->boolean('aktif')->default(true);
            });
        }

        DB::table('mata_uang')->updateOrInsert(
            ['kode' => 'IDR'],
            ['nama' => 'Rupiah', 'simbol' => 'Rp', 'aktif' => true]
        );
        DB::table('mata_uang')->updateOrInsert(
            ['kode' => 'USD'],
            ['nama' => 'Dolar', 'simbol' => '$', 'aktif' => true]
        );

        if (! Schema::hasColumn('keuangan_tagihan', 'mata_uang_id')) {
            Schema::table('keuangan_tagihan', function (Blueprint $table) {
                $table->foreignId('mata_uang_id')
                    ->nullable()
                    ->after('jumlah')
                    ->constrained('mata_uang')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        DB::table('keuangan_tagihan')
            ->whereNull('mata_uang_id')
            ->update([
                'mata_uang_id' => DB::raw("(select id from mata_uang where kode = 'IDR' limit 1)"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('keuangan_tagihan', 'mata_uang_id')) {
            Schema::table('keuangan_tagihan', function (Blueprint $table) {
                $table->dropForeign(['mata_uang_id']);
                $table->dropColumn('mata_uang_id');
            });
        }

        Schema::dropIfExists('mata_uang');
    }
};
