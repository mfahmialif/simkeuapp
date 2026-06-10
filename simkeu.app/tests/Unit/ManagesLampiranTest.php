<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesLampiran;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagesLampiranTest extends TestCase
{
    public function test_it_stores_multiple_attachments_and_keeps_original_names(): void
    {
        Storage::fake('lampiran');
        $manager = $this->manager();
        $request = Request::create('/', 'POST');
        $request->files->set('lampiran', [
            UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('foto.png'),
        ]);

        $lampiran = $manager->update($request, null, 'kegiatan');

        $this->assertCount(2, $lampiran);
        $this->assertSame(['nota.pdf', 'foto.png'], array_column($lampiran, 'name'));
        Storage::disk('lampiran')->assertExists($lampiran[0]['path']);
        Storage::disk('lampiran')->assertExists($lampiran[1]['path']);
    }

    public function test_it_removes_selected_attachment_and_preserves_the_rest(): void
    {
        Storage::fake('lampiran');
        Storage::disk('lampiran')->put('umum/remove.pdf', 'remove');
        Storage::disk('lampiran')->put('umum/keep.pdf', 'keep');
        $manager = $this->manager();
        $request = Request::create('/', 'POST', [
            'hapus_lampiran' => ['umum/remove.pdf'],
        ]);

        $lampiran = $manager->update($request, [
            ['path' => 'umum/remove.pdf', 'name' => 'remove.pdf'],
            ['path' => 'umum/keep.pdf', 'name' => 'keep.pdf'],
        ], 'umum');

        $this->assertSame([
            ['path' => 'umum/keep.pdf', 'name' => 'keep.pdf'],
        ], $lampiran);
        Storage::disk('lampiran')->assertMissing('umum/remove.pdf');
        Storage::disk('lampiran')->assertExists('umum/keep.pdf');
    }

    public function test_it_builds_direct_public_urls_for_attachments(): void
    {
        $manager = $this->manager();
        $data = (object) [
            'lampiran' => [
                ['path' => 'tatapmuka/file.pdf', 'name' => 'Nota.pdf'],
            ],
        ];

        $result = $manager->decorate($data);

        $this->assertSame('Nota.pdf', $result->lampiran[0]['name']);
        $this->assertSame(
            url('lampiran/tatapmuka/file.pdf'),
            $result->lampiran[0]['url']
        );
    }

    private function manager(): object
    {
        return new class
        {
            use ManagesLampiran;

            public function update(
                Request $request,
                array|string|null $existing,
                string $directory
            ): array {
                return $this->updateLampiran($request, $existing, $directory);
            }

            public function decorate(object $data): object
            {
                return $this->appendLampiranUrls($data);
            }
        };
    }
}
