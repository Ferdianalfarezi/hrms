<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalController extends Controller
{
    /**
     * Arahkan user ke modul sesuai haknya: 1 modul langsung masuk,
     * lebih dari 1 tampilkan pilihan, tidak ada akses langsung ditolak.
     * Super admin selalu melihat portal karena ada menu Kelola User.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $applications = $user->accessibleApplications();

        if ($user->is_superadmin) {
            return view('portal', ['applications' => $applications]);
        }

        if ($applications->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'nik' => 'Akun Anda belum memiliki akses ke sistem manapun. Hubungi admin.',
            ]);
        }

        if ($applications->count() === 1) {
            return redirect()->to($applications->first()->homeUrl());
        }

        return view('portal', ['applications' => $applications]);
    }
}
