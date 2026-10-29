<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Models\Kecelakaan;
use App\Modules\Sms\Support\DashboardPatrolExport;
use App\Modules\Sms\Support\GrafikCrossPatrol;
use App\Modules\Sms\Support\GrafikKecelakaan;
use App\Modules\Sms\Support\GrafikTemuanPatrol;
use App\Support\ModuleDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('sms.dashboard', [
            ...ModuleDashboard::build('sms', $request->user()),
            // Kartu "Status Akun" di dashboard SMS diganti 2 kartu: Hari Tanpa Work Accident & Hari Tanpa Traffic Accident.
            'hariTanpa' => $this->hariTanpaKecelakaan(),
            // Grafik Work & Traffic Accident menggantikan kartu Data Diri & kalender akun baru.
            'grafikKecelakaan' => GrafikKecelakaan::build(Carbon::today()),
            // Grafik temuan Daily Patrol per departemen menggantikan kartu "User Terbaru".
            'grafikTemuan' => GrafikTemuanPatrol::build(Carbon::today()),
            // Kartu "Jam Kerja" diganti grafik temuan Cross Patrol per bulan (detail per departemen di modal perbesar).
            'grafikCross' => GrafikCrossPatrol::build(Carbon::today()),
        ]);
    }

    /**
     * Export Excel isi modal perbesar "Temuan Daily Patrol" untuk bulan (atau "all" = setahun) & tahun terpilih.
     */
    public function exportDaily(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'regex:/^(all|[1-9]|1[0-2])$/'],
        ]);

        return DashboardPatrolExport::daily((int) $validated['year'], $this->monthOrNull($validated['month'] ?? null));
    }

    /**
     * Export Excel isi modal perbesar "Cross Patrol": grafik per bulan & per departemen (tahun kartu)
     * plus tabel Progress Perbaikan dengan bulan & tahunnya sendiri.
     */
    public function exportCross(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'progress_year' => ['nullable', 'integer', 'between:2000,2100'],
            'progress_month' => ['nullable', 'regex:/^(all|[1-9]|1[0-2])$/'],
        ]);
        $year = (int) $validated['year'];

        return DashboardPatrolExport::cross(
            $year,
            (int) ($validated['progress_year'] ?? $year),
            $this->monthOrNull($validated['progress_month'] ?? null),
        );
    }

    private function monthOrNull(?string $month): ?int
    {
        return $month === null || $month === 'all' ? null : (int) $month;
    }

    /**
     * Hari tanpa kecelakaan per tipe (Work / Traffic), dihitung dari tanggal kecelakaan terakhir tipe itu sampai hari ini.
     *
     * @return list<array{tipe: string, label: string, days: ?int, value: string, note: string}>
     */
    private function hariTanpaKecelakaan(): array
    {
        return collect(Kecelakaan::TIPE)->map(function (array $info, string $tipe) {
            $since = $this->sinceLast(Kecelakaan::where('tipe', $tipe));
            $last = $since['last'];

            return [
                'tipe' => $tipe,
                'label' => $info['label'],
                'days' => $since['days'],
                'value' => $since['days'] !== null ? number_format($since['days'], 0, ',', '.').' hari' : '–',
                'note' => $last
                    ? 'Terakhir '.$last->waktu->translatedFormat('d F Y').($last->department ? ' · '.$last->department->name : '')
                    : 'Belum ada '.$info['label'].' yang tercatat.',
            ];
        })->values()->all();
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
