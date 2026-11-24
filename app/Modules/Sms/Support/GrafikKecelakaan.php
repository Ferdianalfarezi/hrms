<?php

namespace App\Modules\Sms\Support;

use App\Modules\Sms\Models\Kecelakaan;
use Illuminate\Support\Carbon;

/**
 * Data bar chart kecelakaan di dashboard SMS: jumlah Work Accident & Traffic Accident per bulan untuk tiap tahun.
 * Tahun berjalan selalu tersedia walaupun belum ada kasus.
 */
class GrafikKecelakaan
{
    /** Warna seri tetap per entitas (palet kategorikal yang sudah divalidasi aman buta warna). */
    private const SERIES_COLORS = [
        Kecelakaan::TIPE_WORK => '#2a78d6',
        Kecelakaan::TIPE_TRAFFIC => '#eb6834',
    ];

    public const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    /**
     * @return array{
     *     years: list<int>,
     *     selected: int,
     *     series: list<array{key: string, label: string, color: string}>,
     *     data: array<int, array{work: list<int>, traffic: list<int>}>
     * }
     */
    public static function build(Carbon $today): array
    {
        $data = [$today->year => self::emptyYear()];

        foreach (Kecelakaan::query()->get(['tipe', 'waktu']) as $kecelakaan) {
            $year = $kecelakaan->waktu->year;
            $data[$year] ??= self::emptyYear();
            $data[$year][$kecelakaan->tipe][$kecelakaan->waktu->month - 1]++;
        }

        krsort($data);

        return [
            'years' => array_keys($data),
            'selected' => $today->year,
            'series' => collect(Kecelakaan::TIPE)->map(fn (array $info, string $tipe) => [
                'key' => $tipe,
                'label' => __($info['label']),
                'color' => self::SERIES_COLORS[$tipe],
            ])->values()->all(),
            'data' => $data,
        ];
    }

    /**
     * @return array{work: list<int>, traffic: list<int>}
     */
    public static function emptyYear(): array
    {
        return [
            Kecelakaan::TIPE_WORK => array_fill(0, 12, 0),
            Kecelakaan::TIPE_TRAFFIC => array_fill(0, 12, 0),
        ];
    }
}
