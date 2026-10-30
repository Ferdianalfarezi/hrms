<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AbsensiKomparasiTest extends TestCase
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

    private function karyawan(string $nik, string $nama, ?string $group = 'NS'): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => $nama, 'department_id' => $this->psd->id,
            'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => $group]);
    }

    private function absen(Karyawan $karyawan, string $tanggal, string $kode): void
    {
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal, 'kode' => $kode]);
    }

    private function mesin(string $tanggal, array $niks): void
    {
        foreach ($niks as $nik) {
            AbsensiMesin::create(['tanggal' => $tanggal, 'nik' => $nik]);
        }
    }

    private function hrmsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        return $user;
    }

    /**
     * File .xls seperti export mesin: stream BIFF2 tanpa OLE, semua sel berupa LABEL.
     *
     * @param  list<list<string>>  $rows
     */
    private function xls(array $rows): UploadedFile
    {
        $record = fn (int $type, string $body) => pack('vv', $type, strlen($body)).$body;
        $bytes = $record(0x0809, pack('vv', 0, 0x10));

        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $bytes .= $record(0x0004, pack('vv', $r, $c)."\x00\x00\x00".chr(strlen($value)).$value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($path, $bytes.$record(0x000A, ''));

        return new UploadedFile($path, 'data komparasi absen hrms.xls', 'application/vnd.ms-excel', null, true);
    }

    /**
     * File .xlsx seperti Attendance Report: judul perusahaan di atas, header di baris ke-5, isinya karyawan yang hadir.
     *
     * @param  list<list<string>>  $rows  [Attendance Date, Employee Code, Employee Name, Time In, Time Out]
     */
    private function attendanceReport(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['PT. Sari Takagi Elok Produk']));
        $writer->addRows(array_map(fn () => Row::fromValues(['']), range(1, 3)));
        $writer->addRow(Row::fromValues(['Attendance Date', 'Employee Code', 'Employee Name', 'Organization Code',
            'Organization Name', 'Position Name', 'Time In', 'Time Out']));

        foreach ($rows as [$tanggal, $nik, $nama, $masuk, $keluar]) {
            $writer->addRow(Row::fromValues([$tanggal, $nik, $nama, '301.02', 'PRODUCTION', 'STAFF/OPERATOR', $masuk, $keluar]));
        }

        $writer->close();

        return new UploadedFile($path, 'Attendance_30 Okto26 baru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_upload_menyimpan_karyawan_yang_tidak_ada_di_file_kehadiran(): void
    {
        // Shift dibaca dari Time In, bukan dari grup/rotasi: Bayu grup A tapi kerja malam tetap terbaca.
        $this->karyawan('0001', 'Agus Pagi Kemarin', 'A');
        $this->karyawan('0002', 'Bayu Malam', 'A');
        $this->karyawan('0003', 'Cici Pagi', 'B');
        $this->karyawan('0004', 'Dedi NS', 'NS');
        $this->karyawan('0005', 'Eka Malam Lupa Tap Masuk', null);
        $this->karyawan('0006', 'Fajar Absen', 'B');
        $this->karyawan('0008', 'Hana Malam Lusa', 'B');
        Karyawan::create(['nik' => '0007', 'nama' => 'Gita Baru', 'department_id' => $this->psd->id,
            'jenis_kelamin' => 'Perempuan', 'status' => 'Tetap', 'group' => 'A', 'tanggal_masuk' => '2026-10-20']);
        AbsensiMesin::create(['tanggal' => '2026-10-15', 'nik' => '7777']);

        // Laporan hari kerja 15 Okt = baris 14 Okt yang masuk malam + baris 15 Okt yang masuk pagi / tanpa Time In.
        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.komparasi.store'), ['file' => $this->attendanceReport([
                ['14/10/2026', '0001', 'Agus Pagi Kemarin', '06:20', '16:10'],
                ['14/10/2026', '0002', 'Bayu Malam', '18:46', '06:21'],
                ['14/10/2026', '0008', 'Hana Malam Lusa', '-', '06:16'],
                ['15/10/2026', '0003', 'Cici Pagi', '06:20', '-'],
                ['15/10/2026', '0004', 'Dedi NS', '06:55', '-'],
                ['15/10/2026', '0005', 'Eka Malam Lupa Tap Masuk', '-', '05:01'],
                ['15/10/2026', '3269', 'Tidak Terdaftar', '06:30', '-'],
            ])])
            ->assertRedirect(route('hrms.absensi.komparasi', ['tanggal' => '2026-10-15']))
            ->assertSessionHas('status', 'Data mesin absen hari kerja 15/10/2026 berhasil diupload: 4 karyawan hadir, 3 karyawan tidak absen, 1 Employee Code dilewati karena tidak ada di data karyawan.');

        // Shift pagi kemarin dan tap pulang kemarin pagi (shift malam lusa) tidak dihitung; data lama tanggal itu diganti;
        // karyawan yang belum masuk kerja tidak dihitung.
        $rows = AbsensiMesin::orderBy('nik')->get();
        $this->assertSame(['0001', '0006', '0008'], $rows->pluck('nik')->all());
        $this->assertSame(['Agus Pagi Kemarin', 'Fajar Absen', 'Hana Malam Lusa'], $rows->pluck('nama')->all());
        $this->assertTrue($rows->every(fn ($row) => $row->tanggal->toDateString() === '2026-10-15'));
    }

    public function test_upload_tanpa_tanggal_kemarin_memberi_peringatan(): void
    {
        $this->karyawan('0001', 'Agus Pagi', 'A');
        $this->karyawan('0002', 'Bayu Malam', 'B');

        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.komparasi.store'), ['file' => $this->attendanceReport([
                ['15/10/2026', '0001', 'Agus Pagi', '06:20', '-'],
            ])])
            ->assertSessionHas('status', fn (string $status) => str_ends_with($status, 'File tidak berisi tanggal 14/10/2026, jadi shift malam dianggap tidak absen.'));

        $this->assertSame(['0002'], AbsensiMesin::pluck('nik')->all());
    }

    public function test_file_tanpa_header_mesin_ditolak(): void
    {
        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.komparasi.store'), ['file' => $this->xls([['NIK', 'Nama'], ['0098', 'Sukirno']])])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, AbsensiMesin::count());
    }

    public function test_daftar_belum_ada_keterangan(): void
    {
        // 14 Okt: grup B pagi, jadi grup A shift malam 14 Okt dan masuk hari kerja 15 Okt.
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);

        $this->karyawan('0001', 'Andi Belum');
        $izin = $this->karyawan('0002', 'Budi Izin');
        $malam = $this->karyawan('0003', 'Cici Malam', 'A');
        $malamSalahTanggal = $this->karyawan('0004', 'Dodi Malam', 'A');

        $this->absen($izin, '2026-10-15', 'I');
        $this->absen($malam, '2026-10-14', 'S');            // shift malam: keterangan di tanggal 14
        $this->absen($malamSalahTanggal, '2026-10-15', 'I'); // shift malam berikutnya, bukan hari kerja ini

        // 9999 tidak ada di data karyawan: diabaikan.
        $this->mesin('2026-10-15', ['0001', '0002', '0003', '0004', '9999']);

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.komparasi', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('belum', fn ($rows) => $rows->pluck('karyawan.nik')->all() === ['0001', '0004'])
            ->assertSee('Andi Belum')
            ->assertDontSee('Budi Izin');
    }

    public function test_matriks_absensi_menandai_sel_tanpa_keterangan(): void
    {
        ShiftRotasi::create(['tanggal_mulai' => '2026-10-12', 'grup_pagi' => 'B']);
        $belum = $this->karyawan('0001', 'Andi Belum');
        $malam = $this->karyawan('0002', 'Cici Malam', 'A');
        $izin = $this->karyawan('0003', 'Budi Izin');
        $this->absen($izin, '2026-10-15', 'I');
        $this->mesin('2026-10-15', ['0001', '0002', '0003']);

        // Grup A shift malam 14 Okt: sel merahnya di tanggal 14.
        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.index', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('tanpaKeterangan', fn ($sel) => $sel === [
                $belum->id => ['2026-10-15' => true],
                $malam->id => ['2026-10-14' => true],
            ])
            ->assertSee('Tidak masuk · belum ada keterangan');
    }

    public function test_kode_tidak_hadir_padahal_hadir_di_mesin_ditandai(): void
    {
        $salah = $this->karyawan('0001', 'Andi Salah Input');
        $benar = $this->karyawan('0002', 'Budi Izin');
        $telat = $this->karyawan('0003', 'Cici Telat');
        $this->absen($salah, '2026-10-15', 'I');
        $this->absen($benar, '2026-10-15', 'I');
        $this->absen($telat, '2026-10-15', 'DT<');
        $this->absen($salah, '2026-10-16', 'I'); // tanggal 16 belum diupload: tidak ditandai
        $this->mesin('2026-10-15', ['0002']);

        $this->actingAs($this->hrmsAdmin())
            ->get(route('hrms.absensi.index', ['tanggal' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('hadirMesin', fn ($sel) => isset($sel[$salah->id]['2026-10-15'], $sel[$telat->id]['2026-10-15'])
                && ! isset($sel[$benar->id]) && ! isset($sel[$salah->id]['2026-10-16']))
            ->assertSee('Hadir di mesin absen · admin input Izin, cek ulang');
    }

    public function test_upload_memberi_notifikasi_ke_admin_departemen_sampai_ditutup(): void
    {
        $pwd = Department::create(['name' => 'PWD']);
        $this->karyawan('0001', 'Andi Belum');
        $izin = $this->karyawan('0002', 'Budi Izin');
        Karyawan::create(['nik' => '0003', 'nama' => 'Citra PWD', 'department_id' => $pwd->id, 'jenis_kelamin' => 'Perempuan', 'status' => 'Tetap']);
        $this->absen($izin, '2026-10-15', 'I');

        $adminPsd = User::factory()->create();
        $adminPsd->assignRole('hrms', 'dept_admin', $this->psd->id);
        $adminPwd = User::factory()->create();
        $adminPwd->assignRole('hrms', 'dept_admin', $pwd->id);
        // Citra hadir; Andi dan Budi tidak ada di file (tidak absen), Budi sudah diberi keterangan Izin.
        $file = fn () => $this->attendanceReport([
            ['15/10/2026', '0003', 'Citra PWD', '06:40', '-'],
        ]);

        $this->actingAs($this->hrmsAdmin())
            ->post(route('hrms.absensi.komparasi.store'), ['file' => $file()])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Notifikasi dikirim ke 1 Admin Departemen.'));

        // Hanya Admin Departemen PSD, dan hanya karyawan yang belum ada keterangan.
        $this->assertSame(0, $adminPwd->notifications()->count());
        $notification = $adminPsd->unreadNotifications()->sole();
        $this->assertSame('1 karyawan belum ada keterangan', $notification->data['title']);
        $this->assertSame([['nik' => '0001', 'nama' => 'Andi Belum', 'tanggal' => '2026-10-15']], $notification->data['karyawans']);

        // Upload ulang tanggal yang sama mengganti notifikasi yang belum ditutup, tidak menumpuk.
        $this->actingAs($this->hrmsAdmin())->post(route('hrms.absensi.komparasi.store'), ['file' => $file()]);
        $notification = $adminPsd->unreadNotifications()->sole();

        // Toast tetap tampil di setiap halaman sampai ditutup.
        $this->actingAs($adminPsd)->get('/hrms')->assertOk()->assertSee('Absensi belum lengkap')->assertSee('Andi Belum');
        $this->actingAs($adminPsd)->get(route('hrms.absensi.index'))->assertOk()->assertSee('Absensi belum lengkap');

        // Endpoint polling mengembalikan toast yang sama (notifikasi baru muncul tanpa refresh halaman).
        $this->actingAs($adminPsd)->get(route('notifications.toasts'))->assertOk()
            ->assertSee('data-toast="'.$notification->id.'"', false)->assertSee('Andi Belum');
        $this->actingAs($adminPwd)->get(route('notifications.toasts'))->assertOk()->assertDontSee('Andi Belum');

        // User lain tidak bisa menutup notifikasi milik orang lain.
        $this->actingAs($adminPwd)->post(route('notifications.read', $notification->id))->assertNotFound();

        $this->actingAs($adminPsd)->post(route('notifications.read', $notification->id))->assertNoContent();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($adminPsd->refresh())->get('/hrms')->assertOk()->assertDontSee('Absensi belum lengkap');
    }

    public function test_admin_departemen_tidak_bisa_membuka_komparasi(): void
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'dept_admin', $this->psd->id);

        $this->actingAs($user)->get(route('hrms.absensi.komparasi'))->assertForbidden();
        $this->actingAs($user)->post(route('hrms.absensi.komparasi.store'))->assertForbidden();
    }
}
