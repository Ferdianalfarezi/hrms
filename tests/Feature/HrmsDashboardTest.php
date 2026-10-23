<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Support\HrmsDashboard;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrmsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        // Kamis, 15 Okt 2026 — minggu berjalan: Sen 12 s/d Min 18 Okt.
        Carbon::setTestNow('2026-10-15 10:00:00');

        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);

        $this->karyawan('0001', 'Andi Saputra', $this->psd, 'Tetap', 'Laki-laki', masuk: '2026-10-01', lahir: '1990-10-16');
        $this->karyawan('0002', 'Bunga Lestari', $this->psd, 'PKWT 1', 'Perempuan', masuk: '2026-08-10', lahir: '1995-03-01');
        $this->karyawan('0003', 'Citra Dewi', $this->pwd, 'Pemagangan', 'Perempuan', masuk: '2025-01-05', lahir: '2001-10-12');
        $this->karyawan('0004', 'Dodi Tanpa Dept', null, 'Tetap', 'Laki-laki', masuk: '2010-01-01', lahir: '1980-01-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function karyawan(string $nik, string $nama, ?Department $department, string $status, string $jk, string $masuk, string $lahir): void
    {
        Karyawan::create(['nik' => $nik, 'nama' => $nama, 'department_id' => $department?->id, 'status' => $status,
            'jenis_kelamin' => $jk, 'tanggal_masuk' => $masuk, 'tgl_lahir' => $lahir]);
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    public function test_numbers_come_from_karyawans_table(): void
    {
        User::factory()->count(10)->create(); // akun login tidak ikut dihitung

        $data = HrmsDashboard::build($this->hrmsAdmin());

        $this->assertSame([4, 2, 2], array_column($data['bigStats'], 'value'));        // total, tetap, masuk 2026
        $this->assertSame(['Tetap' => 2, 'PKWT' => 1, 'Pemagangan' => 1], array_column($data['pills'], 'count', 'label'));
        $this->assertSame(['Laki-laki' => 2, 'Perempuan' => 2], array_column($data['pillOutlines'], 'count', 'label'));
        $this->assertSame(2, $data['growth']['total']);                                // Agt & Okt 2026
        $this->assertSame(['Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt'], array_column($data['growth']['bars'], 'label'));
        $this->assertSame('Absensi 15 Okt', $data['composition']['title']);
        $this->assertSame('Data mesin absen hari ini belum diupload.', $data['composition']['note']);
        $this->assertSame('Andi Saputra', $data['people']['items'][0]['name']);        // masuk paling baru

        $birthdays = collect($data['calendar']['days'])->flatMap(fn ($day) => array_column($day['events'], 'title'))->all();
        $this->assertSame(['Citra Dewi ulang tahun ke-25', 'Andi Saputra ulang tahun ke-36'], $birthdays);
    }

    public function test_absensi_hari_ini_memakai_data_mesin_absen(): void
    {
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $id('0004'), 'tanggal' => '2026-10-15', 'kode' => 'I']);
        // Mesin: 0001 & 0002 tidak absen. 0002 belum ada keterangan, 0004 diinput izin padahal absen di mesin.
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0001']);
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0002']);

        $composition = HrmsDashboard::build($this->hrmsAdmin())['composition'];

        $this->assertSame(['Hadir' => 1, 'Tidak hadir' => 2, 'Tidak hadir tanpa keterangan' => 1],
            array_column($composition['segments'], 'count', 'label'));
        $this->assertSame('25%', $composition['value']);
        $this->assertSame('1 kode tidak hadir perlu dicek ulang (hadir di mesin absen).', $composition['note']);
    }

    public function test_persen_hadir_dibulatkan_ke_bawah_satu_desimal(): void
    {
        $this->karyawan('0005', 'Eka', $this->psd, 'Tetap', 'Laki-laki', masuk: '2020-01-01', lahir: '1990-01-01');
        $this->karyawan('0006', 'Fajar', $this->psd, 'Tetap', 'Laki-laki', masuk: '2020-01-01', lahir: '1990-01-01');
        Absensi::create(['karyawan_id' => Karyawan::where('nik', '0001')->value('id'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => Karyawan::where('nik', '0002')->value('id'), 'tanggal' => '2026-10-15', 'kode' => 'I']);

        // 4 dari 6 = 66,67% → tampil 66,6 (bukan dibulatkan ke atas, jadi tidak pernah 100% selama ada yang tidak hadir).
        $this->assertSame('66,6%', HrmsDashboard::build($this->hrmsAdmin())['composition']['value']);
    }

    public function test_department_admin_dashboard_is_scoped(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);

        $data = HrmsDashboard::build($deptAdmin);

        $this->assertSame(2, $data['bigStats'][0]['value']);
        $this->assertSame('Departemen PSD', $data['subtitle']);
        $this->assertSame('Jenis Kelamin', $data['composition']['title']);
        $this->assertSame(['Andi Saputra', 'Bunga Lestari'], array_column($data['people']['items'], 'name'));

        $this->actingAs($deptAdmin)->get('/hrms')
            ->assertOk()
            ->assertSee('Departemen PSD')
            ->assertSee('Andi Saputra')
            ->assertDontSee('Citra Dewi')        // PWD, termasuk dari kalender ulang tahun
            ->assertDontSee('Dodi Tanpa Dept');
    }

    public function test_department_admin_sees_absensi_belum_ada_keterangan_instead_of_karyawan_masuk(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');

        // Mesin: 0001 tidak absen Jumat 9 & Kamis 15 Okt (yang tanggal 9 sudah diisi), 0002 tidak absen Senin 12 Okt.
        // 0003 (PWD) dan data Rabu 7 Okt (di luar 7 hari kerja terakhir) tidak ikut dihitung.
        AbsensiMesin::create(['tanggal' => '2026-10-09', 'nik' => '0001']);
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0001']);
        AbsensiMesin::create(['tanggal' => '2026-10-12', 'nik' => '0002']);
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0003']);
        AbsensiMesin::create(['tanggal' => '2026-10-06', 'nik' => '0002']);
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-09', 'kode' => 'S']);

        $growth = HrmsDashboard::build($deptAdmin)['growth'];

        $this->assertSame('Belum Ada Keterangan', $growth['title']);
        $this->assertSame(2, $growth['total']);
        // Rab 7 s/d Kam 15 Okt tanpa Sabtu & Minggu.
        $this->assertSame(['Rab', 'Kam', 'Jum', 'Sen', 'Sel', 'Rab', 'Kam'], array_column($growth['bars'], 'label'));
        $this->assertSame([0, 0, 0, 1, 0, 0, 1], array_column($growth['bars'], 'count'));
        $this->assertSame([false, false, false, false, false, false, true], array_column($growth['bars'], 'highlight'));

        $this->actingAs($deptAdmin)->get('/hrms')->assertOk()->assertSee('Belum Ada Keterangan')->assertDontSee('Karyawan Masuk');
    }

    public function test_dashboard_page_renders_for_admin_and_empty_state(): void
    {
        $this->actingAs($this->hrmsAdmin())->get('/hrms')
            ->assertOk()
            ->assertSee('Karyawan Masuk')
            ->assertSee('Absensi 15 Okt')
            ->assertSee('Citra Dewi')
            ->assertSee('Andi Saputra');

        Karyawan::query()->delete();
        $this->actingAs($this->hrmsAdmin())->get('/hrms')
            ->assertOk()
            ->assertSee('Belum ada data karyawan.')
            ->assertSee('Tidak ada karyawan yang berulang tahun minggu ini.');
    }
}
