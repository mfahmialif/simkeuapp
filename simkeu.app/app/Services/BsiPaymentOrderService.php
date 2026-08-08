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
        $customerNo = $existing?->customer_no ?: self::customerNumberFromNim($payload['nim']);

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
