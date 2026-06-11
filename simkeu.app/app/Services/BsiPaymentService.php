<?php

namespace App\Services;

use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganJenisPembayaranDetail;
use App\Models\KeuanganNota;
use App\Models\KeuanganPembayaran;
use App\Models\KeuanganPembayaranBsi;
use App\Models\KeuanganPembayaranBsiCallback;
use App\Models\KeuanganTagihan;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BsiPaymentService
{
    public const RESERVING_STATUSES = ['pending', 'paid', 'needs_review'];

    public static function expirePending(): int
    {
        return KeuanganPembayaranBsi::where('status', 'pending')
            ->where('expired_at', '<=', now())
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    public static function signature(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public static function verifySignature(
        string $timestamp,
        string $rawBody,
        string $providedSignature,
        string $secret
    ): bool {
        if ($secret === '' || $providedSignature === '') {
            return false;
        }

        return hash_equals(
            self::signature($timestamp, $rawBody, $secret),
            strtolower(trim($providedSignature))
        );
    }

    public static function buildInternalNumber(int $id, Carbon|string|null $date = null): string
    {
        $date = $date ? Carbon::parse($date) : now();

        return sprintf('BSI-%s-%08d', $date->format('Ymd'), $id);
    }

    public static function buildPostedPaymentNumber(string $bsiNumber, int $sequence): string
    {
        return sprintf('%s-%02d', $bsiNumber, $sequence);
    }

    public function availableTagihan(string $nim): array
    {
        self::expirePending();

        $nim = strtoupper(trim($nim));
        $tagihanData = TagihanMahasiswa::tagihan($nim);
        $items = collect($tagihanData['list_tagihan'] ?? [])
            ->map(function ($tagihan) use ($nim) {
                $tagihanId = (int) data_get($tagihan, 'id');
                $sisaResmi = max(0, (float) data_get($tagihan, 'sisa', 0));
                $reserved = $this->reservedAmount($nim, $tagihanId);
                $tersedia = max(0, $sisaResmi - $reserved);

                return [
                    'id' => $tagihanId,
                    'nama' => data_get($tagihan, 'nama'),
                    'th_akademik_id' => (int) data_get($tagihan, 'th_akademik_id'),
                    'th_akademik_kode' => data_get($tagihan, 'th_akademik_kode'),
                    'tahun_akademik' => data_get($tagihan, 'tahun_akademik'),
                    'jumlah_tagihan' => (float) data_get($tagihan, 'jumlah', 0),
                    'sisa_resmi' => $sisaResmi,
                    'reservasi_bsi' => $reserved,
                    'tersedia' => $tersedia,
                    'mata_uang_kode' => strtoupper((string) data_get($tagihan, 'mata_uang_kode', 'IDR')),
                    'tidak_bisa_dibayar' => (bool) data_get($tagihan, 'tidak_bisa_dibayar', false),
                    'keterangan_pembayaran' => data_get($tagihan, 'keterangan_pembayaran'),
                ];
            })
            ->filter(fn (array $item) => $item['tersedia'] > 0)
            ->values();

        return [
            'nim' => $nim,
            'nama_mahasiswa' => $tagihanData['nama_mhs'] ?? null,
            'nama_prodi' => $tagihanData['nama_prodi'] ?? null,
            'nama_kelas' => $tagihanData['nama_kelas'] ?? null,
            'semester' => $tagihanData['semester'] ?? null,
            'list_tagihan' => $items->all(),
            'total_tersedia' => $items->sum('tersedia'),
        ];
    }

    public function createPending(array $payload): array
    {
        $nim = strtoupper(trim($payload['nim']));
        $normalizedItems = collect($payload['items'])
            ->map(fn (array $item) => [
                'tagihan_id' => (int) $item['tagihan_id'],
                'jumlah' => round((float) $item['jumlah'], 2),
            ])
            ->sortBy('tagihan_id')
            ->values()
            ->all();

        if (count($normalizedItems) !== collect($normalizedItems)->pluck('tagihan_id')->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Tagihan yang sama tidak boleh dikirim lebih dari satu kali.',
            ]);
        }

        $canonical = [
            'request_id' => trim($payload['request_id']),
            'nim' => $nim,
            'va_number' => trim($payload['va_number']),
            'expired_at' => Carbon::parse($payload['expired_at'])->toIso8601String(),
            'items' => $normalizedItems,
        ];
        $requestHash = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES));

        $existing = KeuanganPembayaranBsi::where('request_id', $canonical['request_id'])->first();
        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                throw ValidationException::withMessages([
                    'request_id' => 'request_id sudah digunakan dengan payload berbeda.',
                ]);
            }

            return [$existing->load('details'), false];
        }

        $mahasiswa = $this->resolveMahasiswa($nim);
        if (! $mahasiswa) {
            throw ValidationException::withMessages([
                'nim' => 'Mahasiswa tidak ditemukan.',
            ]);
        }

        $gender = $this->resolveGender($mahasiswa);
        if (! $gender) {
            throw ValidationException::withMessages([
                'nim' => 'Jenis kelamin mahasiswa tidak dapat ditentukan.',
            ]);
        }

        $jenisPembayaran = KeuanganJenisPembayaran::whereIn(DB::raw('LOWER(TRIM(nama))'), [
                'va bsi',
                'va bsi - '.Str::lower($gender['kategori']),
            ])
            ->where('kategori', $gender['kategori'])
            ->where('is_manual', false)
            ->first();

        if (! $jenisPembayaran) {
            throw ValidationException::withMessages([
                'nim' => 'Jenis pembayaran VA BSI '.$gender['kategori'].' belum tersedia.',
            ]);
        }

        try {
            [$payment, $created] = DB::transaction(function () use (
                $canonical,
                $gender,
                $jenisPembayaran,
                $mahasiswa,
                $normalizedItems,
                $requestHash,
            ) {
                KeuanganTagihan::whereIn('id', collect($normalizedItems)->pluck('tagihan_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $existing = KeuanganPembayaranBsi::where('request_id', $canonical['request_id'])->first();
                if ($existing) {
                    if (! hash_equals($existing->request_hash, $requestHash)) {
                        throw ValidationException::withMessages([
                            'request_id' => 'request_id sudah digunakan dengan payload berbeda.',
                        ]);
                    }

                    return [$existing->load('details'), false];
                }

                $available = collect($this->availableTagihan($canonical['nim'])['list_tagihan'])->keyBy('id');
                $details = collect($normalizedItems)->map(function (array $item, int $index) use ($available) {
                    $tagihan = $available->get($item['tagihan_id']);

                    if (! $tagihan) {
                        throw ValidationException::withMessages([
                            "items.$index.tagihan_id" => 'Tagihan tidak tersedia atau sudah lunas.',
                        ]);
                    }

                    if ($tagihan['mata_uang_kode'] !== 'IDR') {
                        throw ValidationException::withMessages([
                            "items.$index.tagihan_id" => 'VA BSI hanya dapat digunakan untuk tagihan IDR.',
                        ]);
                    }

                    if ($tagihan['tidak_bisa_dibayar']) {
                        throw ValidationException::withMessages([
                            "items.$index.tagihan_id" => $tagihan['keterangan_pembayaran'] ?: 'Tagihan belum dapat dibayar.',
                        ]);
                    }

                    if ($item['jumlah'] <= 0 || $item['jumlah'] > $tagihan['tersedia']) {
                        throw ValidationException::withMessages([
                            "items.$index.jumlah" => 'Jumlah harus lebih dari 0 dan tidak melebihi nominal tersedia.',
                        ]);
                    }

                    return [
                        'tagihan_id' => $tagihan['id'],
                        'th_akademik_id' => $tagihan['th_akademik_id'],
                        'tagihan_nama' => $tagihan['nama'],
                        'jumlah_tagihan' => $tagihan['jumlah_tagihan'],
                        'sisa_awal' => $tagihan['tersedia'],
                        'jumlah' => $item['jumlah'],
                        'cara_bayar' => abs($item['jumlah'] - $tagihan['tersedia']) < 0.01 ? 'lunas' : 'cicilan',
                        'urutan' => $index + 1,
                    ];
                });
                $total = round((float) $details->sum('jumlah'), 2);

                $payment = KeuanganPembayaranBsi::create([
                    'request_id' => $canonical['request_id'],
                    'request_hash' => $requestHash,
                    'nim' => $canonical['nim'],
                    'nama_mahasiswa' => data_get($mahasiswa, 'nama'),
                    'jk_id' => $gender['id'],
                    'jenis_pembayaran_id' => $jenisPembayaran->id,
                    'va_number' => $canonical['va_number'],
                    'total' => $total,
                    'status' => 'pending',
                    'expired_at' => Carbon::parse($canonical['expired_at']),
                    'raw_request' => $canonical,
                ]);

                $payment->update([
                    'nomor' => self::buildInternalNumber($payment->id, $payment->created_at),
                ]);

                $payment->details()->createMany($details->all());

                return [$payment, true];
            });
        } catch (QueryException $exception) {
            $payment = KeuanganPembayaranBsi::where('request_id', $canonical['request_id'])->first();
            if (! $payment) {
                throw $exception;
            }

            if (! hash_equals($payment->request_hash, $requestHash)) {
                throw ValidationException::withMessages([
                    'request_id' => 'request_id sudah digunakan dengan payload berbeda.',
                ]);
            }

            $created = false;
        }

        if ($created) {
            $this->reconcileUnmatchedCallbacks($payment);
        }

        return [$payment->refresh()->load('details'), $created];
    }

    public function processCallback(array $payload): KeuanganPembayaranBsiCallback
    {
        $callbackId = trim((string) ($payload['callback_id'] ?? $payload['external_id'] ?? ''));
        $requestId = trim((string) ($payload['request_id'] ?? ''));
        $vaNumber = trim((string) ($payload['va_number'] ?? ''));
        $bankReference = trim((string) ($payload['bank_reference'] ?? $payload['reference_number'] ?? ''));
        $bankStatus = strtolower(trim((string) ($payload['status'] ?? '')));
        $amount = isset($payload['amount'])
            ? (float) $payload['amount']
            : (isset($payload['total_amount']) ? (float) $payload['total_amount'] : null);
        $paidAt = $payload['paid_at'] ?? $payload['transaction_time'] ?? null;

        if ($callbackId === '' || $bankStatus === '' || ($requestId === '' && $vaNumber === '')) {
            throw ValidationException::withMessages([
                'callback' => 'callback_id, status, dan request_id atau va_number wajib diisi.',
            ]);
        }

        $existing = KeuanganPembayaranBsiCallback::where('callback_id', $callbackId)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $amount,
                $bankReference,
                $bankStatus,
                $callbackId,
                $paidAt,
                $payload,
                $requestId,
                $vaNumber
            ) {
                $payment = $this->findPaymentForCallback($requestId, $vaNumber, true);
                $callback = KeuanganPembayaranBsiCallback::create([
                    'callback_id' => $callbackId,
                    'pembayaran_bsi_id' => $payment?->id,
                    'request_id' => $requestId ?: null,
                    'va_number' => $vaNumber ?: null,
                    'bank_reference' => $bankReference ?: null,
                    'amount' => $amount,
                    'bank_status' => $bankStatus,
                    'process_status' => $payment ? 'received' : 'unmatched',
                    'paid_at' => $paidAt ? Carbon::parse($paidAt) : null,
                    'payload' => $payload,
                ]);

                if (! $payment) {
                    $callback->update([
                        'message' => 'Transaksi BSI belum ditemukan.',
                    ]);

                    return $callback;
                }

                return $this->applyCallback($callback, $payment);
            });
        } catch (QueryException $exception) {
            $existing = KeuanganPembayaranBsiCallback::where('callback_id', $callbackId)->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function reservedAmount(string $nim, int $tagihanId, ?int $excludePaymentId = null): float
    {
        return (float) DB::table('keuangan_pembayaran_bsi_detail as detail')
            ->join('keuangan_pembayaran_bsi as pembayaran', 'pembayaran.id', '=', 'detail.pembayaran_bsi_id')
            ->where('pembayaran.nim', strtoupper(trim($nim)))
            ->where('detail.tagihan_id', $tagihanId)
            ->whereIn('pembayaran.status', self::RESERVING_STATUSES)
            ->when($excludePaymentId, fn ($query) => $query->where('pembayaran.id', '!=', $excludePaymentId))
            ->sum('detail.jumlah');
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

    public function postPayment(
        KeuanganPembayaranBsi $payment,
        int $userId,
        bool $confirmReview = false
    ): KeuanganPembayaranBsi {
        $posted = DB::transaction(function () use ($confirmReview, $payment, $userId) {
            $locked = KeuanganPembayaranBsi::lockForUpdate()
                ->with(['details.tahunAkademik', 'details.pembayaran'])
                ->findOrFail($payment->id);

            if ($locked->status === 'posted') {
                return $locked;
            }

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya transaksi BSI berstatus paid yang dapat diposting.',
                ]);
            }

            $paidAt = $locked->paid_at ?: now();
            $nota = Helper::generateNota($paidAt->toDateTimeString(), $locked->jk_id);

            foreach ($locked->details as $detail) {
                if ($detail->pembayaran_id && $detail->pembayaran) {
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
                    'nomor' => self::buildPostedPaymentNumber($locked->nomor, $detail->urutan),
                    'tanggal' => $paidAt,
                    'tagihan_id' => $detail->tagihan_id,
                    'nim' => $locked->nim,
                    'jumlah' => $detail->jumlah,
                    'smt' => $semester,
                    'jml_sks' => 1,
                    'jk_id' => $locked->jk_id,
                    'user_id' => $userId,
                    'sumber' => 'bsi',
                ]);

                KeuanganJenisPembayaranDetail::create([
                    'jenis_pembayaran_id' => $locked->jenis_pembayaran_id,
                    'pembayaran_id' => $ledgerPayment->id,
                ]);

                KeuanganNota::create([
                    'nota' => $nota,
                    'pembayaran_id' => $ledgerPayment->id,
                ]);

                $detail->update([
                    'pembayaran_id' => $ledgerPayment->id,
                ]);
            }

            $locked->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $userId,
            ]);

            return $locked->refresh()->load([
                'details.tahunAkademik',
                'details.pembayaran',
                'jenisPembayaran',
                'postedBy',
            ]);
        });

        SemesterPendek::syncTagihanIds($posted->details->pluck('tagihan_id')->all(), $posted->nim);

        if ($posted->details->contains(
            fn ($detail) => Str::contains(Str::lower($detail->tagihan_nama), ['daftar ulang', 'regist'])
        )) {
            Mahasiswa::updateStatusMahasiswa($posted->nim, 18);
        }

        return $posted;
    }

    public function rejectPayment(
        KeuanganPembayaranBsi $payment,
        int $userId,
        string $reason
    ): KeuanganPembayaranBsi {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $locked = KeuanganPembayaranBsi::lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === 'rejected') {
                return $locked;
            }

            if ($locked->status === 'posted') {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi yang sudah diposting tidak dapat ditolak.',
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $userId,
                'rejection_reason' => trim($reason),
            ]);

            return $locked->refresh()->load(['details', 'jenisPembayaran', 'rejectedBy']);
        });
    }

    private function applyCallback(
        KeuanganPembayaranBsiCallback $callback,
        KeuanganPembayaranBsi $payment
    ): KeuanganPembayaranBsiCallback {
        if (in_array($payment->status, ['posted', 'rejected'], true)) {
            $callback->update([
                'process_status' => 'ignored',
                'message' => 'Transaksi sudah '.$payment->status.'.',
                'processed_at' => now(),
            ]);

            return $callback->refresh();
        }

        $successStatuses = ['paid', 'success', 'successful', 'settlement'];
        $cancelledStatuses = ['cancelled', 'canceled', 'void'];
        $failedStatuses = ['failed', 'failure', 'expired'];

        if (in_array($callback->bank_status, $successStatuses, true)) {
            $amountMatches = $callback->amount !== null
                && abs((float) $callback->amount - (float) $payment->total) < 0.01;
            $newStatus = $amountMatches ? 'paid' : 'needs_review';
            $message = $amountMatches
                ? 'Pembayaran BSI berhasil dikonfirmasi.'
                : 'Nominal callback berbeda dari total transaksi.';

            $payment->update([
                'status' => $newStatus,
                'bank_reference' => $callback->bank_reference ?: $payment->bank_reference,
                'paid_at' => $callback->paid_at ?: now(),
                'raw_callback' => $callback->payload,
            ]);
        } elseif (in_array($callback->bank_status, $cancelledStatuses, true)) {
            $newStatus = 'cancelled';
            $message = 'Transaksi dibatalkan oleh BSI.';
            $payment->update([
                'status' => $newStatus,
                'cancelled_at' => now(),
                'bank_reference' => $callback->bank_reference ?: $payment->bank_reference,
                'raw_callback' => $callback->payload,
            ]);
        } elseif (in_array($callback->bank_status, $failedStatuses, true)) {
            $newStatus = $callback->bank_status === 'expired' ? 'expired' : 'failed';
            $message = 'Pembayaran BSI gagal.';
            $payment->update([
                'status' => $newStatus,
                'bank_reference' => $callback->bank_reference ?: $payment->bank_reference,
                'raw_callback' => $callback->payload,
            ]);
        } else {
            $newStatus = 'needs_review';
            $message = 'Status callback BSI tidak dikenali.';
            $payment->update([
                'status' => $newStatus,
                'bank_reference' => $callback->bank_reference ?: $payment->bank_reference,
                'raw_callback' => $callback->payload,
            ]);
        }

        $callback->update([
            'pembayaran_bsi_id' => $payment->id,
            'process_status' => $newStatus === 'needs_review' ? 'needs_review' : 'processed',
            'message' => $message,
            'processed_at' => now(),
        ]);

        return $callback->refresh();
    }

    private function reconcileUnmatchedCallbacks(KeuanganPembayaranBsi $payment): void
    {
        $callbacks = KeuanganPembayaranBsiCallback::where('process_status', 'unmatched')
            ->where(function ($query) use ($payment) {
                $query->where('request_id', $payment->request_id)
                    ->orWhere(function ($query) use ($payment) {
                        $query->whereNull('request_id')
                            ->where('va_number', $payment->va_number);
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($callbacks as $callback) {
            DB::transaction(function () use ($callback, $payment) {
                $lockedPayment = KeuanganPembayaranBsi::lockForUpdate()->find($payment->id);
                $this->applyCallback($callback, $lockedPayment);
            });
        }
    }

    private function findPaymentForCallback(
        string $requestId,
        string $vaNumber,
        bool $lock = false
    ): ?KeuanganPembayaranBsi {
        $query = KeuanganPembayaranBsi::query();

        if ($requestId !== '') {
            $query->where('request_id', $requestId);
        } else {
            $query->where('va_number', $vaNumber)->latest('id');
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function resolveMahasiswa(string $nim)
    {
        $mahasiswa = Mahasiswa::nim($nim);

        if (! $mahasiswa) {
            return null;
        }

        if (is_array($mahasiswa)) {
            if (array_key_exists('nim', $mahasiswa)) {
                return $mahasiswa;
            }

            return collect($mahasiswa)->first(
                fn ($item) => strtoupper((string) data_get($item, 'nim')) === $nim
            ) ?: collect($mahasiswa)->first();
        }

        return data_get($mahasiswa, 'nim') ? $mahasiswa : null;
    }

    private function resolveGender($mahasiswa): ?array
    {
        $jkId = (int) data_get($mahasiswa, 'jk_id', data_get($mahasiswa, 'jk.id'));
        if ($jkId === 8) {
            return ['id' => 8, 'kategori' => 'Putra'];
        }
        if ($jkId === 9) {
            return ['id' => 9, 'kategori' => 'Putri'];
        }

        $candidates = [
            data_get($mahasiswa, 'jk.kode'),
            data_get($mahasiswa, 'jk.nama'),
            data_get($mahasiswa, 'jenis_kelamin'),
            data_get($mahasiswa, 'gender'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = Str::lower(trim((string) $candidate));
            if (in_array($normalized, ['l', 'laki-laki', 'laki laki', 'putra', 'pria', 'male'], true)) {
                return ['id' => 8, 'kategori' => 'Putra'];
            }
            if (in_array($normalized, ['p', 'perempuan', 'putri', 'wanita', 'female'], true)) {
                return ['id' => 9, 'kategori' => 'Putri'];
            }
        }

        return null;
    }
}
