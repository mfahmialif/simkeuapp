<?php

namespace App\Services;

class TagihanLaporanFilter
{
    public static function includeWisudaSemesterPendek($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function applyWisudaSemesterPendekScope($query, bool $includeWisudaSemesterPendek, string $column = 'kt.nama')
    {
        if ($includeWisudaSemesterPendek) {
            return $query;
        }

        return self::excludeWisudaSemesterPendek($query, $column);
    }

    public static function excludeWisudaSemesterPendek($query, string $column = 'kt.nama')
    {
        return $query
            ->whereRaw("LOWER($column) NOT LIKE ?", ['%wisuda%'])
            ->whereRaw("LOWER($column) NOT LIKE ?", ['%semester pendek%']);
    }

    public static function excludeStandaloneSemesterOneToFive($query, string $column = 'nama')
    {
        foreach (range(1, 5) as $semester) {
            $query->whereRaw("LOWER(TRIM($column)) != ?", ["semester $semester"]);
        }

        return $query;
    }
}
