<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\JapanResume;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\JapanResumeBulanan;
use Database\Seeders\ApplicationSeeder;
use Database\Seeders\JapanResumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiJapanResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    private function karyawan(string $nik, string $group): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => "Karyawan {$nik}", 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => $group,
            'department_id' => Department::firstOrCreate(['name' => 'PSD'])->id]);
    }

    private function absen(Karyawan $karyawan, string $tanggal, string $kode): void
    {
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal, 'kode' => $kode]);
    }

    public function test_data_excel_diseed_per_bulan_dengan_total_seperti_sheet(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $this->seed(JapanResumeSeeder::class);
        $this->seed(JapanResumeSeeder::class);

        // Sheet Mar - Nop'26 tanpa "Mar (2)", aman dijalankan ulang.
        $this->assertSame(184, JapanResume::count());
        $this->assertSame('2026-03-02', Carbon::parse(JapanResume::min('tanggal'))->toDateString());

        // Sama dengan baris "Nop Total" di sheet Nop'26.
        $resume = JapanResumeBulanan::build(Carbon::parse('2026-11-01'), Carbon::today());
        $this->assertCount(14, $resume['rows']);
        $this->assertSame(['total' => 7476, 'hadir' => 7254, 'cuti' => 78, 'sakit_izin' => 144, 'rate' => 3.0, 'persen_cuti' => 1.0, 'persen_sakit_izin' => 1.9], $resume['total']);

        $this->actingAs($this->hrmsAdmin())->get(route('hrms.absensi.japan-resume', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee('Japan Resume')
            ->assertSeeInOrder(['Mar Total', '8.385', '7.937', '235', '213', '5,3%'])
            ->assertSeeInOrder(['3/15', '一直', '173', '163', '6', '4', '5,8%'])
            ->assertDontSee('auto-badge" title', false);
    }

    public function test_hari_setelah_data_excel_dihitung_otomatis_dari_absensi(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');
        // Minggu 12 - 18 Okt: grup B pagi, grup A malam.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        JapanResume::create(['tanggal' => '2026-10-14', 'shift' => '両直', 'total' => 10, 'cuti' => 1, 'sakit_izin' => 1]);

        $this->absen($this->karyawan('0001', 'B'), '2026-10-15', 'SD');
        $this->karyawan('0002', 'NS');
        $this->absen($this->karyawan('0003', 'A'), '2026-10-14', 'A');
        $this->absen($this->karyawan('0004', 'B'), '2026-10-15', 'CT');
        $this->karyawan('0005', 'NS');
        $this->absen($this->karyawan('0006', 'B'), '2026-10-15', 'I');
        $this->karyawan('0007', 'A');
        $this->absen($this->karyawan('0008', 'B'), '2026-10-15', 'WFH');
        // Tidak absen di mesin dan belum ada keterangan = Alpa.
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0005']);

        $rows = JapanResumeBulanan::build(Carbon::parse('2026-10-01'), Carbon::today())['rows'];

        // 14 Okt dari data tersimpan, 15 Okt otomatis: Shift 1 (B + NS = 6 MP) + Shift 2 malam 14 Okt (A = 2 MP).
        $this->assertSame(['2026-10-14', '2026-10-15'], array_map(fn (array $row) => $row['tanggal']->toDateString(), $rows));
        $this->assertFalse($rows[0]['otomatis']);
        $this->assertTrue($rows[1]['otomatis']);
        // Cuti = CT; S+I = SD + I + Alpa mesin (0005) + Alpa malam (0003); WFH dihitung hadir.
        $this->assertSame(['両直', 8, 3, 1, 4, 62.5], [$rows[1]['shift'], $rows[1]['total'], $rows[1]['hadir'], $rows[1]['cuti'], $rows[1]['sakit_izin'], $rows[1]['rate']]);

        $this->actingAs($this->hrmsAdmin())->get(route('hrms.absensi.japan-resume', ['bulan' => '2026-10']))
            ->assertOk()
            ->assertSeeInOrder(['10/15', 'Otomatis', '両直', '8', '3', '1', '4', '62,5%']);
    }

    public function test_hari_libur_dan_cuti_bersama_tidak_tampil(): void
    {
        Carbon::setTestNow('2026-12-24 09:00:00');
        ShiftRotasi::create(['tanggal_mulai' => '2026-12-21', 'grup_pagi' => 'A']);
        $pagi = [$this->karyawan('0001', 'A'), $this->karyawan('0002', 'A'), $this->karyawan('0003', 'NS')];
        $this->karyawan('0004', 'B');

        // 22 Des hari kerja biasa, 23 Des tanpa data sama sekali (libur), 24 Des cuti bersama.
        $this->absen($pagi[0], '2026-12-22', 'S');
        foreach ($pagi as $karyawan) {
            $this->absen($karyawan, '2026-12-24', 'CB');
        }

        $rows = JapanResumeBulanan::build(Carbon::parse('2026-12-01'), Carbon::today())['rows'];

        $this->assertSame(['2026-12-22'], array_map(fn (array $row) => $row['tanggal']->toDateString(), $rows));
        $this->assertSame([4, 3, 0, 1], [$rows[0]['total'], $rows[0]['hadir'], $rows[0]['cuti'], $rows[0]['sakit_izin']]);
    }

    public function test_menu_japan_resume_hanya_untuk_admin_hrms(): void
    {
        $this->actingAs($this->hrmsAdmin())->get(route('hrms.absensi.resume'))
            ->assertSee(route('hrms.absensi.japan-resume'), false)
            ->assertSee('Japan Resume');

        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', Department::firstOrCreate(['name' => 'PSD'])->id);
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.japan-resume'))->assertForbidden();
    }

    public function test_bulan_tidak_valid_kembali_ke_bulan_ini(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');

        $this->actingAs($this->hrmsAdmin())->get(route('hrms.absensi.japan-resume', ['bulan' => 'bukan-bulan']))
            ->assertOk()
            ->assertSee('value="2026-11"', false)
            ->assertSee('Belum ada data kehadiran di bulan ini.');
    }
}
