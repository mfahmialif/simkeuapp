<?php

namespace App\Services;

use App\Models\KeuanganPembayaranBsi;
use App\Models\KeuanganMetodeVa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BsiPaymentOrderService
{
    public function __construct(
        private readonly BsiPaymentService $paymentService,
        private readonly BsiSettingsService $settingsService,
    ) {}

    public function create(array $payload): array
    {
        $settings = $this->settingsService->settings();
        if (blank($settings->kode_bpi)) {
            throw ValidationException::withMessages([
                'configuration' => 'KODE_BPI belum dikonfigurasi.',
            ]);
        }

        $customerNo = self::customerNumberFromNim($payload['nim']);
        $lockName = 'bsi-order-'.substr(hash('sha256', $customerNo), 0, 48);
        $usesNamedLock = DB::connection()->getDriverName() === 'mysql';

        if ($usesNamedLock) {
            $result = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
            if ((int) ($result->acquired ?? 0) !== 1) {
                throw ValidationException::withMessages([
                    'nim' => 'Transaksi mahasiswa sedang diproses. Silakan coba kembali.',
                ]);
            }
        }

        try {
            return $this->createWhileLocked($payload, $settings, $customerNo);
        } finally {
            if ($usesNamedLock) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    private function createWhileLocked(array $payload, $settings, string $customerNo): array
    {

        $existing = KeuanganPembayaranBsi::where('request_id', $payload['request_id'])->first();
        $customerNo = $existing?->customer_no ?: $customerNo;

        if (! $existing) {
            BsiPaymentService::expirePending();
            $activeOrder = KeuanganPembayaranBsi::where('customer_no', $customerNo)
                ->whereIn('status', BsiPaymentService::RESERVING_STATUSES)
                ->latest('id')
                ->first();

            if ($activeOrder) {
                throw ValidationException::withMessages([
                    'nim' => 'Mahasiswa masih memiliki payment order aktif dengan nomor '.
                        ($activeOrder->bsi_payment_number ?: $activeOrder->va_number).'.',
                ]);
            }
        }

        $bsiPaymentNumber = $existing?->va_number ?: $settings->kode_bpi.$customerNo;
        $expiredAt = $existing?->expired_at
            ?: now()->addMinutes((int) $settings->payment_expiry_minutes);
        $adminFee = $this->settingsService->adminFeeConfiguration($settings);

        [$payment, $created] = $this->paymentService->createPending([
            ...$payload,
            'va_number' => $bsiPaymentNumber,
            'expired_at' => $expiredAt,
            'data_test' => (bool) $settings->test_mode,
            'production' => strtolower((string) $settings->environment) === 'production',
            'admin_fee_bearer' => $adminFee['bearer'],
            'admin_fee_amount' => $adminFee['amount'],
        ]);

        if ($created || blank($payment->customer_no)) {
            $payment->update([
                'customer_no' => $customerNo,
                'bsi_payment_number' => $bsiPaymentNumber,
                'interbank_va_number' => '900'.$bsiPaymentNumber,
                'reference_no' => $payment->nomor,
            ]);
        }

        return [$payment->refresh()->load('details'), $created];
    }

    public function data(KeuanganPembayaranBsi $payment): array
    {
        $payment->loadMissing(['details', 'metodeVa']);

        return [
            'request_id' => $payment->request_id,
            'reference_no' => $payment->reference_no ?: $payment->nomor,
            'nim' => $payment->nim,
            'nama_mahasiswa' => $payment->nama_mahasiswa,
            'customer_no' => $payment->customer_no,
            'bsi_payment_number' => $payment->bsi_payment_number ?: $payment->va_number,
            'interbank_va_number' => $payment->interbank_va_number,
            'metode_va_id' => $payment->metode_va_id,
            'metode_pembayaran' => $payment->metodeVa?->nama,
            'va_number' => $payment->metodeVa?->kode === KeuanganMetodeVa::ATM_LAIN
                ? $payment->interbank_va_number
                : ($payment->bsi_payment_number ?: $payment->va_number),
            'total' => $payment->total,
            'admin_fee_bearer' => $payment->admin_fee_bearer ?: 'institution',
            'admin_fee_amount' => (float) $payment->admin_fee_amount,
            'payable_total' => $payment->payableTotal(),
            'expected_settlement_total' => $payment->expectedSettlementTotal(),
            'currency' => 'IDR',
            'status' => $payment->status,
            'data_test' => (bool) $payment->data_test,
            'production' => (bool) $payment->production,
            'transferred' => (bool) $payment->transferred,
            'expired_at' => $payment->expired_at,
            'paid_at' => $payment->paid_at,
            'posted_at' => $payment->posted_at,
            'details' => $payment->details->map(fn ($detail) => [
                'tagihan_id' => $detail->tagihan_id,
                'tagihan_nama' => $detail->tagihan_nama,
                'jumlah' => $detail->jumlah,
                'cara_bayar' => $detail->cara_bayar,
            ])->values(),
        ];
    }

    public function cancel(string $requestId): KeuanganPembayaranBsi
    {
        return DB::transaction(function () use ($requestId) {
            $payment = KeuanganPembayaranBsi::where('request_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'cancelled') {
                return $payment->load('details');
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya payment order pending yang dapat dibatalkan.',
                ]);
            }

            $payment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $payment->refresh()->load('details');
        });
    }

    public function updatePaymentMethod(string $requestId, int $methodId): KeuanganPembayaranBsi
    {
        BsiPaymentService::expirePending();

        return DB::transaction(function () use ($requestId, $methodId) {
            $payment = KeuanganPembayaranBsi::where('request_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Metode pembayaran hanya dapat diubah saat payment order masih pending.',
                ]);
            }

            $method = KeuanganMetodeVa::query()
                ->whereKey($methodId)
                ->where('aktif', true)
                ->first();

            if (! $method) {
                throw ValidationException::withMessages([
                    'metode_va_id' => 'Metode pembayaran tidak aktif atau tidak ditemukan.',
                ]);
            }

            $payment->update(['metode_va_id' => $method->id]);

            return $payment->refresh()->load(['details', 'metodeVa']);
        });
    }

    public static function customerNumberFromNim(string $nim): string
    {
        $number = preg_replace('/[.\s]+/', '', trim($nim)) ?? '';

        if (! preg_match('/^\d{5,12}$/', $number)) {
            throw ValidationException::withMessages([
                'nim' => 'NIM setelah titik dihapus harus berupa 5 sampai 12 digit.',
            ]);
        }

        return $number;
    }
}
