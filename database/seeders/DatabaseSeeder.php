<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ApplicationSeeder::class);

        $psd = Department::firstOrCreate(['name' => 'PSD']);

        // Akun contoh untuk development. Semua password: "password".
        // access: modul => role, atau modul => [role, department_id] untuk Admin Departemen.
        $users = [
            ['nik' => '1001', 'name' => 'Admin Semua Sistem', 'superadmin' => true, 'access' => []],
            ['nik' => '1002', 'name' => 'Admin HRMS', 'access' => ['hrms' => 'admin']],
            ['nik' => '1003', 'name' => 'Staff SMS', 'access' => ['sms' => 'staff']],
            ['nik' => '1004', 'name' => 'Tanpa Akses', 'access' => []],
            ['nik' => '1005', 'name' => 'Admin Dept PSD', 'access' => ['hrms' => ['dept_admin', $psd->id]]],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['nik' => $data['nik']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'is_active' => true,
                    'is_superadmin' => $data['superadmin'] ?? false,
                ],
            );

            $user->applications()->detach();
            foreach ($data['access'] as $application => $role) {
                [$roleCode, $departmentId] = is_array($role) ? $role : [$role, null];
                $user->assignRole($application, $roleCode, $departmentId);
            }
        }
    }
}
