<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_metode_va', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama')->unique();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('keuangan_metode_va')->insert([
            [
                'id' => 1,
                'kode' => 'byond_bsi',
                'nama' => 'Byond BSI',
                'keterangan' => 'Pembayaran melalui aplikasi BYOND by BSI.',
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'kode' => 'atm_bsi',
                'nama' => 'ATM BSI',
                'keterangan' => 'Pembayaran melalui ATM Bank Syariah Indonesia.',
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'kode' => 'atm_lain',
                'nama' => 'ATM LAIN',
                'keterangan' => 'Pembayaran dari bank lain menggunakan nomor VA antarbank.',
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->foreignId('metode_va_id')
                ->nullable()
                ->after('source_bank_code')
                ->constrained('keuangan_metode_va')
                ->nullOnDelete();
        });

        DB::table('keuangan_pembayaran_bsi')
            ->where('channel_id', '6027')
            ->update(['metode_va_id' => 1]);

        DB::table('keuangan_pembayaran_bsi')
            ->where('channel_id', '6011')
            ->where('raw_callback', 'like', '%"virtualAccountNo":"%900%')
            ->update(['metode_va_id' => 3]);

        DB::table('keuangan_pembayaran_bsi')
            ->where('channel_id', '6011')
            ->whereNull('metode_va_id')
            ->update(['metode_va_id' => 2]);
    }

    public function down(): void
    {
        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('metode_va_id');
        });

        Schema::dropIfExists('keuangan_metode_va');
    }
};
