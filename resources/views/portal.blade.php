@extends('layouts.app')

@section('title', 'Pilih Sistem')

@section('styles')
    .portal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
    .module-card { display: flex; flex-direction: column; gap: 28px; min-height: 190px; transition: transform .15s, box-shadow .15s; }
    .module-card:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(30, 64, 175, .14); }
    .module-ico { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; background: var(--soft); color: var(--navy); }
    .module-ico svg { width: 22px; height: 22px; }
    .section-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin: 32px 0 12px; }
@endsection

@section('content')
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 28px;">
    <img src="{{ auth()->user()->photo_url }}" alt="" class="avatar" style="width: 64px; height: 64px; border: 3px solid #fff;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">Halo, {{ auth()->user()->first_name }}</h1>
        <p class="muted" style="margin: 2px 0 0;">NIK {{ auth()->user()->nik }} · Pilih sistem yang ingin dibuka.</p>
    </div>
</div>

<div class="portal-grid">
    @forelse ($applications as $application)
        <a href="{{ $application->homeUrl() }}" class="card module-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div class="module-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                </div>
                <span class="badge">{{ auth()->user()->roleLabel($application->code) }}</span>
            </div>
            <div>
                <div style="font-size: 22px; font-weight: 600;">{{ $application->name }}</div>
                <div class="muted">{{ $application->description }}</div>
            </div>
        </a>
    @empty
        <div class="card muted">Anda belum memiliki akses ke modul manapun.</div>
    @endforelse
</div>

@if (auth()->user()->is_superadmin)
    <div class="section-label">Administrasi</div>
    <div class="portal-grid">
        <a href="{{ route('admin.users.index') }}" class="card module-card">
            <div class="module-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M16 3.1a4 4 0 0 1 0 7.8M22 21v-2a4 4 0 0 0-3-3.9"/></svg>
            </div>
            <div>
                <div style="font-size: 22px; font-weight: 600;">Kelola User</div>
                <div class="muted">Tambah user, reset PIN, atur akses HRMS / SMS.</div>
            </div>
        </a>
    </div>
@endif
@endsection
