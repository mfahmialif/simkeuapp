<?php

use App\Models\BsiReconciliation;
use App\Services\BsiSnapService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bsi_reconciliations')) {
            return;
        }

        try {
            $service = app(BsiSnapService::class);
            $records = BsiReconciliation::where('match_status', '!=', 'matched')->get();

            foreach ($records as $record) {
                $service->recheckReconciliation($record);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
    }
};
