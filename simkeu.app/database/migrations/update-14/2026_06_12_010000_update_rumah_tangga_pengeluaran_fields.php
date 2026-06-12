<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateDetailTable('keuangan_pengeluaran_rumah_tangga', 'idx_pengeluaran_rumah_tangga_kelompok');
        $this->updateDetailTable('keuangan_pengeluaran_rumah_tangga_lpj', 'idx_pengeluaran_rumah_tangga_lpj_kelompok');

        Schema::dropIfExists('keuangan_pengeluaran_rumah_tangga_kelompok');
    }

    public function down(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_rumah_tangga_kelompok')) {
            Schema::create('keuangan_pengeluaran_rumah_tangga_kelompok', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama');
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->unique('nama', 'uq_rumah_tangga_kelompok_nama');
            });
        }

        $this->restoreDetailTable('keuangan_pengeluaran_rumah_tangga', 'idx_pengeluaran_rumah_tangga_kelompok');
        $this->restoreDetailTable('keuangan_pengeluaran_rumah_tangga_lpj', 'idx_pengeluaran_rumah_tangga_lpj_kelompok');
    }

    private function updateDetailTable(string $tableName, string $kelompokIndex): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'kelompok_anggaran')) {
            Schema::table($tableName, function (Blueprint $table) {
                // Temporary nullable so old rows can be backfilled before enforcing NOT NULL.
                $table->string('kelompok_anggaran')->nullable()->after('petugas_id');
            });
        }

        $this->backfillKelompokAnggaran($tableName);
        $this->makeKelompokAnggaranRequired($tableName);

        if (! Schema::hasColumn($tableName, 'jumlah') && ! Schema::hasColumn($tableName, 'volume')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('jumlah')->nullable()->after('nominal');
                $table->unsignedInteger('volume')->nullable()->after('jumlah');
            });
        } elseif (! Schema::hasColumn($tableName, 'jumlah')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('jumlah')->nullable()->after('nominal');
            });
        } elseif (! Schema::hasColumn($tableName, 'volume')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('volume')->nullable()->after('jumlah');
            });
        }

        if (Schema::hasColumn($tableName, 'kelompok_id')) {
            $this->dropIndexIfExists($tableName, $kelompokIndex);

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('kelompok_id');
            });
        }
    }

    private function restoreDetailTable(string $tableName, string $kelompokIndex): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, 'kelompok_anggaran')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('kelompok_anggaran');
            });
        }

        if (! Schema::hasColumn($tableName, 'kelompok_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($kelompokIndex) {
                $table->unsignedBigInteger('kelompok_id')->nullable()->after('tanggal');
                $table->index('kelompok_id', $kelompokIndex);
            });
        }

        if (Schema::hasColumn($tableName, 'jumlah')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('jumlah');
            });
        }

        if (Schema::hasColumn($tableName, 'volume')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('volume');
            });
        }
    }

    private function backfillKelompokAnggaran(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'kelompok_anggaran')) {
            return;
        }

        if (Schema::hasColumn($tableName, 'kelompok_id') && Schema::hasTable('keuangan_pengeluaran_rumah_tangga_kelompok')) {
            DB::statement("
                UPDATE `{$tableName}` AS detail
                LEFT JOIN `keuangan_pengeluaran_rumah_tangga_kelompok` AS kelompok
                    ON kelompok.id = detail.kelompok_id
                SET detail.kelompok_anggaran = COALESCE(
                    NULLIF(TRIM(detail.kelompok_anggaran), ''),
                    NULLIF(TRIM(kelompok.nama), ''),
                    NULLIF(TRIM(kelompok.kode), ''),
                    'Umum'
                )
                WHERE detail.kelompok_anggaran IS NULL
                    OR TRIM(detail.kelompok_anggaran) = ''
            ");

            return;
        }

        DB::table($tableName)
            ->whereNull('kelompok_anggaran')
            ->orWhere('kelompok_anggaran', '')
            ->update(['kelompok_anggaran' => 'Umum']);
    }

    private function makeKelompokAnggaranRequired(string $tableName): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `kelompok_anggaran` VARCHAR(255) NOT NULL");
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Throwable) {
            //
        }
    }
};
