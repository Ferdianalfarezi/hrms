<?php

namespace App\Modules\Sms\Support;

use App\Modules\Sms\Models\Kecelakaan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data grafik Work Accident & Traffic Accident di dashboard SMS: kasus per bulan tahun ini dibanding tahun lalu.
 * Tahun berjalan berhenti di bulan ini (bulan yang belum terjadi = null, tidak digambar).
 * Target = zero accident (0 tiap bulan).
 */
class GrafikKecelakaan
{
    /** Warna seri tetap per entitas (palet kategorikal yang sudah divalidasi aman buta warna). */
    private const COLOR_THIS_YEAR = '#2a78d6';

    private const COLOR_LAST_YEAR = '#eb6834';

    private const COLOR_MONTHLY = '#1baf7a';

    private const KATEGORI_COLORS = ['menabrak' => '#1baf7a', 'ditabrak' => '#eda100', 'tunggal' => '#e87ba4', 'tabrakan' => '#008300'];

    public const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    /**
     * @return array{work: array, traffic: array}
     */
    public static function build(Carbon $today): array
    {
        $year = $today->year;
        $lastYear = $year - 1;
        $currentMonth = $today->month;

        $rows = Kecelakaan::query()
            ->whereBetween('waktu', [Carbon::create($lastYear)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->get(['tipe', 'kategori', 'waktu']);

        $work = $rows->where('tipe', Kecelakaan::TIPE_WORK);
        $traffic = $rows->where('tipe', Kecelakaan::TIPE_TRAFFIC);

        return [
            'work' => [
                'title' => 'Work Accident',
                'subtitle' => "Kasus per bulan — {$year} vs {$lastYear}",
                'series' => [
                    self::series("Accident {$year}", self::COLOR_MONTHLY, self::cutOff(self::monthly($work, $year), $currentMonth)),
                    self::target(),
                    self::series("Acc.Accident {$lastYear}", self::COLOR_LAST_YEAR, self::cumulative(self::monthly($work, $lastYear))),
                    self::series("Acc.Accident {$year}", self::COLOR_THIS_YEAR, self::cutOff(self::cumulative(self::monthly($work, $year)), $currentMonth)),
                ],
            ],
            'traffic' => [
                'title' => 'Traffic Accident',
                'subtitle' => "Akumulasi kasus per bulan — {$year} vs {$lastYear}",
                'series' => [
                    self::target(),
                    self::series("Acc.Accident {$lastYear}", self::COLOR_LAST_YEAR, self::cumulative(self::monthly($traffic, $lastYear))),
                    ...collect(Kecelakaan::KATEGORI_TRAFFIC)->map(fn (string $label, string $kode) => self::series(
                        $label,
                        self::KATEGORI_COLORS[$kode],
                        self::cutOff(self::cumulative(self::monthly($traffic->where('kategori', $kode), $year)), $currentMonth),
                    ))->values()->all(),
                    self::series("Acc.Accident {$year}", self::COLOR_THIS_YEAR, self::cutOff(self::cumulative(self::monthly($traffic, $year)), $currentMonth)),
                ],
            ],
        ];
    }

    /**
     * Jumlah kasus per bulan (index 0 = Januari).
     *
     * @return list<int>
     */
    private static function monthly(Collection $rows, int $year): array
    {
        $counts = array_fill(0, 12, 0);
        foreach ($rows as $row) {
            if ($row->waktu->year === $year) {
                $counts[$row->waktu->month - 1]++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private static function cumulative(array $values): array
    {
        $total = 0;

        return array_map(function (int $value) use (&$total) {
            return $total += $value;
        }, $values);
    }

    /**
     * Bulan setelah bulan berjalan dikosongkan (belum terjadi).
     *
     * @param  list<int>  $values
     * @return list<?int>
     */
    private static function cutOff(array $values, int $currentMonth): array
    {
        return array_map(fn ($value, $index) => $index < $currentMonth ? $value : null, $values, array_keys($values));
    }

    private static function series(string $label, string $color, array $values): array
    {
        return ['label' => $label, 'color' => $color, 'values' => $values, 'target' => false];
    }

    private static function target(): array
    {
        return ['label' => 'Target', 'color' => '#6b7a95', 'values' => array_fill(0, 12, 0), 'target' => true];
    }
}
