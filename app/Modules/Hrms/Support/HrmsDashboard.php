<?php

namespace App\Modules\Hrms\Support;

use App\Models\Application;
use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Support\ModuleDashboard;
use Illuminate\Support\Carbon;

/**
 * Dashboard HRMS berbasis tabel karyawans. Semua angka mengikuti hak akses user:
 * Admin Departemen hanya melihat karyawan di departemennya.
 */
class HrmsDashboard
{
    public static function build(User $viewer): array
    {
        $application = Application::where('code', 'hrms')->firstOrFail();
        $visible = fn () => Karyawan::query()->visibleTo($viewer);
        $managedDepartment = ($id = $viewer->managedDepartmentId()) ? Department::find($id) : null;

        $total = $visible()->count();
        $statusCounts = $visible()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tetap = (int) ($statusCounts['Tetap'] ?? 0);
        $pkwt = (int) $statusCounts->only(['PKWT 1', 'PKWT 2', 'PKWT 3'])->sum();
        $magang = (int) ($statusCounts['Pemagangan'] ?? 0);
        $perempuan = $visible()->where('jenis_kelamin', 'Perempuan')->count();

        // Karyawan masuk per bulan, 7 bulan terakhir (termasuk bulan ini).
        $since = Carbon::now()->startOfMonth()->subMonths(6);
        $joinDates = $visible()->where('tanggal_masuk', '>=', $since)->pluck('tanggal_masuk');
        $bars = collect(range(0, 6))->map(function ($offset) use ($since, $joinDates) {
            $month = $since->copy()->addMonths($offset);

            return [
                'label' => $month->translatedFormat('M'),
                'count' => $joinDates->filter(fn ($date) => $date->isSameMonth($month))->count(),
                'highlight' => $month->isSameMonth(now()),
            ];
        });

        return [
            'application' => $application,
            'subtitle' => $managedDepartment ? "Departemen {$managedDepartment->name}" : $application->description,
            'myApplications' => $viewer->accessibleApplications(),
            'bigStats' => [
                ['label' => 'Karyawan', 'value' => $total, 'icon' => 'users'],
                ['label' => 'Karyawan Tetap', 'value' => $tetap, 'icon' => 'badge'],
                ['label' => 'Masuk '.now()->year, 'value' => $visible()->whereYear('tanggal_masuk', now()->year)->count(), 'icon' => 'calendar'],
            ],
            'pills' => collect([
                ['label' => 'Tetap', 'count' => $tetap],
                ['label' => 'PKWT', 'count' => $pkwt],
                ['label' => 'Pemagangan', 'count' => $magang],
            ])->filter(fn ($item) => $item['count'] > 0)
                ->map(fn ($item) => [...$item, 'percent' => ModuleDashboard::percent($item['count'], $total)])
                ->values()->all(),
            'pillOutlines' => $total > 0 ? [
                ['label' => 'Laki-laki', 'count' => $total - $perempuan],
                ['label' => 'Perempuan', 'count' => $perempuan],
            ] : [],
            // Admin Departemen: daftar tugas absensi yang belum diisi, bukan grafik karyawan baru.
            'growth' => $viewer->isDepartmentAdmin() ? self::belumAdaKeterangan($visible) : [
                'title' => 'Karyawan Masuk',
                'total' => $bars->sum('count'),
                'caption' => "karyawan masuk\n7 bulan terakhir",
                'bubble' => 'bulan ini',
                'bars' => $bars->all(),
                'link' => route('hrms.karyawans.index'),
            ],
            // Admin Departemen hanya punya 1 departemen, jadi yang ditampilkan komposisi jenis kelamin.
            'composition' => $viewer->isDepartmentAdmin()
                ? self::genderComposition($total, $perempuan)
                : self::absensiHariIni($visible),
            'people' => [
                'title' => 'Karyawan Terbaru',
                'counter' => min(5, $total).'/'.$total,
                'items' => $visible()->with('department')->whereNotNull('tanggal_masuk')
                    ->orderByDesc('tanggal_masuk')->orderByDesc('nik')->take(5)->get()
                    ->map(fn (Karyawan $karyawan) => [
                        'name' => $karyawan->nama,
                        'sub' => collect(["NIK {$karyawan->nik}", $karyawan->department?->name, 'masuk '.$karyawan->tanggal_masuk->translatedFormat('d M Y')])
                            ->filter()->join(' · '),
                        'check' => $karyawan->status === 'Tetap',
                        'url' => route('hrms.karyawans.index', ['q' => $karyawan->nik]),
                    ])->all(),
                'empty' => 'Belum ada data karyawan.',
            ],
            'calendar' => ModuleDashboard::week(
                self::birthdaysThisWeek($visible),
                caption: 'Karyawan yang berulang tahun minggu ini.',
                empty: 'Tidak ada karyawan yang berulang tahun minggu ini.',
            ),
        ];
    }

