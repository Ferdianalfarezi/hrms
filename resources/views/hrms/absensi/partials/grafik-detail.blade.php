{{-- Grafik bulanan ketidakhadiran satu shift; dipakai halaman Detail dan PDF Detail. Butuh $section dan $hitungCuti. --}}
@php
    $grafik = $section['grafik'];
    $points = $grafik['points'];
    $values = array_filter([...array_column($points, 'actual'), ...array_column($points, 'tanpa_cuti'), ...array_column($points, 'leader')], fn ($v) => $v !== null);
    $yMax = max(8, (int) ceil((max($values ?: [0]) + 1) / 2) * 2);
    [$left, $right, $top, $bottom] = [30, 552, 14, 176];
    $n = max(count($points), 1);
    $step = ($right - $left) / $n;
    $x = fn (int $i) => round($left + $step * ($i + .5), 1);
    $barWidth = round($step * .55, 1);
    $y = fn (float $v) => round($bottom - ($v / $yMax) * ($bottom - $top), 1);
    $targetLine = collect($points)->keys()->map(fn ($i) => $x($i).','.$y($grafik['target']))->implode(' ');
    $actualLine = collect($points)->filter(fn ($p) => $p['actual'] !== null)->map(fn ($p, $i) => $x($i).','.$y($p['actual']))->implode(' ');
    // Saat cuti tidak dihitung, garis Actual sudah tanpa cuti, jadi garis kedua tidak perlu.
    $tanpaCutiLine = $hitungCuti ? collect($points)->filter(fn ($p) => $p['tanpa_cuti'] !== null)->map(fn ($p, $i) => $x($i).','.$y($p['tanpa_cuti']))->implode(' ') : '';
    $fmt = fn (?float $v) => $v === null ? '' : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
@endphp
    <h3>{{ __('Grafik ketidakhadiran :judul (:tanggal)', ['judul' => __($section['judul']), 'tanggal' => $section['tanggal']->translatedFormat('d F Y')]) }}</h3>
    <svg viewBox="0 0 560 196" role="img" aria-label="Persentase ketidakhadiran {{ __($section['judul']) }} bulan {{ $grafik['bulan']->translatedFormat('F Y') }}">
        <g class="grid">
            @for ($v = 0; $v <= $yMax; $v += 2)
                <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}"/>
                <text x="{{ $left - 6 }}" y="{{ $y($v) + 3 }}" text-anchor="end">{{ $v }}</text>
            @endfor
        </g>
        @foreach ($points as $i => $point)
            <text x="{{ $x($i) }}" y="{{ $bottom + 14 }}" text-anchor="middle" @if ($point['selected']) style="fill: var(--navy); font-weight: 700;" @endif>{{ $point['day'] }}</text>
        @endforeach
        @foreach ($points as $i => $point)
            @if ($point['leader'])
                <rect x="{{ $x($i) - $barWidth / 2 }}" y="{{ $y($point['leader']) }}" width="{{ $barWidth }}" height="{{ round($bottom - $y($point['leader']), 1) }}" fill="#a5b4fc"><title>{{ __('Tgl') }} {{ $point['day'] }}: Asst. Leader up {{ $fmt($point['leader']) }}%</title></rect>
            @endif
        @endforeach
        <polyline points="{{ $targetLine }}" fill="none" stroke="#3b82f6" stroke-width="2"/>
        @foreach ($points as $i => $point)
            <path d="M{{ $x($i) }} {{ $y($grafik['target']) - 3.5 }} l3.5 6 h-7 z" fill="#3b82f6"/>
        @endforeach
        @if ($actualLine !== '')
            <polyline points="{{ $actualLine }}" fill="none" stroke="#ea580c" stroke-width="2" stroke-linejoin="round"/>
            @foreach ($points as $i => $point)
                @if ($point['actual'] !== null)
                    <rect x="{{ $x($i) - 3 }}" y="{{ $y($point['actual']) - 3 }}" width="6" height="6" fill="#ea580c"><title>{{ __('Tgl') }} {{ $point['day'] }}: {{ $fmt($point['actual']) }}%</title></rect>
                @endif
            @endforeach
        @endif
        @if ($tanpaCutiLine !== '')
            <polyline points="{{ $tanpaCutiLine }}" fill="none" stroke="#16a34a" stroke-width="2" stroke-linejoin="round"/>
            @foreach ($points as $i => $point)
                @if ($point['tanpa_cuti'] !== null)
                    <circle cx="{{ $x($i) }}" cy="{{ $y($point['tanpa_cuti']) }}" r="3.2" fill="#16a34a"><title>{{ __('Tgl') }} {{ $point['day'] }}: {{ __('tanpa cuti') }} {{ $fmt($point['tanpa_cuti']) }}%</title></circle>
                @endif
            @endforeach
        @endif
    </svg>
    <div class="legend">
        <span style="--c: #3b82f6">Target {{ $grafik['target'] }}%</span>
        <span style="--c: #ea580c">{{ $hitungCuti ? 'Actual (%)' : __('Actual tanpa cuti (%)') }}</span>
        @if ($hitungCuti)
            <span style="--c: #16a34a">{{ __('Actual tanpa cuti (%)') }}</span>
        @endif
        <span class="bar" style="--c: #a5b4fc">{{ __('Asst. Leader up tanpa cuti (%)') }}</span>
    </div>
    <div class="table-wrap">
        <table class="chart-data">
            <tr><th>{{ __('Tgl') }}</th>@foreach ($points as $point)<th @class(['sel' => $point['selected']])>{{ $point['day'] }}</th>@endforeach</tr>
            <tr><th>{{ __('Target') }}</th>@foreach ($points as $point)<td>{{ $grafik['target'] }}</td>@endforeach</tr>
            <tr><th>{{ __('Actual') }}</th>@foreach ($points as $point)<td @class(['sel' => $point['selected']])>{{ $fmt($point['actual']) }}</td>@endforeach</tr>
            @if ($hitungCuti)
                <tr><th>{{ __('Tanpa cuti') }}</th>@foreach ($points as $point)<td @class(['sel' => $point['selected']])>{{ $fmt($point['tanpa_cuti']) }}</td>@endforeach</tr>
            @endif
            <tr><th>{{ __('Asst. Leader up') }}</th>@foreach ($points as $point)<td @class(['sel' => $point['selected']])>{{ $fmt($point['leader']) }}</td>@endforeach</tr>
        </table>
    </div>
