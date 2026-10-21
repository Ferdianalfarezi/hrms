<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Daftar modul beserta role-nya. Tambah modul baru cukup di sini.
     */
    public function run(): void
    {
        $modules = [
            'hrms' => [
                'name' => 'HRMS',
                'description' => 'Human Resource Management System',
                'sort_order' => 1,
                // dept_admin hanya melihat & mengelola karyawan di departemen yang dipilihkan untuknya.
                'roles' => ['admin' => 'Admin HRMS', 'dept_admin' => 'Admin Departemen'],
            ],
            'sms' => [
                'name' => 'SMS',
                'description' => 'Modul SMS',
                'sort_order' => 2,
                // dept_admin mengisi Daily Patrol untuk departemen (area) yang dipilihkan untuknya.
                'roles' => ['admin' => 'Admin SMS', 'staff' => 'Staff SMS', 'dept_admin' => 'Admin Departemen'],
            ],
        ];

        foreach ($modules as $code => $module) {
            $application = Application::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'sort_order' => $module['sort_order'],
                ],
            );

            foreach ($module['roles'] as $roleCode => $roleName) {
                $application->roles()->updateOrCreate(['code' => $roleCode], ['name' => $roleName]);
            }
        }
    }
}
