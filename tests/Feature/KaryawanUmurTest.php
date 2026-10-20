<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KaryawanUmurTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_umur_follows_today_and_increments_on_birthday(): void
    {
        $karyawan = Karyawan::create(['nik' => '0013', 'nama' => 'Sri', 'jenis_kelamin' => 'Laki-laki',
            'status' => 'Tetap', 'tgl_lahir' => '1975-07-26']);

        Carbon::setTestNow('2026-01-01');
        $this->assertSame(50, $karyawan->fresh()->umur);   // ganti tahun, belum ulang tahun

        Carbon::setTestNow('2026-07-25');
        $this->assertSame(50, $karyawan->fresh()->umur);

        Carbon::setTestNow('2026-07-26');
        $this->assertSame(51, $karyawan->fresh()->umur);   // tepat di hari ulang tahun

        Carbon::setTestNow('2030-12-31');
        $this->assertSame(55, $karyawan->fresh()->umur);
    }

    public function test_umur_is_empty_without_birth_date_and_shown_in_list(): void
    {
        Carbon::setTestNow('2026-10-15');
        $this->seed(ApplicationSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        Karyawan::create(['nik' => '0001', 'nama' => 'Tanpa Lahir', 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        Karyawan::create(['nik' => '0002', 'nama' => 'Ada Lahir', 'jenis_kelamin' => 'Perempuan', 'status' => 'Tetap', 'tgl_lahir' => '2000-10-16']);

        $this->assertNull(Karyawan::where('nik', '0001')->first()->umur);

        $this->actingAs($admin)->get('/hrms/karyawans')
            ->assertOk()
            ->assertSee('Umur')
            ->assertSee('25 th');   // ulang tahun ke-26 baru besok
    }
}
