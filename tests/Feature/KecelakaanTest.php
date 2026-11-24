<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\Kecelakaan;
use App\Modules\Sms\Support\GrafikKecelakaan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        // Hari tanpa kecelakaan tampil sebagai angka besar di header, detail terakhir di tooltip.
        $this->actingAs($staff)->get('/sms')->assertOk()
            ->assertDontSee('<h2 class="card-title">Status Akun</h2>', false)
            ->assertDontSee('Hari Tanpa Kecelakaan')
            ->assertSeeInOrder([
                'title="Belum ada Work Accident yang tercatat."', 'Hari Tanpa Work Accident', '<div class="num">–</div>',
                'title="Belum ada Traffic Accident yang tercatat."', 'Hari Tanpa Traffic Accident', '<div class="num">–</div>',
            ], false);

        // Tiap kartu dihitung dari kecelakaan terakhir tipenya (seluruh perusahaan).
        $this->kecelakaan($this->pwd, ['waktu' => '2026-10-01 08:00', 'tipe' => 'traffic', 'kategori' => 'menabrak']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-10-15 23:30']);

        $this->actingAs($staff)->get('/sms')
            ->assertSeeInOrder([
                'title="Terakhir 15 Oktober 2026 · Korban PSD · PSD"', 'Hari Tanpa Work Accident', '<div class="num" data-count>6</div>',
                'title="Terakhir 01 Oktober 2026 · Korban PWD · PWD"', 'Hari Tanpa Traffic Accident', '<div class="num" data-count>20</div>',
            ], false);

        Carbon::setTestNow();
    }

    public function test_dashboard_bar_chart_counts_accidents_per_month_and_year(): void
    {
        Carbon::setTestNow('2026-03-10 10:00:00');
        $staff = User::factory()->create();
        $staff->assignRole('sms', 'staff');

        $this->kecelakaan($this->psd, ['waktu' => '2026-02-03 08:00']);
        $this->kecelakaan($this->psd, ['waktu' => '2025-08-11 08:00']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-01-20 08:00', 'tipe' => 'traffic', 'kategori' => 'tunggal']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-01-25 08:00', 'tipe' => 'traffic', 'kategori' => 'menabrak']);

        $chart = GrafikKecelakaan::build(Carbon::today());

        $this->assertSame([2026, 2025], $chart['years']);
        $this->assertSame([0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $chart['data'][2026]['work']);
        $this->assertSame([2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $chart['data'][2026]['traffic']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0], $chart['data'][2025]['work']);

        $this->actingAs($staff)->get('/sms')->assertOk()
            ->assertSee('data-kecelakaan=', false)
            ->assertSee('Detail per Bulan')
            ->assertDontSee('<summary>Data Diri</summary>', false)
            ->assertDontSee('Akun karyawan yang dibuat minggu ini.');

        Carbon::setTestNow();
    }

    public function test_kecelakaan_excel_export_contains_selected_year(): void
    {
        Carbon::setTestNow('2026-03-10 10:00:00');
        $staff = User::factory()->create();
        $staff->assignRole('sms', 'staff');
        $this->kecelakaan($this->psd, ['waktu' => '2026-02-03 08:00']);
        $this->kecelakaan($this->psd, ['waktu' => '2026-02-04 08:00', 'tipe' => 'traffic', 'kategori' => 'tunggal']);
        $this->kecelakaan($this->psd, ['waktu' => '2025-02-03 08:00']);

        $response = $this->actingAs($staff)->get('/sms/dashboard/export/kecelakaan?year=2026')->assertOk();
        $this->assertStringContainsString('Kecelakaan 2026.xlsx', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'kecelakaan');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(['Work Accident', 1, 1], [$sheet->getCell('A4')->getValue(), $sheet->getCell('C4')->getValue(), $sheet->getCell('N4')->getValue()]);
        $this->assertSame(['Traffic Accident', 1, 1], [$sheet->getCell('A5')->getValue(), $sheet->getCell('C5')->getValue(), $sheet->getCell('N5')->getValue()]);
        $this->assertSame(['Total', 2, 2], [$sheet->getCell('A6')->getValue(), $sheet->getCell('C6')->getValue(), $sheet->getCell('N6')->getValue()]);

        $this->actingAs($staff)->get('/sms/dashboard/export/kecelakaan?year=abc')->assertSessionHasErrors('year');

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
