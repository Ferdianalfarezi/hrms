<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
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
        ]);
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
