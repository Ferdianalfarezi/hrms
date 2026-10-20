<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
    }

    private function userWithAccess(array $access, array $attributes = []): User
    {
        $user = User::factory()->create(['nik' => '1234', ...$attributes]);

        foreach ($access as $application => $role) {
            $user->assignRole($application, $role);
        }

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/hrms')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('NIK');
    }

    public function test_user_can_login_with_nik_and_password(): void
    {
        $user = $this->userWithAccess(['hrms' => 'admin']);

        $this->post('/login', ['nik' => '1234', 'password' => 'password'])
            ->assertRedirect(route('portal'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->userWithAccess(['hrms' => 'admin']);

        $this->from('/login')->post('/login', ['nik' => '1234', 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('nik');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->userWithAccess(['hrms' => 'admin'], ['is_active' => false]);

        $this->post('/login', ['nik' => '1234', 'password' => 'password'])
            ->assertSessionHasErrors('nik');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $this->userWithAccess(['hrms' => 'admin']);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['nik' => '1234', 'password' => 'salah']);
        }

        $this->post('/login', ['nik' => '1234', 'password' => 'password'])
            ->assertSessionHasErrors('nik');

        $this->assertGuest();
    }

    public function test_user_with_single_module_goes_straight_to_it(): void
    {
        $this->actingAs($this->userWithAccess(['sms' => 'staff']))
            ->get('/')
            ->assertRedirect(route('sms.dashboard'));
    }

    public function test_user_with_multiple_modules_sees_portal(): void
    {
        $this->actingAs($this->userWithAccess(['hrms' => 'admin', 'sms' => 'staff']))
            ->get('/')
            ->assertOk()
            ->assertSee('HRMS')
            ->assertSee('SMS');
    }

    public function test_user_without_access_is_logged_out(): void
    {
        $this->actingAs($this->userWithAccess([]))
            ->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('nik');

        $this->assertGuest();
    }

    public function test_module_access_is_enforced(): void
    {
        $user = $this->userWithAccess(['hrms' => 'admin']);

        $this->actingAs($user)->get('/hrms')->assertOk()->assertSee('Admin HRMS');
        $this->actingAs($user)->get('/sms')->assertForbidden();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs($this->userWithAccess(['hrms' => 'admin']))
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
