<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculatePengeluaranTotals extends Command
{
    protected $signature = 'pengeluaran:recalculate-totals
                            {--dry-run : Show count of mismatched rows without updating}';

    protected $description = 'Recalculate the total column for keuangan_pengeluaran_dosen_kegiatan based on transport + barokah (pegawai) or nominal (non_pegawai)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $table = 'keuangan_pengeluaran_dosen_kegiatan';

        $mismatchCondition = "
            total != CASE
                WHEN kategori_detail = 'pegawai' THEN ROUND(COALESCE(transport, 0) + COALESCE(barokah, 0))
                ELSE COALESCE(nominal, 0)
            END
        ";

        $mismatchedCount = DB::table($table)->whereRaw($mismatchCondition)->count();

        if ($mismatchedCount === 0) {
            $this->info('✓ All totals are correct. Nothing to fix.');
            return self::SUCCESS;
        }

        $this->warn("Found {$mismatchedCount} rows with incorrect totals.");

        if ($dryRun) {
            // Show a small sample
            $sample = DB::table($table)->whereRaw($mismatchCondition)->limit(20)->get();

            $this->table(
                ['ID', 'Kategori', 'Transport', 'Barokah', 'Nominal', 'Total (DB)', 'Total (Correct)'],
                $sample->map(function ($row) {
                    $correct = $row->kategori_detail === 'pegawai'
                        ? (int) round(($row->transport ?? 0) + ($row->barokah ?? 0))
                        : (int) ($row->nominal ?? 0);

                    return [
                        $row->id,
                        $row->kategori_detail,
                        number_format($row->transport ?? 0, 0, ',', '.'),
                        number_format($row->barokah ?? 0, 0, ',', '.'),
                        $row->nominal !== null ? number_format($row->nominal, 0, ',', '.') : 'NULL',
                        number_format($row->total ?? 0, 0, ',', '.'),
                        number_format($correct, 0, ',', '.'),
                    ];
                })->all()
            );

            $this->info("Showing 20 of {$mismatchedCount}. Run without --dry-run to apply fixes.");
            return self::SUCCESS;
        }

        // Fix all totals in a single UPDATE
        $updated = DB::table($table)
            ->whereRaw($mismatchCondition)
            ->update([
                'total' => DB::raw("
                    CASE
                        WHEN kategori_detail = 'pegawai' THEN ROUND(COALESCE(transport, 0) + COALESCE(barokah, 0))
                        ELSE COALESCE(nominal, 0)
                    END
                "),
            ]);

        $this->info("✓ Fixed {$updated} rows.");

        return self::SUCCESS;
    }
}
