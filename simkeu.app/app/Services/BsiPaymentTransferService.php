<?php

namespace App\Services;

use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganJenisPembayaranDetail;
use App\Models\KeuanganNota;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganPembayaranBsi;
use App\Models\KeuanganPembayaranIDN;
use App\Models\KeuanganTagihan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BsiPaymentTransferService
{
    public const PETUGAS_ID = 10795;

    public const JENIS_PEMBAYARAN_ID = 16;

    public function eligibleQuery(): Builder
    {
        return KeuanganPembayaranBsi::query()
            ->where('production', true)
            ->where('data_test', false)
            ->where('status', 'success')
            ->where('transferred', false);
    }

    public function transfer(
        KeuanganPembayaranBsi $payment,
        ?int $initiatedBy = null
    ): KeuanganPembayaranBsi {
        [$transferred, $created] = DB::transaction(function () use ($initiatedBy, $payment) {
            $locked = KeuanganPembayaranBsi::query()
                ->with(['details.tahunAkademik', 'details.pembayaran'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($locked->transferred) {
                return [$locked, false];
            }

            $this->assertEligible($locked);

            if (! DB::table('users')->where('id', self::PETUGAS_ID)->exists()) {
                throw ValidationException::withMessages([
                    'petugas_id' => 'User BSI dengan ID 10795 tidak ditemukan.',
                ]);
            }

            if (! KeuanganJenisPembayaran::whereKey(self::JENIS_PEMBAYARAN_ID)->exists()) {
                throw ValidationException::withMessages([
                    'jenis_pembayaran_id' => 'Jenis pembayaran dengan ID 16 tidak ditemukan.',
                ]);
            }

            if ($locked->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'details' => 'Detail pembayaran BSI tidak ditemukan.',
                ]);
            }

            $paidAt = $locked->paid_at ?: $locked->trx_date_time ?: now();
            $nota = Helper::generateNota($paidAt->toDateTimeString(), $locked->jk_id);

            foreach ($locked->details as $detail) {
                if ($detail->pembayaran_id && $detail->pembayaran) {
                    continue;
                }

                if ((float) $detail->jumlah <= 0) {
                    continue;
                }

                $semester = $this->resolveSemester(
                    $locked->nim,
                    (string) $detail->tahunAkademik?->kode
                );

                if (! $semester) {
                    throw ValidationException::withMessages([
                        'tahun_akademik' => "Semester untuk tagihan {$detail->tagihan_nama} tidak dapat ditentukan.",
                    ]);
                }

                $ledgerPayment = KeuanganPembayaran::create([
                    'th_akademik_id' => $detail->th_akademik_id,
                    'nomor' => $this->postedPaymentNumber($locked->nomor, $detail->urutan),
                    'tanggal' => $paidAt,
                    'tagihan_id' => $detail->tagihan_id,
                    'nim' => $locked->nim,
                    'jumlah' => $detail->jumlah,
                    'smt' => $semester,
                    'jml_sks' => 1,
                    'jk_id' => $locked->jk_id,
                    'user_id' => self::PETUGAS_ID,
                    'sumber' => 'bsi',
                ]);

                KeuanganJenisPembayaranDetail::create([
                    'jenis_pembayaran_id' => self::JENIS_PEMBAYARAN_ID,
                    'pembayaran_id' => $ledgerPayment->id,
                ]);

                KeuanganNota::create([
                    'nota' => $nota,
                    'pembayaran_id' => $ledgerPayment->id,
                ]);

                $detail->update(['pembayaran_id' => $ledgerPayment->id]);
            }

            $locked->update([
                'transferred' => true,
                'posted_at' => now(),
                'posted_by' => $initiatedBy ?: self::PETUGAS_ID,
            ]);

            return [$locked->refresh()->load([
                'details.tahunAkademik',
                'details.pembayaran',
                'postedBy',
            ]), true];
        });

        if ($created) {
            $this->runPaymentSideEffects($transferred);
        }

        return $transferred;
    }

    public function attemptAutomatic(KeuanganPembayaranBsi $payment): bool
    {
        $settings = app(BsiSettingsService::class)->settings();
        if (! $settings->auto_transfer_enabled
            || $payment->status !== 'success'
            || ! $payment->production
            || $payment->data_test
            || $payment->transferred) {
            return false;
        }

        try {
            $this->transfer($payment, self::PETUGAS_ID);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function resolveSemester(string $nim, string $thAkademikKode): ?int
    {
        $tahunMasuk = (int) substr($nim, 0, 4);
        $tahunAkademik = (int) substr($thAkademikKode, 0, 4);
        $semesterAkademik = (int) substr($thAkademikKode, -1);

        if ($tahunMasuk <= 0 || $tahunAkademik <= 0 || ! in_array($semesterAkademik, [1, 2], true)) {
            return null;
        }

        $semester = (($tahunAkademik - $tahunMasuk) * 2) + $semesterAkademik;

        return $semester > 0 ? $semester : null;
    }

    public function postedPaymentNumber(?string $bsiNumber, int $sequence): string
    {
        return sprintf('%s-%02d', $bsiNumber ?: 'BSI', $sequence);
    }

    private function assertEligible(KeuanganPembayaranBsi $payment): void
    {
        if (! $payment->production || $payment->data_test || $payment->status !== 'success') {
            throw ValidationException::withMessages([
                'payment' => 'Hanya transaksi production, non-test, dan berstatus success yang dapat disinkronkan.',
            ]);
        }
    }

    private function runPaymentSideEffects(KeuanganPembayaranBsi $payment): void
    {
        try {
            SemesterPendek::syncTagihanIds(
                $payment->details->pluck('tagihan_id')->filter()->all(),
                $payment->nim
            );

            if ($this->shouldActivateMahasiswa($payment)) {
                Mahasiswa::updateStatusMahasiswa($payment->nim, 18);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Cek apakah pembayaran BSI memenuhi syarat untuk mengaktifkan mahasiswa:
     * 1. Akumulasi pembayaran Herregistrasi/Daftar Ulang + SPP >= Rp 450.000 (baik cicilan maupun langsung), ATAU
     * 2. Tagihan Herregistrasi + SPP pada semester tersebut sudah lunas (sisa <= 0).
     */
    public function shouldActivateMahasiswa(KeuanganPembayaranBsi $payment): bool
    {
        $eligibleDetails = $payment->details->filter(function ($detail) {
            $nama = Str::lower((string) ($detail->tagihan_nama ?? $detail->tagihan?->nama ?? ''));

            return Str::contains($nama, ['daftar ulang', 'regist', 'herregistrasi', 'spp']);
        });

        if ($eligibleDetails->isEmpty()) {
            return false;
        }

        // 1. Cek langsung jika pembayaran pada transaksi BSI ini sudah >= 450.000
        $currentPaid = (float) $eligibleDetails->sum('jumlah');
        if ($currentPaid >= 450000) {
            return true;
        }

        // 2. Cek akumulasi pembayaran herreg/spp pada tahun akademik terkait (cicilan bertahap)
        $thAkademikIds = $eligibleDetails->pluck('th_akademik_id')->filter()->unique()->all();

        if (! empty($thAkademikIds)) {
            $cumulativePaid = (float) KeuanganPembayaran::query()
                ->where('nim', $payment->nim)
                ->whereIn('th_akademik_id', $thAkademikIds)
                ->whereHas('tagihan', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'LIKE', '%daftar ulang%')
                            ->orWhere('nama', 'LIKE', '%regist%')
                            ->orWhere('nama', 'LIKE', '%herregistrasi%')
                            ->orWhere('nama', 'LIKE', '%spp%');
                    });
                })
                ->sum('jumlah');

            $tagihanIds = KeuanganTagihan::query()
                ->whereIn('th_akademik_id', $thAkademikIds)
                ->where(function ($q) {
                    $q->where('nama', 'LIKE', '%daftar ulang%')
                        ->orWhere('nama', 'LIKE', '%regist%')
                        ->orWhere('nama', 'LIKE', '%herregistrasi%')
                        ->orWhere('nama', 'LIKE', '%spp%');
                })
                ->pluck('id')
                ->all();

            $cumulativeIdn = 0;
            if (! empty($tagihanIds)) {
                $cumulativeIdn = (float) KeuanganPembayaranIDN::whereIn('tagihan_id', $tagihanIds)
                    ->where('bill_key', $payment->nim)
                    ->sum('total_bill_amount');
            }

            if (($cumulativePaid + $cumulativeIdn) >= 450000) {
                return true;
            }
        }

        // 3. Jika nominal masih < 450.000, cek apakah seluruh tagihan herreg/spp semester tersebut sudah lunas
        try {
            $tagihanData = TagihanMahasiswa::tagihan($payment->nim);
            $rawBills = collect($tagihanData['list_tagihan'] ?? []);

            $relevantBills = $rawBills->filter(function ($bill) use ($thAkademikIds) {
                $thMatch = empty($thAkademikIds) || in_array((int) data_get($bill, 'th_akademik_id'), $thAkademikIds, true);
                $nameMatch = Str::contains(
                    Str::lower((string) data_get($bill, 'nama', '')),
                    ['daftar ulang', 'regist', 'herregistrasi', 'spp']
                );

                return $thMatch && $nameMatch;
            });

            if ($relevantBills->isNotEmpty() && $relevantBills->every(fn ($b) => (float) data_get($b, 'sisa', 0) <= 0)) {
                return true;
            }
        } catch (Throwable $e) {
            $isDetailsLunas = $eligibleDetails->every(function ($detail) {
                return ((float) $detail->sisa_awal - (float) $detail->jumlah) <= 0;
            });
            if ($isDetailsLunas) {
                return true;
            }
        }

        return false;
    }
}
