<?php

namespace App\Modules\Sms\Support;

use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\CrossPatrol;
use Illuminate\Support\Carbon;

/**
 * Data grafik "Cross Patrol" bulanan di dashboard SMS, dikelompokkan per tahun.
 * Tiap tahun berisi jumlah Open / Close per bulan (grafik kartu) dan jumlah temuan per departemen per bulan
 * (tooltip & heatmap di modal perbesar). Semua tahun dikirim sekaligus supaya pilihan tahun bisa ganti tanpa reload.
 *
 * "zonas" = rekap per zona untuk tabel "Progress Perbaikan" di modal perbesar, setahun ("all") dan per bulan (1-12).
 * Tiap zona berisi Total / Open / Close dan "cells" = jumlah temuan per "kategori.resiko" (mis. "apparatus.A").
 */
class GrafikCrossPatrol
{
    /**
     * @return array{years: list<int>, selected: int, data: array<int, array{open: list<int>, close: list<int>, departments: list<array{department: string, values: list<int>, total: int, open: int, close: int}>, zonas: array{all: array<int, array{total: int, open: int, close: int, cells: array<string, int>}>, months: array<int, array<int, array{total: int, open: int, close: int, cells: array<string, int>}>>}}>}
     */
    public static function build(Carbon $today): array
    {
        $names = Department::pluck('name', 'id');
        $years = [];

        // Sudah dijumlahkan di database: satu baris = satu kombinasi bulan, departemen, status, zona, kategori & resiko.
        foreach (PatrolCounts::byMonth(CrossPatrol::query(), ['department_id', 'status', 'zona', 'kategori', 'resiko']) as $group) {
            $year = $group->year;
            $month = $group->month - 1;
            $count = $group->aggregate;
            $department = $names[$group->department_id] ?? '-';
            $status = $group->status === CrossPatrol::STATUS_CLOSED ? 'close' : 'open';

            $years[$year] ??= self::emptyYear();
            $years[$year][$status][$month] += $count;
            $years[$year]['departments'][$department] ??= ['department' => $department, 'values' => array_fill(0, 12, 0), 'total' => 0, 'open' => 0, 'close' => 0];
            $years[$year]['departments'][$department]['values'][$month] += $count;
            $years[$year]['departments'][$department]['total'] += $count;
            $years[$year]['departments'][$department][$status] += $count;

            $zona = $group->zona !== null ? (int) $group->zona : null;
            $cell = $group->kategori.'.'.$group->resiko;
            self::countZona($years[$year]['zonas']['all'], $zona, $status, $cell, $count);
            self::countZona($years[$year]['zonas']['months'][$month + 1], $zona, $status, $cell, $count);
        }

        // Tahun berjalan selalu ada di pilihan walau belum ada temuan.
        $list = array_unique([...array_keys($years), $today->year]);
        rsort($list);

        $data = [];
        foreach ($list as $year) {
            $entry = $years[$year] ?? self::emptyYear();
            $departments = array_values($entry['departments']);
            usort($departments, fn (array $a, array $b) => strcmp($a['department'], $b['department']));
            $data[$year] = [...$entry, 'departments' => $departments];
        }

        return ['years' => $list, 'selected' => $today->year, 'data' => $data];
    }

    /**
     * @param  array<int, array{total: int, open: int, close: int, cells: array<string, int>}>  $zonas
     */
    private static function countZona(array &$zonas, ?int $zona, string $status, string $cell, int $count): void
    {
        // Data lama sebelum ada kolom zona dikumpulkan di key 0 ("Tanpa zona").
        $zona ??= 0;
        $zonas[$zona] ??= ['total' => 0, 'open' => 0, 'close' => 0, 'cells' => []];
        $zonas[$zona]['total'] += $count;
        $zonas[$zona][$status] += $count;
        $zonas[$zona]['cells'][$cell] = ($zonas[$zona]['cells'][$cell] ?? 0) + $count;
    }

    /**
     * @return array{open: list<int>, close: list<int>, departments: array<string, mixed>, zonas: array{all: array<int, mixed>, months: array<int, array<int, mixed>>}}
     */
    private static function emptyYear(): array
    {
        return [
            'open' => array_fill(0, 12, 0),
            'close' => array_fill(0, 12, 0),
            'departments' => [],
            'zonas' => ['all' => [], 'months' => array_fill(1, 12, [])],
        ];
    }
}
