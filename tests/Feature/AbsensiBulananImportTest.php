<?php

namespace Tests\Feature;

use App\Modules\Hrms\Imports\AbsensiBulananImport;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AbsensiBulananImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'absensi').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    private function karyawan(string $nik): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => "Karyawan {$nik}", 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
    }

    /**
     * Sheet dengan format rekap HRD: judul, "Tanggal :" berisi tanggal lain, header NIK/DATE, nomor tanggal, lalu data.
     *
     * @param  list<array{0: string, 1: array<int, string>}>  $karyawans  [nik, [tanggal => kode]]
     */
    private function sheet(string $name, array $karyawans): void
    {
        $writer = new Writer;
        $writer->openToFile($this->path);
        $writer->getCurrentSheet()->setName('Lain');
        $writer->addRow(Row::fromValues(['Sheet lain']));
        $writer->addNewSheetAndMakeItCurrent()->setName($name);
        $writer->addRow(Row::fromValues([null, null, null, null, null, null, 'LAPORAN ABSENSI HARIAN KARYAWAN']));
        $writer->addRow(Row::fromValues(['Tanggal :', null, '2027-01-05']));
        $writer->addRow(Row::fromValues(['No', 'NIK', 'NAMA', 'BAGIAN', 'GROUP', 'Plant', 'DATE']));
        $writer->addRow(Row::fromValues([null, null, 'KARYAWAN', null, null, null, ...range(1, 31)]));

        foreach ($karyawans as $index => [$nik, $kodes]) {
            $hari = array_map(fn (int $day) => $kodes[$day] ?? null, range(1, 31));
            $writer->addRow(Row::fromValues([$index + 1, $nik, "Karyawan {$nik}", 'PSD', 'A', null, ...$hari]));
        }

        $writer->close();
    }

    public function test_import_menyamakan_absensi_bulan_dari_nama_sheet(): void
    {
        $andi = $this->karyawan('0001');
        $budi = $this->karyawan('3556');
        Absensi::create(['karyawan_id' => $andi->id, 'tanggal' => '2026-11-02', 'kode' => 'S', 'keterangan' => 'Sakit demam']);
        Absensi::create(['karyawan_id' => $andi->id, 'tanggal' => '2026-11-03', 'kode' => 'I']);
        Absensi::create(['karyawan_id' => $andi->id, 'tanggal' => '2026-11-04', 'kode' => 'DT<', 'menit_terlambat' => 12]);
        Absensi::create(['karyawan_id' => $andi->id, 'tanggal' => '2026-11-20', 'kode' => 'A']);
        Absensi::create(['karyawan_id' => $andi->id, 'tanggal' => '2026-10-02', 'kode' => 'S']);

        $this->sheet("Nop'26", [
            ['0001', [2 => 'SD', 3 => 'I', 4 => 'DT>', 31 => 'CT']],
            ['3556', [10 => 'dt<']],
            ['3556', [13 => 'I']],
            ['9999', [5 => 'S']],
            ['0001', [6 => 'XX']],
        ]);

        $hasil = (new AbsensiBulananImport)->import($this->path, "Nop'26");

        $this->assertSame('2026-11', $hasil['bulan']->format('Y-m'));
        $this->assertSame([2, 2, 1, 1], [$hasil['ditambah'], $hasil['diubah'], $hasil['sama'], $hasil['hanya_di_database']]);
        $this->assertSame(['9999'], $hasil['nik_tidak_terdaftar']);
        $this->assertSame(['0001 2026-11-06 "XX"'], $hasil['kode_tidak_dikenal']);

        $kode = fn (Karyawan $karyawan) => Absensi::where('karyawan_id', $karyawan->id)->orderBy('tanggal')->get()
            ->mapWithKeys(fn (Absensi $absensi) => [$absensi->tanggal->toDateString() => $absensi->kode])->all();
        // 31 November tidak ada, jadi dilewati; data di luar bulan dan yang kosong di sheet tidak disentuh.
        $this->assertSame(['2026-10-02' => 'S', '2026-11-02' => 'SD', '2026-11-03' => 'I', '2026-11-04' => 'DT>', '2026-11-20' => 'A'], $kode($andi));
        $this->assertSame(['2026-11-10' => 'DT<', '2026-11-13' => 'I'], $kode($budi));

        $diganti = Absensi::where('karyawan_id', $andi->id)->whereDate('tanggal', '2026-11-02')->first();
        $this->assertSame('Sakit demam', $diganti->keterangan);
        $this->assertSame(12, Absensi::where('karyawan_id', $andi->id)->whereDate('tanggal', '2026-11-04')->value('menit_terlambat'));
    }

    public function test_command_tanpa_simpan_tidak_mengubah_database(): void
    {
        $this->karyawan('0001');
        $this->sheet("Nop'26", [['0001', [2 => 'S']]]);

        $this->artisan('hrms:import-absensi', ['file' => $this->path, 'sheet' => "Nop'26"])
            ->expectsOutputToContain('Cek (dry-run) absensi')
            ->assertSuccessful();
        $this->assertSame(0, Absensi::count());

        $this->artisan('hrms:import-absensi', ['file' => $this->path, 'sheet' => "Nop'26", '--simpan' => true])->assertSuccessful();
        $this->assertSame(1, Absensi::count());
    }

    public function test_bulan_dibaca_dari_berbagai_nama_sheet(): void
    {
        $this->assertSame('2026-09', AbsensiBulananImport::bulanDariNamaSheet("Sept'26")->format('Y-m'));
        $this->assertSame('2026-05', AbsensiBulananImport::bulanDariNamaSheet("Mei'26")->format('Y-m'));
        $this->assertSame('2027-12', AbsensiBulananImport::bulanDariNamaSheet('Des 2027')->format('Y-m'));

        $this->artisan('hrms:import-absensi', ['file' => $this->path, 'sheet' => 'total'])
            ->expectsOutputToContain('Bulan tidak terbaca dari nama sheet')
            ->assertFailed();
    }
}
