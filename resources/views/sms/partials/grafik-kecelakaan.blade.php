{{--
    Kartu bar chart kecelakaan per bulan (Work vs Traffic Accident) + tabel detail yang bisa disalin / diunduh Excel.
    Butuh: $chart (dari GrafikKecelakaan), $index (urutan animasi). SVG & tabel digambar oleh JS di dashboard SMS.
--}}
<div class="card chart-card a-kecelakaan" style="--i: {{ $index }};"
     data-kecelakaan="{{ json_encode($chart['data']) }}" data-kecelakaan-series="{{ json_encode($chart['series']) }}">
    <div class="card-head" style="margin-bottom: 8px; flex-wrap: wrap;">
        <div style="min-width: 0;">
            <h2 class="card-title">{{ __('Kecelakaan') }}</h2>
            <div class="muted small" data-kecelakaan-subtitle></div>
        </div>
        <div class="recap-actions" data-kecelakaan-actions>
            <div class="temuan-tools">
                <select data-kecelakaan-year aria-label="{{ __('Tahun') }}">
                    @foreach ($chart['years'] as $year)
                        <option value="{{ $year }}" @selected($year === $chart['selected'])>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="chart-legend">
        @foreach ($chart['series'] as $series)
            <span><i class="swatch" style="--c: {{ $series['color'] }};"></i>{{ $series['label'] }}</span>
        @endforeach
    </div>
    <div class="chart-plot chart-plot-lg" data-kecelakaan-plot role="img" aria-label="{{ __('Grafik') }} {{ __('Kecelakaan') }}">
        <div class="chart-tip" hidden></div>
    </div>

    <div class="recap-head">
        <h3 class="card-title">{{ __('Detail per Bulan') }}</h3>
        <div class="recap-actions">
            <span class="recap-note" data-kecelakaan-copy-note aria-live="polite"></span>
            <button type="button" class="mini-btn icon-action" data-kecelakaan-copy title="{{ __('Salin tabel') }}" aria-label="{{ __('Salin tabel') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>
            </button>
        </div>
    </div>
    <div class="table-wrap" data-kecelakaan-table></div>
</div>
