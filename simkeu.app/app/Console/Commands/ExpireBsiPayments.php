<?php

namespace App\Console\Commands;

use App\Services\BsiPaymentService;
use Illuminate\Console\Command;

class ExpireBsiPayments extends Command
{
    protected $signature = 'bsi:expire';

    protected $description = 'Menandai transaksi VA BSI pending yang sudah kedaluwarsa';

    public function handle(): int
    {
        $count = BsiPaymentService::expirePending();
        $this->info("$count transaksi BSI ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}
