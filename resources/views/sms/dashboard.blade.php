{{-- Dashboard SMS memakai template dashboard modul, dengan susunan grid sendiri (lihat .dash-grid di bawah). --}}
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

    /* Susunan dashboard SMS: baris atas profil | temuan Daily Patrol | Cross Patrol | hari tanpa kecelakaan (pojok kanan),
       lalu grafik Work Accident dan Traffic Accident bersebelahan. */
    .dash-grid {
        grid-template-areas: "profile temuan cross hari" "work work traffic traffic";
    }
    .dash-grid .a-side { display: contents; }
    .a-hari { grid-area: hari; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .a-hari > .card { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .a-temuan { grid-area: temuan; }
    .a-cross { grid-area: cross; }
    .a-cross .chart-plot-sm { flex: 1 1 auto; height: auto; min-height: 160px; }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-areas: "profile hari" "temuan cross" "work traffic"; }
    }
    @media (max-width: 640px) {
        .dash-grid { grid-template-areas: "profile" "temuan" "cross" "hari" "work" "traffic"; }
    }

    /* Kartu temuan Daily Patrol per departemen (kolom kanan): bar mendatar Open + Close, total di ujung. */
    /* Judul di atas, filter bulan/tahun + tombol perbesar di bawahnya supaya judul tidak terlipat. */
    .temuan-card .card-head { margin-bottom: 8px; flex-direction: column; align-items: stretch; }
    .temuan-card .card-head .card-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .temuan-tools .mini-btn { margin-left: auto; }

    /* Rekap kategori x resiko di modal perbesar Temuan Daily Patrol (digambar di canvas supaya bisa disalin sebagai gambar). */
    .recap-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin: 20px 0 8px; }
    .recap-head .card-title { margin: 0; }
    .recap-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .recap-note { font-size: 12px; color: var(--muted); }
    .recap-actions.chart-actions { justify-content: flex-end; margin-bottom: 10px; }
    .recap-wrap { overflow-x: auto; }
    .recap-wrap canvas { display: block; }
    .modal-filter { margin-bottom: 4px; }
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

    /* Kartu grafik Cross Patrol (per bulan): batang bertumpuk Open + Close. */
    .stack-card { display: flex; flex-direction: column; min-width: 0; }
    .stack-card .card-head { margin-bottom: 2px; align-items: flex-start; }
    .stack-card .chart-plot-sm { height: 160px; margin: 8px 0 10px; }
    .stack-card .chart-legend-sm b { color: var(--text); font-variant-numeric: tabular-nums; }
    .chart-plot .axis text.now { fill: var(--primary); font-weight: 700; }
    .chart-tip .tip-sep { margin: 6px 0 4px; padding-top: 6px; border-top: 1px solid var(--line); font-size: 11px; color: var(--faint); }

    /* Heatmap departemen x bulan di modal perbesar Cross Patrol: makin gelap = makin banyak temuan. */
    table.heat-table td.heat { min-width: 34px; border-radius: 6px; background: var(--h, transparent); color: var(--hc, var(--text)); }
    table.heat-table td.heat.zero { color: var(--faint); }
    table.heat-table td.dept-name { text-align: left; white-space: nowrap; font-weight: 600; }
    table.heat-table tr.sum-row td { font-weight: 700; border-top: 1px solid var(--line); }
    table.heat-table .status-col { font-weight: 600; white-space: nowrap; }
    table.heat-table th.status-col .swatch { margin-right: 5px; vertical-align: -1px; }
    .heat-scale { display: flex; align-items: center; gap: 4px; margin-top: 10px; font-size: 11px; color: var(--muted); }
    .heat-scale i { width: 18px; height: 10px; border-radius: 3px; background: var(--h); }
@endsection

@section('progress')
    {{-- Kartu bawaan "Pertumbuhan" tidak dipakai di dashboard SMS. @hasSection butuh isi yang tidak kosong, jadi diisi komentar HTML. --}}
    <!-- Kartu Pertumbuhan tidak dipakai di dashboard SMS -->
@endsection

@section('clock')
    {{-- Kartu "Jam Kerja" diganti grafik temuan Cross Patrol per bulan; detail per departemen di tooltip & modal perbesar. --}}
    <div class="card a-cross stack-card" style="--i: 4;" data-stack-card="cross" data-stack-years="{{ json_encode($grafikCross['data']) }}">
        <div class="card-head">
            <div style="min-width: 0;">
                <h2 class="card-title">Cross Patrol</h2>
                <div class="muted small">Temuan per bulan</div>
            </div>
            <div class="temuan-tools">
                <select data-stack-year aria-label="Tahun">
                    @foreach ($grafikCross['years'] as $year)
                        <option value="{{ $year }}" @selected($year === $grafikCross['selected'])>{{ $year }}</option>
                    @endforeach
                </select>
                <button type="button" class="mini-btn" data-stack-expand title="Perbesar" aria-label="Perbesar grafik Cross Patrol per departemen">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                </button>
                @can('sms-patrol')
                    <a href="{{ route('sms.cross-patrols.index') }}" class="mini-btn" title="Buka Cross Patrol">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                    </a>
                @endcan
            </div>
        </div>
        <div class="chart-plot chart-plot-sm" data-stack-plot role="img"
             aria-label="Grafik temuan Cross Patrol per bulan, Open dan Close. Klik perbesar untuk rincian per departemen.">
            <div class="chart-tip" hidden></div>
        </div>
        <div class="chart-legend chart-legend-sm">
            <span><i class="swatch" style="--c: #eb6834;"></i>Open</span>
            <span><i class="swatch" style="--c: #1baf7a;"></i>Close</span>
            <span>Total <b data-stack-total></b></span>
        </div>
    </div>
@endsection

@section('grid-bottom')
    @include('sms.partials.grafik-kecelakaan', ['chart' => $grafikKecelakaan['work'], 'area' => 'work', 'index' => 5, 'key' => 'work'])
    @include('sms.partials.grafik-kecelakaan', ['chart' => $grafikKecelakaan['traffic'], 'area' => 'traffic', 'index' => 6, 'key' => 'traffic'])
@endsection

