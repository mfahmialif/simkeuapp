<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranLpj;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagesPengeluaranLpjRekapDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('keuangan_pengeluaran_lpj_rekap_status', function (Blueprint $table) {
            $table->id();
            $table->string('module_key');
            $table->unsignedBigInteger('rekap_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_lpj_rekap_status');

        foreach (array_column(self::lpjModules(), 1) as $lpjTable) {
            Schema::dropIfExists($lpjTable);
        }

        parent::tearDown();
    }

    #[DataProvider('lpjModules')]
    public function test_deleting_a_rekap_removes_its_lpj_rows_files_and_status_only(
        string $rekapTable,
        string $lpjTable,
        string $moduleKey
    ): void {
        Schema::create($lpjTable, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rekap_id');
            $table->string('bukti_transfer')->nullable();
            $table->text('lampiran')->nullable();
        });

        DB::table($lpjTable)->insert([
            [
                'id' => 1,
                'rekap_id' => 10,
                'bukti_transfer' => 'tatapmuka/bukti.pdf',
                'lampiran' => json_encode([['path' => 'tatapmuka/lampiran.pdf']]),
            ],
            [
                'id' => 2,
                'rekap_id' => 11,
                'bukti_transfer' => 'tatapmuka/lain.pdf',
                'lampiran' => json_encode([['path' => 'tatapmuka/lain-lampiran.pdf']]),
            ],
        ]);

        DB::table('keuangan_pengeluaran_lpj_rekap_status')->insert([
            ['module_key' => $moduleKey, 'rekap_id' => 10],
            ['module_key' => 'modul_lain', 'rekap_id' => 10],
            ['module_key' => $moduleKey, 'rekap_id' => 11],
        ]);

        $handler = $this->handler();

        $deleted = $handler->deleteForRekap($rekapTable, 10);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing($lpjTable, ['rekap_id' => 10]);
        $this->assertDatabaseHas($lpjTable, ['rekap_id' => 11]);
        $this->assertDatabaseMissing('keuangan_pengeluaran_lpj_rekap_status', [
            'module_key' => $moduleKey,
            'rekap_id' => 10,
        ]);
        $this->assertDatabaseHas('keuangan_pengeluaran_lpj_rekap_status', [
            'module_key' => 'modul_lain',
            'rekap_id' => 10,
        ]);
        $this->assertSame(['tatapmuka/bukti.pdf'], $handler->deletedBuktiTransfer);
        $this->assertSame(
            [json_encode([['path' => 'tatapmuka/lampiran.pdf']])],
            $handler->deletedLampiran
        );
    }

    public static function lpjModules(): array
    {
        return [
            'dosen tatap muka' => [
                'keuangan_pengeluaran_dosen_rekap',
                'keuangan_pengeluaran_dosen_lpj',
                'tatap_muka',
            ],
            'dosen kegiatan' => [
                'keuangan_pengeluaran_dosen_kegiatan_rekap',
                'keuangan_pengeluaran_dosen_kegiatan_lpj',
                'kegiatan',
            ],
            'rumah tangga' => [
                'keuangan_pengeluaran_rumah_tangga_rekap',
                'keuangan_pengeluaran_rumah_tangga_lpj',
                'rumah_tangga',
            ],
            'sarana prasarana' => [
                'keuangan_pengeluaran_sarana_prasarana_rekap',
                'keuangan_pengeluaran_sarana_prasarana_lpj',
                'sarana_prasarana',
            ],
            'transportasi' => [
                'keuangan_pengeluaran_transportasi_rekap',
                'keuangan_pengeluaran_transportasi_lpj',
                'transportasi',
            ],
            'dosen bulanan' => [
                'keuangan_pengeluaran_dosen_bulanan_rekap',
                'keuangan_pengeluaran_pegawai_bulanan_lpj',
                'dosen_bulanan',
            ],
        ];
    }

    private function handler(): object
    {
        return new class
        {
            use ManagesPengeluaranLpj;

            public array $deletedBuktiTransfer = [];

            public array $deletedLampiran = [];

            public function deleteForRekap(string $rekapTable, int $rekapId): int
            {
                return $this->deleteLpjForDeletedRekap($rekapTable, $rekapId);
            }

            protected function deleteBuktiTransfer(?string $path): void
            {
                if ($path) {
                    $this->deletedBuktiTransfer[] = $path;
                }
            }

            protected function deleteLampiran(array|string|null $lampiran): void
            {
                $this->deletedLampiran[] = $lampiran;
            }
        };
    }
}
