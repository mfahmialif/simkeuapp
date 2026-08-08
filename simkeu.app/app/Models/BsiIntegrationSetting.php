<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BsiIntegrationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'test_mode' => 'boolean',
        'test_nims' => 'array',
        'client_secret' => 'encrypted',
        'reconciliation_secret' => 'encrypted',
        'payment_expiry_minutes' => 'integer',
        'admin_fee_amount' => 'decimal:2',
        'sandbox_admin_fee_amount' => 'decimal:2',
        'timestamp_tolerance' => 'integer',
        'allowed_ips' => 'array',
        'enforce_ip_allowlist' => 'boolean',
        'verify_signatures' => 'boolean',
        'log_payloads' => 'boolean',
        'serve_test_va' => 'boolean',
    ];
}
