<?php

namespace App\Exports\Concerns;

use App\Services\PimpinanExcelSignature;
use Maatwebsite\Excel\Events\AfterSheet;

trait AddsPimpinanSignature
{
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event) {
                PimpinanExcelSignature::append($event);
            },
        ];
    }
}
