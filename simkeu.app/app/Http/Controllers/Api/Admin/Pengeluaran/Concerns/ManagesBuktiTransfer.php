<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

trait ManagesBuktiTransfer
{
    protected function storeBuktiTransfer(UploadedFile $file, string $directory): string
    {
        $path = $this->newBuktiTransferPath($directory, $file->getClientOriginalExtension());

        $storedPath = $file->storeAs(
            dirname($path),
            basename($path),
            'bukti'
        );

        if ($storedPath === false) {
            throw new RuntimeException('Bukti transfer gagal disimpan.');
        }

        return str_replace('\\', '/', $storedPath);
    }

    protected function migrateLegacyBuktiTransfer(?string $path, string $directory): ?string
    {
        if (! $this->isLegacyBuktiTransferPath($path)) {
            return $path;
        }

        $legacyDisk = Storage::disk('public');

        if (! $legacyDisk->exists($path)) {
            return $path;
        }

        $newPath = $this->newBuktiTransferPath($directory, pathinfo($path, PATHINFO_EXTENSION));
        $stream = $legacyDisk->readStream($path);

        if ($stream === false) {
            return $path;
        }

        try {
            try {
                $stored = Storage::disk('bukti')->put($newPath, $stream);
            } catch (Throwable) {
                return $path;
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            return $path;
        }

        $legacyDisk->delete($path);

        return $newPath;
    }

    protected function buktiTransferUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if ($this->isLegacyBuktiTransferPath($path)) {
            return Storage::disk('public')->url($path);
        }

        return url('bukti/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    protected function deleteBuktiTransfer(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = $this->isLegacyBuktiTransferPath($path) ? 'public' : 'bukti';

        Storage::disk($disk)->delete($path);
    }

    private function newBuktiTransferPath(string $directory, ?string $extension): string
    {
        $extension = strtolower(trim((string) $extension, '.'));
        $filename = (string) Str::uuid();

        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        return trim(str_replace('\\', '/', $directory), '/').'/'.$filename;
    }

    private function isLegacyBuktiTransferPath(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'bukti-transfer/');
    }
}
