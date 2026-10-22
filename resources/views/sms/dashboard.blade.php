{{-- Dashboard SMS memakai template dashboard modul; kartu Data Diri & kalender diganti grafik Work & Traffic Accident. --}}
@extends('dashboard.module')

@section('styles')
    @parent
    .chart-card { min-width: 0; display: flex; flex-direction: column; }
    .chart-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 12px; color: var(--muted); }
    .chart-legend span { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .chart-legend-sm { gap: 4px 10px; font-size: 11px; margin-top: auto; }
    .swatch { width: 10px; height: 10px; border-radius: 3px; background: var(--c); flex: none; display: inline-block; }
    .swatch-target { height: 0; width: 14px; border-radius: 0; background: none; border-top: 2px dashed var(--c); }

    .chart-plot { position: relative; }
    .chart-plot-sm { height: 170px; margin: 6px 0 10px; }
    .chart-plot-lg { height: 320px; margin: 14px 0 16px; }
    .chart-plot svg { display: block; width: 100%; height: 100%; overflow: visible; }
    .chart-plot .grid line { stroke: rgba(15, 35, 80, .08); }
    .chart-plot .axis text { fill: var(--muted); font-size: 11px; }
    .chart-plot .crosshair { stroke: rgba(15, 35, 80, .3); stroke-width: 1; }
    .chart-tip {
        position: absolute; z-index: 2; pointer-events: none; min-width: 170px; padding: 10px 12px; border-radius: 12px;
        background: #fff; border: 1px solid var(--line-strong); box-shadow: 0 12px 28px rgba(30, 64, 175, .16); font-size: 12px;
    }
    .chart-tip b { display: block; margin-bottom: 6px; font-size: 12px; color: var(--text); }
    .chart-tip div { display: flex; align-items: center; gap: 6px; color: var(--muted); white-space: nowrap; }
    .chart-tip div span:last-child { margin-left: auto; padding-left: 12px; font-weight: 600; color: var(--text); font-variant-numeric: tabular-nums; }

    #chart-modal { width: min(1100px, calc(100% - 32px)); }

    /* Kartu temuan Daily Patrol per departemen (kolom kanan): bar mendatar Open + Close, total di ujung. */
    .temuan-card .card-head { margin-bottom: 8px; }
    .temuan-tools { display: flex; align-items: center; gap: 6px; }
    .temuan-tools select { width: auto; padding: 6px 28px 6px 12px; font-size: 12px; border-radius: 999px; }
    .temuan-rows { display: grid; gap: 10px; margin-top: 12px; }
    .temuan-row { display: grid; grid-template-columns: minmax(0, 64px) 1fr 26px; align-items: center; gap: 8px; font-size: 12px; }
    .temuan-row .dept { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .temuan-row .track { display: flex; gap: 2px; height: 12px; }
    .temuan-row .track span { height: 100%; border-radius: 4px; min-width: 4px; transform-origin: left center; animation: bar-grow-x .9s cubic-bezier(.22, 1, .36, 1) backwards; }
    @keyframes bar-grow-x { from { transform: scaleX(0); } }
    /* Batang di modal tumbuh dari garis nol saat dibuka. */
    .chart-plot .bar-grow { transform-box: fill-box; transform-origin: bottom; animation: bar-grow .8s cubic-bezier(.22, 1, .36, 1) backwards; }
    @keyframes bar-grow { from { transform: scaleY(0); } }
    @media (prefers-reduced-motion: reduce) { .temuan-row .track span { animation: none; } .chart-plot .bar-grow { animation: none; } }
    .temuan-row .total { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
    .temuan-empty { margin-top: 16px; font-size: 13px; color: var(--faint); }
    .temuan-sum { display: flex; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
    .temuan-sum b { color: var(--text); font-variant-numeric: tabular-nums; }
    table.chart-table { font-size: 12px; }
    table.chart-table th { padding: 8px 6px; text-align: center; background: var(--soft-2); }
    table.chart-table th:first-child { border-radius: 10px 0 0 10px; }
    table.chart-table th:last-child { border-radius: 0 10px 10px 0; }
    table.chart-table td { padding: 7px 6px; text-align: center; font-variant-numeric: tabular-nums; color: var(--text); }
    table.chart-table td.series-name { text-align: left; white-space: nowrap; font-weight: 600; display: flex; align-items: center; gap: 8px; }

    /* Kartu temuan (pengganti Pertumbuhan & Jam Kerja). */
    .temuan-status { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .temuan-status span { padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; border: 1.5px solid; white-space: nowrap; }
    .temuan-status .s-open { color: #b45309; border-color: #f59e0b; background: #fffbeb; }
    .temuan-status .s-closed { color: var(--success); border-color: #34d399; background: var(--success-bg); }

    /* Cross Patrol: batang bulan ini (biru) berdampingan dengan bulan lalu (pucat) per minggu. */
    .cross-card { display: flex; flex-direction: column; }
    .trend { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border: 1.5px solid transparent; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .trend-up { color: #b91c1c; background: #fee2e2; }
    .trend-down { color: var(--success); background: var(--success-bg); }
    .trend-flat { color: var(--muted); background: var(--soft-2); }
    .cross-bars .bar-pair { display: flex; align-items: flex-end; gap: 3px; height: 100%; }
    .cross-bars .bar.prev { background: #b9cbef; }
    .cross-bars .bar-col.now small { color: var(--primary); font-weight: 600; }
    .cross-legend { display: flex; justify-content: center; gap: 12px; margin-top: 10px; font-size: 11px; color: var(--muted); }
    .cross-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .cross-legend i { width: 8px; height: 8px; border-radius: 50%; background: var(--c); }
@endsection

@section('progress')
    {{-- Kartu "Pertumbuhan" diganti temuan Daily Patrol hari ini + 7 hari terakhir. --}}
    <div class="card a-progress" style="--i: 1;">
        <div class="card-head">
            <h2 class="card-title">Daily Patrol</h2>
            @can('sms-patrol')
                <a href="{{ route('sms.daily-patrols.index') }}" class="mini-btn" title="Buka Daily Patrol">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                </a>
            @endcan
        </div>
        <div class="big-metric"><b data-count>{{ $dailyHariIni['total'] }}</b><span class="muted small">temuan<br>hari ini</span></div>
        <div class="temuan-status">
            <span class="s-open">Open {{ $dailyHariIni['open'] }}</span>
            <span class="s-closed">Close {{ $dailyHariIni['closed'] }}</span>
        </div>
        @php($maxDaily = max(collect($dailyHariIni['bars'])->max('count'), 1))
        <div class="bars">
            @foreach ($dailyHariIni['bars'] as $bar)
                <div class="bar-col" style="--j: {{ $loop->index }};">
                    @if ($bar['highlight'])
                        <div class="bar-bubble">{{ $bar['count'] }} temuan</div>
                    @endif
                    <div @class(['bar', 'zero' => $bar['count'] === 0, 'today' => $bar['highlight']])
                         style="height: {{ $bar['count'] ? max(round($bar['count'] / $maxDaily * 100), 12) : 10 }}%;"
                         title="{{ $bar['title'] }}: {{ $bar['count'] }} temuan"></div>
                    <small>{{ $bar['label'] }}</small>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('clock')
    {{-- Kartu "Jam Kerja" diganti temuan Cross Patrol bulan ini, dibandingkan bulan lalu per minggu. --}}
    @php($cross = $crossBulanIni)
    @php($diff = $cross['total'] - $cross['previousTotal'])
    <div class="card a-clock cross-card" style="--i: 2;">
        <div class="card-head">
            <h2 class="card-title">Cross Patrol</h2>
            @can('sms-patrol')
                <a href="{{ route('sms.cross-patrols.index') }}" class="mini-btn" title="Buka Cross Patrol">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                </a>
            @endcan
        </div>
        <div class="big-metric">
            <b data-count>{{ $cross['total'] }}</b>
            <span class="muted small">temuan<br>{{ $cross['month'] }}</span>
        </div>
        {{-- Open / Close di bawah angka, sama seperti kartu Daily Patrol; pembanding bulan lalu ikut di barisnya. --}}
        <div class="temuan-status">
            <span class="s-open">Open {{ $cross['open'] }}</span>
            <span class="s-closed">Close {{ $cross['closed'] }}</span>
            @if ($cross['hasPrevious'])
                <span @class(['trend', 'trend-up' => $diff > 0, 'trend-down' => $diff < 0, 'trend-flat' => $diff === 0])
                      title="Bulan lalu ({{ $cross['previousLabel'] }}): {{ $cross['previousTotal'] }} temuan">
                    {{ $diff > 0 ? '▲ '.$diff : ($diff < 0 ? '▼ '.abs($diff) : 'Sama') }} dari {{ $cross['previousLabel'] }} ({{ $cross['previousTotal'] }})
                </span>
            @endif
        </div>
        @php($maxCross = max(collect($cross['weeks'])->max('current'), collect($cross['weeks'])->max('previous'), 1))
        <div class="bars cross-bars">
            @foreach ($cross['weeks'] as $week)
                <div @class(['bar-col', 'now' => $loop->index === $cross['currentWeek']]) style="--j: {{ $loop->index }};">
                    <div class="bar-pair">
                        @if ($cross['hasPrevious'])
                            <div @class(['bar', 'prev', 'zero' => $week['previous'] === 0])
                                 style="height: {{ $week['previous'] ? max(round($week['previous'] / $maxCross * 100), 12) : 10 }}%;"
                                 title="{{ $week['label'] }} {{ $cross['previousLabel'] }}: {{ $week['previous'] }} temuan"></div>
                        @endif
                        <div @class(['bar', 'today' => $week['current'] > 0, 'zero' => $week['current'] === 0])
                             style="height: {{ $week['current'] ? max(round($week['current'] / $maxCross * 100), 12) : 10 }}%;"
                             title="{{ $week['label'] }} {{ $cross['currentLabel'] }}: {{ $week['current'] }} temuan"></div>
                    </div>
                    <small>{{ $week['label'] }}</small>
                </div>
            @endforeach
        </div>
        @if ($cross['hasPrevious'])
            <div class="cross-legend">
                <span><i style="--c: var(--primary);"></i>{{ $cross['currentLabel'] }}</span>
                <span><i style="--c: #b9cbef;"></i>{{ $cross['previousLabel'] }}</span>
            </div>
        @endif
    </div>
@endsection

@section('grid-bottom')
    @include('sms.partials.grafik-kecelakaan', ['chart' => $grafikKecelakaan['work'], 'area' => 'info', 'index' => 5, 'key' => 'work'])
    @include('sms.partials.grafik-kecelakaan', ['chart' => $grafikKecelakaan['traffic'], 'area' => 'calendar', 'index' => 6, 'key' => 'traffic'])
@endsection

@section('side-bottom')
    <div class="card team-card temuan-card" style="--i: 4;" data-temuan="{{ json_encode($grafikTemuan['data']) }}">
        <div class="card-head">
            <h2 class="card-title">Temuan Daily Patrol</h2>
            <div class="temuan-tools">
                <select data-temuan-year aria-label="Tahun">
                    @foreach ($grafikTemuan['years'] as $year)
                        <option value="{{ $year }}" @selected($year === $grafikTemuan['selected'])>{{ $year }}</option>
                    @endforeach
                </select>
                <button type="button" class="mini-btn" data-temuan-expand title="Perbesar" aria-label="Perbesar grafik temuan Daily Patrol">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                </button>
            </div>
        </div>
        <div class="chart-legend chart-legend-sm" style="margin-top: 0;">
            <span><i class="swatch" style="--c: #eb6834;"></i>Open</span>
            <span><i class="swatch" style="--c: #1baf7a;"></i>Close</span>
            <span>angka = total</span>
        </div>
        <div class="temuan-rows" data-temuan-rows></div>
        <div class="temuan-sum" data-temuan-sum></div>
    </div>
@endsection

@push('scripts')
{{-- Modal perbesar grafik: grafik besar + legenda + tabel angka per bulan. --}}
<dialog class="modal" id="chart-modal">
    <div class="modal-head">
        <div>
            <h2 id="chart-modal-title"></h2>
            <div class="muted small" id="chart-modal-sub"></div>
        </div>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body" id="chart-modal-body"></div>
</dialog>

<script>
    // Grafik garis 12 bulan (SVG): garis melengkung halus, area bergradasi di bawah seri tahun berjalan,
    // titik di ujung garis & saat hover. Saat dashboard dibuka garis "tergambar" dari kiri ke kanan.
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    (function () {
        var NS = 'http://www.w3.org/2000/svg';
        var uid = 0;

        function el(name, attrs, parent) {
            var node = document.createElementNS(NS, name);
            Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
            if (parent) parent.appendChild(node);
            return node;
        }

        // Kurva monoton (Fritsch-Carlson): halus tapi tidak melewati titik data, jadi garis akumulasi tidak pernah turun.
        function smoothPath(points) {
            if (points.length < 2) return points.length ? 'M' + points[0][0] + ' ' + points[0][1] : '';
            var n = points.length, dx = [], m = [], t = [], i;
            for (i = 0; i < n - 1; i++) {
                dx[i] = points[i + 1][0] - points[i][0];
                m[i] = (points[i + 1][1] - points[i][1]) / dx[i];
            }
            t[0] = m[0];
            t[n - 1] = m[n - 2];
            for (i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
            for (i = 0; i < n - 1; i++) {
                if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
                var a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
                if (s > 9) { var k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
            }
            var d = 'M' + points[0][0] + ' ' + points[0][1];
            for (i = 0; i < n - 1; i++) {
                d += 'C' + (points[i][0] + dx[i] / 3) + ' ' + (points[i][1] + t[i] * dx[i] / 3) + ' '
                    + (points[i + 1][0] - dx[i] / 3) + ' ' + (points[i + 1][1] - t[i + 1] * dx[i] / 3) + ' '
                    + points[i + 1][0] + ' ' + points[i + 1][1];
            }
            return d;
        }

        function lastIndex(values) {
            for (var i = values.length - 1; i >= 0; i--) if (values[i] !== null) return i;
            return -1;
        }

        function draw(plot, animateDelay) {
            var data = JSON.parse(plot.dataset.chart);
            var tip = plot.querySelector('.chart-tip');
            var old = plot.querySelector('svg');
            if (old) old.remove();

            var width = plot.clientWidth, height = plot.clientHeight;
            var pad = { top: 12, right: 14, bottom: 24, left: 26 };
            var innerW = width - pad.left - pad.right, innerH = height - pad.top - pad.bottom;
            if (innerW <= 0 || innerH <= 0) return;

            // Skala Y: bilangan bulat, minimal sampai 1, maksimal +- 5 garis bantu.
            var max = 1;
            data.series.forEach(function (s) { s.values.forEach(function (v) { if (v !== null && v > max) max = v; }); });
            var step = Math.max(1, Math.ceil(max / 5));
            var top = Math.ceil(max / step) * step;
            var slot = innerW / 11;
            var x = function (i) { return pad.left + slot * i; };
            var y = function (v) { return pad.top + innerH - innerH * v / top; };
            var base = pad.top + innerH;
            // Kartu sempit: label bulan ditampilkan selang-seling supaya tidak bertumpuk.
            var labelEvery = slot < 26 ? 3 : (slot < 38 ? 2 : 1);
            var id = 'lc' + (++uid);

            var svg = el('svg', { width: width, height: height, 'aria-hidden': 'true' });
            plot.insertBefore(svg, tip);
            var defs = el('defs', {}, svg);
            // Garis & area dibuka dari kiri ke kanan lewat clipPath.
            var clip = el('clipPath', { id: id + '-clip' }, defs);
            var reveal = el('rect', { x: 0, y: 0, width: animateDelay === null ? width : 0, height: height }, clip);

            var grid = el('g', { class: 'grid' }, svg);
            var axis = el('g', { class: 'axis' }, svg);
            for (var t = 0; t <= top; t += step) {
                el('line', { x1: pad.left, x2: width - pad.right, y1: y(t), y2: y(t), 'stroke-dasharray': t === 0 ? 'none' : '3 4' }, grid);
                el('text', { x: pad.left - 8, y: y(t) + 4, 'text-anchor': 'end' }, axis).textContent = t;
            }
            data.months.forEach(function (month, i) {
                if (i % labelEvery !== 0) return;
                el('text', { x: x(i), y: height - 6, 'text-anchor': i === 0 ? 'start' : (i === 11 ? 'end' : 'middle') }, axis).textContent = month;
            });

            var plotLayer = el('g', { 'clip-path': 'url(#' + id + '-clip)' }, svg);
            var primary = data.series.length - 1; // seri terakhir = akumulasi tahun berjalan

            data.series.forEach(function (s, index) {
                // Potong per bagian bulan yang ada nilainya (bulan null tidak digambar).
                var runs = [], run = [];
                s.values.forEach(function (v, i) {
                    if (v === null) { if (run.length) runs.push(run); run = []; return; }
                    run.push([x(i), y(v)]);
                });
                if (run.length) runs.push(run);

                runs.forEach(function (points) {
                    var d = smoothPath(points);
                    if (index === primary && points.length > 1) {
                        var gradient = el('linearGradient', { id: id + '-fill', x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
                        el('stop', { offset: '0%', 'stop-color': s.color, 'stop-opacity': 0.22 }, gradient);
                        el('stop', { offset: '100%', 'stop-color': s.color, 'stop-opacity': 0 }, gradient);
                        el('path', { d: d + 'L' + points[points.length - 1][0] + ' ' + base + 'L' + points[0][0] + ' ' + base + 'Z', fill: 'url(#' + id + '-fill)' }, plotLayer);
                    }
                    el('path', {
                        d: d, fill: 'none', stroke: s.color, 'stroke-width': s.target ? 1.5 : (index === primary ? 2.5 : 2),
                        'stroke-dasharray': s.target ? '5 4' : 'none', 'stroke-linejoin': 'round', 'stroke-linecap': 'round',
                    }, plotLayer);
                });

                // Titik di ujung garis (nilai terakhir yang ada).
                var last = lastIndex(s.values);
                if (!s.target && last >= 0) el('circle', { cx: x(last), cy: y(s.values[last]), r: 4, fill: s.color, stroke: '#fff', 'stroke-width': 2 }, plotLayer);
            });

            // Animasi buka dari kiri ke kanan (hanya saat pertama dibuka, bukan saat resize).
            if (animateDelay !== null) {
                setTimeout(function () {
                    var start = null, duration = 1300;
                    function frame(now) {
                        start = start || now;
                        var p = Math.min((now - start) / duration, 1);
                        reveal.setAttribute('width', width * (1 - Math.pow(1 - p, 3)));
                        if (p < 1) requestAnimationFrame(frame);
                    }
                    requestAnimationFrame(frame);
                }, animateDelay);
            }

            // Hover per bulan: garis bantu vertikal, titik semua seri di bulan itu, dan tooltip.
            var cross = el('line', { class: 'crosshair', y1: pad.top, y2: base, visibility: 'hidden' }, svg);
            var dots = el('g', { visibility: 'hidden' }, svg);
            data.months.forEach(function (month, i) {
                var hit = el('rect', { x: x(i) - slot / 2, y: 0, width: slot, height: height, fill: 'transparent' }, svg);
                hit.addEventListener('mouseenter', function () {
                    cross.setAttribute('x1', x(i));
                    cross.setAttribute('x2', x(i));
                    cross.setAttribute('visibility', 'visible');
                    while (dots.firstChild) dots.removeChild(dots.firstChild);
                    data.series.forEach(function (s) {
                        if (!s.target && s.values[i] !== null) el('circle', { cx: x(i), cy: y(s.values[i]), r: 4, fill: s.color, stroke: '#fff', 'stroke-width': 2 }, dots);
                    });
                    dots.setAttribute('visibility', 'visible');
                    tip.innerHTML = '';
                    var title = document.createElement('b');
                    title.textContent = month;
                    tip.appendChild(title);
                    data.series.forEach(function (s) {
                        var row = document.createElement('div');
                        var swatch = document.createElement('i');
                        swatch.className = 'swatch' + (s.target ? ' swatch-target' : '');
                        swatch.style.setProperty('--c', s.color);
                        var label = document.createElement('span');
                        label.textContent = s.label;
                        var value = document.createElement('span');
                        value.textContent = s.values[i] === null ? '–' : s.values[i];
                        row.append(swatch, label, value);
                        tip.appendChild(row);
                    });
                    tip.hidden = false;
                    var left = x(i) + 14;
                    if (left + tip.offsetWidth > width) left = x(i) - tip.offsetWidth - 14;
                    tip.style.left = Math.max(0, left) + 'px';
                    tip.style.top = pad.top + 'px';
                });
                hit.addEventListener('mouseleave', function () {
                    cross.setAttribute('visibility', 'hidden');
                    dots.setAttribute('visibility', 'hidden');
                    tip.hidden = true;
                });
            });
        }

        // animateDelay: ms sebelum animasi mulai, atau null = langsung tampil penuh.
        function watch(plot, animateDelay) {
            draw(plot, reduceMotion ? null : animateDelay);
            if (!('ResizeObserver' in window)) return;
            var lastWidth = plot.clientWidth;
            new ResizeObserver(function () {
                if (plot.isConnected && plot.clientWidth !== lastWidth) { lastWidth = plot.clientWidth; draw(plot, null); }
            }).observe(plot);
        }

        // Kartu dashboard muncul bertahap (animasi kartu +- 0,8 dtk), jadi garis mulai digambar setelah kartunya tampil.
        document.querySelectorAll('.chart-card > .chart-plot[data-chart]').forEach(function (plot, index) {
            watch(plot, 900 + index * 150);
        });

        // Icon perbesar: salin isi lengkap (legenda, grafik besar, tabel) ke modal, lalu gambar grafiknya.
        var modal = document.getElementById('chart-modal');
        var body = document.getElementById('chart-modal-body');
        document.querySelectorAll('[data-chart-expand]').forEach(function (button) {
            button.addEventListener('click', function () {
                var template = document.getElementById('chart-full-' + button.dataset.chartExpand);
                document.getElementById('chart-modal-title').textContent = template.dataset.title;
                document.getElementById('chart-modal-sub').textContent = template.dataset.subtitle;
                body.innerHTML = '';
                body.appendChild(template.content.cloneNode(true));
                modal.showModal();
                watch(body.querySelector('.chart-plot'), 150);
            });
        });
        modal.querySelector('[data-close]').addEventListener('click', function () { modal.close(); });
        modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });
        modal.addEventListener('close', function () { body.innerHTML = ''; });
    })();

    // Temuan Daily Patrol per departemen: kartu ringkas (bar mendatar) + modal perbesar (batang Total/Open/Close + tabel).
    (function () {
        var card = document.querySelector('[data-temuan]');
        if (!card) return;

        var NS = 'http://www.w3.org/2000/svg';
        var COLORS = { total: '#2a78d6', open: '#eb6834', close: '#1baf7a' };
        var LABELS = { total: 'Total', open: 'Open', close: 'Close' };
        var data = JSON.parse(card.dataset.temuan);
        var select = card.querySelector('[data-temuan-year]');
        var rowsBox = card.querySelector('[data-temuan-rows]');

        function rowsOf(year) { return data[year] || []; }

        function sum(rows, key) { return rows.reduce(function (n, row) { return n + row[key]; }, 0); }

        // delay: ms sebelum bar memanjang (dashboard baru dibuka: tunggu kartunya tampil).
        function renderCompact(delay) {
            var rows = rowsOf(select.value);
            var max = Math.max.apply(null, rows.map(function (row) { return row.total; }).concat([1]));
            rowsBox.innerHTML = '';
            if (!rows.length) {
                rowsBox.innerHTML = '<div class="temuan-empty">Belum ada temuan di tahun ini.</div>';
            }
            rows.forEach(function (row) {
                var line = document.createElement('div');
                line.className = 'temuan-row';
                var dept = document.createElement('span');
                dept.className = 'dept';
                dept.textContent = row.department;
                dept.title = row.department;
                var track = document.createElement('div');
                track.className = 'track';
                ['open', 'close'].forEach(function (key) {
                    if (!row[key]) return;
                    var seg = document.createElement('span');
                    seg.style.width = (row[key] / max * 100) + '%';
                    seg.style.animationDelay = ((typeof delay === 'number' ? delay : 0) + rowsBox.children.length * 60) + 'ms';
                    seg.style.background = COLORS[key];
                    seg.title = row.department + ' · ' + LABELS[key] + ': ' + row[key];
                    track.appendChild(seg);
                });
                var total = document.createElement('span');
                total.className = 'total';
                total.textContent = row.total;
                line.append(dept, track, total);
                rowsBox.appendChild(line);
            });
            card.querySelector('[data-temuan-sum]').innerHTML = '';
            ['total', 'open', 'close'].forEach(function (key) {
                var item = document.createElement('span');
                item.append(LABELS[key] + ' ');
                var value = document.createElement('b');
                value.textContent = sum(rows, key);
                item.appendChild(value);
                card.querySelector('[data-temuan-sum]').appendChild(item);
            });
        }

        function el(name, attrs, parent) {
            var node = document.createElementNS(NS, name);
            Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
            if (parent) parent.appendChild(node);
            return node;
        }

        // Batang dengan ujung atas membulat 4px, dasar rata di garis nol.
        function barPath(x, y, w, h) {
            var r = Math.min(4, w / 2, h);
            return 'M' + x + ' ' + (y + h) + 'V' + (y + r) + 'Q' + x + ' ' + y + ' ' + (x + r) + ' ' + y
                + 'H' + (x + w - r) + 'Q' + (x + w) + ' ' + y + ' ' + (x + w) + ' ' + (y + r) + 'V' + (y + h) + 'Z';
        }

        function drawBars(plot, rows, animate) {
            var tip = plot.querySelector('.chart-tip');
            var old = plot.querySelector('svg');
            if (old) old.remove();
            var width = plot.clientWidth, height = plot.clientHeight;
            var pad = { top: 10, right: 12, bottom: 26, left: 30 };
            var innerW = width - pad.left - pad.right, innerH = height - pad.top - pad.bottom;
            if (innerW <= 0 || !rows.length) return;

            var max = Math.max.apply(null, rows.map(function (row) { return row.total; }).concat([1]));
            var step = Math.max(1, Math.ceil(max / 8));
            var top = Math.ceil(max / step) * step;
            var y = function (v) { return pad.top + innerH - innerH * v / top; };
            var group = innerW / rows.length;
            var barW = Math.min(56, (group * 0.7 - 4) / 3);

            var svg = el('svg', { width: width, height: height, 'aria-hidden': 'true' });
            plot.insertBefore(svg, tip);
            var grid = el('g', { class: 'grid' }, svg), axis = el('g', { class: 'axis' }, svg);
            for (var t = 0; t <= top; t += step) {
                el('line', { x1: pad.left, x2: width - pad.right, y1: y(t), y2: y(t) }, grid);
                el('text', { x: pad.left - 8, y: y(t) + 4, 'text-anchor': 'end' }, axis).textContent = t;
            }

            rows.forEach(function (row, i) {
                var center = pad.left + group * i + group / 2;
                var start = center - (barW * 3 + 4) / 2;
                ['total', 'open', 'close'].forEach(function (key, k) {
                    var h = innerH * row[key] / top;
                    if (h > 0) {
                        var bar = el('path', { d: barPath(start + k * (barW + 2), y(row[key]), barW, h), fill: COLORS[key] }, svg);
                        if (animate) { bar.setAttribute('class', 'bar-grow'); bar.style.animationDelay = (i * 70 + k * 40) + 'ms'; }
                    }
                });
                el('text', { x: center, y: height - 6, 'text-anchor': 'middle', 'font-weight': 600 }, axis).textContent = row.department;

                var hit = el('rect', { x: center - group / 2, y: 0, width: group, height: height, fill: 'transparent' }, svg);
                hit.addEventListener('mouseenter', function () {
                    tip.innerHTML = '';
                    var title = document.createElement('b');
                    title.textContent = row.department;
                    tip.appendChild(title);
                    ['total', 'open', 'close'].forEach(function (key) {
                        var line = document.createElement('div');
                        var swatch = document.createElement('i');
                        swatch.className = 'swatch';
                        swatch.style.setProperty('--c', COLORS[key]);
                        var label = document.createElement('span');
                        label.textContent = LABELS[key];
                        var value = document.createElement('span');
                        value.textContent = row[key];
                        line.append(swatch, label, value);
                        tip.appendChild(line);
                    });
                    tip.hidden = false;
                    var left = center + group / 2;
                    if (left + tip.offsetWidth > width) left = center - group / 2 - tip.offsetWidth;
                    tip.style.left = Math.max(0, left) + 'px';
                    tip.style.top = pad.top + 'px';
                });
                hit.addEventListener('mouseleave', function () { tip.hidden = true; });
            });
        }

        select.addEventListener('change', function () { renderCompact(0); });
        renderCompact(1000);

        // Modal perbesar memakai #chart-modal yang sama dengan grafik kecelakaan.
        card.querySelector('[data-temuan-expand]').addEventListener('click', function () {
            var rows = rowsOf(select.value);
            var modal = document.getElementById('chart-modal');
            var body = document.getElementById('chart-modal-body');
            document.getElementById('chart-modal-title').textContent = 'Grafik Temuan Daily Patrol';
            document.getElementById('chart-modal-sub').textContent = 'Tahun : ' + select.value;
            body.innerHTML = '';

            var legend = document.createElement('div');
            legend.className = 'chart-legend';
            ['total', 'open', 'close'].forEach(function (key) {
                var item = document.createElement('span');
                var swatch = document.createElement('i');
                swatch.className = 'swatch';
                swatch.style.setProperty('--c', COLORS[key]);
                item.append(swatch, LABELS[key].toUpperCase());
                legend.appendChild(item);
            });
            body.appendChild(legend);

            if (!rows.length) {
                var empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'Belum ada temuan di tahun ini.';
                body.appendChild(empty);
                modal.showModal();
                return;
            }

            var plot = document.createElement('div');
            plot.className = 'chart-plot chart-plot-lg';
            plot.innerHTML = '<div class="chart-tip" hidden></div>';
            body.appendChild(plot);

            var wrap = document.createElement('div');
            wrap.className = 'table-wrap';
            var table = document.createElement('table');
            table.className = 'chart-table';
            var head = '<thead><tr><th></th>';
            rows.forEach(function (row) { head += '<th></th>'; });
            table.innerHTML = head + '<th>Jumlah</th></tr></thead><tbody></tbody>';
            table.querySelectorAll('thead th').forEach(function (th, i) { if (i > 0 && i <= rows.length) th.textContent = rows[i - 1].department; });
            ['total', 'open', 'close'].forEach(function (key) {
                var tr = document.createElement('tr');
                var name = document.createElement('td');
                name.className = 'series-name';
                var swatch = document.createElement('i');
                swatch.className = 'swatch';
                swatch.style.setProperty('--c', COLORS[key]);
                name.append(swatch, LABELS[key]);
                tr.appendChild(name);
                rows.forEach(function (row) {
                    var td = document.createElement('td');
                    td.textContent = row[key];
                    tr.appendChild(td);
                });
                var all = document.createElement('td');
                all.style.fontWeight = '700';
                all.textContent = sum(rows, key);
                tr.appendChild(all);
                table.tBodies[0].appendChild(tr);
            });
            wrap.appendChild(table);
            body.appendChild(wrap);

            modal.showModal();
            drawBars(plot, rows, true);
            if ('ResizeObserver' in window) {
                var lastWidth = plot.clientWidth;
                new ResizeObserver(function () {
                    if (plot.isConnected && plot.clientWidth !== lastWidth) { lastWidth = plot.clientWidth; drawBars(plot, rows, false); }
                }).observe(plot);
            }
        });
    })();
</script>
@endpush
