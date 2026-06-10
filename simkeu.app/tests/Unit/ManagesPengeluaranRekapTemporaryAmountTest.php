<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Admin\Pengeluaran\Concerns\ManagesPengeluaranRekap;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ManagesPengeluaranRekapTemporaryAmountTest extends TestCase
{
    public function test_no_detail_uses_temporary_amount(): void
    {
        $amounts = (new TestPengeluaranRekapAmountResolver)
            ->resolve(1000, 0, 0);

        $this->assertSame(1000, $amounts['jumlah']);
        $this->assertTrue($amounts['is_jumlah_sementara']);
        $this->assertSame(1000, $amounts['selisih_sementara']);
        $this->assertFalse($amounts['exceeds_temporary']);
        $this->assertFalse($amounts['should_clear_temporary']);
    }

    public function test_partial_detail_uses_actual_total_and_keeps_target(): void
    {
        $amounts = (new TestPengeluaranRekapAmountResolver)
            ->resolve(1000, 1, 400);

        $this->assertSame(400, $amounts['jumlah']);
        $this->assertFalse($amounts['is_jumlah_sementara']);
        $this->assertSame(600, $amounts['selisih_sementara']);
        $this->assertFalse($amounts['exceeds_temporary']);
        $this->assertFalse($amounts['should_clear_temporary']);
    }

    public function test_matching_detail_total_marks_temporary_amount_for_clearing(): void
    {
        $amounts = (new TestPengeluaranRekapAmountResolver)
            ->resolve(1000, 2, 1000);

        $this->assertSame(1000, $amounts['jumlah']);
        $this->assertSame(0, $amounts['selisih_sementara']);
        $this->assertFalse($amounts['exceeds_temporary']);
        $this->assertTrue($amounts['should_clear_temporary']);
    }

    public function test_detail_above_target_is_invalid(): void
    {
        $amounts = (new TestPengeluaranRekapAmountResolver)
            ->resolve(500, 1, 600);

        $this->assertSame(600, $amounts['jumlah']);
        $this->assertTrue($amounts['exceeds_temporary']);
        $this->assertFalse($amounts['should_clear_temporary']);
    }
}

class TestPengeluaranRekapAmountResolver
{
    use ManagesPengeluaranRekap;

    public function resolve(?int $temporaryAmount, int $detailCount, int $detailAmount): array
    {
        return $this->resolveRekapAmounts($temporaryAmount, $detailCount, $detailAmount);
    }

    protected function rekapModelClass(): string
    {
        return '';
    }

    protected function pengeluaranTable(): string
    {
        return '';
    }

    protected function newRekapPengeluaranQuery()
    {
        return null;
    }

    protected function newRekapBulkPengeluaranQuery(Request $request)
    {
        return null;
    }
}
