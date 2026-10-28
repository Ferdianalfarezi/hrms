<?php

namespace App\Modules\Sms\Support;

use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\CrossPatrol;
use Illuminate\Support\Carbon;

/**
 * Data grafik "Cross Patrol" bulanan di dashboard SMS, dikelompokkan per tahun.
 * Tiap tahun berisi jumlah Open / Close per bulan (grafik kartu) dan jumlah temuan per departemen per bulan
 * (tooltip & heatmap di modal perbesar). Semua tahun dikirim sekaligus supaya pilihan tahun bisa ganti tanpa reload.
 */
class GrafikCrossPatrol
{
    /**
     * @return array{years: list<int>, selected: int, data: array<int, array{open: list<int>, close: list<int>, departments: list<array{department: string, values: list<int>, total: int, open: int, close: int}>}>}
     */
    public static function build(Carbon $today): array
    {
        $names = Department::pluck('name', 'id');
        $years = [];

        foreach (CrossPatrol::query()->select(['department_id', 'tanggal', 'status'])->cursor() as $patrol) {
            $year = $patrol->tanggal->year;
            $month = $patrol->tanggal->month - 1;
            $department = $names[$patrol->department_id] ?? '-';

            $years[$year] ??= ['open' => array_fill(0, 12, 0), 'close' => array_fill(0, 12, 0), 'departments' => []];
            $years[$year][$patrol->isClosed() ? 'close' : 'open'][$month]++;
            $years[$year]['departments'][$department] ??= ['department' => $department, 'values' => array_fill(0, 12, 0), 'total' => 0, 'open' => 0, 'close' => 0];
            $years[$year]['departments'][$department]['values'][$month]++;
            $years[$year]['departments'][$department]['total']++;
            $years[$year]['departments'][$department][$patrol->isClosed() ? 'close' : 'open']++;
        }

        // Tahun berjalan selalu ada di pilihan walau belum ada temuan.
        $list = array_unique([...array_keys($years), $today->year]);
        rsort($list);

        $data = [];
        foreach ($list as $year) {
            $entry = $years[$year] ?? ['open' => array_fill(0, 12, 0), 'close' => array_fill(0, 12, 0), 'departments' => []];
            $departments = array_values($entry['departments']);
            usort($departments, fn (array $a, array $b) => strcmp($a['department'], $b['department']));
            $data[$year] = [...$entry, 'departments' => $departments];
        }

        return ['years' => $list, 'selected' => $today->year, 'data' => $data];
    }
}
