<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
        $this->admin = User::factory()->superadmin()->create(['nik' => '1001']);
    }

    private function roleId(string $application, string $role): int
    {
        return Application::where('code', $application)->firstOrFail()
            ->roles()->where('code', $role)->value('id');
    }

    private function appId(string $application): int
    {
        return Application::where('code', $application)->value('id');
    }

    public function test_non_superadmin_cannot_manage_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('hrms', 'admin');

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->post('/admin/users', [])->assertForbidden();
    }

    public function test_superadmin_always_sees_portal_with_manage_menu(): void
    {
        $this->admin->assignRole('hrms', 'admin');

        $this->actingAs($this->admin)->get('/')
            ->assertOk()
            ->assertSee('Kelola User');
    }

    public function test_superadmin_can_list_and_search_users(): void
    {
        User::factory()->create(['nik' => '2001', 'name' => 'Budi Santoso']);
        User::factory()->create(['nik' => '2002', 'name' => 'Siti Aminah']);

        $this->actingAs($this->admin)->get('/admin/users?q=budi')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_superadmin_can_create_user_with_access(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'nik' => '3001',
            'name' => 'User Baru',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
            'is_active' => '1',
            'access' => [
                $this->appId('hrms') => '',
                $this->appId('sms') => $this->roleId('sms', 'staff'),
            ],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('nik', '3001')->firstOrFail();

        $this->assertTrue(Hash::check('rahasia1', $user->password));
        $this->assertTrue($user->canAccessApplication('sms'));
        $this->assertFalse($user->canAccessApplication('hrms'));
        $this->assertSame('staff', $user->roleFor('sms')->code);
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'nik' => '1001',
            'name' => 'Dobel',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
        ])->assertSessionHasErrors('nik');
    }

    public function test_role_from_another_module_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'nik' => '3002',
            'name' => 'Salah Role',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
            'access' => [$this->appId('hrms') => $this->roleId('sms', 'admin')],
        ])->assertSessionHasErrors('access');

        $this->assertDatabaseMissing('users', ['nik' => '3002']);
    }

    public function test_superadmin_can_update_access_and_keep_password(): void
    {
        $user = User::factory()->create(['nik' => '4001']);
        $user->assignRole('hrms', 'admin');
        $oldPassword = $user->password;

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'nik' => '4001',
            'name' => 'Nama Baru',
            'password' => '',
            'is_active' => '0',
            'access' => [
                $this->appId('hrms') => '',
                $this->appId('sms') => $this->roleId('sms', 'admin'),
            ],
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertSame($oldPassword, $user->password);
        $this->assertFalse($user->canAccessApplication('hrms'));
        $this->assertSame('admin', $user->roleFor('sms')->code);
    }

    public function test_superadmin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", [
            'nik' => '1001',
            'name' => 'Admin',
            'is_active' => '0',
            'is_superadmin' => '0',
        ]);

        $this->admin->refresh();

        $this->assertTrue($this->admin->is_active);
        $this->assertTrue($this->admin->is_superadmin);
    }

    public function test_superadmin_can_delete_others_but_not_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->delete("/admin/users/{$user->id}")
            ->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($user);

        $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")
            ->assertSessionHasErrors('user');
        $this->assertModelExists($this->admin);
    }

    public function test_photo_is_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/admin/users', [
            'nik' => '5001',
            'name' => 'Pakai Foto',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
            'photo' => UploadedFile::fake()->image('foto.jpg', 300, 300),
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('nik', '5001')->firstOrFail();
        $firstPhoto = $user->photo;
        Storage::disk('public')->assertExists($firstPhoto);
        $this->assertStringContainsString($firstPhoto, $user->photo_url);

        // Ganti foto: file lama ikut terhapus.
        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'nik' => '5001',
            'name' => 'Pakai Foto',
            'photo' => UploadedFile::fake()->image('baru.png'),
        ]);
        $secondPhoto = $user->refresh()->photo;
        Storage::disk('public')->assertMissing($firstPhoto);
        Storage::disk('public')->assertExists($secondPhoto);

        // Hapus foto: kembali ke avatar default.
        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'nik' => '5001',
            'name' => 'Pakai Foto',
            'remove_photo' => '1',
        ]);
        $this->assertNull($user->refresh()->photo);
        Storage::disk('public')->assertMissing($secondPhoto);
        $this->assertStringEndsWith('images/default-avatar.svg', $user->photo_url);
    }

    public function test_non_image_photo_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'nik' => '5002',
            'name' => 'Salah File',
            'password' => 'rahasia1',
            'password_confirmation' => 'rahasia1',
            'photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_dashboard_renders_with_tabs(): void
    {
        $this->admin->assignRole('hrms', 'admin');
        $this->admin->assignRole('sms', 'admin');
        User::factory()->create(['name' => 'Rekan Baru']);

        // Dashboard SMS berbasis akun login; dashboard HRMS berbasis data karyawan (lihat HrmsDashboardTest).
        $this->actingAs($this->admin)->get('/sms')
            ->assertOk()
            ->assertSee('Selamat datang')
            ->assertSee('Rekan Baru')
            ->assertSee(route('hrms.dashboard'))
            ->assertSee(route('admin.users.index'));
    }

    public function test_create_and_edit_forms_render(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/create')->assertOk()->assertSee('Hak Akses Modul');
        $this->actingAs($this->admin)->get("/admin/users/{$this->admin->id}/edit")->assertOk()->assertSee('1001');
    }
}
