<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    private Karyawan $andi;

    private Karyawan $budi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
        $this->andi = Karyawan::create(['nik' => '0001', 'nama' => 'Andi PSD', 'department_id' => $this->psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'A']);
        $this->budi = Karyawan::create(['nik' => '0002', 'nama' => 'Budi PWD', 'department_id' => $this->pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'NS']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deptAdmin(Department $department): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'dept_admin', $department->id);

        return $user;
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    private function save(User $user, Karyawan $karyawan, string $tanggal, ?string $kode, ?string $keterangan = null)
    {
        return $this->actingAs($user)->postJson('/hrms/absensi', [
            'karyawan_id' => $karyawan->id, 'tanggal' => $tanggal, 'kode' => $kode, 'keterangan' => $keterangan,
        ]);
    }

    public function test_page_defaults_to_today_and_shows_month_matrix(): void
    {
        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi')
            ->assertOk()
            ->assertSee('Laporan Absensi Harian')
            ->assertSee('Kamis, 15 Oktober 2026')
            ->assertSee('id="selected-day"', false)
            ->assertSee('data-month="2026-10"', false)
            ->assertSee('data-day="31"', false)   // 31 hari di Oktober
            ->assertSee('Andi PSD')
            ->assertSee('Budi PWD');
    }

    public function test_rows_are_ordered_by_nik(): void
    {
        Karyawan::create(['nik' => '99394', 'nama' => 'Nik Lima Digit', 'department_id' => $this->psd->id, 'jenis_kelamin' => 'Perempuan', 'status' => 'Pemagangan']);
        Karyawan::create(['nik' => '1105', 'nama' => 'Nik Seribu', 'department_id' => $this->pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        Karyawan::create(['nik' => '0013', 'nama' => 'Nik Kecil', 'department_id' => $this->pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);

        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi')
            ->assertSeeInOrder(['Andi PSD', 'Budi PWD', 'Nik Kecil', 'Nik Seribu', 'Nik Lima Digit']); // 0001, 0002, 0013, 1105, 99394
    }

    public function test_department_admin_marks_own_department_and_hrms_admin_sees_it(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);

        $this->save($deptAdmin, $this->andi, '2026-10-15', 'PC>', 'PC 13:00')
            ->assertOk()
            ->assertJson(['kode' => 'PC>', 'keterangan' => 'PC 13:00']);

        $this->assertDatabaseHas('absensis', ['karyawan_id' => $this->andi->id, 'kode' => 'PC>', 'created_by' => $deptAdmin->id]);

        // Admin HRMS otomatis melihat tanda yang diinput admin departemen.
        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi')
            ->assertSee('data-kode="PC&gt;"', false)
            ->assertSee('PC 13:00');
    }

    public function test_department_admin_cannot_touch_other_department(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);

        $this->save($deptAdmin, $this->budi, '2026-10-15', 'I')->assertForbidden();
        $this->assertDatabaseCount('absensis', 0);

        $this->actingAs($deptAdmin)->get('/hrms/absensi')
            ->assertOk()
            ->assertSee('Andi PSD')
            ->assertDontSee('Budi PWD')
            ->assertDontSee('Semua departemen');

        // Filter departemen di URL tidak bisa memperluas cakupan.
        $this->actingAs($deptAdmin)->get("/hrms/absensi?department={$this->pwd->id}")->assertDontSee('Budi PWD');
    }

    public function test_saving_again_updates_and_empty_code_means_present(): void
    {
        $admin = $this->hrmsAdmin();

        $this->save($admin, $this->budi, '2026-10-14', 'S')->assertOk();
        $this->save($admin, $this->budi, '2026-10-14', 'SD', 'surat dokter')->assertOk();
        $this->assertSame(1, Absensi::count());
        $this->assertSame('SD', Absensi::first()->kode);

        $this->save($admin, $this->budi, '2026-10-14', null)->assertOk()->assertJson(['kode' => null]);
        $this->assertSame(0, Absensi::count());
    }

    public function test_validation_rejects_unknown_code_and_bad_date(): void
    {
        $admin = $this->hrmsAdmin();

        $this->save($admin, $this->andi, '2026-10-15', 'XX')->assertUnprocessable()->assertJsonValidationErrors('kode');
        $this->save($admin, $this->andi, '15/10/2026', 'I')->assertUnprocessable()->assertJsonValidationErrors('tanggal');
    }

    public function test_date_filter_shows_previous_month(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-09-03', 'kode' => 'CT']);
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-01', 'kode' => 'A']);

        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi?tanggal=2026-09-03')
            ->assertOk()
            ->assertSee('Kamis, 03 September 2026')
            ->assertSee('data-kode="CT"', false)
            ->assertDontSee('data-kode="A"', false)          // tanda Oktober tidak ikut
            ->assertSee('data-month="2026-09"', false)
            ->assertDontSee('data-day="31"', false); // September 30 hari

        // Tanggal tidak valid kembali ke hari ini.
        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi?tanggal=bukan-tanggal')->assertSee('Kamis, 15 Oktober 2026');
    }

    public function test_group_filter_and_access_control(): void
    {
        $this->actingAs($this->hrmsAdmin())->get('/hrms/absensi?group=NS')
            ->assertSee('Budi PWD')->assertDontSee('Andi PSD');

        $smsOnly = User::factory()->create();
        $smsOnly->assignRole('sms', 'admin');
        $this->actingAs($smsOnly)->get('/hrms/absensi')->assertForbidden();
        $this->save($smsOnly, $this->andi, '2026-10-15', 'I')->assertForbidden();
    }

    public function test_absensi_menu_is_visible_for_department_admin(): void
    {
        $this->actingAs($this->deptAdmin($this->psd))->get('/hrms/karyawans')
            ->assertSee(route('hrms.absensi.index'));
    }
}
