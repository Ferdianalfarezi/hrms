<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Support\GrafikCrossPatrol;
use App\Modules\Sms\Support\GrafikTemuanPatrol;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyPatrolTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
        $this->seedKaryawans(['Hartono', 'Dedi', 'Aminudin', 'Danar', 'Yayan R', 'Miftari']);
    }

    /** Karyawan yang dipakai sebagai pilihan PIC / patroler. */
    private function seedKaryawans(array $names): void
    {
        foreach ($names as $index => $nama) {
            Karyawan::create(['nik' => 'K'.($index + 1), 'nama' => $nama, 'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap', 'department_id' => $this->psd->id]);
        }
    }

    private function deptAdmin(Department $department): User
    {
        $user = User::factory()->create();
        $user->assignRole('sms', 'dept_admin', $department->id);

        return $user;
    }

    private function smsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('sms', 'admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'tanggal' => '2026-10-19',
            'finding' => 'Tutup samping mesh troley tidak di tutup',
            'foto' => UploadedFile::fake()->image('temuan.jpg'),
            'potensi_bahaya' => 'Tergores part',
            'plant' => '1',
            'rekomendasi' => 'Tutup cover samping troley',
            'resiko' => 'H',
            'kategori' => 'apparatus',
            'target' => '2026-10-19',
            'pic' => 'Hartono, Dedi, Aminudin, Danar',
            'pic_patrol' => 'Yayan R, Miftari',
            ...$overrides,
        ];
    }

    private function patrol(Department $department, array $overrides = []): DailyPatrol
    {
        return DailyPatrol::create([
            'tanggal' => '2026-10-19', 'finding' => 'Temuan '.$department->name, 'foto' => 'daily-patrol/a.jpg',
            'potensi_bahaya' => 'Tergores', 'department_id' => $department->id, 'plant' => '1', 'rekomendasi' => 'Perbaiki',
            'resiko' => 'S', 'kategori' => 'fire', 'target' => '2026-10-25', 'pic' => 'Hartono', 'pic_patrol' => 'Yayan R', 'status' => 'open',
            ...$overrides,
        ]);
    }

    public function test_department_admin_sees_daily_patrol_menu_and_only_own_department(): void
    {
        $this->patrol($this->psd);
        $this->patrol($this->pwd);

        $this->actingAs($this->deptAdmin($this->psd))->get('/sms/daily-patrols')
            ->assertOk()
            ->assertSee('Daily Patrol')
            ->assertSee('Temuan PSD')
            ->assertSee('Ringan') // resiko S ditampilkan dalam bahasa Indonesia
            ->assertDontSee('Small')
            ->assertDontSee('Temuan PWD');

        $this->actingAs($this->deptAdmin($this->psd))->get('/sms')
            ->assertSee(route('sms.daily-patrols.index'), false);
    }

    public function test_staff_sms_cannot_open_daily_patrol(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('sms', 'staff');

        $this->actingAs($staff)->get('/sms')->assertOk()->assertDontSee(route('sms.daily-patrols.index'), false);
        $this->actingAs($staff)->get('/sms/daily-patrols')->assertForbidden();
    }

    public function test_department_admin_cannot_add_or_delete_only_edit(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);
        $patrol = $this->patrol($this->psd);

        $this->actingAs($deptAdmin)->get('/sms/daily-patrols')->assertOk()
            ->assertDontSee('+ Tambah')
            ->assertDontSee('title="Hapus"', false)
            ->assertSee('data-edit="'.$patrol->id.'"', false);

        $this->actingAs($deptAdmin)->post('/sms/daily-patrols', $this->payload())->assertForbidden();
        $this->actingAs($deptAdmin)->delete("/sms/daily-patrols/{$patrol->id}")->assertForbidden();
        $this->assertSame(1, DailyPatrol::count());

        $this->actingAs($deptAdmin)->put("/sms/daily-patrols/{$patrol->id}", $this->payload(['foto' => null, 'finding' => 'Sudah diedit', 'status' => 'open']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Sudah diedit', $patrol->fresh()->finding);
    }

    public function test_new_finding_by_sms_admin_is_open_in_chosen_department(): void
    {
        $admin = $this->smsAdmin();

        $this->actingAs($admin)->get('/sms/daily-patrols')->assertSee('+ Tambah Temuan');
        $this->actingAs($admin)->post('/sms/daily-patrols', $this->payload(['department_id' => $this->pwd->id]))
            ->assertRedirect(route('sms.daily-patrols.index'))
            ->assertSessionHasNoErrors();

        $patrol = DailyPatrol::sole();
        $this->assertSame('open', $patrol->status);
        $this->assertSame($this->pwd->id, $patrol->department_id);
        $this->assertSame($admin->id, $patrol->created_by);
        $this->assertSame('Hartono, Dedi, Aminudin, Danar', $patrol->pic);
        $this->assertSame('Yayan R, Miftari', $patrol->pic_patrol);
        Storage::disk('public')->assertExists($patrol->foto);
    }

    public function test_validation_requires_photo_and_known_options(): void
    {
        $this->actingAs($this->smsAdmin())
            ->post('/sms/daily-patrols', $this->payload(['department_id' => $this->psd->id, 'foto' => null, 'resiko' => 'X', 'kategori' => 'nope', 'pic_patrol' => '']))
            ->assertSessionHasErrors(['foto', 'resiko', 'kategori', 'pic_patrol']);

        $this->assertSame(0, DailyPatrol::count());
    }

    public function test_pic_must_be_chosen_from_karyawan(): void
    {
        $admin = $this->smsAdmin();

        $this->actingAs($admin)->post('/sms/daily-patrols', $this->payload(['department_id' => $this->psd->id, 'pic' => 'Hartono, Orang Luar']))
            ->assertSessionHasErrors(['pic' => 'Karyawan tidak ditemukan: Orang Luar.']);
        $this->assertSame(0, DailyPatrol::count());

        // Spasi berlebih & nama dobel dirapikan.
        $this->actingAs($admin)->post('/sms/daily-patrols', $this->payload(['department_id' => $this->psd->id, 'pic' => ' Dedi ,Hartono, Dedi ', 'pic_patrol' => 'Miftari']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Dedi, Hartono', DailyPatrol::sole()->pic);
    }

    public function test_edit_keeps_old_free_text_pic(): void
    {
        $patrol = $this->patrol($this->psd, ['pic' => 'Tim Maintenance', 'pic_patrol' => null]);

        $this->actingAs($this->deptAdmin($this->psd))->put("/sms/daily-patrols/{$patrol->id}", $this->payload([
            'foto' => null, 'status' => 'open', 'pic' => 'Tim Maintenance, Dedi', 'pic_patrol' => 'Yayan R',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Tim Maintenance, Dedi', $patrol->fresh()->pic);
        $this->assertSame('Yayan R', $patrol->fresh()->pic_patrol);
    }

    public function test_form_lists_karyawan_options(): void
    {
        $this->actingAs($this->deptAdmin($this->psd))->get('/sms/daily-patrols')
            ->assertOk()
            ->assertSee('data-karyawan-picker', false)
            ->assertSee('Aminudin');
    }

    public function test_cannot_close_without_repair_photo(): void
    {
        $patrol = $this->patrol($this->psd);

        $this->actingAs($this->deptAdmin($this->psd))
            ->put("/sms/daily-patrols/{$patrol->id}", $this->payload(['foto' => null, 'status' => 'closed']))
            ->assertSessionHasErrors('foto_perbaikan');

        $this->assertSame('open', $patrol->fresh()->status);
    }

    public function test_upload_repair_photo_and_close(): void
    {
        $patrol = $this->patrol($this->psd);

        $this->actingAs($this->deptAdmin($this->psd))
            ->put("/sms/daily-patrols/{$patrol->id}", $this->payload([
                'foto' => null,
                'foto_perbaikan' => UploadedFile::fake()->image('perbaikan.jpg'),
                'status' => 'closed',
            ]))
            ->assertSessionHasNoErrors();

        $patrol->refresh();
        $this->assertSame('closed', $patrol->status);
        $this->assertNotNull($patrol->closed_at);
        $this->assertSame('daily-patrol/a.jpg', $patrol->foto); // foto temuan tidak diganti
        Storage::disk('public')->assertExists($patrol->foto_perbaikan);

        // Dibuka lagi: closed_at dikosongkan.
        $this->actingAs($this->deptAdmin($this->psd))
            ->put("/sms/daily-patrols/{$patrol->id}", $this->payload(['foto' => null, 'status' => 'open']))
            ->assertSessionHasNoErrors();
        $this->assertNull($patrol->fresh()->closed_at);
    }

    public function test_department_admin_cannot_touch_other_department(): void
    {
        $patrol = $this->patrol($this->pwd);
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->put("/sms/daily-patrols/{$patrol->id}", $this->payload(['foto' => null, 'status' => 'open']))->assertForbidden();
        $this->actingAs($admin)->delete("/sms/daily-patrols/{$patrol->id}")->assertForbidden();
        $this->assertModelExists($patrol);
    }

    public function test_sms_admin_sees_all_and_must_choose_area(): void
    {
        $this->patrol($this->psd);
        $this->patrol($this->pwd);
        $admin = $this->smsAdmin();

        $this->actingAs($admin)->get('/sms/daily-patrols')->assertOk()->assertSee('Temuan PSD')->assertSee('Temuan PWD');
        $this->actingAs($admin)->get('/sms/daily-patrols?department='.$this->pwd->id)->assertDontSee('Temuan PSD');

        $this->actingAs($admin)->post('/sms/daily-patrols', $this->payload())->assertSessionHasErrors('department_id');
        $this->actingAs($admin)->post('/sms/daily-patrols', $this->payload(['department_id' => $this->pwd->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, DailyPatrol::where('department_id', $this->pwd->id)->where('finding', 'like', 'Tutup%')->count());
    }

    public function test_dashboard_daily_card_charts_open_and_close_for_last_seven_days(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');

        $this->patrol($this->psd, ['tanggal' => '2026-10-22']);
        $this->patrol($this->pwd, ['tanggal' => '2026-10-22', 'status' => 'closed']);
        $this->patrol($this->psd, ['tanggal' => '2026-10-21']);
        $this->patrol($this->psd, ['tanggal' => '2026-10-15']); // 8 hari lalu: di luar grafik

        $response = $this->actingAs($this->smsAdmin())->get('/sms')->assertOk()
            ->assertDontSee('<h2 class="card-title">Pertumbuhan</h2>', false)
            ->assertDontSee('<h2 class="card-title">Jam Kerja</h2>', false)
            ->assertSeeInOrder(['Daily Patrol', '7 hari terakhir', 'Hari ini <b>2</b> temuan'], false);

        $chart = $response->viewData('dailyHariIni');
        $this->assertSame([0, 0, 0, 0, 0, 1, 1], array_column($chart['bars'], 'open'));
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], array_column($chart['bars'], 'close'));
        $this->assertSame([
            ['department' => 'PSD', 'values' => [0, 0, 0, 0, 0, 1, 1], 'total' => 2],
            ['department' => 'PWD', 'values' => [0, 0, 0, 0, 0, 0, 1], 'total' => 1],
        ], $chart['departments']);

        Carbon::setTestNow();
    }

    public function test_dashboard_cross_chart_counts_per_month_and_department(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');

        $cross = fn (Department $department, string $tanggal, string $status = 'open') => CrossPatrol::create([
            'tanggal' => $tanggal, 'plant' => 'Plant 1', 'zona' => 1, 'finding' => 'Temuan', 'foto' => 'cross-patrol/a.jpg',
            'efek' => '2R', 'resiko' => 'C', 'area' => 'OBEYA', 'department_id' => $department->id, 'rekomendasi' => 'Perbaiki',
            'target' => '2026-10-30', 'pic' => 'Dedi', 'kategori' => '5r', 'patroler' => 'Miftari', 'status' => $status,
        ]);
        $cross($this->psd, '2026-10-01');
        $cross($this->psd, '2026-10-31', 'closed');
        $cross($this->pwd, '2026-10-15', 'closed');
        $cross($this->pwd, '2026-01-05');
        $cross($this->psd, '2025-12-31');

        $chart = GrafikCrossPatrol::build(Carbon::today());

        $this->assertSame([2026, 2025], $chart['years']);
        $this->assertSame(2026, $chart['selected']);
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0], $chart['data'][2026]['open']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0], $chart['data'][2026]['close']);
        $this->assertSame([
            ['department' => 'PSD', 'values' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0], 'total' => 2],
            ['department' => 'PWD', 'values' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0], 'total' => 2],
        ], $chart['data'][2026]['departments']);
        $this->assertSame([['department' => 'PSD', 'values' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1], 'total' => 1]], $chart['data'][2025]['departments']);

        $this->actingAs($this->deptAdmin($this->psd))->get('/sms')->assertOk()
            ->assertSee('data-stack-years=', false)
            ->assertSeeInOrder(['Cross Patrol', 'Temuan per bulan', '<option value="2026" selected>2026</option>'], false);

        Carbon::setTestNow();
    }

    public function test_dashboard_for_department_admin_still_shows_all_departments(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');

        $this->patrol($this->psd, ['tanggal' => '2026-10-22']);
        $this->patrol($this->pwd, ['tanggal' => '2026-10-22']);

        // Daftar Daily Patrol dibatasi departemennya, tapi dashboard tetap seluruh perusahaan.
        $this->actingAs($this->deptAdmin($this->psd))->get('/sms')->assertOk()
            ->assertViewHas('dailyHariIni', fn (array $daily) => $daily['total'] === 2
                && array_column($daily['departments'], 'department') === ['PSD', 'PWD']);

        Carbon::setTestNow();
    }

    public function test_dashboard_chart_counts_findings_per_department_and_year(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->patrol($this->psd);
        $this->patrol($this->psd, ['status' => 'closed']);
        $this->patrol($this->pwd, ['status' => 'closed']);
        $this->patrol($this->pwd, ['tanggal' => '2025-05-01']);

        $chart = GrafikTemuanPatrol::build(Carbon::today());

        $this->assertSame([2026, 2025], $chart['years']);
        $this->assertSame(2026, $chart['selected']);
        $this->assertSame([
            ['department' => 'PSD', 'total' => 2, 'open' => 1, 'close' => 1],
            ['department' => 'PWD', 'total' => 1, 'open' => 0, 'close' => 1],
        ], $chart['data'][2026]);
        $this->assertSame([['department' => 'PWD', 'total' => 1, 'open' => 1, 'close' => 0]], $chart['data'][2025]);

        // Kartu "User Terbaru" diganti grafik temuan.
        $this->actingAs($this->smsAdmin())->get('/sms')->assertOk()
            ->assertSee('Temuan Daily Patrol')
            ->assertSee('data-temuan=', false)
            ->assertDontSee('User Terbaru');

        Carbon::setTestNow();
    }

    public function test_delete_removes_photos(): void
    {
        Storage::disk('public')->put('daily-patrol/a.jpg', 'x');
        Storage::disk('public')->put('daily-patrol/b.jpg', 'x');
        $patrol = $this->patrol($this->psd, ['foto_perbaikan' => 'daily-patrol/b.jpg', 'status' => 'closed']);

        $this->actingAs($this->smsAdmin())->delete("/sms/daily-patrols/{$patrol->id}")->assertRedirect();

        $this->assertModelMissing($patrol);
        Storage::disk('public')->assertMissing('daily-patrol/a.jpg');
        Storage::disk('public')->assertMissing('daily-patrol/b.jpg');
    }
}
