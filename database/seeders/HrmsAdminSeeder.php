<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Seeder;

class HrmsAdminSeeder extends Seeder
{
    /**
     * Akun awal: satu Super Admin dan satu Admin Departemen HRD di HRMS. Password: "password" (segera ganti).
     * Aman dijalankan ulang: akun dicari berdasarkan NIK.
     */
    public function run(): void
    {
        $this->call(ApplicationSeeder::class);

        $hrd = Department::firstOrCreate(['name' => 'HRD']);

        User::updateOrCreate(
            ['nik' => '1001'],
            ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'is_superadmin' => true],
        );

        $adminHrd = User::updateOrCreate(
            ['nik' => '1002'],
            ['name' => 'Admin Dept HRD', 'password' => 'password', 'is_active' => true, 'is_superadmin' => false],
        );
        $adminHrd->assignRole('hrms', 'dept_admin', $hrd->id);
    }
}
