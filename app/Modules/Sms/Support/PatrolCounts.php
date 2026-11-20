<?php

namespace App\Modules\Sms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Hitung jumlah temuan per tahun, bulan, dan kolom lain langsung di database (GROUP BY),
 * supaya grafik dashboard tidak membaca semua baris temuan satu per satu.
 */
class PatrolCounts
{
    /**
     * @param  list<string>  $columns  kolom tambahan pengelompokan, mis. ['department_id', 'status']
     * @return Collection<int, object{year: int, month: int, aggregate: int}>
     */
    public static function byMonth(Builder $query, array $columns, string $dateColumn = 'tanggal'): Collection
    {
        [$year, $month] = self::yearMonthExpressions($query, $dateColumn);

        return $query->toBase()
            ->selectRaw("{$year} as year, {$month} as month, ".implode(', ', $columns).', count(*) as aggregate')
            ->groupByRaw("{$year}, {$month}, ".implode(', ', $columns))
            ->get()
            ->map(function (object $row) {
                $row->year = (int) $row->year;
                $row->month = (int) $row->month;
                $row->aggregate = (int) $row->aggregate;

                return $row;
            });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function yearMonthExpressions(Builder $query, string $column): array
    {
        $column = $query->getQuery()->getGrammar()->wrap($column);

        return match ($query->getConnection()->getDriverName()) {
            'sqlite' => ["cast(strftime('%Y', {$column}) as integer)", "cast(strftime('%m', {$column}) as integer)"],
            'pgsql' => ["extract(year from {$column})", "extract(month from {$column})"],
            default => ["year({$column})", "month({$column})"],
        };
    }
}
