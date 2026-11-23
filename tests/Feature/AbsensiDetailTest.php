<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\LaporanShiftGrup;
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

    private function karyawan(string $nik, string $nama, string $group, string $status = 'Tetap', string $jabatan = 'Tandem'): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => $nama, 'department_id' => $this->psd->id, 'jabatan' => $jabatan,
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

    public function test_grup_malam_berganti_mulai_jumat_malam(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);

        $this->assertNull(ShiftRotasi::grupMalam(Carbon::parse('2026-10-09')));
        $this->assertSame('A', ShiftRotasi::grupMalam(Carbon::parse('2026-10-15')));
        $this->assertSame('B', ShiftRotasi::grupPagi(Carbon::parse('2026-10-16')));
        $this->assertSame('B', ShiftRotasi::grupMalam(Carbon::parse('2026-10-16')));
        $this->assertSame('B', ShiftRotasi::grupMalam(Carbon::parse('2026-10-19')));
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

    public function test_laporan_senin_mengambil_shift_malam_hari_jumat(): void
    {
        // Minggu 12 Okt grup B pagi, minggu 19 Okt grup A pagi. Pergantian mulai Jumat malam, jadi malam 16 Okt sudah grup B.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $malamJumat = $this->karyawan('0001', 'Andi Malam Jumat', 'B');
        $this->karyawan('0002', 'Budi Pagi', 'A');
        $this->absen($malamJumat, '2026-10-16', 'S', 'Sakit Jumat');

        $shiftMalam = LaporanAbsensiShift::build(Carbon::parse('2026-10-19'))['sections'][0];

        $this->assertSame('2026-10-16', $shiftMalam['tanggal']->toDateString());
        $this->assertSame(['B'], $shiftMalam['grups']);
        $this->assertSame([$malamJumat->id], $shiftMalam['rows']->pluck('karyawan.id')->all());
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

    public function test_cuti_bisa_tidak_dihitung_di_laporan_detail(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $cuti = $this->karyawan('0001', 'Andi Cuti', 'B');
        $sakit = $this->karyawan('0002', 'Budi Sakit', 'B');
        $this->karyawan('0003', 'Cici Hadir', 'B');
        $this->karyawan('0004', 'Dodi Hadir', 'B');
        $this->absen($cuti, '2026-10-15', 'CT');
        $this->absen($sakit, '2026-10-15', 'S');

        $shiftPagi = LaporanAbsensiShift::build(Carbon::parse('2026-10-15'))['sections'][1];
        $this->assertSame(['CT' => 1, 'S' => 1], array_filter($shiftPagi['totals']));
        $this->assertSame(50.0, $shiftPagi['persen']);

        $shiftPagi = $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.detail', ['tanggal' => '2026-10-15', 'cuti' => 0]))
            ->assertOk()
            ->viewData('sections')[1];
        $this->assertSame([$sakit->id], $shiftPagi['rows']->pluck('karyawan.id')->all());
        $this->assertSame(0, $shiftPagi['totals']['CT']);
        $this->assertSame(25.0, $shiftPagi['persen']);
        $this->assertSame(25.0, collect($shiftPagi['grafik']['points'])->firstWhere('selected', true)['actual']);
    }

    public function test_pdf_detail_berisi_kedua_shift_dan_grafik(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $this->absen($this->karyawan('0001', 'Andi Malam', 'A'), '2026-10-14', 'CT', 'Cuti pulang kampung');
        $this->absen($this->karyawan('0002', 'Budi Pagi', 'B'), '2026-10-15', 'S', 'Sakit demam');

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.detail.pdf', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertSeeInOrder(['LAPORAN ABSENSI HARIAN',
                'Rabu : 14 Oktober 2026 (Shift 2)', 'Andi Malam', 'Cuti pulang kampung',
                'Kamis : 15 Oktober 2026 (Shift 1)', 'Budi Pagi', 'Sakit demam',
                'Grafik ketidakhadiran Shift 2', 'Grafik ketidakhadiran Shift 1']);

        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $this->actingAs($deptAdmin)->get(route('hrms.absensi.detail.pdf'))->assertForbidden();
    }

    public function test_durasi_libur_tidak_terputus_akhir_pekan(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $sakit = $this->karyawan('0001', 'Andi Sakit', 'NS');
        // Kamis 8 Okt masuk; sakit Jumat 9 Okt s/d Kamis 15 Okt (Sabtu & Minggu tidak diinput), lalu Jumat 16 Okt.
        foreach (['2026-10-09', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16'] as $tanggal) {
            $this->absen($sakit, $tanggal, 'S');
        }

        $shiftPagi = LaporanAbsensiShift::build(Carbon::parse('2026-10-15'))['sections'][1];

        $this->assertSame(6, $shiftPagi['rows']->firstWhere('karyawan.id', $sakit->id)['durasi']);
    }

    public function test_durasi_libur_tidak_terputus_tanggal_merah_dan_tanpa_batas_hari(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $sakit = $this->karyawan('0001', 'Andi Sakit', 'NS');
        $lain = $this->karyawan('0002', 'Budi Lain', 'NS');

        // Sakit tiap hari kerja Juli s/d 15 Okt (lebih dari 60 hari), kecuali 17 Agu yang tidak ada data sama sekali (tanggal merah).
        for ($day = Carbon::parse('2026-07-01'); $day->lte(Carbon::parse('2026-10-15')); $day->addDay()) {
            if (! $day->isWeekend() && $day->toDateString() !== '2026-08-17') {
                $this->absen($sakit, $day->toDateString(), 'S');
            }
        }
        // 30 Juni hari kerja biasa (ada data karyawan lain) dan Andi tidak diinput = masuk, jadi hitungan berhenti di situ.
        $this->absen($lain, '2026-06-30', 'I');
        $this->absen($sakit, '2026-06-29', 'S');

        $shiftPagi = LaporanAbsensiShift::build(Carbon::parse('2026-10-15'))['sections'][1];

        // Hari kerja 1 Jul - 15 Okt = 77, dikurangi 17 Agu.
        $this->assertSame(76, $shiftPagi['rows']->firstWhere('karyawan.id', $sakit->id)['durasi']);
    }

    public function test_grafik_berisi_persentase_asst_leader_up(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $leader = $this->karyawan('0001', 'Andi Leader', 'B', jabatan: 'Leader');
        $asstLeader = $this->karyawan('0002', 'Budi Asst', 'B', jabatan: 'Asst. Leader');
        $operator = $this->karyawan('0003', 'Cici Operator', 'B', jabatan: 'Karyawan');
        $this->karyawan('0004', 'Dodi Operator', 'B', jabatan: 'Karyawan');
        $this->absen($leader, '2026-10-15', 'S');
        $this->absen($asstLeader, '2026-10-15', 'CT');
        $this->absen($operator, '2026-10-15', 'I');

        $shiftPagi = $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.detail', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertSee('Actual tanpa cuti (%)')
            ->assertSee('Asst. Leader up tanpa cuti (%)')
            ->viewData('sections')[1];
        $point = collect($shiftPagi['grafik']['points'])->firstWhere('selected', true);

        $this->assertSame(75.0, $point['actual']);       // 3 dari 4 karyawan, cuti dihitung
        $this->assertSame(50.0, $point['tanpa_cuti']);   // 2 dari 4 karyawan
        $this->assertSame(50.0, $point['leader']);       // 1 dari 2 Asst. Leader up; yang cuti tidak dihitung
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

    public function test_grup_laporan_bisa_sama_dan_ns_ikut_pagi_tanpa_mengubah_rotasi(): void
    {
        // Rotasi: malam Jumat 16 Okt grup B, pagi Senin 19 Okt grup A. Laporan 19 Okt diatur A dan A.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $grupA = $this->karyawan('0001', 'Andi Grup A', 'A');
        $grupB = $this->karyawan('0002', 'Budi Grup B', 'B');
        $ns = $this->karyawan('0003', 'Cici NonShift', 'NS');
        $this->absen($grupA, '2026-10-16', 'S', 'Sakit Jumat');
        $this->absen($grupB, '2026-10-16', 'I', 'Izin Jumat');
        $this->absen($ns, '2026-10-19', 'I', 'Izin Senin');

        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.detail.grup'), ['tanggal' => '2026-10-19', 'grup_malam' => 'A', 'grup_pagi' => 'A'])
            ->assertRedirect(route('hrms.absensi.detail', ['tanggal' => '2026-10-19']));

        $report = LaporanAbsensiShift::build(Carbon::parse('2026-10-19'));
        [$shiftMalam, $shiftPagi] = $report['sections'];

        $this->assertSame(['malam' => 'A', 'pagi' => 'A', 'diatur' => true], $report['grupLaporan']);
        $this->assertSame(['A'], $shiftMalam['grups']);
        $this->assertSame([$grupA->id], $shiftMalam['rows']->pluck('karyawan.id')->all());
        $this->assertSame(['A', 'NS'], $shiftPagi['grups']);
        $this->assertSame([$ns->id], $shiftPagi['rows']->pluck('karyawan.id')->all());

        // Jadwal rotasi dan laporan tanggal lain tidak berubah.
        $this->assertSame('B', ShiftRotasi::grupMalam(Carbon::parse('2026-10-16')));
        $this->assertSame(['A'], LaporanAbsensiShift::build(Carbon::parse('2026-10-16'))['sections'][0]['grups']);
    }

    public function test_grup_laporan_memindahkan_alpa_ke_shift_yang_diatur(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $grupA = $this->karyawan('0001', 'Andi Grup A', 'A');
        AbsensiMesin::create(['tanggal' => '2026-10-19', 'nik' => '0001']);
        LaporanShiftGrup::create(['tanggal' => '2026-10-19', 'grup_malam' => 'A', 'grup_pagi' => 'A']);

        [$shiftMalam, $shiftPagi] = LaporanAbsensiShift::build(Carbon::parse('2026-10-19'))['sections'];

        $this->assertSame([$grupA->id], $shiftMalam['rows']->pluck('karyawan.id')->all());
        $this->assertSame([$grupA->id], $shiftPagi['rows']->pluck('karyawan.id')->all());
    }

    public function test_grup_laporan_bisa_dikembalikan_ke_rotasi(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        LaporanShiftGrup::create(['tanggal' => '2026-10-19', 'grup_malam' => 'A', 'grup_pagi' => 'A']);

        $this->actingAs($this->hrmsAdmin())
            ->delete(route('hrms.absensi.detail.grup.destroy'), ['tanggal' => '2026-10-19'])
            ->assertRedirect(route('hrms.absensi.detail', ['tanggal' => '2026-10-19']));

        $report = LaporanAbsensiShift::build(Carbon::parse('2026-10-19'));
        $this->assertSame(['malam' => 'B', 'pagi' => 'A', 'diatur' => false], $report['grupLaporan']);
    }

    public function test_grup_laporan_hanya_untuk_admin_dan_harus_valid(): void
    {
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $data = ['tanggal' => '2026-10-19', 'grup_malam' => 'A', 'grup_pagi' => 'A'];

        $this->actingAs($deptAdmin)->post(route('hrms.absensi.detail.grup'), $data)->assertForbidden();
        $this->actingAs($deptAdmin)->delete(route('hrms.absensi.detail.grup.destroy'), ['tanggal' => '2026-10-19'])->assertForbidden();
        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.detail.grup'), [...$data, 'grup_malam' => 'NS'])
            ->assertSessionHasErrors('grup_malam');

        $this->assertSame(0, LaporanShiftGrup::count());
    }
}
