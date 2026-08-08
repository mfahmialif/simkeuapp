<?php

namespace App\Services;

use App\Models\KeuanganPembayaranBsi;
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

        $existing = KeuanganPembayaranBsi::where('request_id', $payload['request_id'])->first();
        $customerNo = $existing?->customer_no ?: $this->generateCustomerNo();
        $bsiPaymentNumber = $existing?->va_number ?: $settings->kode_bpi.$customerNo;
        $expiredAt = $existing?->expired_at
            ?: now()->addMinutes((int) $settings->payment_expiry_minutes);

        [$payment, $created] = $this->paymentService->createPending([
            ...$payload,
            'va_number' => $bsiPaymentNumber,
            'expired_at' => $expiredAt,
            'data_test' => (bool) $settings->test_mode,
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
        return [
            'request_id' => $payment->request_id,
            'reference_no' => $payment->reference_no ?: $payment->nomor,
            'nim' => $payment->nim,
            'nama_mahasiswa' => $payment->nama_mahasiswa,
            'customer_no' => $payment->customer_no,
            'bsi_payment_number' => $payment->bsi_payment_number ?: $payment->va_number,
            'interbank_va_number' => $payment->interbank_va_number,
            'total' => $payment->total,
            'currency' => 'IDR',
            'status' => $payment->status,
            'data_test' => (bool) $payment->data_test,
            'expired_at' => $payment->expired_at,
            'paid_at' => $payment->paid_at,
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

    private function generateCustomerNo(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $number = str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
            if (! KeuanganPembayaranBsi::where('customer_no', $number)->exists()) {
                return $number;
            }
        }

        throw ValidationException::withMessages([
            'customer_no' => 'Nomor pembayaran belum dapat dibuat. Silakan ulangi.',
        ]);
    }
}
