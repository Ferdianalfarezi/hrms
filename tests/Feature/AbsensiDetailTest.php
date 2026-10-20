<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\LaporanAbsensiShift;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class AbsensiDetailTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function karyawan(string $nik, string $nama, string $group, string $status = 'Tetap'): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => $nama, 'department_id' => $this->psd->id, 'jabatan' => 'Tandem',
            'jenis_kelamin' => 'Laki-laki', 'status' => $status, 'group' => $group]);
    }

    private function absen(Karyawan $karyawan, string $tanggal, string $kode, ?string $keterangan = null): void
    {
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal, 'kode' => $kode, 'keterangan' => $keterangan]);
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    public function test_rotasi_bertukar_setiap_minggu(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A']);

        $this->assertNull(ShiftRotasi::grupPagi(Carbon::parse('2026-10-04')));
        $this->assertSame('A', ShiftRotasi::grupPagi(Carbon::parse('2026-10-11')));
        $this->assertSame('B', ShiftRotasi::grupPagi(Carbon::parse('2026-10-12')));
        $this->assertSame('B', ShiftRotasi::grupPagi(Carbon::parse('2026-10-18')));
        $this->assertSame('A', ShiftRotasi::grupPagi(Carbon::parse('2026-10-19')));
    }

    public function test_laporan_berisi_shift_malam_kemarin_dan_shift_pagi_hari_ini(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $malam = $this->karyawan('0001', 'Andi Malam', 'A', 'PKWT 1');
        $pagi = $this->karyawan('0002', 'Budi Pagi', 'B');
        $ns = $this->karyawan('0003', 'Cici NonShift', 'NS');
        $telat = $this->karyawan('0004', 'Dodi Telat', 'NS');

        $this->absen($malam, '2026-10-14', 'CT', 'Cuti pulang kampung');
        $this->absen($malam, '2026-10-15', 'CT', 'Cuti pulang kampung');
        $this->absen($pagi, '2026-10-14', 'S', 'Sakit kemarin');
        $this->absen($pagi, '2026-10-15', 'SD', 'Sakit demam');
        $this->absen($ns, '2026-10-15', 'I', 'Keperluan keluarga');
        $this->absen($telat, '2026-10-15', 'DT<');

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.detail', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertSeeInOrder(['Rabu : 14 Oktober 2026 (Shift 2)', 'Andi Malam', 'Cuti pulang kampung', '2 Hari', 'Kontrak',
                'Kamis : 15 Oktober 2026 (Shift 1)', 'Budi Pagi', 'Sakit demam', '2 Hari', 'Cici NonShift'])
            ->assertDontSee('Dodi Telat')
            ->assertDontSee('Sakit kemarin');
    }

    public function test_tidak_absen_di_mesin_tanpa_keterangan_dihitung_alpa(): void
    {
        // 14 Okt grup B pagi: grup A shift malam 14 Okt (hari kerja 15 Okt).
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $malam = $this->karyawan('0001', 'Andi Malam', 'A');
        $pagi = $this->karyawan('0002', 'Budi Pagi', 'B');
        $izin = $this->karyawan('0003', 'Cici Izin', 'NS');
        $this->karyawan('0004', 'Dodi Hadir', 'NS');
        $this->absen($izin, '2026-10-15', 'I', 'Keperluan keluarga');
        foreach (['0001', '0002', '0003'] as $nik) {
            AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => $nik]);
        }

        $report = LaporanAbsensiShift::build(Carbon::parse('2026-10-15'));
        [$shiftMalam, $shiftPagi] = $report['sections'];

        $this->assertSame([$malam->id], $shiftMalam['rows']->pluck('karyawan.id')->all());
        $this->assertSame(1, $shiftMalam['totals']['Alpa']);
        // Cici sudah diisi Izin, jadi tidak ikut jadi Alpa; Dodi absen di mesin.
        $this->assertSame([$izin->id, $pagi->id], $shiftPagi['rows']->pluck('karyawan.id')->all());
        $this->assertSame(['I' => 1, 'Alpa' => 1], array_filter($shiftPagi['totals']));
        $this->assertSame(LaporanAbsensiShift::KETERANGAN_TANPA_KETERANGAN, $shiftPagi['rows'][1]['keterangan']);
        $this->assertSame(66.7, $shiftPagi['persen']);   // 2 dari 3 karyawan (grup B + NS)
        $this->assertSame(66.7, collect($shiftPagi['grafik']['points'])->firstWhere('selected', true)['actual']);

        // Setelah Admin Departemen mengisi kode, baris Alpa otomatis diganti kode itu.
        $this->absen($pagi, '2026-10-15', 'S', 'Sakit demam');
        $shiftPagi = LaporanAbsensiShift::build(Carbon::parse('2026-10-15'))['sections'][1];
        $this->assertSame(['S' => 1, 'I' => 1], array_filter($shiftPagi['totals']));
    }

    public function test_export_excel_berisi_shift_malam_dan_pagi(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $malam = $this->karyawan('0001', 'Andi Malam', 'A', 'PKWT 1');
        $pagi = $this->karyawan('0002', 'Budi Pagi', 'B');
        $this->absen($malam, '2026-10-14', 'CT', 'Cuti pulang kampung');
        $this->absen($pagi, '2026-10-15', 'SD', 'Sakit demam');

        $response = $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.detail.export', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertDownload('detail-absensi-2026-10-15.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertSame(['Detail', 'Grafik'], array_keys($sheets));
        $rows = collect($sheets['Detail']);
        $andi = $rows->first(fn ($row) => ($row[2] ?? null) === 'Andi Malam');
        $budi = $rows->first(fn ($row) => ($row[2] ?? null) === 'Budi Pagi');
        $this->assertSame(['0001', 'Andi Malam'], [$andi[1], $andi[2]]);
        $this->assertSame(1, $andi[5]);                     // kolom CT
        $this->assertSame('Cuti pulang kampung', $andi[10]);
        $this->assertSame('Kontrak', $andi[12]);
        $this->assertSame(1, $budi[6]);                     // kolom S
        $this->assertLessThan($rows->search($budi), $rows->search($andi)); // shift malam dulu
    }

    public function test_hanya_admin_hrms_yang_bisa_membuka_detail(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);

        $this->actingAs($deptAdmin)->get(route('hrms.absensi.detail'))->assertForbidden();
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.detail.export'))->assertForbidden();
        $this->actingAs($deptAdmin)->post(route('hrms.absensi.rotasi'), ['tanggal_mulai' => '2026-10-05', 'grup_pagi' => 'A'])->assertForbidden();

        // Admin Departemen tetap melihat Absensi sebagai tab biasa (tanpa dropdown Detail).
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.index'))->assertOk()->assertDontSee(route('hrms.absensi.detail'));
        $this->actingAs($this->hrmsAdmin())->get(route('hrms.absensi.index'))->assertOk()->assertSee(route('hrms.absensi.detail'));
    }

    public function test_admin_bisa_menyimpan_rotasi(): void
    {
        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.rotasi'), ['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B', 'tanggal' => '2026-10-15'])
            ->assertRedirect(route('hrms.absensi.detail', ['tanggal' => '2026-10-15']));

        $this->assertSame('B', ShiftRotasi::grupPagi(Carbon::parse('2026-10-15')));
    }
}
