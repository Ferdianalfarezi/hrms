<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Models\Kecelakaan;
use App\Modules\Sms\Support\GrafikCrossPatrol;
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
            // Kartu "Pertumbuhan" diganti grafik temuan Daily Patrol 7 hari terakhir (Open / Close).
            'dailyHariIni' => $this->dailyHariIni(Carbon::today()),
            // Kartu "Jam Kerja" diganti grafik temuan Cross Patrol per bulan (detail per departemen di modal perbesar).
            'grafikCross' => GrafikCrossPatrol::build(Carbon::today()),
        ]);
    }

    /**
     * Temuan Daily Patrol (seluruh perusahaan) hari ini + jumlah Open / Close per hari selama 7 hari terakhir.
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
                $ofDay = $patrols->filter(fn (DailyPatrol $patrol) => $patrol->tanggal->isSameDay($day));
                $closedOfDay = $ofDay->filter(fn (DailyPatrol $patrol) => $patrol->isClosed())->count();

                return [
                    'label' => $day->translatedFormat('D'),
                    'title' => $day->translatedFormat('D, d M'),
                    'open' => $ofDay->count() - $closedOfDay,
                    'close' => $closedOfDay,
                ];
            })->all(),
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
