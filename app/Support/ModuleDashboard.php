<?php

namespace App\Support;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard umum sebuah modul (dipakai SMS) berbasis data akun login.
 * Bentuk data mengikuti view dashboard/module.blade.php; modul lain bisa membuat builder sendiri
 * dengan bentuk yang sama (lihat App\Modules\Hrms\Support\HrmsDashboard).
 */
class ModuleDashboard
{
    public static function build(string $code, User $viewer): array
    {
        $application = Application::where('code', $code)->firstOrFail();

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $usersWithoutAccess = User::doesntHave('applications')->count();

        $memberIds = DB::table('user_app_roles')->where('application_id', $application->id)->pluck('user_id');
        $members = $memberIds->count();

        $roleCounts = DB::table('user_app_roles')
            ->where('application_id', $application->id)
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles = $application->roles()->orderBy('id')->get()->map(fn ($role) => [
            'code' => $role->code,
            'count' => (int) ($roleCounts[$role->id] ?? 0),
        ]);

        // User baru 7 hari terakhir, dihitung di PHP supaya tidak bergantung pada fungsi tanggal DB.
        $since = Carbon::today()->subDays(6);
        $createdDates = User::where('created_at', '>=', $since)->pluck('created_at');
        $bars = collect(range(0, 6))->map(function ($offset) use ($since, $createdDates) {
            $day = $since->copy()->addDays($offset);

            return [
                'label' => $day->translatedFormat('D'),
                'count' => $createdDates->filter(fn ($date) => $date->isSameDay($day))->count(),
                'highlight' => $day->isToday(),
            ];
        });

        $weekStart = Carbon::now()->startOfWeek();
        $weekUsers = User::whereBetween('created_at', [$weekStart, $weekStart->copy()->endOfWeek()])->get();

        $canSeeTeam = $viewer->isModuleAdmin($code);
        $myApplications = $viewer->accessibleApplications();

        return [
            'application' => $application,
            'myApplications' => $myApplications,
            'bigStats' => [
                ['label' => 'Akun', 'value' => $totalUsers, 'icon' => 'users'],
                ['label' => "Akses {$application->name}", 'value' => $members, 'icon' => 'lock'],
                ['label' => 'Admin', 'value' => $roles->firstWhere('code', 'admin')['count'] ?? 0, 'icon' => 'shield'],
            ],
            'pills' => [],
            'pillOutlines' => [],
            'growth' => [
                'title' => 'Pertumbuhan',
                'total' => $bars->sum('count'),
                'caption' => "user baru\n7 hari terakhir",
                'bubble' => 'baru',
                'bars' => $bars->all(),
                'link' => $viewer->is_superadmin ? route('admin.users.index') : null,
            ],
            'composition' => [
                'title' => 'Status Akun',
                'value' => "{$activeUsers}/{$totalUsers}",
                'unit' => 'user',
                'segments' => [
                    ['label' => 'Aktif', 'count' => $activeUsers, 'tone' => 'primary'],
                    ['label' => 'Nonaktif', 'count' => $totalUsers - $activeUsers, 'tone' => 'navy'],
                ],
                'note' => $usersWithoutAccess > 0 ? "{$usersWithoutAccess} user belum punya akses modul." : 'Semua user sudah punya akses modul.',
            ],
            'people' => $canSeeTeam
                ? [
                    'title' => 'User Terbaru',
                    'counter' => min(5, $totalUsers).'/'.$totalUsers,
                    'items' => User::latest()->take(5)->get()->map(fn (User $user) => [
                        'name' => $user->name,
                        'sub' => "NIK {$user->nik} · ".$user->created_at->translatedFormat('d M Y'),
                        'photo' => $user->photo_url,
                        'check' => $user->is_active,
                    ])->all(),
                    'empty' => 'Belum ada user.',
                ]
                : [
                    'title' => 'Akses Saya',
                    'counter' => $myApplications->count(),
                    'items' => $myApplications->map(fn (Application $app) => [
                        'name' => $app->name,
                        'sub' => $viewer->roleLabel($app->code),
                        'url' => $app->homeUrl(),
                        'check' => true,
                    ])->all(),
                    'empty' => 'Belum ada akses modul.',
                ],
            'calendar' => self::week(
                $weekUsers->map(fn (User $user) => ['date' => $user->created_at, 'name' => $user->first_name, 'title' => "Akun baru: {$user->name}", 'photo' => $user->photo_url]),
                caption: 'Akun karyawan yang dibuat minggu ini.',
                empty: 'Belum ada aktivitas minggu ini.',
            ),
        ];
    }

    /**
     * Kalender minggu berjalan. $events berisi item dengan kunci "date" (Carbon) yang ditempatkan per hari.
     *
     * @param  iterable<array{date: Carbon, name: string, title: string, photo?: ?string}>  $events
     */
    public static function week(iterable $events, string $caption, string $empty): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $events = collect($events);

        return [
            'label' => $weekStart->translatedFormat('F Y'),
            'caption' => $caption,
            'empty' => $empty,
            'days' => collect(range(0, 6))->map(function ($offset) use ($weekStart, $events) {
                $day = $weekStart->copy()->addDays($offset);

                return [
                    'day' => $day->translatedFormat('D'),
                    'date' => $day->day,
                    'today' => $day->isToday(),
                    'events' => $events->filter(fn ($event) => $event['date']->isSameDay($day))->values()->all(),
                ];
            })->all(),
        ];
    }

    public static function percent(int $value, int $total): int
    {
        return $total > 0 ? (int) round($value / $total * 100) : 0;
    }
}
