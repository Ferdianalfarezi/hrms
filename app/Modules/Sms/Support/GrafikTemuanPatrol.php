<?php

namespace App\Modules\Sms\Support;

use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\DailyPatrol;
use Illuminate\Support\Carbon;

/**
 * Data grafik "Temuan Daily Patrol" per departemen (Total / Open / Close), dikelompokkan per tahun.
 * Semua tahun dikirim sekaligus supaya pilihan tahun di dashboard bisa ganti tanpa reload.
 */
class GrafikTemuanPatrol
{
    /**
     * @return array{years: list<int>, selected: int, data: array<int, list<array{department: string, total: int, open: int, close: int}>>}
     */
    public static function build(Carbon $today): array
    {
        $names = Department::pluck('name', 'id');
        $counts = [];

        foreach (DailyPatrol::query()->select(['department_id', 'tanggal', 'status'])->cursor() as $patrol) {
            $year = $patrol->tanggal->year;
            $department = $names[$patrol->department_id] ?? '-';
            $counts[$year][$department] ??= ['department' => $department, 'total' => 0, 'open' => 0, 'close' => 0];
            $counts[$year][$department]['total']++;
            $counts[$year][$department][$patrol->isClosed() ? 'close' : 'open']++;
        }

        // Tahun berjalan selalu ada di pilihan walau belum ada temuan.
        $years = array_unique([...array_keys($counts), $today->year]);
        rsort($years);

        $data = [];
        foreach ($years as $year) {
            $rows = array_values($counts[$year] ?? []);
            usort($rows, fn (array $a, array $b) => strcmp($a['department'], $b['department']));
            $data[$year] = $rows;
        }

        return ['years' => $years, 'selected' => $today->year, 'data' => $data];
    }
}
