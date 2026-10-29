<?php

namespace App\Modules\Sms\Support;

use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Excel isi modal perbesar di dashboard SMS (Temuan Daily Patrol & Cross Patrol).
 * Tiap bagian modal jadi satu sheet, angkanya sama dengan yang tampil di modal untuk periode terpilih.
 */
class DashboardPatrolExport
{
    public const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const HEADER_FILL = 'EEF3FB';

    /**
     * Daily Patrol: ringkasan Total / Open / Close per departemen + rekap kategori x resiko.
     *
     * @param  int|null  $month  null = rekap setahun
     */
    public static function daily(int $year, ?int $month): StreamedResponse
    {
        $chart = GrafikTemuanPatrol::build(Carbon::today());
        $entry = $chart['data'][$year] ?? ['all' => [], 'months' => []];
        $rows = $month === null ? $entry['all'] : ($entry['months'][$month] ?? []);
        $period = self::period($year, $month);

        $spreadsheet = self::spreadsheet();

        $summary = $spreadsheet->getActiveSheet()->setTitle('Ringkasan');
        self::title($summary, 'Ringkasan Temuan Daily Patrol · '.$period);
        $table = array_map(fn (array $row) => [$row['department'], $row['total'], $row['open'], $row['close']], $rows);
        self::simpleTable($summary, 3, ['Departemen', 'Total', 'Open', 'Close'], $table, 'Jumlah', 'Belum ada temuan.');
        self::widths($summary, ['A' => 22, 'B' => 12, 'C' => 12, 'D' => 12]);

        $recap = $spreadsheet->createSheet()->setTitle('Rekap Kategori & Resiko');
        self::title($recap, 'Rekap Temuan Daily Patrol per Kategori & Resiko · '.$period);
        self::recapTable($recap, 3, [
            'rowHeader' => 'Departemen',
            'rows' => array_map(fn (array $row) => ['label' => $row['department'], ...$row], $rows),
            'groups' => ['STOP 6' => [...self::stop6Columns(), 'OTHER' => ['other', 'ergonomi'], '5R' => ['5r']]],
            'resiko' => array_fill_keys(array_keys(DailyPatrol::RESIKO), null),
            'statusGroup' => null,
            'colors' => ['no' => 'F8CBAD', 'label' => 'ED7D31', 'group' => '00B0F0', 'kat' => '00B050', 'res' => 'F8CBAD', 'total' => 'ED7D31', 'openHead' => 'FFFF00', 'closedHead' => '00B050', 'openBody' => null, 'closedBody' => null],
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return self::download($spreadsheet, 'Temuan Daily Patrol '.$period.'.xlsx');
    }

    /**
     * Cross Patrol: Open / Close per bulan, temuan per departemen per bulan (tahun $year),
     * dan Progress Perbaikan per zona untuk periode tersendiri ($progressYear / $progressMonth), sama seperti di modal.
     *
     * @param  int|null  $progressMonth  null = rekap setahun
     */
    public static function cross(int $year, int $progressYear, ?int $progressMonth): StreamedResponse
    {
        $chart = GrafikCrossPatrol::build(Carbon::today());
        $entry = $chart['data'][$year] ?? null;
        $open = $entry['open'] ?? array_fill(0, 12, 0);
        $close = $entry['close'] ?? array_fill(0, 12, 0);
        $months = GrafikKecelakaan::MONTHS;

        $spreadsheet = self::spreadsheet();

        // 1. Open / Close / Total per bulan.
        $monthly = $spreadsheet->getActiveSheet()->setTitle('Per Bulan');
        self::title($monthly, 'Grafik Temuan Cross Patrol · Per bulan · Tahun '.$year);
        $total = array_map(fn (int $o, int $c) => $o + $c, $open, $close);
        $table = [
            ['Open', ...$open, array_sum($open)],
            ['Close', ...$close, array_sum($close)],
            ['Total', ...$total, array_sum($total)],
        ];
        self::simpleTable($monthly, 3, ['', ...$months, 'Jumlah'], $table);
        $monthly->getStyle('A6:'.Coordinate::stringFromColumnIndex(count($months) + 2).'6')->getFont()->setBold(true);
        self::widths($monthly, ['A' => 12]);

        // 2. Temuan per departemen per bulan.
        $departments = $spreadsheet->createSheet()->setTitle('Per Departemen');
        self::title($departments, 'Temuan Cross Patrol per Departemen · Tahun '.$year);
        $table = array_map(fn (array $d) => [$d['department'], ...$d['values'], $d['total'], $d['open'], $d['close']], $entry['departments'] ?? []);
        self::simpleTable($departments, 3, ['Departemen', ...$months, 'Jumlah', 'Open', 'Close'], $table, 'Total', 'Belum ada temuan.');
        self::widths($departments, ['A' => 22]);

        // 3. Progress Perbaikan per zona.
        $progressEntry = $chart['data'][$progressYear]['zonas'] ?? ['all' => [], 'months' => []];
        $zonas = $progressMonth === null ? $progressEntry['all'] : ($progressEntry['months'][$progressMonth] ?? []);
        $empty = ['total' => 0, 'open' => 0, 'close' => 0, 'cells' => []];
        // Zona 1-9 selalu tampil; data lama tanpa zona (key 0) di baris terakhir kalau ada.
        $rows = array_map(fn (int $zona) => ['label' => 'ZONA '.$zona, ...($zonas[$zona] ?? $empty)], CrossPatrol::ZONA);
        if (isset($zonas[0])) {
            $rows[] = ['label' => 'Tanpa zona', ...$zonas[0]];
        }

        $progress = $spreadsheet->createSheet()->setTitle('Progress Perbaikan');
        self::title($progress, 'Progress Perbaikan Cross Patrol · '.self::period($progressYear, $progressMonth));
        $grey = 'DDD9C4';
        self::recapTable($progress, 3, [
            'rowHeader' => 'Zona',
            'rows' => $rows,
            'groups' => [
                'STOP 6' => self::stop6Columns(),
                'LAIN-LAIN' => ['OTHER' => ['other', 'ergonomi'], '5R' => ['5r']],
            ],
            'resiko' => ['A' => 'FF0000', 'B' => '00B0F0', 'C' => '000000'],
            'statusGroup' => 'PERBAIKAN STATUS',
            'colors' => ['no' => $grey, 'label' => $grey, 'group' => $grey, 'kat' => $grey, 'res' => null, 'total' => $grey, 'openHead' => $grey, 'closedHead' => $grey, 'openBody' => 'FFFF00', 'closedBody' => '00B050'],
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return self::download($spreadsheet, 'Temuan Cross Patrol '.$year.'.xlsx');
    }

    public static function period(int $year, ?int $month): string
    {
        return $month === null ? 'Tahun '.$year : self::MONTH_NAMES[$month - 1].' '.$year;
    }

    private static function spreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        return $spreadsheet;
    }

    private static function download(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private static function title(Worksheet $sheet, string $title): void
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    }

    /**
     * @param  array<string, float>  $widths
     */
    private static function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Kategori Stop 6 sebagai kolom rekap: judul (mis. "APPARATUS") => kunci kategori.
     *
     * @return array<string, list<string>>
     */
    private static function stop6Columns(): array
    {
        $columns = [];
        foreach (DailyPatrol::KATEGORI['Stop 6'] as $key => $label) {
            $columns[strtoupper(explode(' (', $label)[0])] = [$key];
        }

        return $columns;
    }

    /**
     * Tabel biasa: 1 baris judul, baris data, dan (opsional) baris jumlah dari kolom angka.
     *
     * @param  list<string>  $headers
     * @param  list<list<string|int>>  $rows
     */
    private static function simpleTable(Worksheet $sheet, int $top, array $headers, array $rows, ?string $sumLabel = null, ?string $emptyText = null): void
    {
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A'.$top, true);
        $header = $sheet->getStyle("A{$top}:{$last}{$top}");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);

        $row = $top + 1;
        if ($rows === [] && $emptyText !== null) {
            $sheet->mergeCells("A{$row}:{$last}{$row}")->setCellValue("A{$row}", $emptyText);
            $row++;
        }
        foreach ($rows as $values) {
            $sheet->fromArray($values, null, 'A'.$row, true);
            $row++;
        }

        if ($sumLabel !== null && $rows !== []) {
            $sums = [$sumLabel];
            for ($i = 1; $i < count($headers); $i++) {
                $sums[] = array_sum(array_column($rows, $i));
            }
            $sheet->fromArray($sums, null, 'A'.$row, true);
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
            $row++;
        }

        $style = $sheet->getStyle("A{$top}:{$last}".($row - 1));
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A'.($top + 1).':A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Tabel rekap ala form: No | label | grup kategori (tiap kategori dipecah per resiko) | TOTAL TEMUAN | OPEN | CLOSED.
     * Sel kategori yang nol dibiarkan kosong, baris Total menampilkan 0 (sama seperti gambar di modal).
     *
     * @param  array{rowHeader: string, rows: list<array{label: string, total: int, open: int, close: int, cells: array<string, int>}>, groups: array<string, array<string, list<string>>>, resiko: array<string, ?string>, statusGroup: ?string, colors: array<string, ?string>}  $cfg
     */
    private static function recapTable(Worksheet $sheet, int $top, array $cfg): void
    {
        $colors = $cfg['colors'];
        $resiko = array_keys($cfg['resiko']);
        $bottomHead = $top + 2;
        $col = fn (int $index) => Coordinate::stringFromColumnIndex($index);
        $fill = function (string $range, ?string $color) use ($sheet) {
            if ($color !== null) {
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
        };

        // Header 3 baris.
        $sheet->mergeCells("A{$top}:A{$bottomHead}")->setCellValue("A{$top}", 'No');
        $fill("A{$top}:A{$bottomHead}", $colors['no']);
        $sheet->mergeCells("B{$top}:B{$bottomHead}")->setCellValue("B{$top}", $cfg['rowHeader']);
        $fill("B{$top}:B{$bottomHead}", $colors['label']);

        $index = 3;
        $columns = [];
        foreach ($cfg['groups'] as $groupLabel => $groupColumns) {
            $groupEnd = $index + count($groupColumns) * count($resiko) - 1;
            $sheet->mergeCells("{$col($index)}{$top}:{$col($groupEnd)}{$top}")->setCellValue("{$col($index)}{$top}", $groupLabel);
            $fill("{$col($index)}{$top}:{$col($groupEnd)}{$top}", $colors['group']);
            foreach ($groupColumns as $label => $keys) {
                $end = $index + count($resiko) - 1;
                $sheet->mergeCells($col($index).($top + 1).':'.$col($end).($top + 1))->setCellValue($col($index).($top + 1), $label);
                $fill($col($index).($top + 1).':'.$col($end).($top + 1), $colors['kat']);
                foreach ($resiko as $code) {
                    $cell = $col($index).$bottomHead;
                    $sheet->setCellValue($cell, $code);
                    $fill($cell, $colors['res']);
                    if ($cfg['resiko'][$code] !== null) {
                        $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB($cfg['resiko'][$code]);
                    }
                    $columns[] = ['keys' => $keys, 'code' => $code, 'index' => $index];
                    $index++;
                }
            }
        }

        $totalCol = $col($index);
        $openCol = $col($index + 1);
        $closedCol = $col($index + 2);
        $sheet->mergeCells("{$totalCol}{$top}:{$totalCol}{$bottomHead}")->setCellValue("{$totalCol}{$top}", 'TOTAL TEMUAN');
        $fill("{$totalCol}{$top}:{$totalCol}{$bottomHead}", $colors['total']);
        $statusTop = $top;
        if ($cfg['statusGroup'] !== null) {
            $sheet->mergeCells("{$openCol}{$top}:{$closedCol}{$top}")->setCellValue("{$openCol}{$top}", $cfg['statusGroup']);
            $fill("{$openCol}{$top}:{$closedCol}{$top}", $colors['openHead']);
            $statusTop = $top + 1;
        }
        $sheet->mergeCells("{$openCol}{$statusTop}:{$openCol}{$bottomHead}")->setCellValue("{$openCol}{$statusTop}", 'OPEN');
        $fill("{$openCol}{$statusTop}:{$openCol}{$bottomHead}", $colors['openHead']);
        $sheet->mergeCells("{$closedCol}{$statusTop}:{$closedCol}{$bottomHead}")->setCellValue("{$closedCol}{$statusTop}", 'CLOSED');
        $fill("{$closedCol}{$statusTop}:{$closedCol}{$bottomHead}", $colors['closedHead']);
        $sheet->getStyle("A{$top}:{$closedCol}{$bottomHead}")->getFont()->setBold(true);

        // Isi.
        $row = $bottomHead + 1;
        $sums = array_fill(0, count($columns), 0);
        $sumTotal = $sumOpen = $sumClose = 0;
        foreach ($cfg['rows'] as $number => $data) {
            $sheet->setCellValue("A{$row}", $number + 1);
            $sheet->setCellValue("B{$row}", $data['label']);
            foreach ($columns as $i => $column) {
                $count = array_sum(array_map(fn (string $key) => $data['cells'][$key.'.'.$column['code']] ?? 0, $column['keys']));
                $sums[$i] += $count;
                if ($count > 0) {
                    $sheet->setCellValue($col($column['index']).$row, $count);
                }
            }
            $sheet->setCellValue("{$totalCol}{$row}", $data['total']);
            $sheet->setCellValue("{$openCol}{$row}", $data['open']);
            $sheet->setCellValue("{$closedCol}{$row}", $data['close']);
            $sumTotal += $data['total'];
            $sumOpen += $data['open'];
            $sumClose += $data['close'];
            $row++;
        }

        $sheet->mergeCells("A{$row}:B{$row}")->setCellValue("A{$row}", 'Total');
        foreach ($columns as $i => $column) {
            $sheet->setCellValue($col($column['index']).$row, $sums[$i]);
        }
        $sheet->setCellValue("{$totalCol}{$row}", $sumTotal);
        $sheet->setCellValue("{$openCol}{$row}", $sumOpen);
        $sheet->setCellValue("{$closedCol}{$row}", $sumClose);
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);

        $fill("{$openCol}".($bottomHead + 1).":{$openCol}{$row}", $colors['openBody']);
        $fill("{$closedCol}".($bottomHead + 1).":{$closedCol}{$row}", $colors['closedBody']);

        $style = $sheet->getStyle("A{$top}:{$closedCol}{$row}");
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(16);
        foreach ($columns as $column) {
            $sheet->getColumnDimension($col($column['index']))->setWidth(4.5);
        }
        $sheet->getColumnDimension($totalCol)->setWidth(14);
        $sheet->getColumnDimension($openCol)->setWidth(10);
        $sheet->getColumnDimension($closedCol)->setWidth(10);
        $sheet->getRowDimension($top + 1)->setRowHeight(28);
    }
}
