<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        DB::table('keuangan_pembayaran_bsi')
            ->whereIn('status', ['success', 'posted'])
            ->where('admin_fee_bearer', 'institution')
            ->where('admin_fee_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($payments) {
                $now = now();
                $reconciliations = DB::table('bsi_reconciliations')
                    ->whereIn('pembayaran_bsi_id', $payments->pluck('id'))
                    ->orderByDesc('id')
                    ->get()
                    ->unique('pembayaran_bsi_id')
                    ->keyBy('pembayaran_bsi_id');
                $rows = $payments->map(function ($payment) use ($now, $reconciliations) {
                    $reconciliation = $reconciliations->get($payment->id);

                    return [
                        'pembayaran_bsi_id' => $payment->id,
                        'bsi_reconciliation_id' => $reconciliation?->id,
                        'tanggal' => $payment->paid_at
                            ?: $payment->posted_at
                            ?: $payment->created_at,
                        'jumlah' => $payment->admin_fee_amount,
                        'dibebankan' => 'instansi',
                        'mata_uang' => 'IDR',
                        'status_rekonsiliasi' => $reconciliation?->match_status
                            ?: $payment->reconciliation_status,
                        'direkonsiliasi_pada' => $reconciliation?->reconciled_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('bsi_biaya_layanan')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsi_biaya_layanan');
    }
};
