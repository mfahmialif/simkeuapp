<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

trait ManagesLampiran
{
    protected function lampiranRules(): array
    {
        return [
            'lampiran' => 'nullable|array|max:10',
            'lampiran.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
            'hapus_lampiran' => 'nullable|array',
            'hapus_lampiran.*' => 'string|max:500',
        ];
    }

    protected function updateLampiran(
        Request $request,
        array|string|null $existing,
        string $directory
    ): array {
        $lampiran = $this->normalizeLampiran($existing);
        $removedPaths = collect($request->input('hapus_lampiran', []))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();

        foreach ($lampiran as $item) {
            if ($removedPaths->contains($item['path'])) {
                Storage::disk('lampiran')->delete($item['path']);
            }
        }

        $lampiran = array_values(array_filter(
            $lampiran,
            fn ($item) => ! $removedPaths->contains($item['path'])
        ));

        $files = $request->file('lampiran', []);
        $files = $files instanceof UploadedFile ? [$files] : $files;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $lampiran[] = $this->storeLampiran($file, $directory);
        }

        return $lampiran;
    }

    protected function appendLampiranUrls($data)
    {
        $data->lampiran = array_map(
            fn ($item) => [
                ...$item,
                'url' => url('lampiran/'.ltrim($item['path'], '/')),
            ],
            $this->normalizeLampiran($data->lampiran ?? null)
        );

        return $data;
    }

    protected function deleteLampiran(array|string|null $lampiran): void
    {
        foreach ($this->normalizeLampiran($lampiran) as $item) {
            Storage::disk('lampiran')->delete($item['path']);
        }
    }

    protected function normalizeLampiran(array|string|null $lampiran): array
    {
        if (is_string($lampiran)) {
            $decoded = json_decode($lampiran, true);
            $lampiran = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($lampiran)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                $item = ['path' => $item, 'name' => basename($item)];
            }

            if (! is_array($item) || empty($item['path'])) {
                return null;
            }

            return [
                'path' => str_replace('\\', '/', $item['path']),
                'name' => $item['name'] ?? basename($item['path']),
            ];
        }, $lampiran)));
    }

    private function storeLampiran(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = (string) Str::uuid();

        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $path = trim(str_replace('\\', '/', $directory), '/').'/'.$filename;
        $storedPath = $file->storeAs(dirname($path), basename($path), 'lampiran');

        if ($storedPath === false) {
            throw new RuntimeException('Lampiran gagal disimpan.');
        }

        return [
            'path' => str_replace('\\', '/', $storedPath),
            'name' => $file->getClientOriginalName(),
        ];
    }
}