    /**
     * Nama panggilan untuk kalender: kata pertama, atau dua kata kalau kata pertama sangat pendek (mis. "I Made").
     */
    private static function shortName(string $nama): string
    {
        $words = explode(' ', $nama);

        return mb_strlen($words[0]) <= 2 && isset($words[1]) ? "{$words[0]} {$words[1]}" : $words[0];
    }

    private static function genderComposition(int $total, int $perempuan): array
    {
        $lakiLaki = $total - $perempuan;

        return [
            'title' => 'Jenis Kelamin',
            'value' => $total,
            'unit' => 'orang',
            'segments' => [
                ['label' => 'Laki-laki', 'count' => $lakiLaki, 'tone' => 'primary'],
                ['label' => 'Perempuan', 'count' => $perempuan, 'tone' => 'navy'],
            ],
            'note' => null,
        ];
    }

    /**
     * Karyawan yang tidak absen di mesin tapi belum diberi kode oleh Admin Departemen, per hari kerja (Senin - Jumat).
     * Hari ini ikut dihitung; kalau hari ini Sabtu/Minggu, grafik berakhir di Jumat terakhir.
     */
    private static function belumAdaKeterangan(\Closure $visible): array
    {
        $today = Carbon::today();
        $days = collect();
        for ($day = $today->isWeekend() ? $today->copy()->previousWeekday() : $today->copy(); $days->count() < 7; $day->subWeekday()) {
            $days->prepend($day->copy());
        }

        $sel = KomparasiAbsensi::selBelumAdaKeterangan($visible()->get(['id', 'nik', 'group']), $days->first(), $days->last());
        $perTanggal = collect($sel)->flatMap(fn (array $tanggal) => array_keys($tanggal))->countBy();

        $bars = $days->map(fn (Carbon $day) => [
            'label' => $day->translatedFormat('D'),
            'count' => $perTanggal[$day->toDateString()] ?? 0,
            'highlight' => $day->isToday(),
        ]);

        return [
            'title' => 'Belum Ada Keterangan',
            'total' => $bars->sum('count'),
            'caption' => "absensi belum diisi\n7 hari kerja terakhir",
            'bubble' => 'hari ini',
            'bars' => $bars->all(),
            'link' => route('hrms.absensi.index'),
        ];
    }

    /**
     * Ringkasan absensi hari ini seperti di halaman Absensi Harian, ditambah hasil komparasi mesin absen.
     */
    private static function absensiHariIni(\Closure $visible): array
    {
        $today = Carbon::today();
        $karyawans = $visible()->get(['id', 'nik', 'group']);
        $total = $karyawans->count();

        $kodes = Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->pluck('kode', 'karyawan_id');

        $tanpaKeterangan = count(KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $today, $today));
        // Admin input tidak hadir, padahal mesin mencatat karyawannya absen (kemungkinan salah input).
        $cekUlang = collect(KomparasiAbsensi::selHadirDiMesin($karyawans, $today, $today))
            ->keys()->filter(fn ($id) => isset($kodes[$id]))->count();
        $hadir = max($total - $kodes->count() - $tanpaKeterangan, 0);

        $note = AbsensiMesin::whereDate('tanggal', $today->toDateString())->exists()
            ? ($cekUlang > 0 ? "{$cekUlang} kode tidak hadir perlu dicek ulang (hadir di mesin absen)." : 'Data mesin absen hari ini sudah diupload.')
            : 'Data mesin absen hari ini belum diupload.';

        return [
            'title' => 'Absensi '.$today->translatedFormat('d M'),
            'value' => ModuleDashboard::percent($hadir, $total).'% hadir',
            'unit' => 'orang',
            'segments' => [
                ['label' => 'Hadir', 'count' => $hadir, 'tone' => 'success'],
                ['label' => 'Tidak hadir', 'count' => $kodes->count(), 'tone' => 'navy'],
                ['label' => 'Tidak hadir tanpa keterangan', 'count' => $tanpaKeterangan, 'tone' => 'danger'],
            ],
            'note' => $note,
        ];
    }

    /**
     * @return list<array{date: Carbon, name: string, title: string}>
     */
    private static function birthdaysThisWeek(\Closure $visible): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $days = collect(range(0, 6))->map(fn ($offset) => $weekStart->copy()->addDays($offset));

        return $visible()->whereNotNull('tgl_lahir')->get(['nama', 'tgl_lahir'])
            ->flatMap(function (Karyawan $karyawan) use ($days) {
                $day = $days->first(fn (Carbon $day) => $day->month === $karyawan->tgl_lahir->month && $day->day === $karyawan->tgl_lahir->day);

                return $day ? [[
                    'date' => $day,
                    'name' => self::shortName($karyawan->nama),
                    'title' => "{$karyawan->nama} ulang tahun ke-".($day->year - $karyawan->tgl_lahir->year),
                ]] : [];
            })->all();
    }
}
