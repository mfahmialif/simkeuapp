<?php

namespace App\Services;

use App\Models\BsiIntegrationSetting;
use App\Models\KeuanganPembayaranBsi;
use Illuminate\Support\Str;

class BsiSettingsService
{
    public const SANDBOX_ADMIN_FEE_BEARER = 'payer';

    public const DEFAULT_SANDBOX_ADMIN_FEE_AMOUNT = 3000.0;

    public const DEFAULT_ALLOWED_IPS = [
        '149.129.255.119',
        '202.74.236.178',
        '103.59.160.254',
        '103.219.251.186',
    ];

    public function settings(): BsiIntegrationSetting
    {
        return BsiIntegrationSetting::query()->firstOrCreate([], [
            'environment' => 'sandbox',
            'payment_expiry_minutes' => 1440,
            'admin_fee_bearer' => self::SANDBOX_ADMIN_FEE_BEARER,
            'admin_fee_amount' => 2500,
            'sandbox_admin_fee_amount' => self::DEFAULT_SANDBOX_ADMIN_FEE_AMOUNT,
            'timestamp_tolerance' => 300,
            'allowed_ips' => self::DEFAULT_ALLOWED_IPS,
            'verify_signatures' => true,
            'log_payloads' => true,
            'serve_test_va' => false,
            'database_failure_mode' => 'none',
            'auto_transfer_enabled' => false,
        ]);
    }

    public function isReady(?BsiIntegrationSetting $settings = null): bool
    {
        $settings ??= $this->settings();

        return $settings->enabled
            && filled($settings->kode_bpi)
            && filled($settings->client_id)
            && filled($settings->client_secret)
            && (! $settings->verify_signatures || filled($settings->bpi_public_key))
            && filled($settings->siakad_api_key_hash);
    }

    public function readiness(BsiIntegrationSetting $settings): array
    {
        return [
            'enabled' => (bool) $settings->enabled,
            'kode_bpi' => filled($settings->kode_bpi),
            'client_id' => filled($settings->client_id),
            'client_secret' => filled($settings->client_secret),
            'bpi_public_key' => ! $settings->verify_signatures || filled($settings->bpi_public_key),
            'reconciliation_secret' => filled($settings->reconciliation_secret)
                || filled($settings->client_secret),
            'siakad_api_key' => filled($settings->siakad_api_key_hash),
            'ready' => $this->isReady($settings),
        ];
    }

    public function publicData(BsiIntegrationSetting $settings): array
    {
        $adminFee = $this->adminFeeConfiguration($settings);

        return [
            'id' => $settings->id,
            'enabled' => (bool) $settings->enabled,
            'environment' => $settings->environment,
            'test_mode' => (bool) $settings->test_mode,
            'institution_name' => $settings->institution_name,
            'kode_bpi' => $settings->kode_bpi,
            'client_id' => $settings->client_id,
            'client_secret_configured' => filled($settings->client_secret),
            'bpi_public_key' => $settings->bpi_public_key,
            'reconciliation_secret_configured' => filled($settings->reconciliation_secret),
            'reconciliation_email' => $settings->reconciliation_email,
            'payment_expiry_minutes' => (int) $settings->payment_expiry_minutes,
            'admin_fee_bearer' => $adminFee['bearer'],
            'admin_fee_amount' => $adminFee['amount'],
            'admin_fee_locked' => $adminFee['locked'],
            'production_admin_fee_bearer' => $settings->admin_fee_bearer ?: 'institution',
            'production_admin_fee_amount' => (float) ($settings->admin_fee_amount ?? 2500),
            'sandbox_admin_fee_amount' => (float) ($settings->sandbox_admin_fee_amount
                ?? self::DEFAULT_SANDBOX_ADMIN_FEE_AMOUNT),
            'timestamp_tolerance' => (int) $settings->timestamp_tolerance,
            'allowed_ips' => $settings->allowed_ips ?: self::DEFAULT_ALLOWED_IPS,
            'enforce_ip_allowlist' => (bool) $settings->enforce_ip_allowlist,
            'verify_signatures' => (bool) $settings->verify_signatures,
            'log_payloads' => (bool) $settings->log_payloads,
            'serve_test_va' => (bool) $settings->serve_test_va,
            'database_failure_mode' => $settings->database_failure_mode ?: 'none',
            'auto_transfer_enabled' => (bool) $settings->auto_transfer_enabled,
            'siakad_api_key_configured' => filled($settings->siakad_api_key_hash),
            'siakad_api_key_hint' => $settings->siakad_api_key_hint,
            'readiness' => $this->readiness($settings),
            'endpoints' => $this->endpoints(),
            'response_codes' => BsiSnapService::RESPONSE_CODE_CATALOG,
            'updated_at' => $settings->updated_at,
        ];
    }

