<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAdminTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    private Karyawan $andi;

    private Karyawan $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
        $this->andi = $this->karyawan('0001', 'Andi PSD', $this->psd);
        $this->budi = $this->karyawan('0002', 'Budi PWD', $this->pwd);
    }

    private function karyawan(string $nik, string $nama, ?Department $department): Karyawan
    {
        return Karyawan::create(['nik' => $nik, 'nama' => $nama, 'department_id' => $department?->id,
            'jenis_kelamin' => 'Laki-laki', 'status' => 'Tetap']);
    }

    private function deptAdmin(?Department $department): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'dept_admin', $department?->id);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [...['nik' => '0099', 'nama' => 'Baru', 'jenis_kelamin' => 'Laki-laki', 'status' => 'PKWT 1'], ...$overrides];
    }

    public function test_department_admin_only_sees_own_department(): void
    {
        $this->karyawan('0003', 'Tanpa Dept', null);

        $this->actingAs($this->deptAdmin($this->psd))->get('/hrms/karyawans')
            ->assertOk()
            ->assertSee('Andi PSD')
            ->assertDontSee('Budi PWD')
            ->assertDontSee('Tanpa Dept')
            ->assertSee('1 karyawan')
            ->assertDontSee('Import Excel')
            ->assertDontSee('Semua departemen');
    }

    public function test_department_filter_in_url_cannot_widen_scope(): void
    {
        $this->actingAs($this->deptAdmin($this->psd))->get("/hrms/karyawans?department={$this->pwd->id}")
            ->assertSee('Andi PSD')
            ->assertDontSee('Budi PWD');

        $this->actingAs($this->deptAdmin($this->psd))->get('/hrms/karyawans?q=Budi')
            ->assertDontSee('Budi PWD');
    }

    public function test_department_admin_can_manage_own_department_only(): void
    {
        $admin = $this->deptAdmin($this->psd);

        // Edit karyawan sendiri: boleh, tapi departemen tidak bisa dipindah.
        $this->actingAs($admin)->from('/hrms/karyawans')
            ->put("/hrms/karyawans/{$this->andi->id}", $this->payload(['nik' => '0001', 'nama' => 'Andi Baru', 'department_id' => $this->pwd->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Andi Baru', $this->andi->refresh()->nama);
        $this->assertSame($this->psd->id, $this->andi->department_id);

        // Karyawan departemen lain: ditolak.
        $this->actingAs($admin)->put("/hrms/karyawans/{$this->budi->id}", $this->payload(['nik' => '0002']))->assertForbidden();
        $this->actingAs($admin)->delete("/hrms/karyawans/{$this->budi->id}")->assertForbidden();
        $this->assertModelExists($this->budi);

        // Tambah karyawan: selalu masuk ke departemennya sendiri.
        $this->actingAs($admin)->from('/hrms/karyawans')
            ->post('/hrms/karyawans', $this->payload(['department_id' => $this->pwd->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->psd->id, Karyawan::where('nik', '0099')->value('department_id'));

        // Hapus karyawan sendiri: boleh.
        $this->actingAs($admin)->from('/hrms/karyawans')->delete("/hrms/karyawans/{$this->andi->id}");
        $this->assertModelMissing($this->andi);
    }

    public function test_department_admin_without_department_sees_nothing_and_cannot_create(): void
    {
        $admin = $this->deptAdmin(null);

        $this->actingAs($admin)->get('/hrms/karyawans')
            ->assertOk()
            ->assertSee('belum dipilihkan departemen')
            ->assertDontSee('Andi PSD')
            ->assertDontSee('Tambah Karyawan');

        $this->actingAs($admin)->post('/hrms/karyawans', $this->payload())->assertForbidden();
    }

    public function test_hrms_admin_sees_everything(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        $this->actingAs($admin)->get('/hrms/karyawans')
            ->assertSee('Andi PSD')
            ->assertSee('Budi PWD')
            ->assertSee('Import Excel')
            ->assertSee(route('hrms.departments.index'));
    }

    public function test_superadmin_can_open_every_module_without_roles(): void
    {
        $super = User::factory()->superadmin()->create();

        $this->assertSame(['hrms', 'sms'], $super->accessibleApplications()->pluck('code')->all());
        $this->actingAs($super)->get('/hrms/karyawans')->assertOk()->assertSee('Budi PWD')->assertSee('Import Excel');
        $this->actingAs($super)->get('/sms')->assertOk();
        $this->actingAs($super)->get('/hrms/departments')->assertOk();
        $this->actingAs($super)->get('/')->assertOk()->assertSee('Super Admin');
    }

    public function test_superadmin_assigns_department_admin_in_user_form(): void
    {
        $super = User::factory()->superadmin()->create();
        $hrms = Application::where('code', 'hrms')->firstOrFail();
        $deptAdminRole = $hrms->roles()->where('code', 'dept_admin')->value('id');
        $base = ['nik' => '7001', 'name' => 'Admin PSD', 'password' => 'rahasia1', 'password_confirmation' => 'rahasia1'];

        // Departemen wajib dipilih untuk Admin Departemen.
        $this->actingAs($super)->post('/admin/users', [...$base, 'access' => [$hrms->id => $deptAdminRole]])
            ->assertSessionHasErrors("department.{$hrms->id}");

        // Bisa lebih dari satu departemen.
        $this->actingAs($super)->post('/admin/users', [...$base,
            'access' => [$hrms->id => $deptAdminRole],
            'department' => [$hrms->id => [$this->psd->id, $this->pwd->id]],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('nik', '7001')->firstOrFail();
        $this->assertSame([$this->psd->id, $this->pwd->id], $user->managedDepartmentIds());
        $this->assertSame('Admin Departemen · PSD, PWD', $user->roleLabel('hrms'));

        $this->actingAs($super)->get('/admin/users')->assertSee('(PSD, PWD)');
        $form = $this->actingAs($super)->get("/admin/users/{$user->id}/edit")->assertSee('Departemen yang dikelola')->getContent();
        foreach ([$this->psd->id, $this->pwd->id] as $departmentId) {
            $this->assertMatchesRegularExpression('/name="department\['.$hrms->id.'\]\[\]" value="'.$departmentId.'"\s+checked/', $form);
        }

        // Edit: kurangi jadi satu departemen.
        $this->actingAs($super)->put("/admin/users/{$user->id}", [
            'nik' => $user->nik, 'name' => $user->name,
            'access' => [$hrms->id => $deptAdminRole],
            'department' => [$hrms->id => [$this->pwd->id]],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$this->pwd->id], $user->refresh()->managedDepartmentIds());
    }

    public function test_department_admin_with_several_departments_sees_combined_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'dept_admin', [$this->psd->id, $this->pwd->id]);

        // Gabungan dua departemen, plus filter yang hanya berisi departemennya.
        $this->actingAs($admin)->get('/hrms/karyawans')->assertOk()
            ->assertSee('Andi PSD')
            ->assertSee('Budi PWD')
            ->assertDontSee('Tanpa Dept')
            ->assertSee('2 karyawan')
            ->assertSee('Semua departemen')
            ->assertDontSee('Tanpa departemen (');
        $this->actingAs($admin)->get("/hrms/karyawans?department={$this->pwd->id}")
            ->assertSee('Budi PWD')
            ->assertDontSee('Andi PSD');

        // Tambah karyawan: wajib memilih salah satu departemennya.
        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', $this->payload(['department_id' => null]))
            ->assertSessionHasErrors('department_id');
        $other = Department::create(['name' => 'QA']);
        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', $this->payload(['department_id' => $other->id]))
            ->assertSessionHasErrors('department_id');
        $this->actingAs($admin)->from('/hrms/karyawans')->post('/hrms/karyawans', $this->payload(['department_id' => $this->pwd->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->pwd->id, Karyawan::where('nik', '0099')->value('department_id'));

        // Bisa mengedit karyawan di kedua departemennya.
        $this->actingAs($admin)->from('/hrms/karyawans')
            ->put("/hrms/karyawans/{$this->budi->id}", $this->payload(['nik' => '0002', 'nama' => 'Budi Baru', 'department_id' => $this->pwd->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Budi Baru', $this->budi->refresh()->nama);
    }

    public function test_role_change_to_hrms_admin_clears_department(): void
    {
        $super = User::factory()->superadmin()->create();
        $user = $this->deptAdmin($this->psd);
        $hrms = Application::where('code', 'hrms')->firstOrFail();

        $this->actingAs($super)->put("/admin/users/{$user->id}", [
            'nik' => $user->nik, 'name' => $user->name,
            'access' => [$hrms->id => $hrms->roles()->where('code', 'admin')->value('id')],
            'department' => [$hrms->id => [$this->psd->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $user->refresh()->managedDepartmentIds());
        $this->assertSame(0, $user->managedDepartments()->count());
        $this->assertTrue($user->isModuleAdmin('hrms'));
    }
}
