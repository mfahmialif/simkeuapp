<?php

namespace App\Services;

use App\Models\KeuanganMetodeVa;

class BsiVaPaymentMethodService
{
    public function resolveId(
        string $channelId,
        string $virtualAccountNo,
        string $kodeBpi
    ): ?int {
        $code = $this->resolveCode($channelId, $virtualAccountNo, $kodeBpi);

        return $code === null
            ? null
            : KeuanganMetodeVa::where('kode', $code)->value('id');
    }

    public function resolveCode(
        string $channelId,
        string $virtualAccountNo,
        string $kodeBpi
    ): ?string {
        $normalizedVa = preg_replace('/\s+/', '', $virtualAccountNo) ?: '';

        if (str_starts_with($normalizedVa, '900'.$kodeBpi)) {
            return KeuanganMetodeVa::ATM_LAIN;
        }

        return match ($channelId) {
            '6027' => KeuanganMetodeVa::BYOND_BSI,
            '6011' => KeuanganMetodeVa::ATM_BSI,
            default => null,
        };
    }
}
