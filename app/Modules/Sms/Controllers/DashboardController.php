<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Models\Kecelakaan;
use App\Modules\Sms\Support\GrafikKecelakaan;
use App\Modules\Sms\Support\GrafikTemuanPatrol;
use App\Support\ModuleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('sms.dashboard', [
            ...ModuleDashboard::build('sms', $request->user()),
            // Kartu "Status Akun" di dashboard SMS diganti jadi "Hari Tanpa Kecelakaan".
            'composition' => $this->hariTanpaKecelakaan(),
            // Grafik Work & Traffic Accident menggantikan kartu Data Diri & kalender akun baru.
            'grafikKecelakaan' => GrafikKecelakaan::build(Carbon::today()),
            // Grafik temuan Daily Patrol per departemen menggantikan kartu "User Terbaru".
            'grafikTemuan' => GrafikTemuanPatrol::build(Carbon::today()),
            // Kartu "Pertumbuhan" diganti temuan Daily Patrol hari ini (grafik 7 hari terakhir).
            'dailyHariIni' => $this->dailyHariIni(Carbon::today()),
            // Kartu "Jam Kerja" diganti temuan Cross Patrol bulan ini, dibandingkan bulan lalu per minggu.
            'crossBulanIni' => $this->crossBulanIni(Carbon::today()),
        ]);
    }

    /**
     * Temuan Daily Patrol (seluruh perusahaan) hari ini + jumlah per hari selama 7 hari terakhir.
     * Bentuk data mengikuti kartu grafik batang di dashboard/module.blade.php.
     */
    private function dailyHariIni(Carbon $today): array
    {
        $since = $today->copy()->subDays(6);
        $patrols = DailyPatrol::whereBetween('tanggal', [$since, $today->copy()->endOfDay()])->get(['tanggal', 'status']);
        $todays = $patrols->filter(fn (DailyPatrol $patrol) => $patrol->tanggal->isSameDay($today));
        $closed = $todays->filter(fn (DailyPatrol $patrol) => $patrol->isClosed())->count();

        return [
            'total' => $todays->count(),
            'open' => $todays->count() - $closed,
            'closed' => $closed,
            'bars' => collect(range(0, 6))->map(function (int $offset) use ($since, $patrols) {
                $day = $since->copy()->addDays($offset);

                return [
                    'label' => $day->translatedFormat('D'),
                    'title' => $day->translatedFormat('d M'),
                    'count' => $patrols->filter(fn (DailyPatrol $patrol) => $patrol->tanggal->isSameDay($day))->count(),
                    'highlight' => $offset === 6,
                ];
            })->all(),
        ];
    }

    /**
     * Temuan Cross Patrol (seluruh perusahaan) bulan ini, per minggu (tgl 1-7, 8-14, 15-21, 22-28, 29-akhir),
     * dibandingkan bulan lalu pada minggu yang sama.
     */
    private function crossBulanIni(Carbon $today): array
    {
        $monthStart = $today->copy()->startOfMonth();
        $previousStart = $monthStart->copy()->subMonthNoOverflow();
        $patrols = CrossPatrol::whereBetween('tanggal', [$previousStart, $today->copy()->endOfMonth()])->get(['tanggal', 'status']);

        $weeks = collect(range(1, 5))->map(fn (int $week) => ['label' => 'Mg '.$week, 'current' => 0, 'previous' => 0])->all();
        $current = ['total' => 0, 'closed' => 0];
        $previousTotal = 0;

        foreach ($patrols as $patrol) {
            $week = min(intdiv($patrol->tanggal->day - 1, 7), 4);

            if ($patrol->tanggal->gte($monthStart)) {
                $weeks[$week]['current']++;
                $current['total']++;
                $current['closed'] += $patrol->isClosed() ? 1 : 0;
            } else {
                $weeks[$week]['previous']++;
                $previousTotal++;
            }
        }

        // Minggu ke-5 hanya ada kalau bulan ini / bulan lalu punya tanggal 29 ke atas (Februari bisa tidak).
        $days = max($monthStart->daysInMonth, $previousStart->daysInMonth);
        $weeks = array_slice($weeks, 0, $days > 28 ? 5 : 4);

        return [
            'total' => $current['total'],
            'open' => $current['total'] - $current['closed'],
            'closed' => $current['closed'],
            'month' => $monthStart->translatedFormat('F Y'),
            'currentLabel' => $monthStart->translatedFormat('M'),
            'previousLabel' => $previousStart->translatedFormat('M'),
            'previousTotal' => $previousTotal,
            'hasPrevious' => $previousTotal > 0,
            'weeks' => $weeks,
            'currentWeek' => min(intdiv($today->day - 1, 7), 4),
        ];
    }

    /**
     * Hari tanpa kecelakaan (seluruh perusahaan), dihitung dari tanggal kecelakaan terakhir sampai hari ini.
     * Bentuk data mengikuti kartu komposisi di dashboard/module.blade.php.
     */
    private function hariTanpaKecelakaan(): array
    {
        $all = $this->sinceLast(Kecelakaan::query());

        $segments = collect(Kecelakaan::TIPE)->map(function (array $info, string $tipe) {
            $since = $this->sinceLast(Kecelakaan::where('tipe', $tipe));

            return [
                'label' => 'Tanpa '.$info['label'],
                'count' => $since['days'] ?? 0,
                'text' => $since['days'] !== null ? number_format($since['days'], 0, ',', '.').' hari' : '–',
                'tone' => $tipe === Kecelakaan::TIPE_WORK ? 'primary' : 'navy',
                'always' => true,
            ];
        })->values()->all();

        $last = $all['last'];

        return [
            'title' => 'Hari Tanpa Kecelakaan',
            'value' => $all['days'] !== null ? number_format($all['days'], 0, ',', '.').' hari' : '–',
            'unit' => 'hari',
            'bar' => false,
            'segments' => $segments,
            'note' => $last
                ? 'Kecelakaan terakhir '.$last->waktu->translatedFormat('d F Y').' · '.$last->tipeLabel()
                    .($last->department ? ' · '.$last->department->name : '')
                : 'Belum ada kecelakaan yang tercatat.',
        ];
    }

    /**
     * @return array{days: ?int, last: ?Kecelakaan}
     */
    private function sinceLast($query): array
    {
        $last = $query->with('department')->orderByDesc('waktu')->orderByDesc('id')->first();

        if (! $last) {
            return ['days' => null, 'last' => null];
        }

        // Tanggal di masa depan (salah input) dianggap hari ini, jadi tidak pernah minus.
        $days = max(0, (int) $last->waktu->copy()->startOfDay()->diffInDays(Carbon::today(), false));

        return ['days' => $days, 'last' => $last];
    }
}
