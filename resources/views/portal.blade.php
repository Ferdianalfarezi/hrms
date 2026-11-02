@extends('layouts.app')

@section('title', 'Pilih Sistem')

@section('styles')
    .app-grid { display: grid; grid-template-columns: repeat(auto-fill, 168px); gap: 16px; }
    .app-tile { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 16px 12px; text-align: center; color: var(--text); background: rgba(255, 255, 255, .78); border: 1px solid rgba(255, 255, 255, .9); border-radius: 22px; box-shadow: 0 8px 24px rgba(30, 64, 175, .08); transition: transform .15s, box-shadow .15s, background .15s; }
    .app-tile:hover { transform: translateY(-3px); background: #fff; box-shadow: 0 16px 36px rgba(30, 64, 175, .16); }
    .app-tile:active { transform: scale(.97); }
    .app-ico { width: 64px; height: 64px; border-radius: 18px; display: grid; place-items: center; color: #fff; box-shadow: 0 8px 18px rgba(15, 35, 80, .22), inset 0 1px 0 rgba(255, 255, 255, .25); }
    .app-ico svg { width: 30px; height: 30px; }
    .app-ico.blue { background: linear-gradient(135deg, #3b82f6, #1e3a8a); }
    .app-ico.green { background: linear-gradient(135deg, #34d399, #047857); }
    .app-ico.orange { background: linear-gradient(135deg, #fbbf24, #c2410c); }
    .app-ico.purple { background: linear-gradient(135deg, #a78bfa, #5b21b6); }
    .app-name { font-size: 15px; font-weight: 700; line-height: 1.2; }
    .app-role { font-size: 11px; color: var(--muted); line-height: 1.2; }
    .section-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin: 32px 0 12px; }
    @media (max-width: 520px) {
        .app-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .app-tile { border-radius: 18px; padding: 10px 6px; gap: 8px; }
        .app-ico { width: 48px; height: 48px; border-radius: 14px; }
        .app-ico svg { width: 24px; height: 24px; }
        .app-name { font-size: 13px; }
    }
@endsection

@section('content')
@php
    $ikon = [
        'hrms' => ['blue', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
        'sms' => ['green', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
    ];
    $ikonLain = ['orange', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'];
@endphp

<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 28px;">
    <img src="{{ auth()->user()->photo_url }}" alt="" class="avatar" style="width: 64px; height: 64px; border: 3px solid #fff;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">Halo, {{ auth()->user()->first_name }}</h1>
        <p class="muted" style="margin: 2px 0 0;">NIK {{ auth()->user()->nik }} · Pilih sistem yang ingin dibuka.</p>
    </div>
</div>

<div class="section-label" style="margin-top: 0;">Aplikasi</div>
<div class="app-grid">
    @forelse ($applications as $application)
        @php [$warna, $svg] = $ikon[strtolower($application->code)] ?? $ikonLain; @endphp
        <a href="{{ $application->homeUrl() }}" class="app-tile" title="{{ $application->description }}">
            <span class="app-ico {{ $warna }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $svg !!}</svg>
            </span>
            <span class="app-name">{{ $application->name }}</span>
            <span class="app-role">{{ auth()->user()->roleLabel($application->code) }}</span>
        </a>
    @empty
        <div class="card muted">Anda belum memiliki akses ke modul manapun.</div>
    @endforelse
</div>

@if (auth()->user()->is_superadmin)
    <div class="section-label">Administrasi</div>
    <div class="app-grid">
        <a href="{{ route('admin.users.index') }}" class="app-tile" title="Tambah user, reset PIN, atur akses HRMS / SMS.">
            <span class="app-ico purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <span class="app-name">Kelola User</span>
            <span class="app-role">Akses &amp; PIN</span>
        </a>
    </div>
@endif
@endsection
