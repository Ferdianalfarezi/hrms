{{--
    Kartu grafik ringkas (Work / Traffic Accident) + isi modal "perbesar" (grafik besar, legenda, tabel angka).
    Butuh: $chart (dari GrafikKecelakaan), $area (nama grid-area), $index (urutan animasi), $key (id unik).
    SVG digambar oleh JS di dashboard SMS supaya tajam di semua lebar layar.
--}}
@php
    $chartData = json_encode(['months' => \App\Modules\Sms\Support\GrafikKecelakaan::MONTHS, 'series' => $chart['series']]);
@endphp
<div class="card chart-card" style="grid-area: {{ $area }}; --i: {{ $index }};">
    <div class="card-head" style="margin-bottom: 2px;">
        <div style="min-width: 0;">
            <h2 class="card-title">{{ $chart['title'] }}</h2>
            <div class="muted small">{{ $chart['subtitle'] }}</div>
        </div>
        <button type="button" class="mini-btn" data-chart-expand="{{ $key }}" title="Perbesar" aria-label="Perbesar grafik {{ $chart['title'] }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
        </button>
    </div>

    <div class="chart-plot chart-plot-sm" data-chart="{{ $chartData }}" role="img"
         aria-label="Grafik {{ $chart['title'] }}, {{ $chart['subtitle'] }}. Klik perbesar untuk tabel angka.">
        <div class="chart-tip" hidden></div>
    </div>
    <div class="chart-legend chart-legend-sm">
        @foreach ($chart['series'] as $series)
            <span><i @class(['swatch', 'swatch-target' => $series['target']]) style="--c: {{ $series['color'] }};"></i>{{ $series['label'] }}</span>
        @endforeach
    </div>

    {{-- Isi modal perbesar, disalin ke #chart-modal saat icon perbesar diklik. --}}
    <template id="chart-full-{{ $key }}" data-title="{{ $chart['title'] }}" data-subtitle="{{ $chart['subtitle'] }}">
        <div class="chart-legend">
            @foreach ($chart['series'] as $series)
                <span><i @class(['swatch', 'swatch-target' => $series['target']]) style="--c: {{ $series['color'] }};"></i>{{ $series['label'] }}</span>
            @endforeach
        </div>
        <div class="chart-plot chart-plot-lg" data-chart="{{ $chartData }}" role="img" aria-label="Grafik {{ $chart['title'] }}">
            <div class="chart-tip" hidden></div>
        </div>
        <div class="table-wrap">
            <table class="chart-table">
                <thead>
                    <tr>
                        <th></th>
                        @foreach (\App\Modules\Sms\Support\GrafikKecelakaan::MONTHS as $month)
                            <th>{{ $month }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chart['series'] as $series)
                        <tr>
                            <td class="series-name"><i @class(['swatch', 'swatch-target' => $series['target']]) style="--c: {{ $series['color'] }};"></i>{{ $series['label'] }}</td>
                            @foreach ($series['values'] as $value)
                                <td>{{ $value ?? '–' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </template>
</div>
