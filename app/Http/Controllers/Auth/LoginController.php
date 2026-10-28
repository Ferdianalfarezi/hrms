<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'nik' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ], [], [
            'nik' => 'NIK',
            'password' => 'PIN / Password',
        ]);

        $throttleKey = Str::lower($credentials['nik']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'nik' => 'Terlalu banyak percobaan login. Coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'nik' => 'NIK atau PIN/password salah, atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Dicatat tanpa mengubah updated_at supaya tidak dianggap edit data user.
        User::withoutTimestamps(fn () => $request->user()->forceFill(['last_login_at' => now()])->saveQuietly());

        // Penanda sekali pakai: halaman berikutnya memutar animasi pintu terbuka (lihat layouts.base).
        return redirect()->route('portal')->with('door', true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
