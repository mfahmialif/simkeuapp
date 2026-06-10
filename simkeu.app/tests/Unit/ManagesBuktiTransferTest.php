<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesBuktiTransfer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagesBuktiTransferTest extends TestCase
{
    public function test_it_stores_file_in_the_requested_public_evidence_directory(): void
    {
        Storage::fake('bukti');

        $path = $this->handler()->store(
            UploadedFile::fake()->image('transfer.jpg'),
            'tatapmuka'
        );

        $this->assertStringStartsWith('tatapmuka/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('bukti')->assertExists($path);
    }

    public function test_it_migrates_legacy_public_storage_file(): void
    {
        Storage::fake('public');
        Storage::fake('bukti');
        Storage::disk('public')->put('bukti-transfer/barokah-dosen/kegiatan/old.pdf', 'proof');

        $path = $this->handler()->migrate(
            'bukti-transfer/barokah-dosen/kegiatan/old.pdf',
            'kegiatan'
        );

        $this->assertStringStartsWith('kegiatan/', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('bukti')->assertExists($path);
        Storage::disk('public')->assertMissing('bukti-transfer/barokah-dosen/kegiatan/old.pdf');
    }

    public function test_it_deletes_new_and_legacy_files_from_the_correct_disk(): void
    {
        Storage::fake('public');
        Storage::fake('bukti');
        Storage::disk('public')->put('bukti-transfer/legacy.pdf', 'legacy');
        Storage::disk('bukti')->put('tatapmuka/current.pdf', 'current');

        $handler = $this->handler();
        $handler->delete('bukti-transfer/legacy.pdf');
        $handler->delete('tatapmuka/current.pdf');

        Storage::disk('public')->assertMissing('bukti-transfer/legacy.pdf');
        Storage::disk('bukti')->assertMissing('tatapmuka/current.pdf');
    }

    public function test_it_builds_a_direct_public_url_for_new_files(): void
    {
        $this->app['url']->forceRootUrl('http://example.test/backend/public_html');

        $url = $this->handler()->url('kegiatan/proof.pdf');

        $this->assertSame(
            'http://example.test/backend/public_html/bukti/kegiatan/proof.pdf',
            $url
        );
    }

    private function handler(): object
    {
        return new class
        {
            use ManagesBuktiTransfer;

            public function store(UploadedFile $file, string $directory): string
            {
                return $this->storeBuktiTransfer($file, $directory);
            }

            public function migrate(?string $path, string $directory): ?string
            {
                return $this->migrateLegacyBuktiTransfer($path, $directory);
            }

            public function delete(?string $path): void
            {
                $this->deleteBuktiTransfer($path);
            }

            public function url(?string $path): ?string
            {
                return $this->buktiTransferUrl($path);
            }
        };
    }
}
