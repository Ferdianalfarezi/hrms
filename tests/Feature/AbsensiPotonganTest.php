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

class AbsensiPotonganTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Karyawan $andi;

    private Karyawan $budi;

    private User $hrmsAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $pwd = Department::create(['name' => 'PWD']);
        $this->andi = Karyawan::create(['nik' => '0001', 'nama' => 'Andi PSD', 'department_id' => $this->psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        $this->budi = Karyawan::create(['nik' => '0002', 'nama' => 'Budi PWD', 'department_id' => $pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        $this->hrmsAdmin = User::factory()->create();
        $this->hrmsAdmin->assignRole('hrms', 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_late_code_requires_minutes_that_match_the_code(): void
    {
        $save = fn (string $kode, ?int $menit) => $this->actingAs($this->hrmsAdmin)->postJson('/hrms/absensi', [
            'karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => $kode, 'menit_terlambat' => $menit,
        ]);

        $save('DT<', null)->assertJsonValidationErrors('menit_terlambat');
        $save('DT<', 31)->assertJsonValidationErrors('menit_terlambat');
        $save('DT>', 20)->assertJsonValidationErrors('menit_terlambat');
        $save('DT>', 45)->assertOk()->assertJson(['kode' => 'DT>', 'menit_terlambat' => 45]);

        // Kode selain DT tidak menyimpan menit.
        $save('I', 45)->assertOk()->assertJson(['menit_terlambat' => null]);
    }

    public function test_late_duration_is_summed_and_rounded_up_per_30_minutes(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-13', 'kode' => 'DT<', 'menit_terlambat' => 10]);  // 1x
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => 'DT>', 'menit_terlambat' => 45]);  // 2x
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-09-14', 'kode' => 'DT>', 'menit_terlambat' => 90]);  // bulan lain
        Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-14', 'kode' => 'DT>', 'menit_terlambat' => 61]);  // 3x
        Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-13', 'kode' => 'S']);                              // bukan DT

        $rows = $this->actingAs($this->hrmsAdmin)->get(route('hrms.absensi.potongan', ['bulan' => '2026-10']))
            ->assertOk()
            ->viewData('rows')
            ->mapWithKeys(fn (array $row) => [$row['karyawan']->nik => [$row['jumlah'], $row['menit'], $row['blok']]]);

        $this->assertSame(['0001' => [2, 55, 3], '0002' => [1, 61, 3]], $rows->all());
    }

    public function test_department_admin_only_sees_own_department(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => 'DT>', 'menit_terlambat' => 45]);
        Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-14', 'kode' => 'DT>', 'menit_terlambat' => 45]);
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);

        $this->actingAs($deptAdmin)->get(route('hrms.absensi.potongan'))
            ->assertOk()
            ->assertSee('Andi PSD')
            ->assertSee('45 mnt')
            ->assertDontSee('Rp ')
            ->assertDontSee('Budi PWD');
    }

    public function test_karyawan_table_no_longer_shows_deduction_amount(): void
    {
        $this->actingAs($this->hrmsAdmin)->get(route('hrms.karyawans.index'))
            ->assertOk()
            ->assertSee('Andi PSD')
            ->assertDontSee('Potongan Absen / 30')
            ->assertDontSee('potongan_absen');
    }
}
