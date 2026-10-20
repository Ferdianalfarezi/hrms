<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
    }

    private function hrmsUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', $role);

        return $user;
    }

    public function test_department_admin_cannot_open_or_manage_master_departments(): void
    {
        $department = Department::create(['name' => 'Finance']);
        $staff = User::factory()->create();
        $staff->assignRole('hrms', 'dept_admin', $department->id);

        $this->actingAs($staff)->get('/hrms/departments')->assertForbidden();
        $this->actingAs($staff)->get('/hrms/karyawans')->assertOk()->assertDontSee(route('hrms.departments.index'));

        $this->actingAs($staff)->post('/hrms/departments', ['name' => 'IT'])->assertForbidden();
        $this->actingAs($staff)->put("/hrms/departments/{$department->id}", ['name' => 'Ubah'])->assertForbidden();
        $this->actingAs($staff)->delete("/hrms/departments/{$department->id}")->assertForbidden();

        $this->assertDatabaseMissing('departments', ['name' => 'IT']);
        $this->assertModelExists($department);
    }

    public function test_user_without_hrms_access_is_blocked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sms', 'admin');

        $this->actingAs($user)->get('/hrms/departments')->assertForbidden();
    }

    public function test_hrms_admin_can_create_update_and_delete(): void
    {
        $admin = $this->hrmsUser('admin');

        $this->actingAs($admin)->post('/hrms/departments', ['name' => 'Finance'])
            ->assertRedirect(route('hrms.departments.index'));
        $department = Department::where('name', 'Finance')->firstOrFail();

        $this->actingAs($admin)->put("/hrms/departments/{$department->id}", ['name' => 'Finance & Accounting'])
            ->assertRedirect(route('hrms.departments.index'));
        $this->assertSame('Finance & Accounting', $department->refresh()->name);

        $this->actingAs($admin)->delete("/hrms/departments/{$department->id}")
            ->assertRedirect(route('hrms.departments.index'));
        $this->assertModelMissing($department);
    }

    public function test_name_is_required_and_unique(): void
    {
        $admin = $this->hrmsUser('admin');
        $existing = Department::create(['name' => 'Finance']);

        $this->actingAs($admin)->post('/hrms/departments', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/hrms/departments', ['name' => 'Finance'])->assertSessionHasErrors('name');

        // Simpan ulang dengan nama sendiri tetap boleh.
        $this->actingAs($admin)->put("/hrms/departments/{$existing->id}", ['name' => 'Finance'])->assertSessionHasNoErrors();
    }

    public function test_failed_validation_redirects_back_with_modal_input(): void
    {
        $admin = $this->hrmsUser('admin');
        Department::create(['name' => 'Finance']);

        $this->actingAs($admin)
            ->from('/hrms/departments')
            ->post('/hrms/departments', ['name' => 'Finance', '_modal' => 'create'])
            ->assertRedirect('/hrms/departments')
            ->assertSessionHasErrors(['name' => 'Departemen dengan nama ini sudah ada.'])
            ->assertSessionHasInput('_modal', 'create');
    }

    public function test_list_reopens_modal_when_there_are_validation_errors(): void
    {
        $admin = $this->hrmsUser('admin');

        // Session diisi langsung dengan bentuk error bag versi serialisasi JSON,
        // karena di test session tidak dimuat ulang dari handler antar request.
        $errors = ['default' => ['messages' => ['name' => ['Departemen dengan nama ini sudah ada.']], 'format' => ':message']];

        $this->actingAs($admin)
            ->withSession(['errors' => $errors, '_old_input' => ['name' => 'Finance', '_modal' => 'create']])
            ->get('/hrms/departments')
            ->assertOk()
            ->assertSee("var reopen = 'create';", false)
            ->assertSee('Departemen dengan nama ini sudah ada.');
    }

    public function test_list_does_not_open_modal_without_errors(): void
    {
        $this->actingAs($this->hrmsUser('admin'))
            ->get('/hrms/departments')
            ->assertSee('var reopen = null;', false);
    }

    public function test_list_can_be_searched_and_shows_module_tabs(): void
    {
        Department::create(['name' => 'Finance']);
        Department::create(['name' => 'Marketing']);

        $this->actingAs($this->hrmsUser('admin'))->get('/hrms/departments?q=fin')
            ->assertOk()
            ->assertSee('Finance')
            ->assertDontSee('Marketing')
            ->assertSee(route('hrms.dashboard'))
            ->assertSee('Tambah Departemen');
    }
}
