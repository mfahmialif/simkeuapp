<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsPengeluaranIndex
{
    protected function aggregatePengeluaranStats($query, string $table): array
    {
        $dateColumn = "{$table}.tanggal";
        $totalColumn = "{$table}.total";
        $rekapColumn = "{$table}.rekap_id";
        $today = now();
        $todayDate = $today->toDateString();
        $weekStart = $today->copy()->startOfWeek()->toDateString();
        $weekEnd = $today->copy()->endOfWeek()->toDateString();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();

        $statsQuery = clone $query;
        $statsQuery->getQuery()->columns = null;
        $statsQuery->reorder();

        $row = $statsQuery
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$dateColumn} = ? THEN {$totalColumn} ELSE 0 END), 0) AS hari_ini_total,
                COUNT(CASE WHEN {$dateColumn} = ? THEN 1 END) AS hari_ini_jumlah,
                COALESCE(SUM(CASE WHEN {$dateColumn} BETWEEN ? AND ? THEN {$totalColumn} ELSE 0 END), 0) AS mingguan_total,
                COUNT(CASE WHEN {$dateColumn} BETWEEN ? AND ? THEN 1 END) AS mingguan_jumlah,
                COALESCE(SUM(CASE WHEN {$dateColumn} BETWEEN ? AND ? THEN {$totalColumn} ELSE 0 END), 0) AS bulanan_total,
                COUNT(CASE WHEN {$dateColumn} BETWEEN ? AND ? THEN 1 END) AS bulanan_jumlah,
                COALESCE(SUM({$totalColumn}), 0) AS keseluruhan_total,
                COUNT(*) AS keseluruhan_jumlah,
                COALESCE(SUM(CASE WHEN {$rekapColumn} IS NULL THEN {$totalColumn} ELSE 0 END), 0) AS belum_rekap_total,
                COUNT(CASE WHEN {$rekapColumn} IS NULL THEN 1 END) AS belum_rekap_jumlah",
                [
                    $todayDate,
                    $todayDate,
                    $weekStart,
                    $weekEnd,
                    $weekStart,
                    $weekEnd,
                    $monthStart,
                    $monthEnd,
                    $monthStart,
                    $monthEnd,
                ]
            )
            ->first();

        return [
            'hari_ini' => [
                'total' => (int) ($row->hari_ini_total ?? 0),
                'jumlah' => (int) ($row->hari_ini_jumlah ?? 0),
            ],
            'mingguan' => [
                'total' => (int) ($row->mingguan_total ?? 0),
                'jumlah' => (int) ($row->mingguan_jumlah ?? 0),
            ],
            'bulanan' => [
                'total' => (int) ($row->bulanan_total ?? 0),
                'jumlah' => (int) ($row->bulanan_jumlah ?? 0),
            ],
            'keseluruhan' => [
                'total' => (int) ($row->keseluruhan_total ?? 0),
                'jumlah' => (int) ($row->keseluruhan_jumlah ?? 0),
            ],
            'belum_rekap' => [
                'total' => (int) ($row->belum_rekap_total ?? 0),
                'jumlah' => (int) ($row->belum_rekap_jumlah ?? 0),
            ],
        ];
    }

    protected function paginateWithKnownTotal(
        $query,
        Request $request,
        int $total,
        int $defaultLimit = 10
    ): LengthAwarePaginator {
        $perPage = max(1, (int) $request->input('limit', $defaultLimit));
        $page = max(1, (int) $request->input('page', 1));
        $items = $query->forPage($page, $perPage)->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
