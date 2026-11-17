<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationSeeder::class);
    }

    public function test_choosing_a_language_stores_it_in_a_cookie_and_returns_to_the_previous_page(): void
    {
        $this->from(route('login'))
            ->get(route('locale.switch', 'ja'))
            ->assertRedirect(route('login'))
            ->assertCookie(SetLocale::COOKIE, 'ja');
    }

    public function test_unknown_language_is_rejected(): void
    {
        $this->get('/locale/en')->assertNotFound()->assertCookieMissing(SetLocale::COOKIE);
    }

    public function test_pages_are_indonesian_by_default_and_japanese_after_choosing_jp(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Gunakan NIK karyawan dan PIN/password Anda.');

        $this->withCookie(SetLocale::COOKIE, 'ja')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('従業員NIKとPIN/パスワードを入力してください。')
            ->assertDontSee('Gunakan NIK karyawan dan PIN/password Anda.');
    }

    public function test_cookie_with_unsupported_language_falls_back_to_indonesian(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'xx')
            ->get(route('login'))
            ->assertSee('<html lang="id">', false);
    }

    public function test_server_messages_follow_the_chosen_language(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hrms', 'admin');

        $this->actingAs($admin)
            ->withCookie(SetLocale::COOKIE, 'ja')
            ->post(route('hrms.departments.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => '部署名は必須です。']);

        $this->actingAs($admin)
            ->withCookie(SetLocale::COOKIE, 'ja')
            ->get(route('hrms.departments.index'))
            ->assertSee('部署を追加')
            // Kamus __t() untuk JavaScript ikut dikirim dalam bahasa yang dipilih.
            // (Js::from menulis tanda kutip sebagai ".)
            ->assertSee(str_replace('"', chr(92).'u0022', '"Tambah Departemen":"部署を追加"'), false);
    }
}
