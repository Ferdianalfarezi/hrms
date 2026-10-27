<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\Kecelakaan;
use App\Modules\Sms\Support\GrafikKecelakaan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KecelakaanTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
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
            'tipe' => 'traffic',
            'kategori' => 'ditabrak',
            'waktu' => '2026-10-20T07:15',
            'nama_korban' => 'Budi Santoso',
            'deskripsi' => 'Ditabrak motor di depan gerbang saat berangkat kerja.',
            ...$overrides,
        ];
    }

    private function kecelakaan(Department $department, array $overrides = []): Kecelakaan
    {
        return Kecelakaan::create([
            'tipe' => 'work', 'waktu' => '2026-10-20 08:00', 'nama_korban' => 'Korban '.$department->name,
            'department_id' => $department->id, 'deskripsi' => 'Tergores', ...$overrides,
        ]);
    }

    public function test_menu_and_list_only_show_own_department(): void
    {
        $this->kecelakaan($this->psd);
        $this->kecelakaan($this->pwd);
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->get('/sms')->assertSee(route('sms.kecelakaan.index'), false);
        $this->actingAs($admin)->get('/sms/kecelakaan')
            ->assertOk()
            ->assertSee('Korban PSD')
            ->assertDontSee('Korban PWD');
    }

    public function test_traffic_accident_requires_kategori(): void
    {
        $admin = $this->smsAdmin();

        $this->actingAs($admin)->post('/sms/kecelakaan', $this->payload(['department_id' => $this->psd->id, 'kategori' => null]))->assertSessionHasErrors('kategori');
        $this->actingAs($admin)->post('/sms/kecelakaan', $this->payload(['department_id' => $this->psd->id]))->assertSessionHasNoErrors();

        $kecelakaan = Kecelakaan::sole();
        $this->assertSame('ditabrak', $kecelakaan->kategori);
        $this->assertSame($this->psd->id, $kecelakaan->department_id);
        $this->assertSame('2026-10-20 07:15', $kecelakaan->waktu->format('Y-m-d H:i'));
    }

    public function test_work_accident_ignores_kategori(): void
    {
        $this->actingAs($this->smsAdmin())
            ->post('/sms/kecelakaan', $this->payload(['department_id' => $this->psd->id, 'tipe' => 'work', 'kategori' => 'menabrak']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Kecelakaan::sole()->kategori);
    }

    public function test_department_admin_can_only_edit_own_department(): void
    {
        $kecelakaan = $this->kecelakaan($this->psd);
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->get('/sms/kecelakaan')->assertOk()
            ->assertDontSee('+ Tambah')
            ->assertDontSee('title="Hapus"', false)
            ->assertSee('data-edit="'.$kecelakaan->id.'"', false);
        $this->actingAs($admin)->post('/sms/kecelakaan', $this->payload())->assertForbidden();
        $this->actingAs($admin)->delete("/sms/kecelakaan/{$kecelakaan->id}")->assertForbidden();
        $this->assertSame(1, Kecelakaan::count());

        $this->actingAs($admin)->put("/sms/kecelakaan/{$kecelakaan->id}", $this->payload(['nama_korban' => 'Nama Diedit']))->assertSessionHasNoErrors();
        $this->assertSame('Nama Diedit', $kecelakaan->fresh()->nama_korban);
    }

    public function test_cannot_edit_or_delete_other_department(): void
    {
        $kecelakaan = $this->kecelakaan($this->pwd);
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->put("/sms/kecelakaan/{$kecelakaan->id}", $this->payload())->assertForbidden();
        $this->actingAs($admin)->delete("/sms/kecelakaan/{$kecelakaan->id}")->assertForbidden();
        $this->assertModelExists($kecelakaan);
    }

    public function test_dashboard_shows_days_since_last_accident(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $staff = User::factory()->create();
        $staff->assignRole('sms', 'staff');

        // Kartu "Status Akun" diganti, bukan kartu baru.
        $this->actingAs($staff)->get('/sms')->assertOk()
            ->assertSee('Hari Tanpa Kecelakaan')
            ->assertDontSee('<h2 class="card-title">Status Akun</h2>', false)
            ->assertSee('Belum ada kecelakaan yang tercatat.');

        // Acuan = kecelakaan paling akhir (seluruh perusahaan): 15 Okt -> 6 hari.
        $this->kecelakaan($this->pwd, ['waktu' => '2026-10-01 08:00', 'tipe' => 'traffic', 'kategori' => 'menabrak']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-10-15 23:30']);

        $this->actingAs($staff)->get('/sms')
            ->assertSee('<b data-count>6 hari</b>', false)
            ->assertSee('Kecelakaan terakhir 15 Oktober 2026')
            ->assertSee('Tanpa Traffic Accident')
            ->assertSee('20 hari'); // traffic terakhir 1 Okt

        Carbon::setTestNow();
    }

    public function test_dashboard_charts_replace_profile_and_calendar_cards(): void
    {
        Carbon::setTestNow('2026-03-10 10:00:00');
        $staff = User::factory()->create();
        $staff->assignRole('sms', 'staff');

        $this->kecelakaan($this->psd, ['waktu' => '2026-02-03 08:00']);                                          // work Feb 2026
        $this->kecelakaan($this->psd, ['waktu' => '2025-08-11 08:00']);                                          // work Agu 2025
        $this->kecelakaan($this->psd, ['waktu' => '2026-01-20 08:00', 'tipe' => 'traffic', 'kategori' => 'tunggal']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-03-01 08:00', 'tipe' => 'traffic', 'kategori' => 'tunggal']);

        $charts = GrafikKecelakaan::build(Carbon::today());
        $work = collect($charts['work']['series'])->keyBy('label');
        $traffic = collect($charts['traffic']['series'])->keyBy('label');

        $this->assertSame([0, 1, 0, null, null, null, null, null, null, null, null, null], $work['Accident 2026']['values']);
        $this->assertSame([0, 1, 1, null, null, null, null, null, null, null, null, null], $work['Acc.Accident 2026']['values']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1], $work['Acc.Accident 2025']['values']);
        $this->assertSame([1, 1, 2], array_slice($traffic['Kecelakaan Tunggal']['values'], 0, 3));
        $this->assertSame([0, 0, 0], array_slice($traffic['Menabrak']['values'], 0, 3));
        $this->assertSame(array_fill(0, 12, 0), $traffic['Target']['values']);

        $this->actingAs($staff)->get('/sms')->assertOk()
            ->assertSee('Work Accident')
            ->assertSee('Traffic Accident')
            ->assertSee('data-chart=', false)
            ->assertSee('data-chart-expand="work"', false)
            ->assertSee('id="chart-full-traffic"', false)
            ->assertDontSee('<summary>Data Diri</summary>', false)
            ->assertDontSee('Akun karyawan yang dibuat minggu ini.');

        // Dashboard HRMS tetap memakai kartu bawaan.
        Carbon::setTestNow();
    }

    public function test_update_switching_to_work_clears_kategori(): void
    {
        $kecelakaan = $this->kecelakaan($this->psd, ['tipe' => 'traffic', 'kategori' => 'tabrakan']);

        $this->actingAs($this->deptAdmin($this->psd))
            ->put("/sms/kecelakaan/{$kecelakaan->id}", $this->payload(['tipe' => 'work']))
            ->assertSessionHasNoErrors();

        $this->assertSame('work', $kecelakaan->fresh()->tipe);
        $this->assertNull($kecelakaan->fresh()->kategori);
    }
}
