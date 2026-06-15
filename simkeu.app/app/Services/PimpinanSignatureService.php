<?php

namespace App\Services;

use App\Models\Pimpinan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class PimpinanSignatureService
{
    private const SIGNATURE_DIRECTORY = 'pimpinan/ttd';
    private const LEGACY_QR_DIRECTORY = 'pimpinan/qr';

    public static function active(CarbonInterface|string|null $date = null): ?Pimpinan
    {
        return self::activeList($date)->first();
    }

    public static function activeList(CarbonInterface|string|null $date = null): Collection
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        return Pimpinan::query()
            ->where('status', 'aktif')
            ->whereDate('tanggal_awal_menjabat', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('tanggal_akhir_menjabat')
                    ->orWhereDate('tanggal_akhir_menjabat', '>=', $date);
            })
            ->latest('tanggal_awal_menjabat')
            ->get();
    }

    public static function payload(?Pimpinan $pimpinan, bool $includeSignatureImage = true): ?array
    {
        if (! $pimpinan) {
            return null;
        }

        $fileTtdPath = self::ensurePublicSignatureFile($pimpinan);
        $fileTtdUrl = $fileTtdPath ? url($fileTtdPath) : null;
        $imagePath = $includeSignatureImage ? self::imagePath($pimpinan) : null;

        return [
            'id' => $pimpinan->id,
            'nama' => $pimpinan->nama,
            'jabatan' => $pimpinan->jabatan,
            'file_ttd' => $pimpinan->file_ttd,
            'file_ttd_url' => $fileTtdUrl,
            'mode_ttd' => 'file',
            'tanggal_awal_menjabat' => $pimpinan->tanggal_awal_menjabat?->format('Y-m-d'),
            'tanggal_akhir_menjabat' => $pimpinan->tanggal_akhir_menjabat?->format('Y-m-d'),
            'status' => $pimpinan->status,
            'ttd_url' => $includeSignatureImage && $imagePath
                ? url(self::relativePublicPath($imagePath))
                : $fileTtdUrl,
            'created_at' => $pimpinan->created_at,
            'updated_at' => $pimpinan->updated_at,
        ];
    }

    public static function imagePath(?Pimpinan $pimpinan): ?string
    {
        if (! $pimpinan) {
            return null;
        }

        $fileTtdPath = self::ensurePublicSignatureFile($pimpinan);

        if (! $fileTtdPath) {
            return null;
        }

        $absolutePath = public_path($fileTtdPath);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    public static function documentSigner(
        ?string $fallbackName = null,
        ?string $fallbackPosition = null
    ): array {
        $pimpinan = self::active();

        return [
            'pimpinan' => $pimpinan,
            'nama' => $pimpinan?->nama ?: ($fallbackName ?: '-'),
            'jabatan' => $pimpinan?->jabatan ?: $fallbackPosition,
            'image_path' => self::imagePath($pimpinan),
        ];
    }

    public static function drawFpdf(
        object $fpdf,
        ?Pimpinan $pimpinan,
        float $x,
        float $y,
        float $width = 24,
        float $height = 16
    ): bool {
        $path = self::imagePath($pimpinan);

        if (! $path || ! is_file($path)) {
            return false;
        }

        $fpdf->Image($path, $x, $y, $width, $height);

        return true;
    }

    public static function deleteLegacySignatureFiles(Pimpinan $pimpinan): void
    {
        $disk = Storage::disk('public');
        $legacyDirectory = self::LEGACY_QR_DIRECTORY;

        if (! is_dir($disk->path($legacyDirectory))) {
            return;
        }

        foreach ($disk->files($legacyDirectory) as $file) {
            if (str_starts_with(basename($file), "{$pimpinan->id}-")) {
                $disk->delete($file);
            }
        }
    }

    public static function deleteSignatureFile(?string $path): void
    {
        $path = self::normalizeRelativePath($path);

        if (! $path) {
            return;
        }

        $absolutePath = public_path($path);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        Storage::disk('public')->delete($path);
    }

    private static function ensurePublicSignatureFile(Pimpinan $pimpinan): ?string
    {
        $path = self::normalizeRelativePath($pimpinan->file_ttd);

        if (! $path) {
            return null;
        }

        if (! str_starts_with($path, self::SIGNATURE_DIRECTORY.'/')) {
            return is_file(public_path($path)) ? $path : null;
        }

        $absolutePath = public_path($path);

        if (is_file($absolutePath)) {
            return $path;
        }

        $legacyDisk = Storage::disk('public');

        if (! $legacyDisk->exists($path)) {
            return null;
        }

        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $stream = $legacyDisk->readStream($path);

        if ($stream === false) {
            return null;
        }

        try {
            $written = file_put_contents($absolutePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $written === false ? null : $path;
    }

    private static function normalizeRelativePath(?string $path): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        return $path === '' ? null : $path;
    }

    private static function relativePublicPath(string $absolutePath): string
    {
        $root = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
    }
}
