@extends('layouts.base')

@section('body')
<div class="shell">
    {{-- Navbar (menu utama + menu modul) tetap menempel di atas saat halaman di-scroll. --}}
    <div class="app-nav">
    <header class="topbar">
        <a href="{{ route('portal') }}" class="brand">HR System</a>

        {{-- Menu modul sebaris dengan brand. --}}
        @if (count($moduleTabs) > 1)
            <nav class="subtabs" aria-label="Menu modul">
                @foreach ($moduleTabs as $tab)
                    @if ($tab['children'])
                        {{-- Dropdown terbuka saat kursor di atas tab (atau tab difokus/ditekan di layar sentuh). --}}
                        <div class="subtab-drop">
                            <button type="button" @class(['subtab', 'active' => $tab['active']]) aria-haspopup="true">
                                {{ $tab['label'] }}
                                @if ($tab['badge'] > 0)
                                    <span class="menu-badge" title="{{ $tab['badge'] }} menunggu tindakan">{{ $tab['badge'] > 99 ? '99+' : $tab['badge'] }}</span>
                                @endif
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="subtab-menu" role="menu">
                                @foreach ($tab['children'] as $child)
                                    <a href="{{ $child['url'] }}" role="menuitem" @class(['active' => $child['active']])>
                                        {{ $child['label'] }}
                                        @if ($child['badge'] > 0)
                                            <span class="menu-badge">{{ $child['badge'] > 99 ? '99+' : $child['badge'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $tab['url'] }}" @class(['subtab', 'active' => $tab['active']])>
                            {{ $tab['label'] }}
                            @if ($tab['badge'] > 0)
                                <span class="menu-badge">{{ $tab['badge'] > 99 ? '99+' : $tab['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>
        @endif

        <nav class="tabs" aria-label="Menu utama">
            <div class="tabs-inner">
                @foreach ($navTabs as $tab)
                    <a href="{{ $tab['url'] }}" @class(['tab', 'active' => $tab['active']])>{{ $tab['label'] }}</a>
                @endforeach
            </div>
        </nav>

        <div class="top-actions">
            @if (auth()->user()->is_superadmin)
                <a href="{{ route('admin.users.create') }}" class="icon-btn" title="Tambah user">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="icon-btn" title="Keluar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
                </button>
            </form>
            <img src="{{ auth()->user()->photo_url }}" alt="{{ auth()->user()->name }}" title="{{ auth()->user()->name }} ({{ auth()->user()->nik }})"
                 class="avatar" style="width: 38px; height: 38px; border: 2px solid #fff;">
        </div>
    </header>
    </div>

    @if (session('status'))
        <div class="success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->has('user'))
        <div class="alert">{{ $errors->first('user') }}</div>
    @endif

    @yield('content')
</div>

{{-- Notifikasi (mis. absensi belum ada keterangan) di pojok kanan bawah; baru hilang setelah ditutup dengan tombol ×. --}}
{{-- Dicek ulang otomatis tiap 15 detik (lihat hrToasts di layouts.base), jadi notifikasi baru muncul tanpa refresh. --}}
<div class="toast-stack" id="toast-stack" aria-live="polite" data-poll-url="{{ route('notifications.toasts') }}">
    @include('partials.toasts', ['toasts' => auth()->user()->unreadNotifications])
</div>
<script>hrToasts.prepare(document.getElementById('toast-stack'));</script>
@endsection