@section('side-top')
    {{-- Kartu "Status Akun" diganti 2 kartu: hari tanpa Work Accident & hari tanpa Traffic Accident. --}}
    <div class="a-hari">
    @foreach ($hariTanpa as $item)
        <div class="card" style="--i: 2;" data-hari-tanpa="{{ $item['tipe'] }}">
            <div class="status-top" style="gap: 12px;">
                <h2 class="card-title" style="min-width: 0;">Hari Tanpa {{ $item['label'] }}</h2>
                <b data-count style="white-space: nowrap;">{{ $item['value'] }}</b>
            </div>
            <div class="muted small" style="margin-top: 10px;">{{ $item['note'] }}</div>
        </div>
    @endforeach
    </div>
@endsection

@section('side-bottom')
    <div class="card temuan-card a-temuan" style="--i: 3;" data-temuan="{{ json_encode($grafikTemuan['data']) }}">
        <div class="card-head">
            <h2 class="card-title">Temuan Daily Patrol</h2>
            <div class="temuan-tools">
                <select data-temuan-month aria-label="Bulan">
                    <option value="all">All</option>
                    @foreach (\App\Modules\Sms\Support\GrafikKecelakaan::MONTHS as $index => $monthLabel)
                        <option value="{{ $index + 1 }}" @selected($index + 1 === $grafikTemuan['selectedMonth'])>{{ ucfirst(strtolower($monthLabel)) }}</option>
                    @endforeach
                </select>
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
                var plot = body.querySelector('.chart-plot');
                var series = JSON.parse(plot.dataset.chart).series;
                body.insertBefore(PatrolImage.chartActions(plot, template.dataset.title + ' · ' + template.dataset.subtitle, series.map(function (s) {
                    return { label: s.label, color: s.color, dashed: s.target };
                })), body.firstChild);
                modal.showModal();
                watch(plot, 150);
            });
        });
        modal.querySelector('[data-close]').addEventListener('click', function () { modal.close(); });
        modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });
        modal.addEventListener('close', function () { body.innerHTML = ''; });
    })();

    // Helper bersama modal Daily & Cross Patrol: gambar kanvas, tombol salin/unduh gambar, dan tabel rekap
    // "kategori x resiko" ala form Excel (dipakai rekap Daily per departemen & Progress Perbaikan Cross per zona).
    var PatrolImage = (function () {
        // Kanvas putih resolusi 2x (tajam saat di-paste) dengan ukuran tampilan width x height.
        function blankCanvas(width, height) {
            var canvas = document.createElement('canvas');
            canvas.width = width * 2;
            canvas.height = height * 2;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            var ctx = canvas.getContext('2d');
            ctx.scale(2, 2);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            return { canvas: canvas, ctx: ctx };
        }

        // Tombol "Salin gambar" + "Unduh PNG". getCanvas mengembalikan Promise<canvas> (grafik SVG perlu dimuat dulu).
        function imageActions(getCanvas, getFileName) {
            var actions = document.createElement('div');
            actions.className = 'recap-actions';
            var note = document.createElement('span');
            note.className = 'recap-note';
            note.setAttribute('aria-live', 'polite');
            var copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'btn btn-sm';
            copyBtn.textContent = 'Salin gambar';
            var downloadBtn = document.createElement('button');
            downloadBtn.type = 'button';
            downloadBtn.className = 'btn btn-ghost btn-sm';
            downloadBtn.textContent = 'Unduh PNG';
            actions.append(note, copyBtn, downloadBtn);

            function flash(text) { note.textContent = text; setTimeout(function () { if (note.textContent === text) note.textContent = ''; }, 3000); }
            function download() {
                return getCanvas().then(function (canvas) {
                    var link = document.createElement('a');
                    link.download = getFileName() + '.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                });
            }
            downloadBtn.addEventListener('click', function () { download(); });
            copyBtn.addEventListener('click', function () {
                // Salin gambar ke clipboard hanya diizinkan browser di HTTPS / localhost; selain itu diunduh sebagai PNG.
                if (!window.isSecureContext || !navigator.clipboard || !window.ClipboardItem) {
                    download().then(function () { flash('Browser tidak mengizinkan salin gambar di alamat ini, file PNG diunduh.'); });
                    return;
                }
                var blob = getCanvas().then(function (canvas) {
                    return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/png'); });
                });
                navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
                    .then(function () { flash('Gambar tersalin, tinggal paste.'); })
                    .catch(function () { download().then(function () { flash('Gagal menyalin, file PNG diunduh.'); }); });
            });
            return actions;
        }

        // Judul bagian + elemen di kanan (filter / tombol).
        function sectionHead(text, right, first) {
            var head = document.createElement('div');
            head.className = 'recap-head';
            if (first) head.style.marginTop = '4px';
            var title = document.createElement('h3');
            title.className = 'card-title';
            title.textContent = text;
            head.append(title, right);
            return head;
        }

        /**
         * Tabel rekap: No | label baris | grup kategori (tiap kategori dipecah per resiko) | TOTAL TEMUAN | OPEN | CLOSED.
         * cfg: { rowHeader, rows: [{label, total, open, close, cells: {"kategori.resiko": n}}],
         *        groups: [{label, cols: [{label, keys: [kategori]}]}], resiko: [{code, color}], statusGroup?: label,
         *        colors: {no, label, group, kat, res, total, openHead, closedHead, openBody?, closedBody?}, targetWidth }
         * Lebar kolom minimum; kalau tempatnya lebih lebar, semua kolom diperbesar supaya tabel penuh.
         */
        function recapTable(cfg) {
            var C = cfg.colors;
            var cols = [].concat.apply([], cfg.groups.map(function (g) { return g.cols; }));
            var R = cfg.resiko;
            var W = { no: 40, label: 120, res: 26, total: 92, open: 62, closed: 66 };
            var natural = W.no + W.label + W.res * R.length * cols.length + W.total + W.open + W.closed;
            var factor = Math.max(1, ((cfg.targetWidth || 0) - 1) / natural);
            Object.keys(W).forEach(function (key) { W[key] = Math.floor(W[key] * factor); });
            var catW = W.res * R.length;
            var used = W.no + W.label + catW * cols.length + W.total + W.open + W.closed;
            W.label += Math.max(0, Math.floor(natural * factor) - used);
            var rowH = Math.round(26 * Math.min(factor, 1.2));
            var headH = rowH * 3;
            var width = W.no + W.label + catW * cols.length + W.total + W.open + W.closed + 1;
            var height = headH + rowH * (cfg.rows.length + 1) + 1;
            var font = getComputedStyle(document.body).fontFamily;
            var fontBase = Math.round(12 * Math.min(factor, 1.15));

            var c = blankCanvas(width, height);
            var ctx = c.ctx;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.lineWidth = 1;

            // Kotak berisi teks di tengah; teks dikecilkan kalau tidak muat (mis. ELECTRICAL).
            function cell(x, y, w, h, text, fill, opts) {
                opts = opts || {};
                if (fill) { ctx.fillStyle = fill; ctx.fillRect(x, y, w, h); }
                ctx.strokeStyle = '#7f7f7f';
                ctx.strokeRect(x + .5, y + .5, w, h);
                if (text === '' || text === null || text === undefined) return;
                var size = fontBase;
                do { ctx.font = (opts.bold ? '700 ' : '400 ') + size + 'px ' + font; size--; } while (size > 7 && ctx.measureText(String(text)).width > w - 6);
                ctx.fillStyle = opts.color || '#000000';
                ctx.fillText(String(text), x + w / 2, y + h / 2 + 1);
            }

            function countOf(row, col, code) {
                return col.keys.reduce(function (n, key) { return n + ((row.cells || {})[key + '.' + code] || 0); }, 0);
            }

            // Header 3 baris.
            var x = 0;
            cell(x, 0, W.no, headH, 'No', C.no); x += W.no;
            cell(x, 0, W.label, headH, cfg.rowHeader, C.label); x += W.label;
            cfg.groups.forEach(function (g) {
                cell(x, 0, catW * g.cols.length, rowH, g.label, C.group);
                g.cols.forEach(function (col) {
                    cell(x, rowH, catW, rowH, col.label, C.kat);
                    R.forEach(function (r, k) { cell(x + k * W.res, rowH * 2, W.res, rowH, r.code, C.res, { bold: !!r.color, color: r.color }); });
                    x += catW;
                });
            });
            cell(x, 0, W.total, headH, 'TOTAL TEMUAN', C.total); x += W.total;
            if (cfg.statusGroup) {
                cell(x, 0, W.open + W.closed, rowH, cfg.statusGroup, C.openHead);
                cell(x, rowH, W.open, rowH * 2, 'OPEN', C.openHead);
                cell(x + W.open, rowH, W.closed, rowH * 2, 'CLOSED', C.closedHead);
            } else {
                cell(x, 0, W.open, headH, 'OPEN', C.openHead);
                cell(x + W.open, 0, W.closed, headH, 'CLOSED', C.closedHead);
            }

            // Isi: sel kategori yang nol dibiarkan kosong, baris Total menampilkan 0.
            var sums = {}, sumTotal = 0, sumOpen = 0, sumClose = 0;
            cfg.rows.forEach(function (row, i) {
                var y = headH + i * rowH;
                x = 0;
                cell(x, y, W.no, rowH, i + 1); x += W.no;
                cell(x, y, W.label, rowH, row.label); x += W.label;
                cols.forEach(function (col, ci) {
                    R.forEach(function (r) {
                        var v = countOf(row, col, r.code);
                        sums[ci + '.' + r.code] = (sums[ci + '.' + r.code] || 0) + v;
                        cell(x, y, W.res, rowH, v || ''); x += W.res;
                    });
                });
                cell(x, y, W.total, rowH, row.total); x += W.total;
                cell(x, y, W.open, rowH, row.open, C.openBody); x += W.open;
                cell(x, y, W.closed, rowH, row.close, C.closedBody);
                sumTotal += row.total; sumOpen += row.open; sumClose += row.close;
            });
            var sumY = headH + cfg.rows.length * rowH;
            cell(0, sumY, W.no + W.label, rowH, 'Total', null, { bold: true });
            x = W.no + W.label;
            cols.forEach(function (col, ci) {
                R.forEach(function (r) { cell(x, sumY, W.res, rowH, sums[ci + '.' + r.code] || 0); x += W.res; });
            });
            cell(x, sumY, W.total, rowH, sumTotal); x += W.total;
            cell(x, sumY, W.open, rowH, sumOpen, C.openBody); x += W.open;
            cell(x, sumY, W.closed, rowH, sumClose, C.closedBody);

            c.canvas.setAttribute('role', 'img');
            c.canvas.setAttribute('aria-label', (cfg.ariaLabel || 'Tabel rekap temuan') + '. Total ' + sumTotal + ' temuan, ' + sumOpen + ' open, ' + sumClose + ' closed.');
            return c.canvas;
        }

        // Judul + legenda di atas gambar grafik. legend: [{label, color, dashed?}] (dashed = garis target putus-putus).
        function drawImageHeader(ctx, title, legend, x, y) {
            var font = getComputedStyle(document.body).fontFamily;
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillStyle = '#0f172a';
            ctx.font = '700 15px ' + font;
            ctx.fillText(title, x, y + 9);
            var lx = x;
            ctx.font = '400 12px ' + font;
            legend.forEach(function (item) {
                ctx.fillStyle = item.color;
                if (item.dashed) {
                    ctx.strokeStyle = item.color;
                    ctx.lineWidth = 2;
                    ctx.setLineDash([4, 3]);
                    ctx.beginPath();
                    ctx.moveTo(lx, y + 35);
                    ctx.lineTo(lx + 14, y + 35);
                    ctx.stroke();
                    ctx.setLineDash([]);
                } else {
                    ctx.fillRect(lx, y + 30, 10, 10);
                }
                var swatchW = item.dashed ? 19 : 15;
                ctx.fillStyle = '#475569';
                ctx.fillText(item.label, lx + swatchW, y + 35);
                lx += swatchW + ctx.measureText(item.label).width + 16;
            });
        }

        // Grafik SVG di modal -> PNG: style dari CSS disalin ke elemen SVG supaya ikut tergambar.
        function chartCanvas(plot, title, legend) {
            var svg = plot.querySelector('svg');
            if (!svg) return Promise.reject(new Error('Grafik belum tampil.'));
            var clone = svg.cloneNode(true);
            var originals = svg.querySelectorAll('*'), copies = clone.querySelectorAll('*');
            originals.forEach(function (node, i) {
                var style = getComputedStyle(node);
                ['fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'font-size', 'font-family', 'font-weight'].forEach(function (prop) {
                    copies[i].style.setProperty(prop, style.getPropertyValue(prop));
                });
                copies[i].removeAttribute('class');
            });
            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            var width = Number(svg.getAttribute('width')), height = Number(svg.getAttribute('height'));
            var source = new XMLSerializer().serializeToString(clone);

            return new Promise(function (resolve, reject) {
                var img = new Image();
                img.onload = function () {
                    var pad = 20, headH = 56;
                    var c = blankCanvas(width + pad * 2, height + headH + pad * 2);
                    drawImageHeader(c.ctx, title, legend, pad, pad);
                    c.ctx.drawImage(img, pad, pad + headH, width, height);
                    resolve(c.canvas);
                };
                img.onerror = reject;
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(source);
            });
        }

        function slug(text) { return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }

        // Baris tombol "Salin gambar" + "Unduh PNG" (rata kanan) untuk grafik SVG di dalam plot.
        function chartActions(plot, title, legend) {
            var actions = imageActions(function () { return chartCanvas(plot, title, legend); }, function () { return slug(title); });
            actions.classList.add('chart-actions');
            return actions;
        }

        // Tombol "Export Excel" (link unduhan .xlsx); href diisi pemanggil sesuai periode yang dipilih di modal.
        function exportLink() {
            var link = document.createElement('a');
            link.className = 'btn btn-sm';
            link.textContent = 'Export Excel';
            link.setAttribute('data-turbo', 'false');
            return link;
        }

        return { blankCanvas: blankCanvas, imageActions: imageActions, sectionHead: sectionHead, recapTable: recapTable, chartCanvas: chartCanvas, chartActions: chartActions, slug: slug, exportLink: exportLink };
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
        var monthSelect = card.querySelector('[data-temuan-month]');
        var rowsBox = card.querySelector('[data-temuan-rows]');
        var MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        var KATEGORI = @json(\App\Modules\Sms\Models\DailyPatrol::KATEGORI);
        var RESIKO = @json(array_keys(\App\Modules\Sms\Models\DailyPatrol::RESIKO));
        var blankCanvas = PatrolImage.blankCanvas, imageActions = PatrolImage.imageActions, sectionHead = PatrolImage.sectionHead;

        // Bulan "all" = rekap setahun; selain itu temuan bulan terpilih di tahun terpilih.
        // Kartu & modal punya pilihan bulan/tahun sendiri-sendiri, jadi helper menerima periode sebagai argumen.
        function rowsFor(year, month) {
            var entry = data[year];
            if (!entry) return [];
            return month === 'all' ? entry.all : (entry.months[month] || []);
        }

        function labelFor(year, month) {
            return month === 'all' ? 'Tahun ' + year : MONTH_NAMES[month - 1] + ' ' + year;
        }

        function emptyFor(month) {
            return month === 'all' ? 'Belum ada temuan di tahun ini.' : 'Belum ada temuan di bulan ini.';
        }

        function currentRows() { return rowsFor(select.value, monthSelect.value); }

        function emptyText() { return emptyFor(monthSelect.value); }

        function sum(rows, key) { return rows.reduce(function (n, row) { return n + row[key]; }, 0); }

        // delay: ms sebelum bar memanjang (dashboard baru dibuka: tunggu kartunya tampil).
        function renderCompact(delay) {
            var rows = currentRows();
            var max = Math.max.apply(null, rows.map(function (row) { return row.total; }).concat([1]));
            rowsBox.innerHTML = '';
            if (!rows.length) {
                rowsBox.innerHTML = '<div class="temuan-empty"></div>';
                rowsBox.firstChild.textContent = emptyText();
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

        // Rekap Daily: departemen x kategori (S/H/E), semua di bawah "STOP 6" seperti form referensi.
        // Ergonomi/Kesehatan tidak punya kolom sendiri di form, jadi dihitung di kolom OTHER.
        function drawRecap(rows, targetWidth) {
            var stop6 = Object.keys(KATEGORI['Stop 6']).map(function (key) {
                return { keys: [key], label: KATEGORI['Stop 6'][key].split(' (')[0].toUpperCase() };
            });
            return PatrolImage.recapTable({
                rowHeader: 'Departemen',
                rows: rows.map(function (row) { return { label: row.department, total: row.total, open: row.open, close: row.close, cells: row.cells }; }),
                groups: [{ label: 'STOP 6', cols: stop6.concat([{ keys: ['other', 'ergonomi'], label: 'OTHER' }, { keys: ['5r'], label: '5R' }]) }],
                resiko: RESIKO.map(function (code) { return { code: code }; }),
                colors: { no: '#f8cbad', label: '#ed7d31', group: '#00b0f0', kat: '#00b050', res: '#f8cbad', total: '#ed7d31', openHead: '#ffff00', closedHead: '#00b050' },
                targetWidth: targetWidth,
                ariaLabel: 'Tabel rekap temuan Daily Patrol per departemen, kategori dan resiko',
            });
        }

        function slug(text) { return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }

        // Judul + legenda di bagian atas gambar supaya jelas periodenya saat di-paste di chat / dokumen.
        function chartCanvas(plot, title) {
            return PatrolImage.chartCanvas(plot, title, ['total', 'open', 'close'].map(function (key) {
                return { label: LABELS[key], color: COLORS[key] };
            }));
        }

        // Tabel ringkasan Total / Open / Close per departemen -> PNG.
        function summaryCanvas(rows, title, targetWidth) {
            var font = getComputedStyle(document.body).fontFamily;
            var pad = 20, headH = 34, rowH = 30, firstW = 90, lastW = 76;
            var colW = Math.max(64, Math.floor(((targetWidth || 0) - pad * 2 - firstW - lastW) / Math.max(rows.length, 1)));
            var tableW = firstW + colW * rows.length + lastW;
            var c = blankCanvas(tableW + pad * 2, headH + rowH * 4 + pad * 2);
            var ctx = c.ctx;
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'left';
            ctx.font = '700 15px ' + font;
            ctx.fillText(title, pad, pad + 9);

            function box(x, y, w, h, text, opts) {
                opts = opts || {};
                if (opts.fill) { ctx.fillStyle = opts.fill; ctx.fillRect(x, y, w, h); }
                ctx.strokeStyle = '#cbd5e1';
                ctx.strokeRect(x + .5, y + .5, w, h);
                var size = 12;
                do { ctx.font = (opts.bold ? '700 ' : '400 ') + size + 'px ' + font; size--; } while (size > 8 && ctx.measureText(String(text)).width > w - 8);
                ctx.fillStyle = '#0f172a';
                if (opts.swatch) {
                    ctx.textAlign = 'left';
                    var tw = ctx.measureText(String(text)).width, sx = x + (w - tw - 16) / 2;
                    ctx.fillStyle = opts.swatch;
                    ctx.fillRect(sx, y + h / 2 - 5, 10, 10);
                    ctx.fillStyle = '#0f172a';
                    ctx.fillText(String(text), sx + 16, y + h / 2 + 1);
                } else {
                    ctx.textAlign = 'center';
                    ctx.fillText(String(text), x + w / 2, y + h / 2 + 1);
                }
            }

            var top = pad + headH, x = pad;
            box(x, top, firstW, rowH, '', { fill: '#eef3fb' }); x += firstW;
            rows.forEach(function (row) { box(x, top, colW, rowH, row.department, { fill: '#eef3fb', bold: true }); x += colW; });
            box(x, top, lastW, rowH, 'Jumlah', { fill: '#eef3fb', bold: true });
            ['total', 'open', 'close'].forEach(function (key, k) {
                var y = top + rowH * (k + 1);
                x = pad;
                box(x, y, firstW, rowH, LABELS[key], { swatch: COLORS[key] }); x += firstW;
                rows.forEach(function (row) { box(x, y, colW, rowH, row[key]); x += colW; });
                box(x, y, lastW, rowH, sum(rows, key), { bold: true });
            });
            return c.canvas;
        }

        function summaryTable(rows) {
            var wrap = document.createElement('div');
            wrap.className = 'table-wrap';
            var table = document.createElement('table');
            table.className = 'chart-table';
            var head = '<thead><tr><th></th>';
            rows.forEach(function () { head += '<th></th>'; });
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
            return wrap;
        }

        select.addEventListener('change', function () { renderCompact(0); });
        monthSelect.addEventListener('change', function () { renderCompact(0); });
        renderCompact(1000);

        // Modal perbesar memakai #chart-modal yang sama dengan grafik kecelakaan.
        // Punya pilihan bulan & tahun sendiri (awalnya sama dengan kartu) yang mengatur grafik, ringkasan & rekap.
        var modalState = null;
        card.querySelector('[data-temuan-expand]').addEventListener('click', function () {
            var modal = document.getElementById('chart-modal');
            var body = document.getElementById('chart-modal-body');
            document.getElementById('chart-modal-title').textContent = 'Grafik Temuan Daily Patrol';
            body.innerHTML = '';

            var filter = document.createElement('div');
            filter.className = 'temuan-tools modal-filter';
            var modalMonth = monthSelect.cloneNode(true);
            var modalYear = select.cloneNode(true);
            modalMonth.removeAttribute('data-temuan-month');
            modalYear.removeAttribute('data-temuan-year');
            modalMonth.value = monthSelect.value;
            modalYear.value = select.value;
            // Export Excel mengikuti bulan & tahun yang dipilih di modal.
            var exportLink = PatrolImage.exportLink();
            exportLink.style.marginLeft = 'auto';
            filter.append(modalMonth, modalYear, exportLink);
            var content = document.createElement('div');
            body.append(filter, content);

            function render(animate) {
                var rows = rowsFor(modalYear.value, modalMonth.value);
                var period = labelFor(modalYear.value, modalMonth.value);
                var fileBase = slug(period);
                document.getElementById('chart-modal-sub').textContent = period;
                exportLink.href = @json(route('sms.dashboard.export.daily')) + '?' + new URLSearchParams({ year: modalYear.value, month: modalMonth.value });
                content.innerHTML = '';
                modalState = null;

                if (!rows.length) {
                    var empty = document.createElement('p');
                    empty.className = 'muted';
                    empty.textContent = emptyFor(modalMonth.value);
                    content.appendChild(empty);
                    return;
                }

                // 1. Grafik batang.
                var plot = document.createElement('div');
                plot.className = 'chart-plot chart-plot-lg';
                plot.style.marginTop = '8px';
                plot.innerHTML = '<div class="chart-tip" hidden></div>';
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
                content.append(
                    sectionHead('Grafik Temuan per Departemen', imageActions(function () {
                        return chartCanvas(plot, 'Grafik Temuan Daily Patrol · ' + period);
                    }, function () { return 'grafik-temuan-daily-patrol-' + fileBase; }), true),
                    legend, plot
                );

                // 2. Ringkasan Total / Open / Close.
                var summary = summaryTable(rows);
                content.append(
                    sectionHead('Ringkasan', imageActions(function () {
                        return Promise.resolve(summaryCanvas(rows, 'Ringkasan Temuan Daily Patrol · ' + period, summary.clientWidth));
                    }, function () { return 'ringkasan-temuan-daily-patrol-' + fileBase; })),
                    summary
                );

                // 3. Rekap kategori x resiko (canvas, selebar modal).
                var recapWrap = document.createElement('div');
                recapWrap.className = 'recap-wrap';
                var recapCanvas = null;
                content.append(
                    sectionHead('Rekap per Kategori & Resiko', imageActions(function () {
                        return Promise.resolve(recapCanvas);
                    }, function () { return 'rekap-temuan-daily-patrol-' + fileBase; })),
                    recapWrap
                );

                function redraw(withAnimation) {
                    drawBars(plot, rows, withAnimation);
                    var next = drawRecap(rows, recapWrap.clientWidth);
                    if (recapCanvas) recapCanvas.replaceWith(next); else recapWrap.appendChild(next);
                    recapCanvas = next;
                }
                modalState = { plot: plot, width: plot.clientWidth, redraw: redraw };
                if (modal.open) redraw(animate);
            }

            modalMonth.addEventListener('change', function () { render(true); });
            modalYear.addEventListener('change', function () { render(true); });
            render(true);
            modal.showModal();
            if (modalState) { modalState.width = modalState.plot.clientWidth; modalState.redraw(true); }

            // Satu observer untuk semua pembukaan modal: gambar ulang saat lebar modal berubah.
            if ('ResizeObserver' in window && !body.dataset.temuanObserved) {
                body.dataset.temuanObserved = '1';
                new ResizeObserver(function () {
                    if (!modalState || !modalState.plot.isConnected) return;
                    var width = modalState.plot.clientWidth;
                    if (width !== modalState.width) { modalState.width = width; modalState.redraw(false); }
                }).observe(body);
            }
        });
    })();
    // Kartu Cross Patrol (per bulan): batang bertumpuk Open + Close, tooltip per batang,
    // dan modal perbesar (grafik besar + tabel; Cross Patrol ditambah heatmap departemen x bulan).
    (function () {
        var NS = 'http://www.w3.org/2000/svg';
        var SERIES = [{ key: 'open', label: 'Open', color: '#eb6834' }, { key: 'close', label: 'Close', color: '#1baf7a' }];
        var MONTHS = @json(\App\Modules\Sms\Support\GrafikKecelakaan::MONTHS);
        var MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        // Skala biru berurutan (terang = sedikit, gelap = banyak) untuk heatmap.
        var HEAT = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95'];
        var today = new Date();

        function el(name, attrs, parent) {
            var node = document.createElementNS(NS, name);
            Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
            if (parent) parent.appendChild(node);
            return node;
        }

        function html(tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        function swatch(color) {
            var node = html('i', 'swatch');
            node.style.setProperty('--c', color);
            return node;
        }

        function sum(values) { return values.reduce(function (n, v) { return n + v; }, 0); }

        // Ujung atas membulat 4px (hanya segmen teratas), dasar rata.
        function barPath(x, y, w, h, round) {
            var r = round ? Math.min(4, w / 2, h) : 0;
            return 'M' + x + ' ' + (y + h) + 'V' + (y + r) + 'Q' + x + ' ' + y + ' ' + (x + r) + ' ' + y
                + 'H' + (x + w - r) + 'Q' + (x + w) + ' ' + y + ' ' + (x + w) + ' ' + (y + r) + 'V' + (y + h) + 'Z';
        }

        // chart: { labels, titles, highlight, open: [], close: [], details?: [[{name, count}]] }
        function draw(plot, chart, animateDelay) {
            var tip = plot.querySelector('.chart-tip');
            var old = plot.querySelector('svg');
            if (old) old.remove();
            var width = plot.clientWidth, height = plot.clientHeight;
            var pad = { top: 10, right: 6, bottom: 24, left: 26 };
            var innerW = width - pad.left - pad.right, innerH = height - pad.top - pad.bottom;
            if (innerW <= 0 || innerH <= 0) return;

            var n = chart.labels.length;
            var totals = chart.labels.map(function (_, i) { return chart.open[i] + chart.close[i]; });
            var max = Math.max.apply(null, totals.concat([1]));
            var step = Math.max(1, Math.ceil(max / 4));
            var top = Math.ceil(max / step) * step;
            var y = function (v) { return pad.top + innerH - innerH * v / top; };
            var base = pad.top + innerH;
            var slot = innerW / n;
            var barW = Math.max(4, Math.min(22, slot * 0.56));
            var labelEvery = slot < 22 ? 3 : (slot < 30 ? 2 : 1);

            var svg = el('svg', { width: width, height: height, 'aria-hidden': 'true' });
            plot.insertBefore(svg, tip);
            var grid = el('g', { class: 'grid' }, svg), axis = el('g', { class: 'axis' }, svg);
            for (var t = 0; t <= top; t += step) {
                el('line', { x1: pad.left, x2: width - pad.right, y1: y(t), y2: y(t), 'stroke-dasharray': t === 0 ? 'none' : '3 4' }, grid);
                el('text', { x: pad.left - 8, y: y(t) + 4, 'text-anchor': 'end' }, axis).textContent = t;
            }

            chart.labels.forEach(function (label, i) {
                var center = pad.left + slot * i + slot / 2;
                if (i % labelEvery === 0) {
                    el('text', { x: center, y: height - 6, 'text-anchor': 'middle', class: i === chart.highlight ? 'now' : '' }, axis).textContent = label;
                }

                // Open di bawah, Close di atas; celah 2px antar segmen.
                var bottom = 0;
                var parts = SERIES.filter(function (s) { return chart[s.key][i] > 0; });
                parts.forEach(function (s, k) {
                    var value = chart[s.key][i];
                    var yTop = y(bottom + value), h = y(bottom) - yTop - (k > 0 ? 2 : 0);
                    bottom += value;
                    if (h <= 0) return;
                    var bar = el('path', { d: barPath(center - barW / 2, yTop, barW, h, k === parts.length - 1), fill: s.color }, svg);
                    if (animateDelay !== null) { bar.setAttribute('class', 'bar-grow'); bar.style.animationDelay = (animateDelay + i * 50) + 'ms'; }
                });

                var hit = el('rect', { x: center - slot / 2, y: 0, width: slot, height: base, fill: 'transparent' }, svg);
                hit.addEventListener('mouseenter', function () {
                    tip.innerHTML = '';
                    tip.appendChild(html('b', '', chart.titles[i]));
                    SERIES.forEach(function (s) {
                        var row = html('div');
                        row.append(swatch(s.color), html('span', '', s.label), html('span', '', chart[s.key][i]));
                        tip.appendChild(row);
                    });
                    var total = html('div');
                    total.append(html('span', '', 'Total'), html('span', '', totals[i]));
                    tip.appendChild(total);
                    var details = (chart.details || [])[i] || [];
                    if (details.length) {
                        tip.appendChild(html('div', 'tip-sep', 'Per departemen'));
                        details.slice(0, 6).forEach(function (d) {
                            var row = html('div');
                            row.append(html('span', '', d.name), html('span', '', d.count));
                            tip.appendChild(row);
                        });
                        if (details.length > 6) tip.appendChild(html('div', 'tip-sep', '+' + (details.length - 6) + ' departemen lain'));
                    }
                    tip.hidden = false;
                    var left = center + slot / 2 + 6;
                    if (left + tip.offsetWidth > width) left = center - slot / 2 - tip.offsetWidth - 6;
                    tip.style.left = Math.max(0, left) + 'px';
                    tip.style.top = pad.top + 'px';
                });
                hit.addEventListener('mouseleave', function () { tip.hidden = true; });
            });
        }

        function watch(plot, getChart, animateDelay) {
            draw(plot, getChart(), reduceMotion ? null : animateDelay);
            if (!('ResizeObserver' in window)) return;
            // Lebar & tinggi dipantau: plot Cross Patrol mengisi tinggi kartu, yang ikut berubah saat isi kartu sebelahnya berubah.
            var lastSize = plot.clientWidth + 'x' + plot.clientHeight;
            new ResizeObserver(function () {
                var size = plot.clientWidth + 'x' + plot.clientHeight;
                if (plot.isConnected && size !== lastSize) { lastSize = size; draw(plot, getChart(), null); }
            }).observe(plot);
        }

        // Tabel angka Open / Close / Total per kolom (hari atau bulan).
        function totalsTable(chart) {
            var wrap = html('div', 'table-wrap');
            var table = html('table', 'chart-table');
            var headRow = html('tr');
            headRow.appendChild(html('th'));
            chart.labels.forEach(function (label) { headRow.appendChild(html('th', '', label)); });
            headRow.appendChild(html('th', '', 'Jumlah'));
            table.appendChild(html('thead')).appendChild(headRow);
            var body = table.appendChild(html('tbody'));
            SERIES.concat([{ key: 'total', label: 'Total' }]).forEach(function (s) {
                var values = s.key === 'total' ? chart.labels.map(function (_, i) { return chart.open[i] + chart.close[i]; }) : chart[s.key];
                var tr = html('tr');
                var name = html('td', 'series-name');
                if (s.color) name.appendChild(swatch(s.color));
                name.append(s.label);
                tr.appendChild(name);
                values.forEach(function (v) { tr.appendChild(html('td', '', v)); });
                var all = html('td', '', sum(values));
                all.style.fontWeight = '700';
                tr.appendChild(all);
                body.appendChild(tr);
            });
            wrap.appendChild(table);
            return wrap;
        }

        // Rincian per departemen untuk tooltip: hanya yang ada temuannya, terbanyak dulu.
        function detailsOf(departments, count) {
            var details = [];
            for (var i = 0; i < count; i++) {
                details.push(departments
                    .filter(function (d) { return d.values[i] > 0; })
                    .map(function (d) { return { name: d.department, count: d.values[i] }; })
                    .sort(function (a, b) { return b.count - a.count; }));
            }
            return details;
        }

        // Heatmap departemen x kolom (hari untuk Daily, bulan untuk Cross).
        function heatTable(departments, labels, titles) {
            var wrap = html('div', 'table-wrap');
            if (!departments.length) {
                wrap.appendChild(html('p', 'muted', 'Belum ada temuan.'));
                return wrap;
            }
            var max = Math.max.apply(null, departments.map(function (d) { return Math.max.apply(null, d.values); }).concat([1]));
            var table = html('table', 'chart-table heat-table');
            var headRow = html('tr');
            headRow.appendChild(html('th', '', 'Departemen'));
            labels.forEach(function (label) { headRow.appendChild(html('th', '', label)); });
            headRow.appendChild(html('th', '', 'Jumlah'));
            SERIES.forEach(function (s) {
                var th = html('th', 'status-col');
                th.append(swatch(s.color), s.label);
                headRow.appendChild(th);
            });
            table.appendChild(html('thead')).appendChild(headRow);
            var body = table.appendChild(html('tbody'));
            var monthTotals = labels.map(function () { return 0; });
            var statusTotals = { open: 0, close: 0 };
            departments.forEach(function (d) {
                var tr = html('tr');
                tr.appendChild(html('td', 'dept-name', d.department));
                d.values.forEach(function (v, i) {
                    monthTotals[i] += v;
                    var td = html('td', 'heat' + (v ? '' : ' zero'), v || '–');
                    if (v) {
                        var level = Math.min(HEAT.length - 1, Math.ceil(v / max * HEAT.length) - 1);
                        td.style.setProperty('--h', HEAT[level]);
                        if (level >= 3) td.style.setProperty('--hc', '#fff');
                    }
                    td.title = d.department + ' · ' + titles[i] + ': ' + v + ' temuan';
                    tr.appendChild(td);
                });
                var all = html('td', '', d.total);
                all.style.fontWeight = '700';
                tr.appendChild(all);
                // Dari jumlah itu, berapa yang masih Open & sudah Close.
                SERIES.forEach(function (s) {
                    var v = d[s.key] || 0;
                    statusTotals[s.key] += v;
                    var td = html('td', 'status-col', v);
                    td.style.color = s.color;
                    td.title = d.department + ' · ' + s.label + ': ' + v + ' temuan';
                    tr.appendChild(td);
                });
                body.appendChild(tr);
            });
            var sumRow = html('tr', 'sum-row');
            sumRow.appendChild(html('td', 'dept-name', 'Total'));
            monthTotals.forEach(function (v) { sumRow.appendChild(html('td', '', v)); });
            sumRow.appendChild(html('td', '', sum(monthTotals)));
            SERIES.forEach(function (s) {
                var td = html('td', 'status-col', statusTotals[s.key]);
                td.style.color = s.color;
                sumRow.appendChild(td);
            });
            body.appendChild(sumRow);
            wrap.appendChild(table);

            var scale = html('div', 'heat-scale');
            scale.append('Sedikit');
            HEAT.forEach(function (color) { var i = html('i'); i.style.setProperty('--h', color); scale.appendChild(i); });
            scale.append('Banyak');
            wrap.appendChild(scale);
            return wrap;
        }

        function openModal(title, subtitle, chart, extra) {
            var modal = document.getElementById('chart-modal');
            var body = document.getElementById('chart-modal-body');
            document.getElementById('chart-modal-title').textContent = title;
            document.getElementById('chart-modal-sub').textContent = subtitle;
            body.innerHTML = '';
            var legend = html('div', 'chart-legend');
            SERIES.forEach(function (s) { var item = html('span'); item.append(swatch(s.color), s.label); legend.appendChild(item); });
            var plot = html('div', 'chart-plot chart-plot-lg');
            plot.innerHTML = '<div class="chart-tip" hidden></div>';
            body.append(PatrolImage.chartActions(plot, title + ' · ' + subtitle, SERIES), legend, plot);
            body.appendChild(totalsTable(chart));
            if (extra) {
                var heading = html('h3', 'card-title', 'Temuan per Departemen');
                heading.style.margin = '20px 0 8px';
                body.append(heading, extra);
            }
            modal.showModal();
            watch(plot, function () { return chart; }, 100);
        }

        // Tabel "Progress Perbaikan Cross Patrol" di modal perbesar: zona x kategori (resiko A/B/C) + Total / Open / Closed,
        // dengan pilihan bulan (All = setahun) & tahun sendiri, bisa disalin / diunduh sebagai gambar.
        var CROSS_KATEGORI = @json(\App\Modules\Sms\Models\CrossPatrol::KATEGORI);
        var CROSS_ZONA = @json(\App\Modules\Sms\Models\CrossPatrol::ZONA);

        function crossProgressSection(years, initialYear) {
            var section = html('div');
            var right = html('div', 'recap-actions');
            var monthSelect = html('select');
            monthSelect.setAttribute('aria-label', 'Bulan');
            monthSelect.appendChild(new Option('All', 'all'));
            MONTHS.forEach(function (label, i) { monthSelect.appendChild(new Option(label.charAt(0) + label.slice(1).toLowerCase(), String(i + 1))); });
            var yearSelect = html('select');
            yearSelect.setAttribute('aria-label', 'Tahun');
            Object.keys(years).sort().reverse().forEach(function (year) { yearSelect.appendChild(new Option(year, year)); });
            yearSelect.value = String(initialYear);
            // Default bulan berjalan kalau tahun yang dibuka = tahun ini; tahun lain langsung rekap setahun.
            monthSelect.value = Number(initialYear) === today.getFullYear() ? String(today.getMonth() + 1) : 'all';

            var filter = html('div', 'temuan-tools');
            filter.append(monthSelect, yearSelect);
            var wrap = html('div', 'recap-wrap');
            var canvas = null;

            function period() {
                return monthSelect.value === 'all' ? 'Tahun ' + yearSelect.value : MONTH_NAMES[monthSelect.value - 1] + ' ' + yearSelect.value;
            }

            var actions = PatrolImage.imageActions(function () { return Promise.resolve(canvas); }, function () {
                return 'progress-perbaikan-cross-patrol-' + period().toLowerCase().replace(/[^a-z0-9]+/g, '-');
            });
            right.append(filter, actions);
            section.append(PatrolImage.sectionHead('Progress Perbaikan Cross Patrol', right), wrap);

            var stop6 = Object.keys(CROSS_KATEGORI['Stop 6']).map(function (key) {
                return { keys: [key], label: CROSS_KATEGORI['Stop 6'][key].split(' (')[0].toUpperCase() };
            });

            section.render = function () {
                var entry = years[yearSelect.value] || {};
                var zonas = entry.zonas ? (monthSelect.value === 'all' ? entry.zonas.all : entry.zonas.months[monthSelect.value]) : {};
                zonas = zonas || {};
                var empty = { total: 0, open: 0, close: 0, cells: {} };
                // Zona 1-9 selalu tampil; data lama tanpa zona (key 0) ditaruh di baris terakhir kalau ada.
                var rows = CROSS_ZONA.map(function (zona) { return Object.assign({ label: 'ZONA ' + zona }, zonas[zona] || empty); });
                if (zonas[0]) rows.push(Object.assign({ label: 'Tanpa zona' }, zonas[0]));

                var next = PatrolImage.recapTable({
                    rowHeader: 'Zona',
                    rows: rows,
                    // Ergonomi/Kesehatan tidak punya kolom sendiri di form, jadi dihitung di kolom OTHER.
                    groups: [
                        { label: 'STOP 6', cols: stop6 },
                        { label: 'LAIN-LAIN', cols: [{ keys: ['other', 'ergonomi'], label: 'OTHER' }, { keys: ['5r'], label: '5R' }] },
                    ],
                    resiko: [{ code: 'A', color: '#ff0000' }, { code: 'B', color: '#00b0f0' }, { code: 'C', color: '#000000' }],
                    statusGroup: 'PERBAIKAN STATUS',
                    colors: { no: '#ddd9c4', label: '#ddd9c4', group: '#ddd9c4', kat: '#ddd9c4', res: '#ffffff', total: '#ddd9c4', openHead: '#ddd9c4', closedHead: '#ddd9c4', openBody: '#ffff00', closedBody: '#00b050' },
                    targetWidth: wrap.clientWidth,
                    ariaLabel: 'Tabel progress perbaikan Cross Patrol ' + period() + ' per zona, kategori dan resiko',
                });
                if (canvas) canvas.replaceWith(next); else wrap.appendChild(next);
                canvas = next;
            };

            section.filters = function () { return { month: monthSelect.value, year: yearSelect.value }; };
            monthSelect.addEventListener('change', section.render);
            yearSelect.addEventListener('change', section.render);
            if ('ResizeObserver' in window) {
                var lastWidth = 0;
                new ResizeObserver(function () {
                    if (wrap.isConnected && wrap.clientWidth && wrap.clientWidth !== lastWidth) { lastWidth = wrap.clientWidth; section.render(); }
                }).observe(wrap);
            }
            return section;
        }

        // Cross Patrol: data semua tahun dikirim sekaligus; grafik kartu ganti saat tahun dipilih.
        var crossCard = document.querySelector('[data-stack-card="cross"]');
        if (crossCard) {
            var years = JSON.parse(crossCard.dataset.stackYears);
            var select = crossCard.querySelector('[data-stack-year]');
            var crossPlot = crossCard.querySelector('[data-stack-plot]');

            var crossChart = function () {
                var year = Number(select.value);
                var data = years[year];
                return {
                    labels: MONTHS,
                    titles: MONTH_NAMES.map(function (m) { return m + ' ' + year; }),
                    highlight: year === today.getFullYear() ? today.getMonth() : -1,
                    open: data.open,
                    close: data.close,
                    details: detailsOf(data.departments, MONTHS.length),
                };
            };
            var showTotal = function () {
                var data = years[select.value];
                crossCard.querySelector('[data-stack-total]').textContent = sum(data.open) + sum(data.close);
            };

            showTotal();
            watch(crossPlot, crossChart, 1050);
            select.addEventListener('change', function () { showTotal(); draw(crossPlot, crossChart(), reduceMotion ? null : 0); });
            crossCard.querySelector('[data-stack-expand]').addEventListener('click', function () {
                openModal('Grafik Temuan Cross Patrol', 'Per bulan · Tahun ' + select.value, crossChart(), heatTable(years[select.value].departments, MONTHS, crossChart().titles));
                var progress = crossProgressSection(years, select.value);
                var modalBody = document.getElementById('chart-modal-body');
                modalBody.appendChild(progress);
                progress.render();

                // Export Excel di baris tombol atas: tahun grafik + bulan/tahun tabel Progress Perbaikan.
                var exportLink = PatrolImage.exportLink();
                var year = select.value;
                var updateExport = function () {
                    var filters = progress.filters();
                    exportLink.href = @json(route('sms.dashboard.export.cross')) + '?' + new URLSearchParams({ year: year, progress_year: filters.year, progress_month: filters.month });
                };
                progress.querySelectorAll('select').forEach(function (s) { s.addEventListener('change', updateExport); });
                updateExport();
                modalBody.querySelector('.chart-actions').prepend(exportLink);
            });
        }
    })();
</script>
@endpush
