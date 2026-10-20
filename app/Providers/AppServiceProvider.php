<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Policies\KaryawanPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Boleh mengelola data master modul: super admin, atau role "admin" di modul tersebut.
        Gate::define('manage-module', fn (User $user, string $module) => $user->isModuleAdmin($module));

        Gate::policy(Karyawan::class, KaryawanPolicy::class);

        View::composer('layouts.app', function ($view) {
            $view->with('navTabs', $this->mainTabs());
            $view->with('moduleTabs', $this->moduleTabs());
        });
    }

    /**
     * Tab utama di bagian atas, disusun dari hak akses user yang sedang login.
     */
    private function mainTabs(): array
    {
        $user = auth()->user();
        $applications = $user->accessibleApplications();
        $tabs = [];

        if ($user->is_superadmin || $applications->count() > 1) {
            $tabs[] = ['label' => 'Beranda', 'url' => route('portal'), 'active' => request()->routeIs('portal')];
        }

        foreach ($applications as $application) {
            $tabs[] = [
                'label' => $application->name,
                'url' => $application->homeUrl(),
                'active' => request()->routeIs($application->code.'.*'),
            ];
        }

        if ($user->is_superadmin) {
            $tabs[] = ['label' => 'Kelola User', 'url' => route('admin.users.index'), 'active' => request()->routeIs('admin.*')];
        }

        return $tabs;
    }

    /**
     * Tab menu modul yang sedang dibuka, dibaca dari app/Modules/{Modul}/menu.php.
     */
    private function moduleTabs(): array
    {
        $module = Str::before((string) request()->route()?->getName(), '.');
        $menuFile = app_path('Modules/'.ucfirst($module).'/menu.php');

        if ($module === '' || ! is_file($menuFile)) {
            return [];
        }

        return $this->menuItems(require $menuFile);
    }

    /**
     * Item dengan kunci "can" hanya tampil kalau user lolos gate tersebut, mis. ['manage-module', 'hrms'].
     * Item dengan "children" jadi dropdown; kalau anaknya tinggal satu, tampil sebagai tab biasa.
     */
    private function menuItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => (! isset($item['can']) || Gate::allows(...$item['can']))
                && (! isset($item['cannot']) || Gate::denies(...$item['cannot'])))
            ->map(function (array $item) {
                $children = isset($item['children']) ? $this->menuItems($item['children']) : [];

                if (isset($item['children']) && $children === []) {
                    return null;
                }

                return [
                    'label' => $item['label'],
                    'url' => $children[0]['url'] ?? route($item['route']),
                    'active' => request()->routeIs($item['active']),
                    'children' => count($children) > 1 ? $children : [],
                ];
            })->filter()->values()->all();
    }
}
