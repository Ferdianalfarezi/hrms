@extends('layouts.base')

@section('title', 'Login')

@section('styles')
    .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 16px; }
    .login-shell {
        width: 100%; max-width: 880px; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; border-radius: 30px;
        background: linear-gradient(180deg, var(--shell-top), var(--shell-bottom)); border: 1px solid #fff;
        box-shadow: 0 30px 80px rgba(30, 55, 110, .18);
    }
    .login-hero {
        background: linear-gradient(160deg, var(--primary), var(--navy-deep)); color: #fff; padding: 36px;
        display: flex; flex-direction: column; justify-content: space-between; gap: 32px; position: relative; overflow: hidden;
    }
    .login-hero::after { content: ""; position: absolute; width: 320px; height: 320px; border-radius: 50%; right: -120px; bottom: -120px;
                         border: 40px solid rgba(255, 255, 255, .08); }
    .login-hero .brand { color: #fff; background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.3); align-self: flex-start; }
    .login-hero h2 { font-size: 30px; font-weight: 500; letter-spacing: -.02em; line-height: 1.2; }
    .login-form { padding: 40px 36px; }
    @media (max-width: 720px) { .login-shell { grid-template-columns: 1fr; } .login-hero { padding: 28px; } .login-form { padding: 28px; } }
@endsection

@section('body')
<div class="login-wrap">
    <div class="login-shell">
        <div class="login-hero">
            <span class="brand">HR System</span>
            <div>
                <h2>Satu login untuk HRMS dan SMS.</h2>
                <p style="opacity: .75; margin: 10px 0 0;">Masuk dengan NIK, sistem akan mengarahkan Anda ke modul sesuai hak akses.</p>
            </div>
        </div>

        <div class="login-form">
            <h1 style="font-size: 24px; margin-bottom: 4px;">Masuk</h1>
            <p class="muted" style="margin: 0 0 24px;">Gunakan NIK karyawan dan PIN/password Anda.</p>

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div style="margin-bottom: 16px;">
                    <label for="nik">NIK</label>
                    <input id="nik" type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric"
                           autocomplete="username" placeholder="Contoh: 1234" required autofocus>
                </div>

                <div style="margin-bottom: 16px;">
                    <label for="password">PIN / Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>

                <label class="check" style="margin-bottom: 22px;">
                    <input type="checkbox" name="remember" value="1"> Ingat saya
                </label>

                <button type="submit" class="btn" style="width: 100%; padding: 13px;">Masuk</button>
            </form>
        </div>
    </div>
</div>
@endsection
