@extends('layouts.app')

@section('title', $application->name)

{{-- Jangan tampilkan preview dari cache Turbo: animasi masuk bisa terputar dua kali (preview lalu halaman asli). --}}
@push('head')
    <meta name="turbo-cache-control" content="no-preview">
@endpush

@section('styles')
    .dash-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 32px; flex-wrap: wrap; margin-bottom: 24px; }
    .welcome { font-size: clamp(28px, 4vw, 44px); font-weight: 500; letter-spacing: -.03em; margin-bottom: 18px; }
    .module-tag { font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    .pillbars { display: flex; gap: 6px; align-items: flex-end; max-width: 720px; }
    @media (max-width: 640px) { .pillbars { flex-wrap: wrap; } }
    .pillbar { min-width: 88px; }
    .pillbar.hatch { min-width: 36px; }
    .pillbar span { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pill { height: 34px; border-radius: 999px; display: flex; align-items: center; padding: 0 14px; font-size: 12px; font-weight: 600; color: #fff; }
    .pill-0 { background: var(--navy); }
    .pill-1 { background: var(--primary); }
    .pill-2 { background: #5b8def; }
    .pill-hatch { background: repeating-linear-gradient(-45deg, rgba(37,99,235,.18) 0 4px, transparent 4px 9px); }
    .pill-outline { background: transparent; color: var(--text); border: 1.5px solid var(--line-strong); }
    .bigstats { display: flex; gap: 36px; }
    .bigstat { display: flex; align-items: flex-end; gap: 10px; }
    .bigstat .ico { width: 30px; height: 30px; border-radius: 50%; background: var(--glass-strong); display: grid; place-items: center; color: var(--muted); margin-bottom: 8px; }
    .bigstat .ico svg { width: 15px; height: 15px; }
    .bigstat .num { font-size: clamp(36px, 4.5vw, 54px); font-weight: 400; letter-spacing: -.04em; line-height: 1; }
    .bigstat .lbl { font-size: 12px; color: var(--muted); }

    .dash-grid {
        display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr));
        grid-template-areas: "profile progress clock side" "info calendar calendar side";
    }
    .a-profile { grid-area: profile; } .a-progress { grid-area: progress; } .a-clock { grid-area: clock; }
    .a-side { grid-area: side; display: flex; flex-direction: column; gap: 16px; }
    .a-info { grid-area: info; } .a-calendar { grid-area: calendar; }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-areas: "profile progress" "clock side" "info side" "calendar calendar"; }
    }
    @media (max-width: 640px) {
        .dash-grid { grid-template-columns: minmax(0, 1fr); grid-template-areas: "profile" "progress" "clock" "side" "info" "calendar"; }
        .bigstats { gap: 16px 20px; flex-wrap: wrap; }
        .bigstat .num { font-size: 34px; }
    }
    .card-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 14px; }
    .mini-btn { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; background: var(--glass-strong); border: 1px solid var(--line); color: var(--text); }
    .mini-btn svg { width: 14px; height: 14px; }

    .profile-card { position: relative; padding: 0; overflow: hidden; min-height: 280px; background-size: cover; background-position: center top; }
    .profile-card.no-photo { background-color: var(--soft); background-size: auto 75%; background-position: center 20%; background-repeat: no-repeat; }
    .profile-card .overlay { position: absolute; inset: auto 0 0 0; padding: 60px 18px 18px; background: linear-gradient(180deg, transparent, rgba(15, 30, 70, .72)); color: #fff;
                              display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; }
    .profile-card .name { font-size: 18px; font-weight: 600; line-height: 1.2; }
    .profile-card .role { font-size: 12px; opacity: .85; }
    .glass-pill { padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,.6); background: rgba(255,255,255,.15); font-size: 12px; font-weight: 600; white-space: nowrap; backdrop-filter: blur(6px); }

    .big-metric { display: flex; align-items: baseline; gap: 10px; margin-bottom: 8px; }
    .big-metric b { font-size: 32px; font-weight: 500; letter-spacing: -.03em; }
    .bars { display: flex; justify-content: space-between; align-items: flex-end; height: 120px; padding-top: 28px; }
    .bar-col { display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1; height: 100%; justify-content: flex-end; position: relative; }
    .bar { width: 7px; border-radius: 999px; background: var(--navy); min-height: 10px; }
    .bar.zero { background: var(--line-strong); }
    .bar.today { background: var(--primary); }
    .bar-bubble { position: absolute; top: -26px; background: var(--primary); color: #fff; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .bar-col small { color: var(--faint); font-size: 11px; }

    .clock { display: grid; place-items: center; position: relative; margin: 4px auto 10px; width: 170px; height: 170px; }
    .clock svg { position: absolute; inset: 0; transform: rotate(-90deg); }
    .clock-time { font-size: 34px; font-weight: 500; letter-spacing: -.02em; line-height: 1; text-align: center; }
    .clock-sub { font-size: 11px; color: var(--muted); text-align: center; margin-top: 4px; }

    .status-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .status-top b { font-size: 36px; font-weight: 400; letter-spacing: -.03em; line-height: 1; }
    .stackbar { display: flex; gap: 3px; height: 12px; margin: 16px 0 14px; }
    .stackbar span { min-width: 6px; border-radius: 999px; }
    .legend { display: grid; gap: 8px; }
    .legend-scroll { max-height: 240px; overflow-y: auto; padding-right: 6px; }
    /* Layar lebar: kolom kanan tidak ikut menentukan tinggi baris (supaya foto profil dll. tidak melar);
       daftar panjang di kartu komposisi mengisi sisa ruang lalu di-scroll. */
    @media (min-width: 641px) {
        .a-side:has(.comp-scroll) { contain: size; }
        .a-side .comp-scroll { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; }
        .a-side .comp-scroll .legend-scroll { flex: 1 1 0; max-height: none; min-height: 0; }
        .a-side:has(.comp-scroll) .team-card { flex: none; height: 270px; display: flex; flex-direction: column; }
        .a-side:has(.comp-scroll) .team-list { flex: 1 1 0; min-height: 0; overflow-y: auto; padding-right: 6px; }
    }
    .legend-row { display: flex; align-items: center; gap: 8px; font-size: 13px; min-width: 0; }
    .legend-row .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
    .legend-label { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .legend-value { margin-left: auto; font-size: 13px; font-weight: 600; white-space: nowrap; }
    .tone-primary { background: var(--primary); } .tone-navy { background: var(--navy); } .tone-primary-soft { background: #5b8def; } .tone-soft { background: #b9cbef; } .tone-success { background: #22c55e; } .tone-danger { background: #ef4444; }

    .team-card { flex: 1; }
    .team-card .card-head b { font-size: 26px; font-weight: 400; }
    .team-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); }
    .team-item:last-child { border-bottom: 0; }
    .team-item .t-name { font-size: 13px; font-weight: 600; }
    .team-item .t-sub { font-size: 11px; color: var(--muted); }
    .initials { width: 34px; height: 34px; border-radius: 50%; background: var(--soft); color: var(--navy); display: grid; place-items: center; font-size: 12px; font-weight: 700; flex: none; }
    .team-item .check { margin-left: auto; width: 20px; height: 20px; border-radius: 50%; background: var(--primary); display: grid; place-items: center; flex: none; }
    .team-item .check svg { width: 12px; height: 12px; }

    details.acc { border-bottom: 1px solid var(--line); }
    details.acc:last-of-type { border-bottom: 0; }
    details.acc summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 14px 0; font-weight: 500; }
    details.acc summary::-webkit-details-marker { display: none; }
    details.acc summary::after { content: ""; width: 7px; height: 7px; border-right: 1.5px solid var(--muted); border-bottom: 1.5px solid var(--muted); transform: rotate(45deg); transition: transform .15s; margin-right: 4px; }
    details.acc[open] summary::after { transform: rotate(-135deg); }
    .acc-body { padding: 0 0 14px; display: grid; gap: 8px; }
    .kv { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; }
    .kv span:first-child { color: var(--muted); }

    .cal-head { display: flex; justify-content: center; align-items: center; position: relative; margin-bottom: 14px; }
    .cal-head .badge { position: absolute; right: 0; }
    .cal-days, .cal-events { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; text-align: center; }
    .cal-days div { padding: 6px 0; }
    .cal-days small { display: block; font-size: 11px; color: var(--faint); }
    .cal-days b { display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; font-weight: 500; font-size: 13px; color: var(--muted); }
    .cal-days .today b { background: var(--navy); color: #fff; }
    .cal-events { margin-top: 8px; padding-top: 12px; border-top: 1px dashed var(--line-strong); min-height: 120px; align-items: start; }
    .cal-col { display: grid; gap: 6px; }
    .event { background: #fff; border-radius: 10px; padding: 6px 8px; text-align: left; font-size: 11px; box-shadow: 0 2px 8px rgba(15, 35, 80, .06); display: flex; align-items: center; gap: 6px; min-width: 0; }
    .event.primary { background: var(--primary); color: #fff; }
    .event span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cal-empty { grid-column: 1 / -1; align-self: center; color: var(--faint); font-size: 13px; }

    /* Animasi masuk dashboard. --i = urutan kartu, --j = urutan item di dalamnya (diisi dari Blade).
       Pakai fill-mode "backwards" supaya setelah selesai, efek hover kartu tetap bisa jalan. */
    @keyframes dash-rise { from { opacity: 0; transform: translateY(24px) scale(.97); } }
    @keyframes dash-fade { from { opacity: 0; transform: translateY(10px); } }
    @keyframes dash-pop { from { opacity: 0; transform: scale(.6); } }
    @keyframes dash-grow-x { from { transform: scaleX(0); } }
    @keyframes dash-grow-y { from { transform: scaleY(0); } }
    @keyframes dash-pulse { 0% { box-shadow: 0 0 0 0 rgba(30, 58, 138, .45); } 70%, 100% { box-shadow: 0 0 0 10px rgba(30, 58, 138, 0); } }

    .dash-head .module-tag { animation: dash-fade .6s cubic-bezier(.22, 1, .36, 1) backwards; }
    .dash-head .welcome { animation: dash-fade .7s cubic-bezier(.22, 1, .36, 1) .08s backwards; }
    .pillbar span { animation: dash-fade .5s ease-out backwards; animation-delay: calc(.2s + var(--j, 0) * 80ms); }
    .pill { transform-origin: left center; animation: dash-grow-x .8s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(.25s + var(--j, 0) * 80ms); }
    .bigstat { animation: dash-fade .7s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(.3s + var(--j, 0) * 110ms); }

    .dash-grid .card {
        --d: calc(.35s + var(--i, 0) * 90ms);
        animation: dash-rise .8s cubic-bezier(.22, 1, .36, 1) var(--d) backwards;
        transition: transform .25s cubic-bezier(.22, 1, .36, 1), box-shadow .25s;
    }
    .dash-grid .card:hover { transform: translateY(-4px); box-shadow: 0 20px 44px rgba(30, 64, 175, .16); }

    .profile-card .overlay { animation: dash-fade .7s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(var(--d) + .35s); }
    .bar { transform-origin: bottom; animation: dash-grow-y .9s cubic-bezier(.34, 1.4, .64, 1) backwards; animation-delay: calc(var(--d) + .3s + var(--j, 0) * 60ms); }
    .bar-bubble { animation: dash-pop .45s cubic-bezier(.34, 1.56, .64, 1) backwards; animation-delay: calc(var(--d) + 1s); }
    .clock { animation: dash-pop .7s cubic-bezier(.34, 1.4, .64, 1) backwards; animation-delay: calc(var(--d) + .15s); }
    #clock-ring { transition: stroke-dashoffset 1.6s cubic-bezier(.22, 1, .36, 1) .6s; }
    .stackbar span { transform-origin: left center; animation: dash-grow-x .7s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(var(--d) + .3s + var(--j, 0) * 90ms); }
    .legend-row, .team-item { animation: dash-fade .5s ease-out backwards; animation-delay: calc(var(--d) + .4s + var(--j, 0) * 70ms); }
    details.acc { animation: dash-fade .5s ease-out backwards; animation-delay: calc(var(--d) + .3s); }
    details.acc:nth-of-type(2) { animation-delay: calc(var(--d) + .4s); }
    details.acc:nth-of-type(3) { animation-delay: calc(var(--d) + .5s); }
    [data-count] { font-variant-numeric: tabular-nums; }
    .cal-days > div { animation: dash-pop .5s cubic-bezier(.34, 1.56, .64, 1) backwards; animation-delay: calc(var(--d) + .25s + var(--j, 0) * 50ms); }
    .cal-days .today b { animation: dash-pulse 2.2s ease-out 1.6s infinite; }
    .cal-col, .cal-empty { animation: dash-fade .6s ease-out backwards; animation-delay: calc(var(--d) + .7s); }

    @media (prefers-reduced-motion: reduce) {
        .dash-head *, .dash-grid, .dash-grid * { animation: none !important; transition: none !important; }
    }
@endsection

@section('content')
@php
    $viewer = auth()->user();
    // Jam kerja yang ditampilkan di kartu "Jam Kerja".
    $workStart = '07:00';
    $workEnd = '16:00';
    $icons = [
        'users' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M16 3.1a4 4 0 0 1 0 7.8M22 21v-2a4 4 0 0 0-3-3.9"/>',
        'lock' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'badge' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    ];
@endphp

<section class="dash-head">
    <div style="flex: 1; min-width: 280px;">
        <div class="module-tag">{{ $application->name }} · {{ $subtitle ?? $application->description }}</div>
        <h1 class="welcome">Selamat datang, {{ $viewer->first_name }}</h1>
        <div class="pillbars">
            @foreach ($pills as $item)
                <div class="pillbar" style="flex: {{ max($item['percent'], 14) }}; --j: {{ $loop->index }};">
                    <span>{{ $item['label'] }}</span>
                    <div class="pill pill-{{ $loop->index % 3 }}" data-count>{{ number_format($item['count'], 0, ',', '.') }}</div>
                </div>
            @endforeach
            @if ($pillOutlines)
                <div class="pillbar hatch" style="flex: 10; --j: {{ count($pills) }};"><span>&nbsp;</span><div class="pill pill-hatch"></div></div>
                @foreach ($pillOutlines as $item)
                    <div class="pillbar" style="flex: 14; --j: {{ count($pills) + 1 + $loop->index }};">
                        <span>{{ $item['label'] }}</span>
                        <div class="pill pill-outline" data-count>{{ number_format($item['count'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="bigstats">
        @foreach ($bigStats as $stat)
            <div class="bigstat" style="--j: {{ $loop->index }};">
                <div>
                    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">{!! $icons[$stat['icon']] ?? $icons['users'] !!}</svg></div>
                    <div class="lbl">{{ $stat['label'] }}</div>
                </div>
                <div class="num" data-count>{{ number_format($stat['value'], 0, ',', '.') }}</div>
            </div>
        @endforeach
    </div>
</section>
<section class="dash-grid">
    {{-- Profil user yang login --}}
    <div @class(['card', 'profile-card', 'a-profile', 'no-photo' => ! $viewer->photo]) style="background-image: url('{{ $viewer->photo_url }}'); --i: 0;">
        <div class="overlay">
            <div>
                <div class="name">{{ $viewer->name }}</div>
                <div class="role">{{ $viewer->roleLabel($application->code) }}</div>
            </div>
            <div class="glass-pill">NIK {{ $viewer->nik }}</div>
        </div>
    </div>

    {{-- Kartu kiri atas: modul bisa menggantinya lewat @section('progress') (mis. SMS: ringkasan temuan patrol). --}}
    @hasSection('progress')
        @yield('progress')
    @else
    {{-- Grafik batang --}}
    <div class="card a-progress" style="--i: 1;">
        <div class="card-head">
            <h2 class="card-title">{{ $growth['title'] }}</h2>
            @if ($growth['link'] ?? null)
                <a href="{{ $growth['link'] }}" class="mini-btn" title="Lihat detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                </a>
            @endif
        </div>
        <div class="big-metric"><b data-count>{{ $growth['total'] }}</b><span class="muted small">{!! nl2br(e($growth['caption'])) !!}</span></div>
        @php($maxBar = max(collect($growth['bars'])->max('count'), 1))
        <div class="bars">
            @foreach ($growth['bars'] as $bar)
                <div class="bar-col" style="--j: {{ $loop->index }};">
                    @if ($bar['highlight'])
                        <div class="bar-bubble">{{ $bar['count'] }} {{ $growth['bubble'] }}</div>
                    @endif
                    <div @class(['bar', 'zero' => $bar['count'] === 0, 'today' => $bar['highlight']])
                         style="height: {{ $bar['count'] ? max(round($bar['count'] / $maxBar * 100), 12) : 10 }}%;"
                         title="{{ $bar['label'] }}: {{ $bar['count'] }}"></div>
                    <small>{{ $bar['label'] }}</small>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Kartu jam kerja: modul bisa menggantinya lewat @section('clock') (mis. SMS: grafik Cross Patrol). --}}
    @hasSection('clock')
        @yield('clock')
    @else
    {{-- Jam kerja --}}
    <div class="card a-clock" style="--i: 2;">
        <div class="card-head"><h2 class="card-title">Jam Kerja</h2><span class="badge">{{ $workStart }}–{{ $workEnd }}</span></div>
        <div class="clock">
            <svg viewBox="0 0 170 170">
                <circle cx="85" cy="85" r="72" fill="none" stroke="var(--soft)" stroke-width="12" stroke-dasharray="2 6"/>
                <circle id="clock-ring" cx="85" cy="85" r="72" fill="none" stroke="var(--primary)" stroke-width="12" stroke-linecap="round"
                        stroke-dasharray="452.4" stroke-dashoffset="452.4"/>
            </svg>
            <div>
                <div class="clock-time" id="clock-time">--:--</div>
                <div class="clock-sub" id="clock-date">&nbsp;</div>
            </div>
        </div>
        <div class="muted small" id="clock-status" style="text-align: center;">&nbsp;</div>
    </div>
    @endif

    <div class="a-side">
        {{-- Komposisi --}}
        <div @class(['card', 'comp-scroll' => $composition['scroll'] ?? false]) style="--i: 3;">
            <div class="status-top">
                <h2 class="card-title">{{ $composition['title'] }}</h2>
                <b data-count>{{ $composition['value'] }}</b>
            </div>
            {{-- Segmen bernilai 0 disembunyikan, kecuali ditandai "always". "text" menggantikan angka + satuan di legenda. --}}
            @php($segments = collect($composition['segments'])->filter(fn ($segment) => ($segment['always'] ?? false) || $segment['count'] > 0)->values())
            {{-- Satu bar proporsional + legenda, supaya tidak meluber walau kartu sempit. "bar" => false menyembunyikan bar. --}}
            @if ($composition['bar'] ?? true)
                <div class="stackbar">
                    @foreach ($segments as $segment)
                        <span class="tone-{{ $segment['tone'] }}" style="flex: {{ $segment['count'] }}; --j: {{ $loop->index }};" title="{{ $segment['label'] }}: {{ $segment['count'] }}"></span>
                    @endforeach
                </div>
            @endif
            {{-- "scroll" => true: legenda panjang (mis. per karyawan) dibatasi tingginya dan bisa di-scroll. --}}
            <div @class(['legend', 'legend-scroll' => $composition['scroll'] ?? false]) @unless ($composition['bar'] ?? true) style="margin-top: 16px;" @endunless>
                @foreach ($segments as $segment)
                    <div class="legend-row" style="--j: {{ min($loop->index, 10) }};" @if ($segment['title'] ?? null) title="{{ $segment['title'] }}" @endif>
                        <i class="dot tone-{{ $segment['tone'] }}"></i>
                        <span class="legend-label">{{ $segment['label'] }}</span>
                        <span class="legend-value">{{ $segment['text'] ?? number_format($segment['count'], 0, ',', '.').' '.$composition['unit'] }}</span>
                    </div>
                @endforeach
            </div>
            @if ($composition['note'])
                <div class="muted small" style="margin-top: 12px;">{{ $composition['note'] }}</div>
            @endif
        </div>

        {{-- Kartu bawah kolom kanan: modul bisa menggantinya lewat @section('side-bottom') (mis. SMS: grafik temuan patrol). --}}
        @hasSection('side-bottom')
            @yield('side-bottom')
        @else
        {{-- Daftar orang --}}
        <div class="card team-card" style="--i: 4;">
            <div class="card-head">
                <h2 class="card-title">{{ $people['title'] }}</h2>
                <b>{{ $people['counter'] }}</b>
            </div>
            <div class="team-list">
            @forelse ($people['items'] as $person)
                <a @if ($person['url'] ?? null) href="{{ $person['url'] }}" @endif class="team-item" style="--j: {{ $loop->index }};">
                    @if ($person['photo'] ?? null)
                        <img src="{{ $person['photo'] }}" alt="" class="avatar" style="width: 34px; height: 34px;">
                    @else
                        <span class="initials">{{ \Illuminate\Support\Str::of($person['name'])->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
                    @endif
                    <div style="min-width: 0;">
                        <div class="t-name">{{ $person['name'] }}</div>
                        <div class="t-sub">{{ $person['sub'] }}</div>
                    </div>
                    @if ($person['check'] ?? false)
                        <span class="check"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    @endif
                </a>
            @empty
                <div class="muted small">{{ $people['empty'] }}</div>
            @endforelse
            </div>
        </div>
        @endif
    </div>

    {{-- Baris bawah: modul bisa menggantinya lewat @section('grid-bottom') (mis. SMS: grafik kecelakaan). --}}
    @hasSection('grid-bottom')
        @yield('grid-bottom')
    @else
    {{-- Informasi akun --}}
    <div class="card a-info" style="padding-top: 6px; padding-bottom: 6px; --i: 5;">
        <details class="acc" open>
            <summary>Data Diri</summary>
            <div class="acc-body">
                <div class="kv"><span>NIK</span><span>{{ $viewer->nik }}</span></div>
                <div class="kv"><span>Nama</span><span>{{ $viewer->name }}</span></div>
                <div class="kv"><span>Email</span><span>{{ $viewer->email ?? '-' }}</span></div>
            </div>
        </details>
        <details class="acc">
            <summary>Hak Akses</summary>
            <div class="acc-body">
                @forelse ($myApplications as $app)
                    <div class="kv"><span>{{ $app->name }}</span><span class="badge">{{ $viewer->roleLabel($app->code) }}</span></div>
                @empty
                    <span class="muted">Belum ada akses modul.</span>
                @endforelse
            </div>
        </details>
        <details class="acc">
            <summary>Status Akun</summary>
            <div class="acc-body">
                <div class="kv"><span>Status</span><span>{{ $viewer->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                <div class="kv"><span>Super Admin</span><span>{{ $viewer->is_superadmin ? 'Ya' : 'Tidak' }}</span></div>
                <div class="kv"><span>Terdaftar</span><span>{{ $viewer->created_at?->translatedFormat('d M Y') }}</span></div>
            </div>
        </details>
    </div>

    {{-- Kalender minggu ini --}}
    <div class="card a-calendar" style="--i: 6;">
        <div class="cal-head">
            <h2 class="card-title">{{ $calendar['label'] }}</h2>
            <span class="badge">Minggu ini</span>
        </div>
        <div class="cal-days">
            @foreach ($calendar['days'] as $day)
                <div @class(['today' => $day['today']]) style="--j: {{ $loop->index }};"><small>{{ $day['day'] }}</small><b>{{ $day['date'] }}</b></div>
            @endforeach
        </div>
        <div class="cal-events">
            @if (collect($calendar['days'])->sum(fn ($day) => count($day['events'])) === 0)
                <div class="cal-empty">{{ $calendar['empty'] }}</div>
            @else
                @foreach ($calendar['days'] as $day)
                    <div class="cal-col">
                        @foreach (array_slice($day['events'], 0, 3) as $event)
                            <div @class(['event', 'primary' => $day['today']]) title="{{ $event['title'] }}">
                                @if ($event['photo'] ?? null)
                                    <img src="{{ $event['photo'] }}" alt="" class="avatar" style="width: 18px; height: 18px;">
                                @endif
                                <span>{{ $event['name'] }}</span>
                            </div>
                        @endforeach
                        @if (count($day['events']) > 3)
                            <span class="muted small" title="{{ collect(array_slice($day['events'], 3))->pluck('title')->join(', ') }}">+{{ count($day['events']) - 3 }} lagi</span>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
        <div class="muted small" style="margin-top: 10px;">{{ $calendar['caption'] }}</div>
    </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    (function () {
        var ring = document.getElementById('clock-ring');
        // Kartu jam kerja bisa diganti modul (mis. SMS), jadi script jam tidak dijalankan.
        if (!ring) return;
        var circumference = 452.4;
        var start = {{ Js::from($workStart) }}.split(':'), end = {{ Js::from($workEnd) }}.split(':');
        start = start[0] * 60 + +start[1];
        end = end[0] * 60 + +end[1];

        function tick() {
            var now = new Date();
            var minutes = now.getHours() * 60 + now.getMinutes();
            var progress = Math.min(Math.max((minutes - start) / (end - start), 0), 1);

            document.getElementById('clock-time').textContent =
                String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            document.getElementById('clock-date').textContent =
                now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short' });
            document.getElementById('clock-status').textContent = minutes < start
                ? 'Jam kerja belum dimulai'
                : minutes >= end ? 'Jam kerja selesai' : 'Sisa ' + Math.floor((end - minutes) / 60) + ' jam ' + ((end - minutes) % 60) + ' menit';
            ring.style.strokeDashoffset = circumference * (1 - progress);
        }

        // Paksa browser membaca posisi awal ring (kosong) dulu, supaya transisinya terlihat berputar saat halaman dibuka.
        getComputedStyle(ring).strokeDashoffset;
        tick();
        // Dengan Turbo halaman tidak reload, jadi timer dari kunjungan sebelumnya dihentikan dulu.
        clearInterval(window.hrClockTimer);
        window.hrClockTimer = setInterval(function () {
            if (!document.getElementById('clock-ring')) return clearInterval(window.hrClockTimer);
            tick();
        }, 15000);
    })();

    // Angka statistik dihitung naik dari 0 saat dashboard dibuka (mis. "1.234", "85% hadir").
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        document.querySelectorAll('[data-count]').forEach(function (el) {
            var match = el.textContent.trim().match(/^(\D*)(\d[\d.]*)(\D*)$/);
            if (!match) return;

            var prefix = match[1], suffix = match[3], grouped = match[2].indexOf('.') !== -1;
            var target = +match[2].replace(/\./g, '');
            var format = function (n) { return prefix + (grouped ? n.toLocaleString('id-ID') : n) + suffix; };
            var duration = 1400, startedAt = null;

            el.textContent = format(0);
            function step(now) {
                startedAt = startedAt || now;
                var progress = Math.min((now - startedAt) / duration, 1);
                el.textContent = format(Math.round(target * (1 - Math.pow(1 - progress, 3))));
                if (progress < 1) requestAnimationFrame(step);
            }
            setTimeout(function () { requestAnimationFrame(step); }, 350);
        });
    })();
</script>
@endpush
