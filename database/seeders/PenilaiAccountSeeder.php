<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Support\JenjangPenilaian;
use Illuminate\Database\Seeder;

/**
 * Akun login untuk semua penilai (jabatan Asst. Leader/Senior Staff ke atas) dari data karyawan.
 * Login pakai NIK, password awal "step123".
 *
 * Akun baru diberi role "Admin Departemen" HRMS untuk departemennya sendiri, jadi menu lain (dashboard,
 * karyawan, absensi) ikut terbatas ke departemennya. GM/Direktur tanpa departemen dipegangkan semua departemen.
 *
 * Aman dijalankan ulang: akun yang sudah ada (dicari berdasarkan NIK) tidak diubah password maupun role HRMS-nya.
 * Jalankan: php artisan db:seed --class=PenilaiAccountSeeder
 */
class PenilaiAccountSeeder extends Seeder
{
    public const PASSWORD_AWAL = 'step123';

    public function run(): void
    {
        $this->call(ApplicationSeeder::class);

        $jabatans = array_keys(array_filter(
            JenjangPenilaian::JABATAN,
            fn (string $level) => JenjangPenilaian::rank($level) >= JenjangPenilaian::RANK['asst_leader'],
        ));
        $semuaDepartemen = Department::pluck('id')->all();
        $dibuat = 0;
        $diberiAkses = 0;

        $karyawans = Karyawan::query()->whereIn('jabatan', $jabatans)->orderBy('nik')->get();

        foreach ($karyawans as $karyawan) {
            $user = User::firstOrNew(['nik' => $karyawan->nik]);

            if (! $user->exists) {
                $user->fill([
                    'name' => $karyawan->nama,
                    'password' => self::PASSWORD_AWAL,
                    'is_active' => true,
                    'is_superadmin' => false,
                ])->save();
                $dibuat++;
            }

            if ($user->is_superadmin || $user->applications()->where('code', 'hrms')->exists()) {
                continue;
            }

            $departmentIds = $karyawan->department_id
                ? [$karyawan->department_id]
                : (JenjangPenilaian::rank(JenjangPenilaian::levelOf($karyawan->jabatan)) >= JenjangPenilaian::RANK['gm'] ? $semuaDepartemen : []);

            $user->assignRole('hrms', 'dept_admin', $departmentIds);
            $diberiAkses++;
        }

        $this->command?->info("Penilai: {$karyawans->count()} karyawan, {$dibuat} akun baru (password: ".self::PASSWORD_AWAL."), {$diberiAkses} akun diberi akses HRMS.");
    }
}
