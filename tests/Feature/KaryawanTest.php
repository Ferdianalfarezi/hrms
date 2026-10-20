<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Imports\KaryawanImport;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class KaryawanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
    }

    private function hrmsUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', $role);

        return $user;
    }

    /**
     * Buat file xlsx dengan susunan seperti "data upload hrms.xlsx": header 2 baris
     * dan kolom TANGGAL/BULAN/TAHUN di antara TEMPAT LAHIR dan TGL LAHIR.
     *
     * @param  list<list<mixed>>  $dataRows
     */
    private function makeExcel(array $dataRows, ?array $header = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kry').'.xlsx';
        $date = (new Style)->withFormat('d-mmm-yyyy');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? ['NIK', 'NAMA', "JENIS \nKELAMIN", 'TEMPAT LAHIR', '', '', '', 'TGL LAHIR', 'JABATAN', '', 'STATUS']));
        $writer->addRow(Row::fromValues(['', '', '', '', 'TANGGAL', 'BULAN', 'TAHUN', '', '', 'TANGGAL MASUK', '', 'dept']));
        foreach ($dataRows as $row) {
            $writer->addRow(Row::fromValuesWithStyles($row, [7 => $date, 9 => $date]));
        }
        $writer->close();

        return $path;
    }

    private function row(mixed $nik, string $nama, string $status = 'Tetap', string $jabatan = 'Karyawan', mixed $tglLahir = null, string $dept = ''): array
    {
        return [$nik, $nama, 'Laki-laki', 'Bekasi', 26, 'Jul', 1975, $tglLahir ?? new DateTimeImmutable('1975-07-26'), $jabatan, new DateTimeImmutable('1997-03-07'), $status, $dept];
    }

    public function test_import_reads_two_row_header_and_normalizes_values(): void
    {
        $path = $this->makeExcel([
            $this->row('0013', 'Sri Widodo S'),
            $this->row(774, 'Muhammad Zaenudin'),                  // NIK angka => 0774 + peringatan
            $this->row('0020', 'Muntolib ', 'TETAP', 'DIREKTUR'),   // huruf besar & spasi di ujung
            $this->row('0021', 'Ani', 'pkwt 1', 'Direktur'),
            $this->row('', 'YOSHIDA MASAMI'),                        // NIK kosong => gagal
            $this->row('0022', 'Salah Status', 'Kontrak'),           // status tidak dikenal => gagal
            $this->row('0013', 'Dobel'),                             // NIK dobel => gagal
            ['', '', '', '', '', '', '', '', '', '', ''],            // baris kosong diabaikan
            $this->row('0023', 'Tanggal Teks', tglLahir: '31/01/2000'),
        ]);

        $result = (new KaryawanImport)->import($path);

        $this->assertSame(5, $result['created']);
        $this->assertCount(3, $result['failed']);
        $this->assertSame([7, 8, 9], array_column($result['failed'], 'row'));
        $this->assertStringContainsString('NIK kosong', $result['failed'][0]['errors'][0]);
        $this->assertStringContainsString('baris 3', $result['failed'][2]['errors'][0]);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('0774', $result['warnings'][0]['message']);

        $sri = Karyawan::where('nik', '0013')->firstOrFail();
        $this->assertSame('Sri Widodo S', $sri->nama);
        $this->assertSame('1975-07-26', $sri->tgl_lahir->format('Y-m-d'));
        $this->assertSame('1997-03-07', $sri->tanggal_masuk->format('Y-m-d'));
        $this->assertSame('Laki-laki', $sri->jenis_kelamin);

        $this->assertTrue(Karyawan::where('nik', '0774')->exists());
        $this->assertSame('Muntolib', Karyawan::where('nik', '0020')->value('nama'));
        $this->assertSame('Tetap', Karyawan::where('nik', '0020')->value('status'));
        $this->assertSame('PKWT 1', Karyawan::where('nik', '0021')->value('status'));
        // Ejaan jabatan pertama yang muncul dipakai untuk variasi huruf besar/kecil.
        $this->assertSame(['DIREKTUR'], Karyawan::whereIn('nik', ['0020', '0021'])->distinct()->pluck('jabatan')->all());
        $this->assertSame('2000-01-31', Karyawan::where('nik', '0023')->first()->tgl_lahir->format('Y-m-d'));
    }

    public function test_dept_is_linked_to_departments_table(): void
    {
        $psd = Department::create(['name' => 'PSD']);
        $hrd = Department::create(['name' => 'HRD']);

        $result = (new KaryawanImport)->import($this->makeExcel([
            $this->row('0013', 'Sri', dept: 'PSD'),
            $this->row('0015', 'Silfina', dept: 'hrd'),   // huruf kecil tetap cocok
            $this->row('0016', 'Isnur', dept: 'JAP'),     // belum terdaftar
            $this->row('0017', 'Kasmoyo', dept: 'JAP'),
            $this->row('0018', 'Tanpa Dept'),             // sel kosong
        ]));

        $this->assertSame(5, $result['created']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(['JAP' => 2], $result['unknown_departments']);
        $this->assertCount(2, $result['warnings']);
        $this->assertStringContainsString('"JAP" belum terdaftar', $result['warnings'][0]['message']);

        $this->assertSame($psd->id, Karyawan::where('nik', '0013')->value('department_id'));
        $this->assertSame($hrd->id, Karyawan::where('nik', '0015')->value('department_id'));
        $this->assertNull(Karyawan::where('nik', '0016')->value('department_id'));
        $this->assertNull(Karyawan::where('nik', '0018')->value('department_id'));
        $this->assertSame('PSD', Karyawan::where('nik', '0013')->first()->department->name);

        // Setelah departemen JAP ditambahkan, import ulang file yang sama mengisi departemennya.
        $jap = Department::create(['name' => 'JAP']);
        $again = (new KaryawanImport)->import($this->makeExcel([
            $this->row('0016', 'Isnur', dept: 'JAP'),
            $this->row('0017', 'Kasmoyo', dept: 'JAP'),
        ]));
        $this->assertSame(2, $again['updated']);
        $this->assertSame([], $again['unknown_departments']);
        $this->assertSame(2, $jap->karyawans()->count());
    }

    public function test_file_without_dept_column_keeps_existing_department(): void
    {
        $psd = Department::create(['name' => 'PSD']);
        Karyawan::create(['nik' => '0013', 'nama' => 'Sri', 'department_id' => $psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);

        // File format lama: header 1 baris tanpa kolom DEPT.
        $path = tempnam(sys_get_temp_dir(), 'kry').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['NIK', 'NAMA', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TGL LAHIR', 'JABATAN', 'TANGGAL MASUK', 'STATUS']));
        $writer->addRow(Row::fromValues(['0013', 'Sri Widodo', 'Laki-laki', 'Sragen', '26/07/1975', 'Leader', '07/03/1997', 'Tetap']));
        $writer->close();

        $result = (new KaryawanImport)->import($path);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('Sri Widodo', Karyawan::where('nik', '0013')->value('nama'));
        $this->assertSame($psd->id, Karyawan::where('nik', '0013')->value('department_id'));
    }

    public function test_department_in_use_cannot_be_deleted(): void
    {
        $admin = $this->hrmsUser('admin');
        $psd = Department::create(['name' => 'PSD']);
        Karyawan::create(['nik' => '0013', 'nama' => 'Sri', 'department_id' => $psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);

        $this->actingAs($admin)->delete("/hrms/departments/{$psd->id}")
            ->assertRedirect(route('hrms.departments.index'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'masih dipakai 1 karyawan'));
        $this->assertModelExists($psd);

        $this->actingAs($admin)->get('/hrms/departments')->assertSee('1 karyawan');
    }

    public function test_list_can_filter_by_department(): void
    {
        $psd = Department::create(['name' => 'PSD']);
        Karyawan::create(['nik' => '0001', 'nama' => 'Andi', 'department_id' => $psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        Karyawan::create(['nik' => '0002', 'nama' => 'Bunga', 'jenis_kelamin' => 'Perempuan', 'status' => 'Tetap']);

        $user = $this->hrmsUser('admin');
        $this->actingAs($user)->get("/hrms/karyawans?department={$psd->id}")->assertSee('Andi')->assertDontSee('Bunga');
        $this->actingAs($user)->get('/hrms/karyawans?department=none')->assertSee('Bunga')->assertDontSee('Andi');
    }

    public function test_reimport_updates_existing_nik(): void
    {
        Karyawan::create(['nik' => '0013', 'nama' => 'Nama Lama', 'jenis_kelamin' => 'Laki-laki', 'status' => 'PKWT 1']);
        Karyawan::create(['nik' => '0014', 'nama' => 'Tetap Sama', 'jenis_kelamin' => 'Laki-laki', 'tempat_lahir' => 'Bekasi',
            'tgl_lahir' => '1975-07-26', 'jabatan' => 'Karyawan', 'tanggal_masuk' => '1997-03-07', 'status' => 'Tetap']);

        $result = (new KaryawanImport)->import($this->makeExcel([
            $this->row('0013', 'Nama Baru'),
            $this->row('0014', 'Tetap Sama'),
            $this->row('0015', 'Orang Baru'),
        ]));

        $this->assertSame(['created' => 1, 'updated' => 1, 'unchanged' => 1], array_intersect_key($result, array_flip(['created', 'updated', 'unchanged'])));
        $this->assertSame('Nama Baru', Karyawan::where('nik', '0013')->value('nama'));
        $this->assertSame('Tetap', Karyawan::where('nik', '0013')->value('status'));
        $this->assertSame(3, Karyawan::count());
    }

    public function test_missing_column_is_reported(): void
    {
        $path = $this->makeExcel([], ['NIK', 'NAMA', 'JENIS KELAMIN']);

        $this->expectExceptionMessage('Kolom "TEMPAT LAHIR" tidak ditemukan');
        (new KaryawanImport)->import($path);
    }

    public function test_admin_can_import_via_upload(): void
    {
        $path = $this->makeExcel([$this->row('0013', 'Sri Widodo S'), $this->row('', 'Tanpa NIK')]);
        $file = new UploadedFile($path, 'data upload hrms.xlsx', null, null, true);

        $this->actingAs($this->hrmsUser('admin'))
            ->post('/hrms/karyawans/import', ['file' => $file])
            ->assertRedirect(route('hrms.karyawans.index'))
            ->assertSessionHas('import_result', fn ($result) => $result['created'] === 1 && count($result['failed']) === 1);

        $this->assertTrue(Karyawan::where('nik', '0013')->exists());
    }

    public function test_upload_rejects_non_xlsx_and_staff(): void
    {
        $admin = $this->hrmsUser('admin');
        $this->actingAs($admin)
            ->post('/hrms/karyawans/import', ['file' => UploadedFile::fake()->create('data.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $path = $this->makeExcel([$this->row('0013', 'Sri')]);
        $deptAdmin = User::factory()->create();
        $deptAdmin->assignRole('hrms', 'dept_admin', Department::create(['name' => 'PSD'])->id);
        $this->actingAs($deptAdmin)
            ->post('/hrms/karyawans/import', ['file' => new UploadedFile($path, 'a.xlsx', null, null, true)])
            ->assertForbidden();
        $this->actingAs($deptAdmin)->get('/hrms/karyawans/template')->assertForbidden();
        $this->assertSame(0, Karyawan::count());
    }

    public function test_corrupt_file_shows_friendly_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        file_put_contents($path, 'bukan excel');

        $this->actingAs($this->hrmsUser('admin'))
            ->post('/hrms/karyawans/import', ['file' => new UploadedFile($path, 'rusak.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
            ->assertSessionHasErrors('file');
    }

    public function test_crud_via_modal_endpoints(): void
    {
        $admin = $this->hrmsUser('admin');
        $hrd = Department::create(['name' => 'HRD']);
        $data = ['nik' => '0099', 'nama' => 'Budi', 'department_id' => $hrd->id, 'jenis_kelamin' => 'Laki-laki', 'tempat_lahir' => 'Bekasi',
            'tgl_lahir' => '1990-05-01', 'jabatan' => 'Leader', 'tanggal_masuk' => '2020-01-02', 'status' => 'PKWT 2'];

        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', $data)->assertRedirect('/hrms/karyawans');
        $karyawan = Karyawan::where('nik', '0099')->firstOrFail();

        $this->actingAs($admin)->from('/hrms/karyawans')->put("/hrms/karyawans/{$karyawan->id}", [...$data, 'status' => 'Tetap'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Tetap', $karyawan->refresh()->status);
        $this->assertSame($hrd->id, $karyawan->department_id);

        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', [...$data, 'status' => 'Kontrak'])
            ->assertSessionHasErrors(['nik', 'status']);
        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', [...$data, 'nik' => '0100', 'department_id' => 999])
            ->assertSessionHasErrors('department_id');

        $this->actingAs($admin)->from('/hrms/karyawans')->delete("/hrms/karyawans/{$karyawan->id}");
        $this->assertModelMissing($karyawan);
    }

    public function test_list_filters_by_status_jabatan_and_search(): void
    {
        Karyawan::create(['nik' => '0001', 'nama' => 'Andi', 'jenis_kelamin' => 'Laki-laki', 'jabatan' => 'Leader', 'status' => 'Tetap']);
        Karyawan::create(['nik' => '0002', 'nama' => 'Bunga', 'jenis_kelamin' => 'Perempuan', 'jabatan' => 'Karyawan', 'status' => 'PKWT 1']);

        $admin = $this->hrmsUser('admin');
        $this->actingAs($admin)->get('/hrms/karyawans?status=PKWT+1')
            ->assertOk()->assertSee('Bunga')->assertDontSee('Andi')->assertSee('Import Excel');
        $this->actingAs($admin)->get('/hrms/karyawans?jabatan=Leader')->assertSee('Andi')->assertDontSee('Bunga');
        $this->actingAs($admin)->get('/hrms/karyawans?q=0002')->assertSee('Bunga')->assertDontSee('Andi');
    }

    public function test_template_can_be_downloaded_and_reimported(): void
    {
        $response = $this->actingAs($this->hrmsUser('admin'))->get('/hrms/karyawans/template');
        $response->assertOk()->assertDownload('template-import-karyawan.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $result = (new KaryawanImport)->import($path);
        $this->assertSame(1, $result['created']);
        $this->assertSame('2000-01-31', Karyawan::where('nik', '0001')->first()->tgl_lahir->format('Y-m-d'));
    }
}
