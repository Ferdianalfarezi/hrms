<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Support\PresentaseAbsensi;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiPresentaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kamis, 15 Okt 2026.
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function karyawan(string $nik, string $status, string $jabatan, ?string $masuk = null): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => "Karyawan {$nik}", 'jenis_kelamin' => 'Laki-laki', 'group' => 'NS',
            'status' => $status, 'jabatan' => $jabatan, 'tanggal_masuk' => $masuk]);
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

    public function test_kelompok_karyawan_mengikuti_status_dan_jabatan(): void
    {
        $this->assertSame('ast_lead', PresentaseAbsensi::kelompok(new Karyawan(['status' => 'Tetap', 'jabatan' => 'Leader'])));
        $this->assertSame('pkwtt', PresentaseAbsensi::kelompok(new Karyawan(['status' => 'Tetap', 'jabatan' => 'Karyawan'])));
        $this->assertSame('pkwt', PresentaseAbsensi::kelompok(new Karyawan(['status' => 'PKWT 2', 'jabatan' => 'Leader'])));
        $this->assertSame('magang', PresentaseAbsensi::kelompok(new Karyawan(['status' => 'Pemagangan', 'jabatan' => 'Karyawan'])));
    }

    public function test_presentase_harian_per_kelompok(): void
    {
        $leader = $this->karyawan('0001', 'Tetap', 'Leader');
        $this->karyawan('0002', 'Tetap', 'Supervisor');
        $operator = $this->karyawan('0003', 'Tetap', 'Karyawan');
        $this->karyawan('0004', 'Tetap', 'Karyawan');
        $pkwt = $this->karyawan('0005', 'PKWT 1', 'Karyawan');
        $this->karyawan('0006', 'PKWT 3', 'Karyawan');
        $wfh = $this->karyawan('0007', 'Pemagangan', 'Karyawan');
        $this->karyawan('0008', 'PKWT 2', 'Karyawan', masuk: '2026-10-14');

        $this->absen($leader, '2026-10-13', 'CT');
        $this->absen($operator, '2026-10-13', 'S');
        $this->absen($wfh, '2026-10-13', 'WFH');
        $this->absen($leader, '2026-10-10', 'CT'); // Sabtu, tidak ditampilkan
        // Tidak absen di mesin tanpa keterangan = alpa, masuk S+I.
        AbsensiMesin::create(['tanggal' => '2026-10-13', 'nik' => '0005']);

        $data = PresentaseAbsensi::build(Carbon::parse('2026-10-01'));
        $days = collect($data['days'])->keyBy(fn (array $day) => $day['tanggal']->toDateString());

        // Hari kerja 1 - 15 Okt (hari ini), tanpa Sabtu/Minggu.
        $this->assertSame(11, $days->count());
        $this->assertFalse($days->has('2026-10-10'));
        $this->assertSame('2026-10-15', $days->keys()->last());

        $kolom = fn (array $row) => [$row['total'], $row['hadir'], $row['cuti'], $row['si']];
        $selasa = $days['2026-10-13']['rows'];
        $this->assertSame([2, 1, 1, 0], $kolom($selasa['ast_lead']));
        $this->assertSame([2, 1, 0, 1], $kolom($selasa['pkwtt']));
        $this->assertSame([2, 1, 0, 1], $kolom($selasa['pkwt']));   // 0008 belum masuk kerja
        $this->assertSame([1, 1, 0, 0], $kolom($selasa['magang'])); // WFH dihitung hadir
        $this->assertEquals(50, $selasa['ast_lead']['rate']);

        $this->assertSame(3, $days['2026-10-14']['rows']['pkwt']['total']);
        $this->assertSame(1, $data['total']['cuti']);
        $this->assertSame(2, $data['total']['si']);
    }

    public function test_halaman_presentase_hanya_untuk_admin_hrms(): void
    {
        $this->karyawan('0001', 'Tetap', 'Leader');

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.presentase', ['bulan' => '2026-10']))
            ->assertOk()
            ->assertSee('AST.LEAD UP')
            ->assertSee('PEMAGANGAN')
            ->assertSee('15/10');

        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', Department::create(['name' => 'PSD'])->id);
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.presentase'))->assertForbidden();
    }
}
