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

    public function test_dt_kecil_otomatis_30_menit_dan_dt_besar_wajib_isi_menit(): void
    {
        $save = fn (string $kode, ?int $menit) => $this->actingAs($this->hrmsAdmin)->postJson('/hrms/absensi', [
            'karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => $kode, 'menit_terlambat' => $menit,
        ]);

        // DT< boleh isi menit sebenarnya (maks. 30); kosong = 30 menit.
        $save('DT<', null)->assertOk()->assertJson(['kode' => 'DT<', 'menit_terlambat' => 30]);
        $save('DT<', 5)->assertOk()->assertJson(['menit_terlambat' => 5]);
        $save('DT<', 31)->assertJsonValidationErrors(['menit_terlambat' => 'DT< maksimal 30 menit. Lebih dari 30 menit pilih DT>.']);

        // DT> wajib isi menit sebenarnya, tanpa batas minimal 30.
        $save('DT>', null)->assertJsonValidationErrors('menit_terlambat');
        $save('DT>', 20)->assertOk()->assertJson(['kode' => 'DT>', 'menit_terlambat' => 20]);
        $save('DT>', 45)->assertOk()->assertJson(['kode' => 'DT>', 'menit_terlambat' => 45]);

        // Jalur lain (mis. import) yang menyimpan DT< tanpa menit juga otomatis 30 menit.
        $import = Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-14', 'kode' => 'DT<']);
        $this->assertSame(30, $import->fresh()->menit_terlambat);

        // Kode selain DT tidak menyimpan menit.
        $save('I', 45)->assertOk()->assertJson(['menit_terlambat' => null]);
    }

    public function test_pc_kecil_otomatis_30_menit_dan_pc_besar_wajib_isi_menit_tanpa_ikut_potongan_terlambat(): void
    {
        $save = fn (string $kode, ?int $menit) => $this->actingAs($this->hrmsAdmin)->postJson('/hrms/absensi', [
            'karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => $kode, 'menit_terlambat' => $menit,
        ]);

        $save('PC<', null)->assertOk()->assertJson(['kode' => 'PC<', 'menit_terlambat' => 30]);
        $save('PC<', 15)->assertOk()->assertJson(['menit_terlambat' => 15]);
        $save('PC<', 45)->assertJsonValidationErrors(['menit_terlambat' => 'PC< maksimal 30 menit. Lebih dari 30 menit pilih PC>.']);

        $save('PC>', null)->assertJsonValidationErrors(['menit_terlambat' => 'Isi berapa menit karyawan pulang cepat.']);
        $save('PC>', 75)->assertOk()->assertJson(['kode' => 'PC>', 'menit_terlambat' => 75]);

        $import = Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-14', 'kode' => 'PC<']);
        $this->assertSame(30, $import->fresh()->menit_terlambat);

        // Pulang cepat tidak masuk rekap potongan datang terlambat.
        $rows = $this->actingAs($this->hrmsAdmin)->get(route('hrms.absensi.potongan', ['bulan' => '2026-10']))->viewData('rows');
        $this->assertCount(0, $rows);
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
            ->assertSee('1 jam')   // telat 45 menit dihitung 2x 30 menit
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
