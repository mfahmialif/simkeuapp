<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bsi_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('sandbox');
            $table->string('institution_name')->nullable();
            $table->string('kode_bpi', 4)->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('bpi_public_key')->nullable();
            $table->text('reconciliation_secret')->nullable();
            $table->string('reconciliation_email')->nullable();
            $table->unsignedInteger('payment_expiry_minutes')->default(1440);
            $table->unsignedInteger('timestamp_tolerance')->default(300);
            $table->json('allowed_ips')->nullable();
            $table->boolean('enforce_ip_allowlist')->default(false);
            $table->string('siakad_api_key_hash', 64)->nullable();
            $table->string('siakad_api_key_hint', 20)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->string('customer_no', 12)->nullable()->unique()->after('va_number');
            $table->string('bsi_payment_number', 16)->nullable()->after('customer_no');
            $table->string('interbank_va_number', 19)->nullable()->after('bsi_payment_number');
            $table->string('payment_request_id')->nullable()->unique()->after('bank_reference');
            $table->string('payment_request_hash', 64)->nullable()->after('payment_request_id');
            $table->string('inquiry_request_id')->nullable()->index()->after('payment_request_hash');
            $table->string('channel_id', 10)->nullable()->after('inquiry_request_id');
            $table->string('source_bank_code', 10)->nullable()->after('channel_id');
            $table->dateTime('trx_date_time')->nullable()->after('source_bank_code');
            $table->string('reference_no')->nullable()->index()->after('trx_date_time');
            $table->json('payment_response')->nullable()->after('raw_callback');
            $table->string('reconciliation_status', 30)->nullable()->index()->after('payment_response');
        });

        Schema::create('bsi_snap_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_bsi_id')
                ->nullable()
                ->constrained('keuangan_pembayaran_bsi')
                ->nullOnDelete();
            $table->string('operation', 30)->index();
            $table->string('external_id')->nullable()->index();
            $table->string('response_code', 10)->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 30)->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('source_ip', 64)->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('requested_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('bsi_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('recon_id')->unique();
            $table->foreignId('pembayaran_bsi_id')
                ->nullable()
                ->constrained('keuangan_pembayaran_bsi')
                ->nullOnDelete();
            $table->string('journal_number')->nullable()->index();
            $table->string('payment_number')->nullable()->index();
            $table->dateTime('transaction_at')->nullable();
            $table->dateTime('reconciled_at')->nullable();
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->decimal('settlement_amount', 15, 2)->nullable();
            $table->string('settlement_code')->nullable();
            $table->string('bank_status', 40)->nullable();
            $table->boolean('checksum_valid')->default(false);
            $table->string('match_status', 30)->index();
            $table->json('payload');
            $table->timestamps();
        });

        DB::table('keuangan_pembayaran_bsi')
            ->where('status', 'posted')
            ->update(['status' => 'success']);
    }

    public function down(): void
    {
        DB::table('keuangan_pembayaran_bsi')
            ->where('status', 'success')
            ->update(['status' => 'posted']);

        Schema::dropIfExists('bsi_reconciliations');
        Schema::dropIfExists('bsi_snap_logs');

        Schema::table('keuangan_pembayaran_bsi', function (Blueprint $table) {
            $table->dropUnique(['customer_no']);
            $table->dropUnique(['payment_request_id']);
            $table->dropIndex(['inquiry_request_id']);
            $table->dropIndex(['reference_no']);
            $table->dropIndex(['reconciliation_status']);
            $table->dropColumn([
                'customer_no',
                'bsi_payment_number',
                'interbank_va_number',
                'payment_request_id',
                'payment_request_hash',
                'inquiry_request_id',
                'channel_id',
                'source_bank_code',
                'trx_date_time',
                'reference_no',
                'payment_response',
                'reconciliation_status',
            ]);
        });

        Schema::dropIfExists('bsi_integration_settings');
    }
};
