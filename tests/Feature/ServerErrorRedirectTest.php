<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ServerErrorRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/halaman-rusak', fn () => throw new RuntimeException('query gagal'));
        Route::middleware('web')->post('/_test/halaman-rusak', fn () => throw new RuntimeException('query gagal'));
    }

    public function test_server_error_saat_pindah_menu_kembali_ke_halaman_sebelumnya(): void
    {
        $this->get('/_test/halaman-rusak', ['Referer' => url('/hrms')])
            ->assertRedirect(url('/hrms'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'dikembalikan ke halaman sebelumnya'))
            ->assertSessionHas('server_error_redirect', true);
    }

    public function test_halaman_sebelumnya_juga_error_diarahkan_ke_portal(): void
    {
        $this->withSession(['server_error_redirect' => true])
            ->get('/_test/halaman-rusak', ['Referer' => url('/hrms')])
            ->assertRedirect(route('portal'))
            ->assertSessionHas('server_error_redirect', true);
    }

    public function test_portal_sendiri_error_tampilkan_halaman_error_supaya_tidak_berputar(): void
    {
        Route::middleware('web')->get('/', fn () => throw new RuntimeException('query gagal'));

        $this->withSession(['server_error_redirect' => true])
            ->get('/', ['Referer' => url('/hrms')])
            ->assertStatus(500);
    }

    public function test_request_json_dan_submit_form_tetap_mendapat_error_500(): void
    {
        $this->getJson('/_test/halaman-rusak', ['Referer' => url('/hrms')])->assertStatus(500);
        $this->post('/_test/halaman-rusak', [], ['Referer' => url('/hrms')])->assertStatus(500);
    }

    public function test_dibuka_langsung_atau_refresh_diarahkan_ke_portal(): void
    {
        $this->get('/_test/halaman-rusak', ['Referer' => url('/_test/halaman-rusak')])
            ->assertRedirect(route('portal'));
    }
}
