<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->decimal('sandbox_admin_fee_amount', 15, 2)
                ->default(3000)
                ->after('admin_fee_amount');
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->boolean('production')->default(false)->after('data_test')->index();
        });

        $production = DB::table('bsi_integration_settings')
            ->whereRaw('LOWER(environment) = ?', ['production'])
            ->exists();

        if ($production) {
            DB::table('keuangan_pembayaran_bsi')->update(['production' => true]);
        }

        DB::table('keuangan_pembayaran')
            ->whereRaw("LOWER(TRIM(COALESCE(sumber, ''))) = ?", ['bsi'])
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($payments) {
                $paymentIds = $payments->pluck('id');

                DB::table('keuangan_pembayaran_bsi_detail')
                    ->whereIn('pembayaran_id', $paymentIds)
                    ->update(['pembayaran_id' => null]);
                DB::table('keuangan_nota')->whereIn('pembayaran_id', $paymentIds)->delete();
                DB::table('keuangan_jenis_pembayaran_detail')
                    ->whereIn('pembayaran_id', $paymentIds)
                    ->delete();
                DB::table('keuangan_pembayaran')->whereIn('id', $paymentIds)->delete();
            });

        Schema::dropIfExists('bsi_biaya_layanan');
    }

    public function down(): void
    {
        Schema::create('bsi_biaya_layanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_bsi_id')
                ->unique()
                ->constrained('keuangan_pembayaran_bsi')
                ->cascadeOnDelete();
            $table->foreignId('bsi_reconciliation_id')
                ->nullable()
                ->constrained('bsi_reconciliations')
                ->nullOnDelete();
            $table->dateTime('tanggal')->index();
            $table->decimal('jumlah', 15, 2);
            $table->string('dibebankan', 20)->default('instansi')->index();
            $table->string('mata_uang', 3)->default('IDR');
            $table->string('status_rekonsiliasi', 30)->nullable()->index();
            $table->dateTime('direkonsiliasi_pada')->nullable();
            $table->timestamps();
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropIndex(['production']);
            $table->dropColumn('production');
        });

        Schema::table('bsi_integration_settings', function (Blueprint $table) {
            $table->dropColumn('sandbox_admin_fee_amount');
        });
    }
};
