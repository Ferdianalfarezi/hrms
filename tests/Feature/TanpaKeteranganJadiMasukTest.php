<?php

namespace Tests\Feature;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\KomparasiAbsensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TanpaKeteranganJadiMasukTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psd = Department::create(['name' => 'PSD']);
        // Minggu 2 Nov grup A pagi dan B malam; Jumat malam 6 Nov sudah grup A (data mesinnya di Senin 9 Nov).
        ShiftRotasi::create(['tanggal_mulai' => '2026-11-02', 'grup_pagi' => 'A']);
    }

    private function karyawan(string $nik, string $group): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => 'Karyawan '.$nik, 'department_id' => $this->psd->id,
            'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => $group]);
    }

    private function mesin(string $tanggal, Karyawan $karyawan): AbsensiMesin
    {
        return AbsensiMesin::create(['tanggal' => $tanggal, 'nik' => $karyawan->nik]);
    }

    public function test_only_unexplained_cells_on_that_date_become_present(): void
    {
        $pagi = $this->karyawan('1001', 'A');
        $malam = $this->karyawan('1002', 'A');
        $sakit = $this->karyawan('1003', 'A');
        $grupB = $this->karyawan('1004', 'B');
        $pagiMesin = $this->mesin('2026-11-06', $pagi);
        $malamMesin = $this->mesin('2026-11-09', $malam);
        $sakitMesin = $this->mesin('2026-11-06', $sakit);
        Absensi::create(['karyawan_id' => $sakit->id, 'tanggal' => '2026-11-06', 'kode' => 'S']);
        // Tanggal laporan lain tidak boleh tersentuh: grup B malam Kamis 5 Nov (data mesin 6 Nov) dan Senin 9 Nov.
        $malamKamis = $this->mesin('2026-11-06', $grupB);
        $pagiSenin = $this->mesin('2026-11-09', $grupB);

        $this->artisan('absensi:tanpa-keterangan-jadi-masuk', ['tanggal' => '2026-11-06', '--force' => true])
            ->expectsOutputToContain('2 data mesin dihapus')
            ->assertSuccessful();

        $this->assertModelMissing($pagiMesin);
        $this->assertModelMissing($malamMesin);
        $this->assertModelExists($sakitMesin);
        $this->assertModelExists($malamKamis);
        $this->assertModelExists($pagiSenin);
        $hariItu = Carbon::parse('2026-11-06');
        $this->assertSame([], KomparasiAbsensi::selBelumAdaKeterangan(Karyawan::all(), $hariItu, $hariItu));
    }

    public function test_dry_run_and_invalid_date_change_nothing(): void
    {
        $pagi = $this->karyawan('1001', 'A');
        $pagiMesin = $this->mesin('2026-11-06', $pagi);

        $this->artisan('absensi:tanpa-keterangan-jadi-masuk', ['tanggal' => '2026-11-06', '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->artisan('absensi:tanpa-keterangan-jadi-masuk', ['tanggal' => '06-11-2026'])->assertFailed();

        $this->assertModelExists($pagiMesin);
    }
}
