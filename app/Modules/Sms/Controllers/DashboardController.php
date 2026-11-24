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
            // Hari tanpa Work Accident & Traffic Accident tampil sebagai angka besar di header dashboard.
            'bigStats' => $this->hariTanpaKecelakaan(),
            // Bar chart kecelakaan per bulan + tabel detail menggantikan kartu Data Diri & kalender akun baru.
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

    /**
     * Export Excel tabel detail kecelakaan per bulan untuk tahun terpilih.
     */
    public function exportKecelakaan(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        return DashboardPatrolExport::kecelakaan((int) $validated['year']);
    }

    private function monthOrNull(?string $month): ?int
    {
        return $month === null || $month === 'all' ? null : (int) $month;
    }

    /**
     * Hari tanpa kecelakaan per tipe (Work / Traffic), dihitung dari tanggal kecelakaan terakhir tipe itu sampai hari ini.
     *
     * Detail kecelakaan terakhir tampil sebagai tooltip angkanya.
     *
     * @return list<array{label: string, value: int|string, icon: string, title: string}>
     */
    private function hariTanpaKecelakaan(): array
    {
        return collect(Kecelakaan::TIPE)->map(function (array $info, string $tipe) {
            $since = $this->sinceLast(Kecelakaan::where('tipe', $tipe));
            $last = $since['last'];

            return [
                'label' => __('Hari Tanpa :jenis', ['jenis' => __($info['label'])]),
                'value' => $since['days'] ?? '–',
                'icon' => $tipe === Kecelakaan::TIPE_TRAFFIC ? 'car' : 'shield',
                'title' => $last
                    ? __('Terakhir :tanggal', ['tanggal' => $last->waktu->translatedFormat('d F Y')])
                        .(filled($last->nama_korban) ? ' · '.$last->nama_korban : '')
                        .($last->department ? ' · '.$last->department->name : '')
                    : __('Belum ada :jenis yang tercatat.', ['jenis' => __($info['label'])]),
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
