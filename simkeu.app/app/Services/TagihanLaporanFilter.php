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

    public static function excludeStandaloneSemester($query, string $column = 'nama')
    {
        return $query->whereRaw("LOWER(TRIM($column)) NOT REGEXP ?", ['^semester[[:space:]]+[0-9]+$']);
    }
}
