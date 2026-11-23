<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
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

        $this->assertSame(['-', 4, 2, 2], array_column($data['bigStats'], 'value'));   // rotasi belum diatur, total, tetap, masuk 2026
        $this->assertSame(['Tetap' => 2, 'PKWT' => 1, 'Pemagangan' => 1], array_column($data['pills'], 'count', 'label'));
        $this->assertSame(['Laki-laki' => 2, 'Perempuan' => 2], array_column($data['pillOutlines'], 'count', 'label'));
        $this->assertNull($data['growth']);                                            // kartu Karyawan Masuk diganti tren kehadiran
        $this->assertSame('Absensi 15 Okt', $data['composition']['title']);
        $this->assertSame('Data mesin absen hari ini belum diupload.', $data['composition']['note']);
    }

    public function test_shift_aktif_mengikuti_jadwal_rotasi(): void
    {
        // Acuan: grup A pagi mulai Senin 5 Okt, bertukar tiap minggu. Minggu 12 - 18 Okt: B pagi, A malam.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);

        $stats = HrmsDashboard::build($this->hrmsAdmin())['bigStats'];

        $this->assertSame(['Shift Pagi' => 'B', 'Shift Malam' => 'A'], array_column(array_slice($stats, 0, 2), 'value', 'label'));
        $this->actingAs($this->hrmsAdmin())->get('/hrms')->assertOk()->assertSee('Shift Malam');
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

        $this->assertSame(['Hadir' => 1, 'Tidak hadir' => 3, 'Sakit' => 1, 'Izin' => 1, 'Cuti' => 0, 'WFH' => 0, 'Alpa' => 1],
            array_column($composition['segments'], 'count', 'label'));
        $this->assertSame(['1 orang · 25%', '3 orang · 75%', '1 orang · 25%', '1 orang · 25%', '0 orang · 0%'],
            array_slice(array_column($composition['segments'], 'text'), 0, 5));
        $this->assertSame(false, $composition['segments'][1]['stack']);                   // tidak hadir hanya ringkasan, tidak ikut bar
        $this->assertSame('25%', $composition['value']);
        $this->assertSame('1 kode tidak hadir perlu dicek ulang (hadir di mesin absen).', $composition['note']);
    }

    public function test_persen_hadir_dibulatkan_ke_bawah_satu_desimal(): void
    {
        $this->karyawan('0005', 'Eka', $this->psd, 'Tetap', 'Laki-laki', masuk: '2020-01-01', lahir: '1990-01-01');
        $this->karyawan('0006', 'Fajar', $this->psd, 'Tetap', 'Laki-laki', masuk: '2020-01-01', lahir: '1990-01-01');
        Absensi::create(['karyawan_id' => Karyawan::where('nik', '0001')->value('id'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => Karyawan::where('nik', '0002')->value('id'), 'tanggal' => '2026-10-15', 'kode' => 'I']);

        $composition = HrmsDashboard::build($this->hrmsAdmin())['composition'];

        // 4 dari 6 = 66,67% → tampil 66,6 (bukan dibulatkan ke atas, jadi tidak pernah 100% selama ada yang tidak hadir).
        $this->assertSame('66,6%', $composition['value']);
        // Tidak hadir = sisa dari persen hadir (66,6 + 33,4 = 100); per kategori dibulatkan biasa (1/6 = 16,7).
        $this->assertSame(['4 orang · 66,6%', '2 orang · 33,4%', '1 orang · 16,7%'], array_slice(array_column($composition['segments'], 'text'), 0, 3));
    }

    public function test_department_admin_dashboard_is_scoped(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);

        $data = HrmsDashboard::build($deptAdmin);

        $this->assertSame(2, collect($data['bigStats'])->firstWhere('label', 'Karyawan')['value']);
        $this->assertSame('Departemen PSD', $data['subtitle']);
        $this->assertSame('Kehadiran Okt', $data['composition']['title']);
        $this->assertSame('Departemen PSD', $data['composition']['subtitle']);

        $this->actingAs($deptAdmin)->get('/hrms')
            ->assertOk()
            ->assertSee('Departemen PSD')
            ->assertSee('Andi Saputra')
            ->assertDontSee('Citra Dewi')        // PWD
            ->assertDontSee('Dodi Tanpa Dept');
    }

    public function test_department_admin_sees_kehadiran_per_karyawan_bulan_ini(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        // 1 - 15 Okt 2026 = 11 hari kerja (Senin - Jumat).
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-14', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-13', 'kode' => 'DT<']); // terlambat tetap hadir
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-12', 'kode' => 'I']);
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-10', 'kode' => 'I']); // Sabtu, tidak dihitung
        AbsensiMesin::create(['tanggal' => '2026-10-13', 'nik' => '0002']);                         // tidak absen, belum ada keterangan

        $composition = HrmsDashboard::build($deptAdmin)['composition'];

        $this->assertSame('86,3%', $composition['value']);                                           // 19 dari 22 hari
        $this->assertSame(['Bunga Lestari' => '81,8%', 'Andi Saputra' => '90,9%'],
            array_column($composition['segments'], 'text', 'label'));
        $this->assertSame('danger', $composition['segments'][0]['tone']);
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
            ->assertSee('Tren Kehadiran (7 Hari)')
            ->assertSee('Absensi 15 Okt')
            ->assertSee('Man-hour Hari Ini')
            ->assertDontSee('Produktivitas Mingguan')                     // digabung ke tren kehadiran
            ->assertDontSee('Karyawan Masuk')
            ->assertDontSee('Watchlist Ketidakhadiran')
            ->assertDontSee('Status Data Hari Ini')
            ->assertDontSee('Kehadiran per Plant')
            ->assertDontSee('Man Power per Kelompok')
            ->assertDontSee('Kehadiran per Shift')                        // rinciannya pindah ke kartu absensi
            ->assertDontSee('Karyawan Terbaru')
            ->assertDontSee('Data Diri');

        Karyawan::query()->delete();
        $this->actingAs($this->hrmsAdmin())->get('/hrms')->assertOk()->assertSee('Tren Kehadiran (7 Hari)');
    }

    public function test_produktivitas_hari_ini_dari_satu_potret_absensi(): void
    {
        // Minggu 12 - 18 Okt: B pagi, A malam.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);
        foreach (['0001' => 'B', '0002' => 'NS', '0003' => 'A'] as $nik => $grup) {
            Karyawan::where('nik', $nik)->update(['group' => $grup]);
        }
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-15', 'kode' => 'DT>', 'menit_terlambat' => 45]);
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-15', 'kode' => 'DT<']);              // otomatis 30 menit
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0004']);                                      // belum ada keterangan

        $hariIni = HrmsDashboard::build($this->hrmsAdmin())['hariIni'];

        // 4 orang x 8 jam = 32; hilang 2 x 8 jam tidak hadir + 75 menit terlambat.
        $this->assertSame(32, $hariIni['manHour']['tersedia']);
        $this->assertEqualsWithDelta(14.75, $hariIni['manHour']['efektif'], 0.001);
        $this->assertEqualsWithDelta(46.0, $hariIni['manHour']['persen'], 0.001);

        $this->assertSame(2, $hariIni['keterlambatan']['orang']);
        $this->assertSame(75, $hariIni['keterlambatan']['menit']);
        $this->assertSame(['DT<' => 1, 'DT>' => 1, 'PC<' => 0, 'PC>' => 0], array_column($hariIni['keterlambatan']['rows'], 'count', 'kode'));
        $this->assertSame(['PSD' => 1, 'PWD' => 1], $hariIni['keterlambatan']['departemen']);

        // Karyawan tanpa grup (0004) tidak dihitung di shift mana pun.
        $this->assertSame(['Shift 1 · Pagi · 15 Okt' => 2, 'Shift 2 · Malam · 14 Okt' => 1], array_column($hariIni['perShift'], 'total', 'label'));
        $this->assertSame([1, 1], array_column($hariIni['perShift'], 'hadir'));
        $this->assertSame('Grup B + NS · 07.00 - 16.00', $hariIni['perShift'][0]['sub']);
    }

    public function test_kartu_absensi_hari_ini_dirinci_per_shift(): void
    {
        // Minggu 12 - 18 Okt: B pagi, A malam.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);
        foreach (['0001' => 'B', '0002' => 'NS', '0003' => 'A', '0004' => 'B'] as $nik => $grup) {
            Karyawan::where('nik', $nik)->update(['group' => $grup]);
        }
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        // Shift 2 = malam kemarin (14 Okt), jadi memakai absensi 14 Okt; absensi grup A hari ini tidak dihitung.
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-14', 'kode' => 'CT']);
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '0004']);                                      // tidak absen, belum ada keterangan

        $shifts = HrmsDashboard::build($this->hrmsAdmin())['composition']['shifts'];

        $this->assertSame(['Shift 1 · Pagi · 15 Okt', 'Shift 2 · Malam · 14 Okt'], array_column($shifts, 'label'));
        $this->assertSame(['33,3%', '0%'], array_column($shifts, 'value'));
        $this->assertSame(['Hadir' => 1, 'Tidak hadir' => 2, 'Sakit' => 1, 'Izin' => 0, 'Cuti' => 0, 'WFH' => 0, 'Alpa' => 1],
            array_column($shifts[0]['segments'], 'count', 'label'));
        $this->assertSame(['1 orang · 33,3%', '2 orang · 66,7%'], array_column(array_slice($shifts[0]['segments'], 0, 2), 'text'));
        $this->assertSame(['Hadir' => 0, 'Tidak hadir' => 1, 'Sakit' => 0, 'Izin' => 0, 'Cuti' => 1, 'WFH' => 0, 'Alpa' => 0],
            array_column($shifts[1]['segments'], 'count', 'label'));

        // Shift 1 di atas Shift 2; persen tidak hadir per shift di baris bawah.
        $this->actingAs($this->hrmsAdmin())->get('/hrms')->assertOk()
            ->assertSeeInOrder(['Man-hour Hari Ini', 'Absensi 15 Okt', 'Shift 1 · Pagi · 15 Okt', 'Shift 2 · Malam · 14 Okt', 'Keterlambatan Hari Ini', 'Tidak Hadir per Shift']);
    }

    public function test_hari_pergantian_rotasi_grup_yang_sama_masuk_shift_2_kemarin_dan_shift_1_hari_ini(): void
    {
        // Senin 19 Okt: A pagi. Shift malam yang diambil Jumat 16 Okt, juga grup A (minggu 12 - 18: B pagi, A malam).
        Carbon::setTestNow('2026-10-19 10:00:00');
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);
        foreach (['0001' => 'A', '0002' => 'NS', '0003' => 'B', '0004' => 'A'] as $nik => $grup) {
            Karyawan::where('nik', $nik)->update(['group' => $grup]);
        }
        Absensi::create(['karyawan_id' => Karyawan::where('nik', '0001')->value('id'), 'tanggal' => '2026-10-19', 'kode' => 'S']);

        $shifts = HrmsDashboard::build($this->hrmsAdmin())['hariIni']['perShift'];

        // Grup B (pagi kemarin, malam nanti) tidak di shift mana pun dan bukan "Tanpa grup".
        $this->assertSame(['Shift 1 · Pagi · 19 Okt', 'Shift 2 · Malam · 16 Okt'], array_column($shifts, 'label'));
        $this->assertSame([3, 2], array_column($shifts, 'total'));
        $this->assertSame([2, 2], array_column($shifts, 'hadir'));       // sakit hari ini tidak mengurangi malam kemarin
    }

    public function test_tren_kehadiran_tujuh_hari_kerja_sampai_hari_ini_per_jenis(): void
    {
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-15', 'kode' => 'CT']);
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-12', 'kode' => 'A']);
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-10', 'kode' => 'S']); // Sabtu, tidak dihitung
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-06', 'kode' => 'S']); // di luar 7 hari kerja
        AbsensiMesin::create(['tanggal' => '2026-10-14', 'nik' => '0004']);                         // tidak absen, belum ada keterangan

        $tren = HrmsDashboard::build($this->hrmsAdmin())['tren'];
        $series = array_column($tren['series'], 'counts', 'label');

        // Rab 7 s/d Kam 15 Okt tanpa Sabtu & Minggu, hari ini ikut.
        $this->assertSame(['Rab', 'Kam', 'Jum', 'Sen', 'Sel', 'Rab', 'Kam'], array_column($tren['days'], 'label'));
        $this->assertSame([false, false, false, false, false, false, true], array_column($tren['days'], 'today'));
        $this->assertSame([4, 4, 4, 3, 4, 3, 2], $series['Hadir']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $series['Sakit']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $series['Cuti']);
        // Tidak absen di mesin dan belum ada keterangan (Rab 14) ikut dihitung Alpa.
        $this->assertSame([0, 0, 0, 1, 0, 1, 0], $series['Alpa']);
        $this->assertArrayNotHasKey('Tanpa keterangan', $series);
        $this->assertSame([24, 1, 0, 1, 0, 2], array_column($tren['series'], 'total'));
        $this->assertSame('85,7', $tren['series'][0]['persen']);                                  // 24 dari 28 hari-orang
        $this->assertSame('85,7%', $tren['value']);
        $this->assertSame(['100', '100', '100', '75', '100', '75', '50'], array_column($tren['days'], 'persen'));
        $this->assertSame([0, 0, 0, 1, 0, 1, 2], array_column($tren['days'], 'tidakHadir'));
        $this->assertSame([false, false, false, true, false, true, true], array_column($tren['days'], 'diAtasTarget'));
        $this->assertSame([0, 1, 2, 3, 4], $tren['ticks']);
        // Garis Hadir mulai di kiri atas sumbu (4 dari maks 4) dan berakhir di kanan pada nilai 2.
        $this->assertStringStartsWith('M0 0 ', $tren['series'][0]['path']);
        $this->assertStringEndsWith(' 700 120', $tren['series'][0]['path']);
    }

    public function test_persen_tidak_hadir_hari_ini_per_shift_seperti_grafik_laporan_detail(): void
    {
        // Minggu 12 - 18 Okt: B pagi (Shift 1, bersama NS), A malam (Shift 2).
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);
        $set = fn (string $nik, string $group, ?string $jabatan = null) => Karyawan::where('nik', $nik)->update(['group' => $group, 'jabatan' => $jabatan]);
        $set('0001', 'B', 'Leader');
        $set('0002', 'B');
        $set('0003', 'A');
        $set('0004', 'NS');
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-15', 'kode' => 'CT']);
        Absensi::create(['karyawan_id' => $id('0003'), 'tanggal' => '2026-10-14', 'kode' => 'I']);   // Shift 2 = malam kemarin

        $shifts = HrmsDashboard::build($this->hrmsAdmin())['persenShift'];

        $this->assertSame(['Shift 1 · Pagi · 15 Okt', 'Shift 2 · Malam · 14 Okt'], array_column($shifts, 'label'));
        // Shift 1: 2 dari 3 tidak hadir, tanpa cuti 1 dari 3, Asst. Leader up 1 dari 1.
        $this->assertSame([3, 66.7, 33.3, 100.0], [$shifts[0]['target'], $shifts[0]['actual'], $shifts[0]['tanpa_cuti'], $shifts[0]['leader']]);
        // Shift 2: tidak ada Asst. Leader up di grup A.
        $this->assertSame([100.0, 100.0, null], [$shifts[1]['actual'], $shifts[1]['tanpa_cuti'], $shifts[1]['leader']]);

        $this->actingAs($this->hrmsAdmin())->get('/hrms')->assertOk()
            ->assertSee('Tidak Hadir per Shift')
            ->assertSeeInOrder(['Actual', '66,7%', '100%', 'Tanpa cuti', '33,3%', '100%', 'Asst. Leader up', '100%', '-'])
            ->assertDontSee('Ulang Tahun Minggu Ini');

        // Data seluruh karyawan, jadi tidak ditampilkan untuk Admin Departemen.
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $this->assertNull(HrmsDashboard::build($deptAdmin)['persenShift']);
        $this->actingAs($deptAdmin)->get('/hrms')->assertOk()->assertDontSee('Tidak Hadir per Shift');
    }

    public function test_man_hour_dikurangi_menit_pulang_cepat(): void
    {
        $id = fn (string $nik) => Karyawan::where('nik', $nik)->value('id');
        Absensi::create(['karyawan_id' => $id('0001'), 'tanggal' => '2026-10-15', 'kode' => 'PC>', 'menit_terlambat' => 90]);
        Absensi::create(['karyawan_id' => $id('0002'), 'tanggal' => '2026-10-15', 'kode' => 'PC<']);                   // otomatis 30 menit

        $manHour = HrmsDashboard::build($this->hrmsAdmin())['hariIni']['manHour'];

        // 4 x 8 jam = 32, hilang 2 jam pulang cepat.
        $this->assertEqualsWithDelta(30.0, $manHour['efektif'], 0.001);
        $this->assertSame(['Tidak hadir' => 0, 'Terlambat' => 0, 'Pulang cepat' => 2], array_map('intval', array_column($manHour['hilang'], 'jam', 'label')));
    }

    public function test_department_admin_tidak_melihat_tren_kehadiran(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);

        $this->assertNull(HrmsDashboard::build($deptAdmin)['tren']);
        $this->actingAs($deptAdmin)->get('/hrms')->assertOk()->assertDontSee('Tren Kehadiran (7 Hari)');
    }
}
