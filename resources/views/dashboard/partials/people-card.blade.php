{{-- Kartu daftar orang (mis. ulang tahun minggu ini). $people: title, counter, items (name, sub, photo?, check?, url?), empty. --}}
<div class="card team-card {{ $class ?? '' }}" style="--i: {{ $urutan ?? 4 }};">
    <div class="card-head">
        <h2 class="card-title">{{ __($people['title']) }}</h2>
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
        <div class="muted small">{{ __($people['empty']) }}</div>
    @endforelse
    </div>
</div>
