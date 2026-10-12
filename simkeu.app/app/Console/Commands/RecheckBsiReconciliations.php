<?php

namespace App\Console\Commands;

use App\Models\BsiReconciliation;
use App\Services\BsiSnapService;
use Illuminate\Console\Command;

class RecheckBsiReconciliations extends Command
{
    protected $signature = 'bsi:recheck-reconciliations {--all : Periksa ulang semua data, bukan hanya yang belum cocok}';

    protected $description = 'Periksa dan cocokkan ulang data rekonsiliasi BSI dengan aturan terbaru';

    public function handle(BsiSnapService $service): int
    {
        $query = BsiReconciliation::query();

        if (! $this->option('all')) {
            $query->where('match_status', '!=', 'matched');
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->info('Tidak ada data rekonsiliasi yang perlu diperiksa ulang.');

            return self::SUCCESS;
        }

        $matchedCount = 0;
        $mismatchedCount = 0;

        $this->output->progressStart($records->count());

        foreach ($records as $record) {
            $updated = $service->recheckReconciliation($record);
            if ($updated->match_status === 'matched') {
                $matchedCount++;
            } else {
                $mismatchedCount++;
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info("Pemeriksaan ulang selesai: {$matchedCount} cocok, {$mismatchedCount} masih mismatch.");

        return self::SUCCESS;
    }
}
