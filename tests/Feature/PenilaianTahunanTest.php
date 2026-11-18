<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\PenilaianTahunan;
use App\Modules\Hrms\Models\PenilaianTokenRequest;
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

    public function test_account_jabatan_grants_the_menu_without_employee_data(): void
    {
        $user = User::factory()->create(['nik' => '0007', 'jabatan' => 'Asst. Leader']);
        $user->assignRole('hrms', 'dept_admin', $this->psd->id);
        $operatorPsd = $this->karyawan('0008', 'Operator PSD', 'Karyawan', $this->psd);

        $response = $this->actingAs($user)->get(route('hrms.penilaian.index'))->assertOk();

        $this->assertSame([$operatorPsd->id], $response->viewData('rows')->pluck('karyawan.id')->all());
    }

    public function test_assessment_is_filled_by_asst_leader_then_approved_by_leader_and_section(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => []])
            ->assertSessionHasErrors(['skor' => 'Semua butir penilaian (poin II s/d VI) wajib diisi sebelum dikirim.']);
        $this->actingAs($this->section)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
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

    public function test_each_approver_gives_own_scores_and_items_are_averaged(): void
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

        // Asst. Leader B (4) + Leader A (5): tiap butir 4,5 → performance 72, ditambah absen 20.
        $penilaian->refresh();
        $this->assertSame('section', $penilaian->posisi_level);
        $this->assertSame(92.0, $penilaian->nilai_akhir);
        $this->assertSame('A', $penilaian->huruf);
        $this->assertSame($this->asstLeader->id, $penilaian->penilai_id);
        $this->assertSame(['kirim', 'ubah', 'setuju'], $penilaian->logs()->pluck('aksi')->all());
        $this->assertSame(['asst_leader', 'leader'], array_column($penilaian->skorPerPenilai(), 'level'));
        $this->assertSame($this->skorSemua('operator', 'B'), $penilaian->skorPerPenilai()[0]['skor']);

        // Section Chief C (3): (4 + 5 + 3) / 3 = 4 per butir → performance 64.
        $this->actingAs($this->section)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'C')]);
        $penilaian->refresh();
        $this->assertSame(84.0, $penilaian->nilai_akhir);

        $performance = $this->actingAs($this->leader)->get(route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('color: #1d4ed8;', false)
            ->viewData('performance');
        // Kemampuan kerja (4 butir): kolom A = 4 x 5/3, B = 4 x 4/3, C = 4 x 3/3; jumlahnya = total 16.
        $this->assertSame(['A' => 6.67, 'B' => 5.33, 'C' => 4.0, 'D' => 0.0, 'E' => 0.0], $performance['kategori']['kemampuan']['kolom']);
        $this->assertSame(16.0, $performance['kategori']['kemampuan']['total']);
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

    public function test_print_evidence_is_available_once_the_first_signature_exists(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $print = route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]);
        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'draft', 'skor' => $this->skorSemua('operator', 'B')]);
        $this->actingAs($this->leader)->get($print)->assertNotFound();

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $response = $this->actingAs($this->leader)->get($print)->assertOk();
        $this->assertSame([null, 'section', 'leader', 'asst_leader'], array_map(fn ($kotak) => $kotak['level'] ?? null, $response->viewData('kotakPenilai')));
        $this->assertNotNull($response->viewData('kotakPenilai')[3]['log']);
        $this->assertNull($response->viewData('kotakPenilai')[2]['log']);

        $this->actingAs($this->leader)->post($url, $setuju);
        $this->actingAs($this->section)->post($url, $setuju);

        // Default hanya centang & nilai absen: angka nilai lain & huruf tidak dicetak.
        $this->actingAs($this->leader)->get($print)
            ->assertOk()
            ->assertSee('HRD / FMR / 7')
            ->assertSee('Operator HRD')
            ->assertSee('color: #15803d;">✓', false)
            ->assertSeeInOrder(['<td class="val">20,00</td>', '<td class="val">A</td>'], false)
            ->assertDontSee('84,00')
            ->assertDontSee('= 12,80');

        // Toggle "Tampilkan nilai" di modal Print → ?nilai=1.
        $this->actingAs($this->leader)->get($print.'&nilai=1')
            ->assertOk()
            ->assertSee('84,00')
            ->assertSee('= 12,80')
            ->assertSee('Print / Simpan PDF');

        // Preview di modal Print (?modal=1): tanpa toolbar & tanpa dialog print otomatis.
        $this->actingAs($this->leader)->get($print.'&modal=1')
            ->assertOk()
            ->assertDontSee('Print / Simpan PDF')
            ->assertDontSee('window.print(); }, 300', false);

        $this->actingAs($this->leader)->get(route('hrms.penilaian.index', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('data-print-url="'.e($print).'"', false);
    }

    public function test_leader_can_take_over_filling_from_asst_leader_and_skips_own_approval(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'draft', 'skor' => $this->skorSemua('operator', 'C')]);

        $this->actingAs($this->section)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertForbidden();
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertRedirect();

        $penilaian = PenilaianTahunan::sole();
        $this->assertSame('section', $penilaian->posisi_level);
        $this->assertSame($this->leader->id, $penilaian->penilai_id);
        $this->assertSame([['kirim', 0, 'leader'], ['setuju', 1, 'leader']], $penilaian->logs()->get()
            ->map(fn ($log) => [$log->aksi, $log->tahap, $log->level])->all());
    }

    public function test_every_signature_uses_one_token_of_the_signer(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $skor = $this->skorSemua('operator', 'B');

        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'draft', 'skor' => $skor]);
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $skor]);
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'simpan', 'skor' => $skor]);
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $skor]);
        $this->actingAs($this->section)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $skor]);

        $this->assertSame(PenilaianTahunan::STATUS_DISETUJUI, PenilaianTahunan::sole()->status);
        foreach ([$this->asstLeader, $this->leader, $this->section] as $penilai) {
            $this->assertSame(User::TOKEN_PENILAIAN_AWAL - 1, $penilai->fresh()->token_penilaian);
        }
    }

    public function test_signing_is_blocked_when_tokens_run_out(): void
    {
        $this->asstLeader->update(['token_penilaian' => 0]);

        $this->actingAs($this->asstLeader)->post(route('hrms.penilaian.save', $this->operator), ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')])
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('penilaian_tahunans', 0);
        $this->assertSame(0, $this->asstLeader->fresh()->token_penilaian);
    }

    public function test_assessor_requests_tokens_and_hrms_admin_approves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        $this->actingAs($this->leader)->post(route('hrms.penilaian.token.store'), ['jumlah' => 20, 'alasan' => 'Masih ada token'])
            ->assertSessionHasErrors('jumlah', null, 'token');
        $this->assertDatabaseCount('penilaian_token_requests', 0);

        $this->leader->update(['token_penilaian' => 0]);
        $this->actingAs($this->leader)->post(route('hrms.penilaian.token.store'), ['jumlah' => 20, 'alasan' => 'Masih banyak yang belum dinilai'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->leader)->post(route('hrms.penilaian.token.store'), ['jumlah' => 5, 'alasan' => 'Lagi'])
            ->assertSessionHasErrors('jumlah', null, 'token');
        $tokenRequest = PenilaianTokenRequest::sole();

        $this->actingAs($this->leader)->get(route('hrms.penilaian.token.approval'))->assertForbidden();
        $this->actingAs($this->leader)->post(route('hrms.penilaian.token.approve', $tokenRequest))->assertForbidden();

        $this->actingAs($admin)->get(route('hrms.penilaian.token.approval'))->assertOk()->assertSee($this->leader->name);
        $this->actingAs($admin)->post(route('hrms.penilaian.token.approve', $tokenRequest))->assertRedirect();
        $this->actingAs($admin)->post(route('hrms.penilaian.token.approve', $tokenRequest))->assertSessionHasErrors('approval');

        $this->assertSame(20, $this->leader->fresh()->token_penilaian);
        $this->assertSame(PenilaianTokenRequest::STATUS_APPROVED, $tokenRequest->fresh()->status);
    }

    public function test_profile_modal_shows_token_code_and_balance(): void
    {
        $this->actingAs($this->leader)->get(route('hrms.penilaian.index'))
            ->assertOk()
            ->assertSee('Sisa token penilaian')
            ->assertSee($this->leader->token_kode)
            ->assertSee('Leader')
            ->assertDontSee('Ajukan penambahan token');

        $this->leader->update(['token_penilaian' => 0]);
        $this->actingAs($this->leader)->get(route('hrms.penilaian.index'))->assertSee('Ajukan penambahan token');
    }

    public function test_printed_signature_qr_links_to_public_verification(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'B')]);
        $this->actingAs($this->leader)->post($url, $setuju);
        $this->actingAs($this->section)->post($url, $setuju);

        $this->actingAs($this->leader)->get(route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('<svg', false);

        $kode = PenilaianTahunan::sole()->logs()->where('aksi', 'kirim')->value('kode_verifikasi');
        $this->assertNotNull($kode);

        auth()->logout();
        $this->get(route('verifikasi.penilaian', $kode))
            ->assertOk()
            ->assertSee('Tanda tangan elektronik sah')
            ->assertSee($this->asstLeader->name)
            ->assertSee('Operator HRD')
            ->assertSee($this->asstLeader->token_kode);

        $this->get(route('verifikasi.penilaian', 'kode-palsu'))->assertNotFound()->assertSee('Tanda tangan tidak dikenali');
    }

    public function test_print_shows_one_check_per_box_from_the_highest_assessor(): void
    {
        $url = route('hrms.penilaian.save', $this->operator);
        $sama = $this->skorSemua('operator', 'B');
        $beda = $sama;
        $beda['kemampuan'][0] = 'A';
        $this->actingAs($this->asstLeader)->post($url, ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $sama]);
        $this->actingAs($this->leader)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $sama]);
        $this->actingAs($this->section)->post($url, ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $beda]);

        $html = $this->actingAs($this->leader)->get(route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]))->assertOk()->getContent();

        // Baris pertama: Asst. Leader & Leader B → kotak B hanya biru (Leader), Section Chief A → hijau.
        // Baris lain semua B → hanya hijau (Section Chief). Asst. Leader (hitam) tidak pernah tampil.
        $hitam = substr_count($html, 'color: #111111;">✓');
        $biru = substr_count($html, 'color: #1d4ed8;">✓');
        $hijau = substr_count($html, 'color: #15803d;">✓');
        $this->assertSame([0, 1, count(array_merge(...array_values($sama)))], [$hitam, $biru, $hijau]);
    }

    public function test_preview_renders_unsaved_input_without_saving_or_using_tokens(): void
    {
        $preview = route('hrms.penilaian.preview', $this->operator);
        $isian = ['tahun' => 2026, 'aksi' => 'kirim', 'skor' => $this->skorSemua('operator', 'A'), 'catatan' => 'Catatan preview'];

        $this->actingAs($this->section)->post($preview, $isian)->assertForbidden();

        $response = $this->actingAs($this->asstLeader)->post($preview, $isian)
            ->assertOk()
            ->assertSee('Catatan preview')
            ->assertSee('Akan ditandatangani')
            ->assertDontSee('window.print()', false);
        $this->assertSame(100.0, $response->viewData('penilaian')->nilai_akhir);

        $this->assertDatabaseCount('penilaian_tahunans', 0);
        $this->assertDatabaseCount('penilaian_tahunan_logs', 0);
        $this->assertSame(User::TOKEN_PENILAIAN_AWAL, $this->asstLeader->fresh()->token_penilaian);
    }

    public function test_print_signature_boxes_fill_from_the_right_by_level(): void
    {
        $setuju = ['tahun' => 2026, 'aksi' => 'setuju', 'skor' => $this->skorSemua('operator', 'B')];
        $kirim = ['aksi' => 'kirim'] + $setuju;
        $levels = fn ($response) => array_map(fn ($kotak) => $kotak['level'] ?? null, $response->viewData('kotakPenilai'));

        // Operator HRD: Asst. Leader, Leader, Section Chief di 3 kotak paling kanan.
        $url = route('hrms.penilaian.save', $this->operator);
        $this->actingAs($this->asstLeader)->post($url, $kirim);
        $this->actingAs($this->leader)->post($url, $setuju);
        $this->actingAs($this->section)->post($url, $setuju);
        $response = $this->actingAs($this->leader)->get(route('hrms.penilaian.print', [$this->operator, 'tahun' => 2026]))->assertOk();
        $this->assertSame([null, 'section', 'leader', 'asst_leader'], $levels($response));

        // Departemen tanpa Asst. Leader: langsung Leader di kotak ke-2, Manager di kotak paling kiri.
        $operatorPsd = $this->karyawan('0005', 'Operator PSD', 'Karyawan', $this->psd);
        $leaderPsd = $this->penilai('0007', 'Leader', $this->psd);
        $managerPsd = $this->penilai('0008', 'Manager', $this->psd);
        $url = route('hrms.penilaian.save', $operatorPsd);
        $this->actingAs($leaderPsd)->post($url, $kirim);
        $this->actingAs($managerPsd)->post($url, $setuju);
        $response = $this->actingAs($managerPsd)->get(route('hrms.penilaian.print', [$operatorPsd, 'tahun' => 2026]))->assertOk();
        $this->assertSame(['manager', null, 'leader', null], $levels($response));
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