    public function adminFeeConfiguration(BsiIntegrationSetting $settings): array
    {
        if (strtolower((string) $settings->environment) === 'sandbox') {
            return [
                'bearer' => self::SANDBOX_ADMIN_FEE_BEARER,
                'amount' => (float) ($settings->sandbox_admin_fee_amount
                    ?? self::DEFAULT_SANDBOX_ADMIN_FEE_AMOUNT),
                'locked' => false,
            ];
        }

        return [
            'bearer' => $settings->admin_fee_bearer ?: 'institution',
            'amount' => (float) ($settings->admin_fee_amount ?? 2500),
            'locked' => false,
        ];
    }

    public function snapTransactionAmount(
        KeuanganPembayaranBsi $payment,
        BsiIntegrationSetting $settings
    ): float {
        return $payment->snapTransactionAmount();
    }

    public function expectedSettlementAmount(
        KeuanganPembayaranBsi $payment,
        BsiIntegrationSetting $settings
    ): float {
        return $payment->expectedSettlementTotal();
    }

    /**
     * Konfigurasi lengkap yang hanya boleh dikirim melalui endpoint admin.
     * Secret tetap dienkripsi oleh model saat tersimpan di database.
     */
    public function adminData(BsiIntegrationSetting $settings): array
    {
        return [
            ...$this->publicData($settings),
            'client_secret' => $settings->client_secret,
            'reconciliation_secret' => $settings->reconciliation_secret,
        ];
    }

    public function endpoints(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'auth' => $base.'/api/bpi-bi-snap/auth',
            'inquiry' => $base.'/api/bpi-bi-snap/inquiry',
            'payment' => $base.'/api/bpi-bi-snap/payment',
            'advice' => $base.'/api/bpi-bi-snap/advice',
            'reconciliation' => $base.'/api/bpi-bi-snap/reconciliation',
            'siakad_bills' => $base.'/api/v1/integrations/siakad/bsi/bills/{nim}',
            'siakad_payment_orders' => $base.'/api/v1/integrations/siakad/bsi/payment-orders',
        ];
    }

    public function rotateSiakadKey(BsiIntegrationSetting $settings, ?int $userId = null): string
    {
        $plain = 'bsi_siakad_'.Str::random(48);
        $settings->update([
            'siakad_api_key_hash' => hash('sha256', $plain),
            'siakad_api_key_hint' => substr($plain, -8),
            'updated_by' => $userId,
        ]);

        return $plain;
    }

    public function rotateH2hCredentials(BsiIntegrationSetting $settings, ?int $userId = null): array
    {
        $clientId = (string) Str::uuid();
        $clientSecret = Str::random(64);

        $settings->update([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'updated_by' => $userId,
        ]);

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    public function rotateReconciliationSecret(
        BsiIntegrationSetting $settings,
        ?int $userId = null
    ): string {
        $secret = Str::random(64);

        $settings->update([
            'reconciliation_secret' => $secret,
            'updated_by' => $userId,
        ]);

        return $secret;
    }

    public function validPublicKey(?string $key): bool
    {
        if (blank($key)) {
            return false;
        }

        try {
            return openssl_pkey_get_public($key) !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
