<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\PenilaianTahunan;
use App\Modules\Hrms\Support\FormPenilaian;
use Database\Seeders\ApplicationSeeder;
use Database\Seeders\PenilaiAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PenilaianTahunanTest extends TestCase
{
    use RefreshDatabase;

    private Department $hrd;

    private Department $psd;

    private Karyawan $operator;

    private User $asstLeader;

    private User $leader;

    private User $section;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-11 09:00:00');
        $this->seed(ApplicationSeeder::class);
        $this->hrd = Department::create(['name' => 'HRD']);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->operator = $this->karyawan('0001', 'Operator HRD', 'Karyawan', $this->hrd);
        $this->asstLeader = $this->penilai('0002', 'Asst. Leader', $this->hrd);
        $this->leader = $this->penilai('0003', 'Leader', $this->hrd);
        $this->section = $this->penilai('0004', 'Section Chief', $this->hrd);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_score_follows_the_excel_reference(): void
    {
        $tanggal = Carbon::parse('2026-01-01');
        $absen = ['I' => 1, 'DT<' => 4, 'DT>' => 9, 'PC>' => 1, 'SD' => 8];
        foreach ($absen as $kode => $jumlah) {
            for ($i = 0; $i < $jumlah; $i++) {
                Absensi::create(['karyawan_id' => $this->operator->id, 'tanggal' => $tanggal->addDay()->toDateString(), 'kode' => $kode]);
            }
        }
        Absensi::create(['karyawan_id' => $this->operator->id, 'tanggal' => '2025-12-31', 'kode' => 'A']);

        $absen = $this->actingAs($this->asstLeader)
            ->get(route('hrms.penilaian.show', [$this->operator, 'tahun' => 2026]))
            ->assertOk()
            ->viewData('absen');

        $this->assertSame(25, $absen['total_poin']);
        $this->assertSame(12.5, $absen['nilai']);
    }

    public function test_each_level_only_sees_lower_levels_in_its_department(): void
    {
        $this->karyawan('0005', 'Operator PSD', 'Karyawan', $this->psd);

        $niks = fn (User $user) => $this->actingAs($user)->get(route('hrms.penilaian.index'))
            ->assertOk()
            ->viewData('rows')->map(fn (array $row) => $row['karyawan']->nik)->all();

        $this->assertSame(['0001'], $niks($this->asstLeader));
        $this->assertSame(['0001', '0002'], $niks($this->leader));
        $this->assertSame(['0001', '0002', '0003'], $niks($this->section));
    }

    public function test_list_is_paginated_while_status_counts_cover_all_rows(): void
    {
        for ($i = 10; $i < 40; $i++) {
            $this->karyawan('00'.$i, "Operator {$i}", 'Karyawan', $this->hrd);
        }

        $response = $this->actingAs($this->asstLeader)->get(route('hrms.penilaian.index', ['page' => 2]))->assertOk();

        $this->assertCount(6, $response->viewData('rows'));
        $this->assertSame(31, $response->viewData('rows')->total());
        $this->assertSame(31, $response->viewData('statusCounts')->sum());
        $this->assertSame(31, $response->viewData('perluTindakan'));
    }

    public function test_employee_level_account_cannot_open_the_menu(): void
    {
        $operatorUser = $this->penilai('0006', 'Karyawan', $this->hrd);

        $this->actingAs($operatorUser)->get(route('hrms.penilaian.index'))->assertForbidden();
    }

    public function test_assessment_is_filled_by_asst_leader_then_approved_by_leader_and_section(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => []])
            ->assertSessionHasErrors(['skor' => 'Semua butir penilaian (poin II s/d VI) wajib diisi sebelum dikirim.']);
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertForbidden();

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertRedirect();

        $penilaian = PenilaianTahunan::sole();
        $this->assertSame(['asst_leader', 'leader', 'section'], $penilaian->tahapan);
        $this->assertSame(PenilaianTahunan::STATUS_MENUNGGU, $penilaian->status);
        $this->assertSame('leader', $penilaian->posisi_level);
        $this->assertSame(20.0, $penilaian->nilai_absen);
        $this->assertSame(64.0, $penilaian->nilai_performance);
        $this->assertSame(84.0, $penilaian->nilai_akhir);
        $this->assertSame('B', $penilaian->huruf);

        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];
        $this->actingAs($this->section)->post($url, $setuju)->assertForbidden();
        $this->actingAs($this->leader)->get(route('hrms.penilaian.show', [$this->operator, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Simpan & Setujui');
        $this->actingAs($this->leader)->post($url, $setuju)->assertRedirect();
        $this->assertSame('section', $penilaian->fresh()->posisi_level);
        $this->actingAs($this->section)->post($url, $setuju)->assertRedirect();

        $penilaian->refresh();
        $this->assertSame(PenilaianTahunan::STATUS_DISETUJUI, $penilaian->status);
        $this->assertNull($penilaian->posisi_level);
        $this->assertSame(['kirim', 'setuju', 'setuju'], $penilaian->logs()->pluck('aksi')->all());
    }

    public function test_current_approver_can_change_scores_before_approving(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $penilaian = PenilaianTahunan::sole();
        $tidakLengkap = $this->skorSemua('operator', 'A');
        unset($tidakLengkap['kreativitas'][3]);

        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $tidakLengkap])
            ->assertSessionHasErrors(['skor' => 'Semua butir penilaian (poin II s/d VI) wajib diisi sebelum disetujui.']);
        $this->assertSame('leader', $penilaian->fresh()->posisi_level);

        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'A')])
            ->assertRedirect();

        $penilaian->refresh();
        $this->assertSame('section', $penilaian->posisi_level);
        $this->assertSame(100.0, $penilaian->nilai_akhir);
        $this->assertSame('A', $penilaian->huruf);
        $this->assertSame($this->asstLeader->id, $penilaian->penilai_id);
        $this->assertSame(['kirim', 'ubah', 'setuju'], $penilaian->logs()->pluck('aksi')->all());
    }

    public function test_manager_can_override_a_pending_lower_approval(): void
    {
        $manager = $this->penilai('0011', 'Manager', $this->hrd);
        $url = route('hrms.penilaian.save', $this->operator);
        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $this->actingAs($this->leader)->post($url, $setuju);

        $this->actingAs($this->asstLeader)->post($url, $setuju)->assertForbidden();
        $this->actingAs($manager)->post($url, $setuju)->assertRedirect();

        $penilaian = PenilaianTahunan::sole();
        $this->assertSame(PenilaianTahunan::STATUS_DISETUJUI, $penilaian->status);
        $kolomPenilai = $this->actingAs($manager)->get(route('hrms.penilaian.show', [$this->operator, 'tahun' => 2026]))
            ->assertOk()
            ->viewData('penilaiPerTahap');
        $this->assertSame('Asst. Manager / Manager', $kolomPenilai[2]['label']);
        $this->assertSame('Section Chief', $kolomPenilai[2]['override']);
    }

    public function test_print_evidence_is_only_available_after_final_approval(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $print = route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]);
        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $this->actingAs($this->leader)->post($url, $setuju);

        $this->actingAs($this->leader)->get($print)->assertNotFound();

        $this->actingAs($this->section)->post($url, $setuju);

        $this->actingAs($this->leader)->get($print)
            ->assertOk()
            ->assertSee('HRD / FMR / 7')
            ->assertSee('Operator HRD')
            ->assertSee('84,00');
    }

    public function test_leader_cannot_override_a_higher_pending_approval(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')]);

        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertForbidden();

        $this->assertSame('section', PenilaianTahunan::sole()->posisi_level);
    }

    public function test_missing_level_in_department_is_skipped(): void
    {
        $operatorPsd = $this->karyawan('0005', 'Operator PSD', 'Karyawan', $this->psd);
        $leaderPsd = $this->penilai('0007', 'Leader', $this->psd);
        $managerPsd = $this->penilai('0008', 'Manager', $this->psd);

        $this->actingAs($leaderPsd)->post(route('hrms.penilaian.save', $operatorPsd), ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'A')])
            ->assertRedirect();

        $penilaian = PenilaianTahunan::sole();
        $this->assertSame(['leader', 'manager'], $penilaian->tahapan);
        $this->assertSame(1, PenilaianTahunan::perluTindakan($managerPsd)->count());
    }

    public function test_seeder_creates_assessor_accounts_scoped_to_their_department(): void
    {
        $sectionPsd = $this->karyawan('0009', 'Section PSD', 'Section Chief', $this->psd);
        $existing = User::factory()->create(['nik' => '0010', 'password' => 'rahasia']);
        $this->karyawan('0010', 'Leader Lama', 'Leader', $this->psd);

        $this->seed(PenilaiAccountSeeder::class);

        $user = User::where('nik', $sectionPsd->nik)->sole();
        $this->assertTrue(Hash::check('step123', $user->password));
        $this->assertSame([$this->psd->id], $user->managedDepartmentIds());
        $this->assertFalse(User::where('nik', $this->operator->nik)->exists());
        $this->assertTrue(Hash::check('rahasia', $existing->fresh()->password));
        $this->assertSame([$this->psd->id], $existing->fresh()->managedDepartmentIds());
    }

    private function karyawan(string $nik, string $nama, string $jabatan, Department $department): Karyawan
    {
        return Karyawan::create([
            'nik' => $nik, 'nama' => $nama, 'jabatan' => $jabatan, 'department_id' => $department->id,
            'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap',
        ]);
    }

    private function penilai(string $nik, string $jabatan, Department $department): User
    {
        $this->karyawan($nik, "{$jabatan} {$department->name}", $jabatan, $department);
        $user = User::factory()->create(['nik' => $nik]);
        $user->assignRole('hrms', 'dept_admin', $department->id);

        return $user;
    }

    /**
     * @return array<string, list<string>>
     */
    private function skorSemua(string $form, string $huruf): array
    {
        return array_map(fn (array $butir) => array_fill(0, count($butir), $huruf), FormPenilaian::form($form)['butir']);
    }
}
