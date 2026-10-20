<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiEntryTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Karyawan $andi;

    private Karyawan $budi;

    private User $deptAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $pwd = Department::create(['name' => 'PWD']);
        $this->andi = Karyawan::create(['nik' => '0001', 'nama' => 'Andi PSD', 'department_id' => $this->psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'A']);
        $this->budi = Karyawan::create(['nik' => '0002', 'nama' => 'Budi PWD', 'department_id' => $pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'group' => 'NS']);
        $this->deptAdmin = User::factory()->create();
        $this->deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_department_admin_gets_crud_table_instead_of_matrix(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-15', 'kode' => 'S', 'keterangan' => 'Demam']);

        $this->actingAs($this->deptAdmin)->get('/hrms/absensi')
            ->assertOk()
            ->assertSee('+ Tambah Absensi')
            ->assertSee('Demam')
            ->assertDontSee('id="abs-table"', false);
    }

    public function test_store_range_skips_weekend_and_overwrites_existing(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-16', 'kode' => 'S']);

        // 16 Okt (Jumat) s/d 19 Okt (Senin): 17 Okt (Sabtu) & 18 Okt (Minggu) dilewati.
        $this->actingAs($this->deptAdmin)->post(route('hrms.absensi.entries.store'), [
            'karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-16', 'sampai' => '2026-10-19', 'kode' => 'CT', 'keterangan' => 'Cuti pulang kampung',
        ])->assertRedirect(route('hrms.absensi.index', ['tanggal' => '2026-10-16']));

        $this->assertSame(['2026-10-16', '2026-10-19'],
            Absensi::orderBy('tanggal')->get()->map(fn ($a) => $a->tanggal->toDateString())->all());
        $this->assertSame(2, Absensi::where('kode', 'CT')->count());
    }

    public function test_weekend_date_is_rejected(): void
    {
        $absensi = Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-16', 'kode' => 'S']);

        // 17 Okt 2026 = Sabtu, 18 Okt 2026 = Minggu.
        $this->actingAs($this->deptAdmin)->post(route('hrms.absensi.entries.store'), ['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-17', 'kode' => 'I'])
            ->assertSessionHasErrors('tanggal');
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), ['tanggal' => '2026-10-18', 'kode' => 'I'])
            ->assertSessionHasErrors('tanggal');

        $this->assertSame(1, Absensi::count());
        $this->assertSame('2026-10-16', $absensi->fresh()->tanggal->toDateString());
    }

    public function test_update_and_delete(): void
    {
        $absensi = Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-15', 'kode' => 'S']);
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => 'I']);

        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), ['tanggal' => '2026-10-15', 'kode' => 'SD', 'keterangan' => 'Surat dokter'])
            ->assertRedirect();
        $this->assertSame('SD', $absensi->fresh()->kode);

        // Pindah ke tanggal yang sudah terisi ditolak.
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), ['tanggal' => '2026-10-14', 'kode' => 'SD'])
            ->assertSessionHasErrors('tanggal');

        $this->actingAs($this->deptAdmin)->delete(route('hrms.absensi.entries.destroy', $absensi))->assertRedirect();
        $this->assertModelMissing($absensi);
    }

    public function test_department_admin_cannot_touch_other_department(): void
    {
        $lain = Absensi::create(['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-15', 'kode' => 'S']);

        $this->actingAs($this->deptAdmin)->post(route('hrms.absensi.entries.store'), ['karyawan_id' => $this->budi->id, 'tanggal' => '2026-10-15', 'kode' => 'I'])->assertForbidden();
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $lain), ['tanggal' => '2026-10-15', 'kode' => 'I'])->assertForbidden();
        $this->actingAs($this->deptAdmin)->delete(route('hrms.absensi.entries.destroy', $lain))->assertForbidden();
        $this->assertSame('S', $lain->fresh()->kode);
    }

    public function test_hrms_admin_matrix_marks_present_days_green(): void
    {
        Absensi::create(['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-14', 'kode' => 'S']);
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        $html = $this->actingAs($admin)->get('/hrms/absensi')->assertOk()->getContent();

        // Hari kerja s/d hari ini yang kosong = hijau; tanggal yang ada kodenya, Sabtu/Minggu, dan hari depan tidak.
        $this->assertMatchesRegularExpression('/class="cell editable present"\s+data-day="15"/', $html);
        $this->assertDoesNotMatchRegularExpression('/present"\s+data-day="14"[^>]*data-kode="S"/', $html);
        $this->assertDoesNotMatchRegularExpression('/present"\s+data-day="10"/', $html); // Sabtu
        $this->assertDoesNotMatchRegularExpression('/present"\s+data-day="11"/', $html); // Minggu
        $this->assertDoesNotMatchRegularExpression('/present"\s+data-day="16"/', $html); // besok
    }
}
