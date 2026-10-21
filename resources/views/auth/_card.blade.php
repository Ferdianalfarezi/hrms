{{-- Kartu login. $preview = salinan diam untuk animasi terbelah setelah login (lihat layouts.base), tanpa form yang bisa dikirim. --}}
@php($preview = $preview ?? false)
<div class="login-wrap">
    <div class="login-shell">
        <div class="login-hero">
            <span class="brand">HR System</span>
            <div>
                <h2>Satu login untuk HRMS dan SMS.</h2>
                <p style="opacity: .75; margin: 10px 0 0;">Masuk dengan NIK, sistem akan mengarahkan Anda ke modul sesuai hak akses.</p>
            </div>
            {{-- Maskot menempel di bawah panel, kakinya terpotong. Pupil digambar terpisah supaya bisa mengikuti kursor (lihat script di layouts.base). --}}
            <div class="mascot-frame" aria-hidden="true">
                <div class="mascot">
                    <img src="{{ asset('images/satpam.png') }}" alt="" draggable="false">
                    <span class="mascot-eye" style="left: 58.52%; top: 17.97%;"><i class="mascot-pupil"></i></span>
                    <span class="mascot-eye" style="left: 64.77%; top: 19.10%;"><i class="mascot-pupil"></i></span>
                </div>
            </div>
        </div>

        <div class="login-form">
            <h1 style="font-size: 24px; margin-bottom: 4px;">Masuk</h1>
            <p class="muted" style="margin: 0 0 24px;">Gunakan NIK karyawan dan PIN/password Anda.</p>

            @if (! $preview && $errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form @unless ($preview) method="POST" action="{{ route('login.store') }}" @endunless>
                @unless ($preview)
                    @csrf
                @endunless

                <div style="margin-bottom: 16px;">
                    <label @unless ($preview) for="nik" @endunless>NIK</label>
                    @if ($preview)
                        <input type="text" value="{{ auth()->user()->nik }}" tabindex="-1" readonly>
                    @else
                        <input id="nik" type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric"
                               autocomplete="username" placeholder="Contoh: 1234" required autofocus>
                    @endif
                </div>

                <div style="margin-bottom: 16px;">
                    <label @unless ($preview) for="password" @endunless>PIN / Password</label>
                    @if ($preview)
                        <input type="password" value="********" tabindex="-1" readonly>
                    @else
                        <input id="password" type="password" name="password" autocomplete="current-password" required>
                    @endif
                </div>

                <label class="check" style="margin-bottom: 22px;">
                    <input type="checkbox" @unless ($preview) name="remember" value="1" @else tabindex="-1" disabled @endunless> Ingat saya
                </label>

                <button @if ($preview) type="button" tabindex="-1" @else type="submit" @endif class="btn" style="width: 100%; padding: 13px;">Masuk</button>
            </form>
        </div>
    </div>
</div>
