<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $rekapTables = [
        'keuangan_pengeluaran_dosen_rekap' => 'idx_dosen_rekap_tahun_bulan',
        'keuangan_pengeluaran_dosen_kegiatan_rekap' => 'idx_dosen_kegiatan_rekap_tahun_bulan',
        'keuangan_pengeluaran_dosen_bulanan_rekap' => 'idx_dosen_bulanan_rekap_tahun_bulan',
        'keuangan_pengeluaran_staff_bulanan_rekap' => 'idx_staff_bulanan_rekap_tahun_bulan',
    ];

    private array $detailIndexes = [
        'keuangan_pengeluaran_dosen' => [
            'name' => 'idx_pengeluaran_dosen_rekap_tanggal',
            'columns' => ['rekap_id', 'tanggal'],
        ],
        'keuangan_pengeluaran_dosen_kegiatan' => [
            'name' => 'idx_pengeluaran_dosen_kegiatan_rekap_tanggal',
            'columns' => ['rekap_id', 'tanggal'],
        ],
        'keuangan_pengeluaran_pegawai_bulanan' => [
            'name' => 'idx_pengeluaran_pegawai_bulanan_rekap_pegawai',
            'columns' => ['rekap_id', 'pegawai_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->rekapTables as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'bulan')) {
                    $table->unsignedTinyInteger('bulan')->nullable()->after('nama');
                }

                if (! Schema::hasColumn($tableName, 'tahun')) {
                    $table->unsignedSmallInteger('tahun')->nullable()->after('bulan');
                }
            });

            if (! $this->indexExists($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->index(['tahun', 'bulan'], $indexName);
                });
            }
        }

        $this->backfillRekapPeriods(
            'keuangan_pengeluaran_dosen_rekap',
            'keuangan_pengeluaran_dosen'
        );
        $this->backfillRekapPeriods(
            'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'keuangan_pengeluaran_dosen_kegiatan'
        );
        $this->backfillMonthlyRekapPeriods(
            'keuangan_pengeluaran_dosen_bulanan_rekap',
            'dosen'
        );
        $this->backfillMonthlyRekapPeriods(
            'keuangan_pengeluaran_staff_bulanan_rekap',
            'staff'
        );

        foreach ($this->detailIndexes as $tableName => $definition) {
            if (
                ! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'rekap_id')
                || $this->indexExists($tableName, $definition['name'])
            ) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($definition) {
                $table->index($definition['columns'], $definition['name']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->detailIndexes as $tableName => $definition) {
            if (Schema::hasTable($tableName) && $this->indexExists($tableName, $definition['name'])) {
                Schema::table($tableName, function (Blueprint $table) use ($definition) {
                    $table->dropIndex($definition['name']);
                });
            }
        }

        foreach ($this->rekapTables as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if ($this->indexExists($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }

            $columns = array_values(array_filter(
                ['bulan', 'tahun'],
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }

    private function backfillRekapPeriods(string $rekapTable, string $detailTable): void
    {
        if (
            ! Schema::hasTable($rekapTable)
            || ! Schema::hasTable($detailTable)
            || ! Schema::hasColumn($detailTable, 'tanggal')
        ) {
            return;
        }

        DB::table($rekapTable)
            ->whereNull('tahun')
            ->orderBy('id')
            ->chunkById(100, function ($rekaps) use ($rekapTable, $detailTable) {
                foreach ($rekaps as $rekap) {
                    $tanggal = DB::table($detailTable)
                        ->where('rekap_id', $rekap->id)
                        ->min('tanggal');

                    $this->updatePeriodFromDate($rekapTable, $rekap->id, $tanggal);
                }
            });
    }

    private function backfillMonthlyRekapPeriods(string $rekapTable, string $pegawaiTipe): void
    {
        if (
            ! Schema::hasTable($rekapTable)
            || ! Schema::hasTable('keuangan_pengeluaran_pegawai_bulanan')
            || ! Schema::hasTable('pegawai')
        ) {
            return;
        }

        DB::table($rekapTable)
            ->whereNull('tahun')
            ->orderBy('id')
            ->chunkById(100, function ($rekaps) use ($rekapTable, $pegawaiTipe) {
                foreach ($rekaps as $rekap) {
                    $periode = DB::table('keuangan_pengeluaran_pegawai_bulanan as detail')
                        ->join('pegawai', 'pegawai.id', '=', 'detail.pegawai_id')
                        ->where('detail.rekap_id', $rekap->id)
                        ->where('pegawai.tipe', $pegawaiTipe)
                        ->orderBy('detail.tanggal')
                        ->select(['detail.tanggal', 'detail.bulan', 'detail.tahun'])
                        ->first();

                    if (! $periode) {
                        continue;
                    }

                    if ($periode->bulan && $periode->tahun) {
                        DB::table($rekapTable)
                            ->where('id', $rekap->id)
                            ->update([
                                'bulan' => (int) $periode->bulan,
                                'tahun' => (int) $periode->tahun,
                            ]);
                        continue;
                    }

                    $this->updatePeriodFromDate($rekapTable, $rekap->id, $periode->tanggal);
                }
            });
    }

    private function updatePeriodFromDate(string $tableName, int $id, $tanggal): void
    {
        if (! $tanggal) {
            return;
        }

        $timestamp = strtotime((string) $tanggal);

        if ($timestamp === false) {
            return;
        }

        DB::table($tableName)
            ->where('id', $id)
            ->update([
                'bulan' => (int) date('n', $timestamp),
                'tahun' => (int) date('Y', $timestamp),
            ]);
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return ! empty(DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        ));
    }
};
