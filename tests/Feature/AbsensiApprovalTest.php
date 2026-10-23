<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiApproval;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbsensiApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Karyawan $andi;

    private Karyawan $budi;

    private User $deptAdmin;

    private User $hrmsAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 09:00:00');
        Storage::fake('local');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $pwd = Department::create(['name' => 'PWD']);
        $this->andi = Karyawan::create(['nik' => '0001', 'nama' => 'Andi PSD', 'department_id' => $this->psd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        $this->budi = Karyawan::create(['nik' => '0002', 'nama' => 'Budi PWD', 'department_id' => $pwd->id, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
        $this->deptAdmin = User::factory()->create();
        $this->deptAdmin->assignRole('hrms', 'dept_admin', $this->psd->id);
        $this->hrmsAdmin = User::factory()->create();
        $this->hrmsAdmin->assignRole('hrms', 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function alpa(Karyawan $karyawan): Absensi
    {
        return Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-10-15', 'kode' => 'A']);
    }

    public function test_department_admin_edit_waits_for_hrd_approval(): void
    {
        $absensi = $this->alpa($this->andi);

        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), [
            'tanggal' => '2026-10-15', 'kode' => 'DT>', 'menit_terlambat' => 45, 'alasan' => 'Ternyata datang terlambat',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('A', $absensi->fresh()->kode);
        $this->assertDatabaseHas('absensi_approvals', [
            'absensi_id' => $absensi->id, 'kode_lama' => 'A', 'kode_baru' => 'DT>', 'menit_baru' => 45,
            'status' => AbsensiApproval::STATUS_PENDING, 'requested_by' => $this->deptAdmin->id,
        ]);

        $this->actingAs($this->deptAdmin)->get('/hrms/absensi?tanggal=2026-10-15')
            ->assertOk()
            ->assertSee('Menunggu approval HRD');

        // Selama masih menunggu, tidak bisa mengajukan perubahan lain.
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), [
            'tanggal' => '2026-10-15', 'kode' => 'I', 'alasan' => 'Izin',
        ])->assertSessionHasErrors('approval');
        $this->assertSame(1, AbsensiApproval::count());
    }

    public function test_edit_requires_reason_and_sick_code_requires_doctor_letter(): void
    {
        $absensi = $this->alpa($this->andi);

        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), ['tanggal' => '2026-10-15', 'kode' => 'SD'])
            ->assertSessionHasErrors(['alasan', 'evidence']);
        $this->assertSame(0, AbsensiApproval::count());

        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), [
            'tanggal' => '2026-10-15', 'kode' => 'SD', 'alasan' => 'Sakit tifus',
            'evidence' => UploadedFile::fake()->create('surat-dokter.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $approval = AbsensiApproval::sole();
        $this->assertSame('surat-dokter.pdf', $approval->evidence_name);
        Storage::disk('local')->assertExists($approval->evidence_path);
    }

    public function test_hrms_admin_approves_and_change_is_applied(): void
    {
        $absensi = $this->alpa($this->andi);
        $approval = AbsensiApproval::create([
            'absensi_id' => $absensi->id, 'karyawan_id' => $this->andi->id,
            'tanggal_lama' => '2026-10-15', 'kode_lama' => 'A', 'tanggal_baru' => '2026-10-15', 'kode_baru' => 'S',
            'keterangan_baru' => 'Demam', 'alasan' => 'Sakit', 'status' => AbsensiApproval::STATUS_PENDING, 'requested_by' => $this->deptAdmin->id,
        ]);

        $this->actingAs($this->hrmsAdmin)->get(route('hrms.absensi.approval'))
            ->assertOk()
            ->assertSee('Andi PSD')
            ->assertSee('Tidak Setuju');

        $this->actingAs($this->hrmsAdmin)->post(route('hrms.absensi.approval.approve', $approval), ['catatan_review' => 'OK'])
            ->assertRedirect();

        $this->assertSame(['S', 'Demam'], [$absensi->fresh()->kode, $absensi->fresh()->keterangan]);
        $this->assertSame(AbsensiApproval::STATUS_APPROVED, $approval->fresh()->status);
        $this->assertSame($this->hrmsAdmin->id, $approval->fresh()->reviewed_by);

        // Yang sudah diputus tidak bisa diputus lagi.
        $this->actingAs($this->hrmsAdmin)->post(route('hrms.absensi.approval.reject', $approval))->assertSessionHasErrors('approval');
        $this->assertSame(AbsensiApproval::STATUS_APPROVED, $approval->fresh()->status);
    }

    public function test_rejected_change_keeps_original_data(): void
    {
        $absensi = $this->alpa($this->andi);
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), [
            'tanggal' => '2026-10-15', 'kode' => 'I', 'alasan' => 'Izin keluarga',
        ]);

        $this->actingAs($this->hrmsAdmin)->post(route('hrms.absensi.approval.reject', AbsensiApproval::sole()), ['catatan_review' => 'Tidak ada bukti'])
            ->assertRedirect();

        $this->assertSame('A', $absensi->fresh()->kode);
        $this->assertSame(AbsensiApproval::STATUS_REJECTED, AbsensiApproval::sole()->status);
        $this->actingAs($this->deptAdmin)->get('/hrms/absensi?tanggal=2026-10-15')->assertSee('Ditolak HRD')->assertSee('Tidak ada bukti');
    }

    public function test_department_admin_delete_is_a_request_until_approved(): void
    {
        $absensi = $this->alpa($this->andi);

        $this->actingAs($this->deptAdmin)->delete(route('hrms.absensi.entries.destroy', $absensi))
            ->assertSessionHasErrorsIn('hapus', 'alasan');

        $this->actingAs($this->deptAdmin)->delete(route('hrms.absensi.entries.destroy', $absensi), ['alasan' => 'Salah input'])
            ->assertRedirect();
        $this->assertModelExists($absensi);

        $this->actingAs($this->hrmsAdmin)->post(route('hrms.absensi.approval.approve', AbsensiApproval::sole()))->assertRedirect();
        $this->assertModelMissing($absensi);
    }

    public function test_department_admin_cannot_change_existing_cell_from_matrix(): void
    {
        $absensi = $this->alpa($this->andi);

        $this->actingAs($this->deptAdmin)->postJson('/hrms/absensi', ['karyawan_id' => $this->andi->id, 'tanggal' => '2026-10-15', 'kode' => null])
            ->assertStatus(422);
        $this->assertModelExists($absensi);
    }

    public function test_only_hrms_admin_can_review(): void
    {
        $absensi = $this->alpa($this->andi);
        $approval = AbsensiApproval::create([
            'absensi_id' => $absensi->id, 'karyawan_id' => $this->andi->id,
            'tanggal_lama' => '2026-10-15', 'kode_lama' => 'A', 'tanggal_baru' => '2026-10-15', 'kode_baru' => 'S',
            'alasan' => 'Sakit', 'status' => AbsensiApproval::STATUS_PENDING,
        ]);

        $this->actingAs($this->deptAdmin)->get(route('hrms.absensi.approval'))->assertForbidden();
        $this->actingAs($this->deptAdmin)->post(route('hrms.absensi.approval.approve', $approval))->assertForbidden();
        $this->assertSame('A', $absensi->fresh()->kode);
    }

    public function test_pending_approval_count_shows_in_menu_for_hrms_admin_only(): void
    {
        foreach ([$this->andi, $this->budi] as $karyawan) {
            $absensi = $this->alpa($karyawan);
            AbsensiApproval::create([
                'absensi_id' => $absensi->id, 'karyawan_id' => $karyawan->id,
                'tanggal_lama' => '2026-10-15', 'kode_lama' => 'A', 'tanggal_baru' => '2026-10-15', 'kode_baru' => 'I',
                'alasan' => 'Izin', 'status' => AbsensiApproval::STATUS_PENDING,
            ]);
        }

        $this->actingAs($this->hrmsAdmin)->get(route('hrms.absensi.index'))
            ->assertOk()
            ->assertSee('<span class="menu-badge" title="2 menunggu tindakan">2</span>', false);

        $this->actingAs($this->deptAdmin)->get(route('hrms.absensi.index'))
            ->assertOk()
            ->assertDontSee('<span class="menu-badge"', false);
    }

    public function test_evidence_is_visible_to_hrms_admin_and_owning_department_only(): void
    {
        $absensi = $this->alpa($this->andi);
        $this->actingAs($this->deptAdmin)->put(route('hrms.absensi.entries.update', $absensi), [
            'tanggal' => '2026-10-15', 'kode' => 'S', 'alasan' => 'Sakit',
            'evidence' => UploadedFile::fake()->image('surat.jpg'),
        ]);
        $approval = AbsensiApproval::sole();
        $otherDeptAdmin = User::factory()->create();
        $otherDeptAdmin->assignRole('hrms', 'dept_admin', $this->budi->department_id);

        $this->actingAs($this->hrmsAdmin)->get(route('hrms.absensi.approval.evidence', $approval))->assertOk();
        $this->actingAs($this->deptAdmin)->get(route('hrms.absensi.approval.evidence', $approval))->assertOk();
        $this->actingAs($otherDeptAdmin)->get(route('hrms.absensi.approval.evidence', $approval))->assertForbidden();
    }
}
