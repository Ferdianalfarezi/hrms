<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\ResumeAbsensi;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
        // Minggu 12 - 18 Okt: grup B pagi, grup A malam.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function karyawan(string $nik, ?string $department, string $group): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => "Karyawan {$nik}", 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => $group,
            'department_id' => $department ? Department::firstOrCreate(['name' => $department])->id : null]);
    }

    private function absen(Karyawan $karyawan, string $tanggal, string $kode): void
    {
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal, 'kode' => $kode]);
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    public function test_resume_dipisah_per_shift_dan_plant(): void
    {
        $this->absen($this->karyawan('0001', 'PSD', 'B'), '2026-10-15', 'SD');
        $this->karyawan('0002', 'PSD', 'NS');
        $this->absen($this->karyawan('0003', 'PSD', 'A'), '2026-10-14', 'A');
        $this->absen($this->karyawan('0004', 'PWD', 'B'), '2026-10-15', 'CT');
        $this->karyawan('0005', 'PHD', 'NS');
        $this->absen($this->karyawan('0006', 'QCD', 'B'), '2026-10-15', 'I');
        $this->karyawan('0007', 'QCD', 'A');
        $this->absen($this->karyawan('0008', null, 'B'), '2026-10-15', 'WFH');
        // Tidak absen di mesin dan belum ada keterangan = Alpa.
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0005']);

        [$shift1, $shift2] = ResumeAbsensi::build(Carbon::parse('2026-10-15'))['sections'];
        $kolom = fn (array $row) => [$row['label'], $row['jumlah'], $row['cuti'], $row['sakit'], $row['ijin'], $row['alpa'],
            $row['total_absen'], $row['kerja'], $row['skd'], $row['ct']];

        // Shift 1 = pagi tanggal ini (grup B + NS). WFH tidak dihitung absen; tanpa departemen masuk ALL MP.
        $this->assertSame('Shift 1', $shift1['judul']);
        $this->assertSame([
            ['MP PSD', 2, 0, 1, 0, 0, 1, 1, 1, 0],
            ['MP PWD', 1, 1, 0, 0, 0, 1, 0, 0, 1],
            ['MP PHD', 1, 0, 0, 0, 1, 1, 0, 0, 0],
            ['ALL MP Ex PSD, PWD, PHD', 2, 0, 0, 1, 0, 1, 1, 0, 0],
        ], array_map($kolom, $shift1['rows']));
        $this->assertSame(['TOTAL', 6, 1, 1, 1, 1, 4, 2, 1, 1], $kolom($shift1['total']));
        $this->assertEqualsWithDelta(66.67, $shift1['total']['persen'], 0.01);

        // Shift 2 = malam tanggal sebelumnya (grup A).
        $this->assertSame('Shift 2', $shift2['judul']);
        $this->assertSame('2026-10-14', $shift2['tanggal']->toDateString());
        $this->assertSame([1, 0, 0, 1], array_column($shift2['rows'], 'jumlah'));
        $this->assertEquals(100, $shift2['rows'][0]['persen']);
        $this->assertNull($shift2['rows'][3]['persen']);
    }

    public function test_halaman_resume_hanya_untuk_admin_hrms(): void
    {
        $this->karyawan('0001', 'PSD', 'B');

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.resume', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertSee('Kamis, 15 Oktober 2026 (Shift 1)')
            ->assertSee('MP PSD');

        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', Department::where('name', 'PSD')->value('id'));
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.resume'))->assertForbidden();
    }
}
