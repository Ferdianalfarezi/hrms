<?php

namespace App\Modules\Sms\Support;

use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\DailyPatrol;
use Illuminate\Support\Carbon;

/**
 * Data grafik "Temuan Daily Patrol" per departemen (Total / Open / Close), dikelompokkan per tahun & bulan.
 * Semua tahun dikirim sekaligus supaya pilihan tahun & bulan di dashboard bisa ganti tanpa reload.
 * Default yang dipilih: bulan & tahun berjalan; pilihan bulan "all" = rekap setahun.
 * "cells" = jumlah temuan per "kategori.resiko" (mis. "apparatus.S") untuk tabel rekap di modal perbesar.
 */
class GrafikTemuanPatrol
{
    /**
     * @return array{years: list<int>, selected: int, selectedMonth: int, data: array<int, array{all: list<array{department: string, total: int, open: int, close: int, cells: array<string, int>}>, months: array<int, list<array{department: string, total: int, open: int, close: int, cells: array<string, int>}>>}>}
     */
    public static function build(Carbon $today): array
    {
        $names = Department::pluck('name', 'id');
        $counts = [];

        // Sudah dijumlahkan di database: satu baris = satu kombinasi bulan, departemen, status, kategori & resiko.
        foreach (PatrolCounts::byMonth(DailyPatrol::query(), ['department_id', 'status', 'kategori', 'resiko']) as $group) {
            $year = $group->year;
            $count = $group->aggregate;
            $department = $names[$group->department_id] ?? '-';
            $status = $group->status === DailyPatrol::STATUS_CLOSED ? 'close' : 'open';
            $cell = $group->kategori.'.'.$group->resiko;

            foreach (['all', $group->month] as $period) {
                $counts[$year][$period][$department] ??= ['department' => $department, 'total' => 0, 'open' => 0, 'close' => 0, 'cells' => []];
                $counts[$year][$period][$department]['total'] += $count;
                $counts[$year][$period][$department][$status] += $count;
                $counts[$year][$period][$department]['cells'][$cell] = ($counts[$year][$period][$department]['cells'][$cell] ?? 0) + $count;
            }
        }

        // Tahun berjalan selalu ada di pilihan walau belum ada temuan.
        $years = array_unique([...array_keys($counts), $today->year]);
        rsort($years);

        $data = [];
        foreach ($years as $year) {
            $months = [];
            foreach (range(1, 12) as $month) {
                $months[$month] = self::sorted($counts[$year][$month] ?? []);
            }
            $data[$year] = ['all' => self::sorted($counts[$year]['all'] ?? []), 'months' => $months];
        }

        return ['years' => $years, 'selected' => $today->year, 'selectedMonth' => $today->month, 'data' => $data];
    }

    /**
     * @param  array<string, array{department: string, total: int, open: int, close: int, cells: array<string, int>}>  $rows
     * @return list<array{department: string, total: int, open: int, close: int, cells: array<string, int>}>
     */
    private static function sorted(array $rows): array
    {
        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => strcmp($a['department'], $b['department']));
        // Urutan kunci "kategori.resiko" dibuat tetap (urut abjad), tidak tergantung urutan hasil query.
        foreach (array_keys($rows) as $index) {
            ksort($rows[$index]['cells']);
        }

        return $rows;
    }
}
