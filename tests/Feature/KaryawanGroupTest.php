<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Imports\KaryawanImport;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class KaryawanGroupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Susunan seperti "data upload hrms.xlsx" versi terbaru: GROUP di kolom M (header baris 1, di-merge M1:M2).
     *
     * @param  list<array{0: string, 1: string, 2: mixed}>  $rows  [nik, nama, group]
     */
    private function makeExcel(array $rows, bool $withGroup = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'grp').'.xlsx';
        $date = (new Style)->withFormat('d-mmm-yyyy');
        $writer = new Writer;
        $writer->openToFile($path);

        $header = ['NIK', 'NAMA', "JENIS \nKELAMIN", 'TEMPAT LAHIR', '', '', '', 'TGL LAHIR', 'JABATAN', '', 'STATUS', ''];
        $writer->addRow(Row::fromValues($withGroup ? [...$header, 'GROUP'] : $header));
        $writer->addRow(Row::fromValues(['', '', '', '', 'TANGGAL', 'BULAN', 'TAHUN', '', '', 'TANGGAL MASUK', '', 'dept']));

        foreach ($rows as [$nik, $nama, $group]) {
            $values = [$nik, $nama, 'Laki-laki', 'Bekasi', 1, 'Jan', 1990, new DateTimeImmutable('1990-01-01'), 'Karyawan', new DateTimeImmutable('2020-01-01'), 'Tetap', ''];
            $writer->addRow(Row::fromValuesWithStyles($withGroup ? [...$values, $group] : $values, [7 => $date, 9 => $date]));
        }
        $writer->close();

        return $path;
    }

    public function test_group_column_is_imported_and_normalized(): void
    {
        $result = (new KaryawanImport)->import($this->makeExcel([
            ['0001', 'Andi', 'A'],
            ['0002', 'Budi', 'b'],      // huruf kecil => B
            ['0003', 'Citra', ' ns '],  // spasi & huruf kecil => NS
            ['0004', 'Dodi', ''],       // kosong => null
        ]));

        $this->assertSame(4, $result['created']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(
            ['0001' => 'A', '0002' => 'B', '0003' => 'NS', '0004' => null],
            Karyawan::orderBy('nik')->pluck('group', 'nik')->all(),
        );
    }

    public function test_reimport_updates_group_and_file_without_group_keeps_it(): void
    {
        (new KaryawanImport)->import($this->makeExcel([['0001', 'Andi', 'A']]));

        $result = (new KaryawanImport)->import($this->makeExcel([['0001', 'Andi', 'NS']]));
        $this->assertSame(1, $result['updated']);
        $this->assertSame('NS', Karyawan::where('nik', '0001')->value('group'));

        // File tanpa kolom GROUP tidak mengosongkan group yang sudah ada.
        $result = (new KaryawanImport)->import($this->makeExcel([['0001', 'Andi', null]], withGroup: false));
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame('NS', Karyawan::where('nik', '0001')->value('group'));
    }

    public function test_group_filter_and_form(): void
    {
        $this->seed(ApplicationSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        Karyawan::create(['nik' => '0001', 'nama' => 'Andi Shift A', 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'A']);
        Karyawan::create(['nik' => '0002', 'nama' => 'Budi Non Shift', 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'NS']);
        Karyawan::create(['nik' => '0003', 'nama' => 'Citra Kosong', 'jenis_kelamin' => 'Perempuan', 'status' => 'Tetap']);

        $this->actingAs($admin)->get('/hrms/karyawans?group=A')
            ->assertOk()->assertSee('Andi Shift A')->assertDontSee('Budi Non Shift')->assertSee('Group NS');
        $this->actingAs($admin)->get('/hrms/karyawans?group=none')
            ->assertSee('Citra Kosong')->assertDontSee('Andi Shift A');

        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', [
            'nik' => '0004', 'nama' => 'Baru', 'jenis_kelamin' => 'Laki-laki', 'status' => 'PKWT 1', 'group' => ' b ',
        ])->assertSessionHasNoErrors();
        $this->assertSame('B', Karyawan::where('nik', '0004')->value('group'));
    }
}
