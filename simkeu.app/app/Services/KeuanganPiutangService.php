<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KeuanganPiutangService
{
    public function activeSummaryForPegawaiIds(
        array $pegawaiIds,
        array $excludePengeluaranIds = [],
        string $cicilanMode = 'pisah'
    ): Collection
    {
        $pegawaiIds = collect($pegawaiIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pegawaiIds === []) {
            return collect();
        }

        $paymentSums = $this->paymentSumsQuery($excludePengeluaranIds);
        $paidSql = 'COALESCE(pembayaran.total_terbayar, 0)';
        $remainingSql = "(piutang.nominal - {$paidSql})";
        $defaultCicilanSql = $this->defaultCicilanSql($remainingSql, $cicilanMode);

        return DB::table('keuangan_piutang as piutang')
            ->leftJoinSub($paymentSums, 'pembayaran', 'pembayaran.piutang_id', '=', 'piutang.id')
            ->whereIn('piutang.pegawai_id', $pegawaiIds)
            ->whereRaw("{$remainingSql} > 0")
            ->select([
                'piutang.pegawai_id',
                DB::raw('COUNT(*) as jumlah_piutang'),
                DB::raw('COALESCE(SUM(piutang.nominal), 0) as total_piutang'),
                DB::raw("COALESCE(SUM({$paidSql}), 0) as total_terbayar"),
                DB::raw("COALESCE(SUM({$remainingSql}), 0) as sisa"),
                DB::raw("{$defaultCicilanSql} as default_cicilan"),
            ])
            ->groupBy('piutang.pegawai_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->pegawai_id => [
                    'jumlah_piutang' => (int) $row->jumlah_piutang,
                    'total_piutang' => (int) $row->total_piutang,
                    'total_terbayar' => (int) $row->total_terbayar,
                    'sisa' => (int) $row->sisa,
                    'default_cicilan' => (int) $row->default_cicilan,
                ],
            ]);
    }

    public function potongGajiTotalsForPengeluaranIds(array $pengeluaranIds): Collection
    {
        $pengeluaranIds = collect($pengeluaranIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pengeluaranIds === []) {
            return collect();
        }

        return DB::table('keuangan_piutang_pembayaran')
            ->where('jenis', 'potong_gaji')
            ->whereIn('pengeluaran_id', $pengeluaranIds)
            ->select([
                'pengeluaran_id',
                DB::raw('COALESCE(SUM(nominal), 0) as total'),
            ])
            ->groupBy('pengeluaran_id')
            ->pluck('total', 'pengeluaran_id')
            ->map(fn ($total) => (int) $total);
    }

    public function deletePotongGajiForPengeluaran(int $pengeluaranId): int
    {
        return DB::table('keuangan_piutang_pembayaran')
            ->where('pengeluaran_id', $pengeluaranId)
            ->where('jenis', 'potong_gaji')
            ->delete();
    }

    public function replacePotongGajiForPengeluaran(
        int $pengeluaranId,
        int $pegawaiId,
        string $tanggal,
        int $nominal,
        ?int $createdBy,
        ?string $keterangan = null
    ): array {
        $loans = DB::table('keuangan_piutang')
            ->where('pegawai_id', $pegawaiId)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->deletePotongGajiForPengeluaran($pengeluaranId);

        if ($nominal <= 0) {
            return ['inserted' => 0, 'nominal' => 0];
        }

        if ($loans->isEmpty()) {
            throw ValidationException::withMessages([
                'piutang_nominal' => ['Pegawai tidak memiliki piutang aktif.'],
            ]);
        }

        $loanIds = $loans->pluck('id')->all();
        $paidByLoan = DB::table('keuangan_piutang_pembayaran')
            ->whereIn('piutang_id', $loanIds)
            ->select([
                'piutang_id',
                DB::raw('COALESCE(SUM(nominal), 0) as total'),
            ])
            ->groupBy('piutang_id')
            ->pluck('total', 'piutang_id');

        $remainingByLoan = $loans
            ->map(function ($loan) use ($paidByLoan) {
                $paid = (int) ($paidByLoan[$loan->id] ?? 0);

                return [
                    'id' => (int) $loan->id,
                    'sisa' => max(0, (int) $loan->nominal - $paid),
                ];
            })
            ->filter(fn ($loan) => $loan['sisa'] > 0)
            ->values();

        $totalRemaining = (int) $remainingByLoan->sum('sisa');

        if ($nominal > $totalRemaining) {
            throw ValidationException::withMessages([
                'piutang_nominal' => ['Nominal potongan tidak boleh melebihi sisa piutang.'],
            ]);
        }

        $remainingNominal = $nominal;
        $now = now();
        $rows = [];

        foreach ($remainingByLoan as $loan) {
            if ($remainingNominal <= 0) {
                break;
            }

            $allocated = min($remainingNominal, $loan['sisa']);
            $remainingNominal -= $allocated;

            $rows[] = [
                'piutang_id' => $loan['id'],
                'tanggal' => $tanggal,
                'nominal' => $allocated,
                'jenis' => 'potong_gaji',
                'pengeluaran_id' => $pengeluaranId,
                'keterangan' => $keterangan,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('keuangan_piutang_pembayaran')->insert($rows);
        }

        return ['inserted' => count($rows), 'nominal' => $nominal];
    }

    private function paymentSumsQuery(array $excludePengeluaranIds = [])
    {
        $excludePengeluaranIds = collect($excludePengeluaranIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $query = DB::table('keuangan_piutang_pembayaran')
            ->select([
                'piutang_id',
                DB::raw('COALESCE(SUM(nominal), 0) as total_terbayar'),
            ]);

        if ($excludePengeluaranIds !== []) {
            $query->where(function ($scope) use ($excludePengeluaranIds) {
                $scope->whereNull('pengeluaran_id')
                    ->orWhereNotIn('pengeluaran_id', $excludePengeluaranIds);
            });
        }

        return $query->groupBy('piutang_id');
    }

    private function defaultCicilanSql(string $remainingSql, string $cicilanMode): string
    {
        if ($cicilanMode === 'gabung') {
            return "COALESCE(MAX(LEAST(piutang.default_cicilan, {$remainingSql})), 0)";
        }

        return "COALESCE(SUM(LEAST(piutang.default_cicilan, {$remainingSql})), 0)";
    }
}
