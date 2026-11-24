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

    /* Susunan dashboard SMS: baris atas profil | temuan Daily Patrol | Cross Patrol, lalu bar chart kecelakaan selebar halaman. */
    .dash-grid {
        grid-template-areas: "profile temuan cross cross" "kecelakaan kecelakaan kecelakaan kecelakaan";
    }
    .dash-grid .a-side { display: contents; }
    .a-kecelakaan { grid-area: kecelakaan; }
    .a-temuan { grid-area: temuan; }
    .a-cross { grid-area: cross; }
    .a-cross .chart-plot-sm { flex: 1 1 auto; height: auto; min-height: 160px; }
    .bigstat .lbl { max-width: 120px; line-height: 1.3; }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-areas: "profile temuan" "cross cross" "kecelakaan kecelakaan"; }
    }
    @media (max-width: 640px) {
        .dash-grid { grid-template-areas: "profile" "temuan" "cross" "kecelakaan"; }
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
    /* Tombol ikon Excel / Salin / Unduh di modal, selalu sebaris. */
    .icon-action { flex: none; cursor: pointer; transition: background .15s, color .15s; }
    .icon-action:hover { background: var(--soft-2); color: var(--primary); }
    .icon-action.icon-excel { background: #1d6f42; border-color: #1d6f42; color: #fff; }
    .icon-action.icon-excel:hover { background: #185c37; color: #fff; }
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
                <h2 class="card-title">{{ __('Cross Patrol') }}</h2>
                <div class="muted small">{{ __('Temuan per bulan') }}</div>
            </div>
            <div class="temuan-tools">
                <select data-stack-year aria-label="{{ __('Tahun') }}">
                    @foreach ($grafikCross['years'] as $year)
                        <option value="{{ $year }}" @selected($year === $grafikCross['selected'])>{{ $year }}</option>
                    @endforeach
                </select>
                <button type="button" class="mini-btn" data-stack-expand title="{{ __('Perbesar') }}" aria-label="{{ __('Perbesar grafik Cross Patrol per departemen') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                </button>
                @can('sms-patrol')
                    <a href="{{ route('sms.cross-patrols.index') }}" class="mini-btn" title="{{ __('Buka Cross Patrol') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                    </a>
                @endcan
            </div>
        </div>
        <div class="chart-plot chart-plot-sm" data-stack-plot role="img"
             aria-label="{{ __('Grafik temuan Cross Patrol per bulan, Open dan Close. Klik perbesar untuk rincian per departemen.') }}">
            <div class="chart-tip" hidden></div>
        </div>
        <div class="chart-legend chart-legend-sm">
            <span><i class="swatch" style="--c: #eb6834;"></i>{{ __('Open') }}</span>
            <span><i class="swatch" style="--c: #1baf7a;"></i>{{ __('Close') }}</span>
            <span>{{ __('Total') }} <b data-stack-total></b></span>
        </div>
    </div>
@endsection

@section('grid-bottom')
    @include('sms.partials.grafik-kecelakaan', ['chart' => $grafikKecelakaan, 'index' => 5])
@endsection

@section('side-top')
    {{-- Hari tanpa kecelakaan pindah ke angka besar di header. @hasSection butuh isi yang tidak kosong, jadi diisi komentar HTML. --}}
    <!-- Kartu komposisi tidak dipakai di dashboard SMS -->
@endsection

@section('side-bottom')
    <div class="card temuan-card a-temuan" style="--i: 3;" data-temuan="{{ json_encode($grafikTemuan['data']) }}">
        <div class="card-head">
            <h2 class="card-title">{{ __('Temuan Daily Patrol') }}</h2>
            <div class="temuan-tools">
                <select data-temuan-month aria-label="{{ __('Bulan') }}">
                    <option value="all">{{ __('All') }}</option>
                    @foreach (\App\Modules\Sms\Support\GrafikKecelakaan::MONTHS as $index => $monthLabel)
                        <option value="{{ $index + 1 }}" @selected($index + 1 === $grafikTemuan['selectedMonth'])>{{ ucfirst(strtolower($monthLabel)) }}</option>
                    @endforeach
                </select>
                <select data-temuan-year aria-label="{{ __('Tahun') }}">
                    @foreach ($grafikTemuan['years'] as $year)
                        <option value="{{ $year }}" @selected($year === $grafikTemuan['selected'])>{{ $year }}</option>
                    @endforeach
                </select>
                <button type="button" class="mini-btn" data-temuan-expand title="{{ __('Perbesar') }}" aria-label="{{ __('Perbesar grafik temuan Daily Patrol') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                </button>
            </div>
        </div>
        <div class="chart-legend chart-legend-sm" style="margin-top: 0;">
            <span><i class="swatch" style="--c: #eb6834;"></i>{{ __('Open') }}</span>
            <span><i class="swatch" style="--c: #1baf7a;"></i>{{ __('Close') }}</span>
            <span>{{ __('angka = total') }}</span>
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
        <button type="button" class="modal-close" data-close aria-label="{{ __('Tutup') }}">&times;</button>
    </div>
    <div class="modal-body" id="chart-modal-body"></div>
</dialog>

<script>
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    (function () {
        // Modal perbesar dipakai bersama kartu Daily & Cross Patrol.
        var modal = document.getElementById('chart-modal');
        var body = document.getElementById('chart-modal-body');
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

        // Tombol aksi di modal berupa ikon bulat; teksnya jadi tooltip & label aksesibilitas.
        var ICONS = {
            copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
            download: '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
            excel: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 13l5 5M14 13l-5 5"/>',
        };

        function iconButton(tag, label, icon, extraClass) {
            var button = document.createElement(tag);
            if (tag === 'button') button.type = 'button';
            button.className = 'mini-btn icon-action' + (extraClass ? ' ' + extraClass : '');
            button.title = label;
            button.setAttribute('aria-label', label);
            button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + icon + '</svg>';
            return button;
        }

        // Tombol "Salin gambar" + "Unduh PNG". getCanvas mengembalikan Promise<canvas> (grafik SVG perlu dimuat dulu).
        function imageActions(getCanvas, getFileName) {
            var actions = document.createElement('div');
            actions.className = 'recap-actions';
            var note = document.createElement('span');
            note.className = 'recap-note';
            note.setAttribute('aria-live', 'polite');
            var copyBtn = iconButton('button', __t('Salin gambar'), ICONS.copy);
            var downloadBtn = iconButton('button', __t('Unduh PNG'), ICONS.download);
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
                    download().then(function () { flash(__t('Browser tidak mengizinkan salin gambar di alamat ini, file PNG diunduh.')); });
                    return;
                }
                var blob = getCanvas().then(function (canvas) {
                    return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/png'); });
                });
                navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
                    .then(function () { flash(__t('Gambar tersalin, tinggal paste.')); })
                    .catch(function () { download().then(function () { flash(__t('Gagal menyalin, file PNG diunduh.')); }); });
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
            cell(x, 0, W.total, headH, __t('TOTAL TEMUAN'), C.total); x += W.total;
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
            cell(0, sumY, W.no + W.label, rowH, __t('Total'), null, { bold: true });
            x = W.no + W.label;
            cols.forEach(function (col, ci) {
                R.forEach(function (r) { cell(x, sumY, W.res, rowH, sums[ci + '.' + r.code] || 0); x += W.res; });
            });
            cell(x, sumY, W.total, rowH, sumTotal); x += W.total;
            cell(x, sumY, W.open, rowH, sumOpen, C.openBody); x += W.open;
            cell(x, sumY, W.closed, rowH, sumClose, C.closedBody);

            c.canvas.setAttribute('role', 'img');
            c.canvas.setAttribute('aria-label', (cfg.ariaLabel || __t('Tabel rekap temuan')) + '. ' + __t('Total :total temuan, :open open, :close closed.', { total: sumTotal, open: sumOpen, close: sumClose }));
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
            if (!svg) return Promise.reject(new Error(__t('Grafik belum tampil.')));
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

        // Tombol "Export Excel" (link unduhan .xlsx, ikon hijau); href diisi pemanggil sesuai periode yang dipilih di modal.
        function exportLink() {
            var link = iconButton('a', __t('Export Excel'), ICONS.excel, 'icon-excel');
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
        var LABELS = { total: __t('Total'), open: __t('Open'), close: __t('Close') };
        var data = JSON.parse(card.dataset.temuan);
        var select = card.querySelector('[data-temuan-year]');
        var monthSelect = card.querySelector('[data-temuan-month]');
        var rowsBox = card.querySelector('[data-temuan-rows]');
        var MONTH_NAMES = {{ Js::from(collect(range(1, 12))->map(fn (int $bulan) => \Illuminate\Support\Carbon::create(2000, $bulan, 1)->translatedFormat('F'))) }};
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
            return month === 'all' ? __t('Tahun :tahun', { tahun: year }) : MONTH_NAMES[month - 1] + ' ' + year;
        }

        function emptyFor(month) {
            return month === 'all' ? __t('Belum ada temuan di tahun ini.') : __t('Belum ada temuan di bulan ini.');
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
                rowHeader: __t('Departemen'),
                rows: rows.map(function (row) { return { label: row.department, total: row.total, open: row.open, close: row.close, cells: row.cells }; }),
                groups: [{ label: 'STOP 6', cols: stop6.concat([{ keys: ['other', 'ergonomi'], label: 'OTHER' }, { keys: ['5r'], label: '5R' }]) }],
                resiko: RESIKO.map(function (code) { return { code: code }; }),
                colors: { no: '#f8cbad', label: '#ed7d31', group: '#00b0f0', kat: '#00b050', res: '#f8cbad', total: '#ed7d31', openHead: '#ffff00', closedHead: '#00b050' },
                targetWidth: targetWidth,
                ariaLabel: __t('Tabel rekap temuan Daily Patrol per departemen, kategori dan resiko'),
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
            box(x, top, lastW, rowH, __t('Jumlah'), { fill: '#eef3fb', bold: true });
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
            document.getElementById('chart-modal-title').textContent = __t('Grafik Temuan Daily Patrol');
            body.innerHTML = '';

            var filter = document.createElement('div');
            filter.className = 'temuan-tools modal-filter';
            var modalMonth = monthSelect.cloneNode(true);
            var modalYear = select.cloneNode(true);
            modalMonth.removeAttribute('data-temuan-month');
            modalYear.removeAttribute('data-temuan-year');
            modalMonth.value = monthSelect.value;
            modalYear.value = select.value;
            // Export Excel mengikuti bulan & tahun yang dipilih di modal; ikonnya sebaris dengan Salin/Unduh grafik.
            var exportLink = PatrolImage.exportLink();
            filter.append(modalMonth, modalYear);
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
                var chartButtons = imageActions(function () {
                    return chartCanvas(plot, __t('Grafik Temuan Daily Patrol') + ' · ' + period);
                }, function () { return 'grafik-temuan-daily-patrol-' + fileBase; });
                chartButtons.insertBefore(exportLink, chartButtons.querySelector('.icon-action'));
                content.append(sectionHead(__t('Grafik Temuan per Departemen'), chartButtons, true), legend, plot);

                // 2. Ringkasan Total / Open / Close.
                var summary = summaryTable(rows);
                content.append(
                    sectionHead(__t('Ringkasan'), imageActions(function () {
                        return Promise.resolve(summaryCanvas(rows, __t('Ringkasan Temuan Daily Patrol') + ' · ' + period, summary.clientWidth));
                    }, function () { return 'ringkasan-temuan-daily-patrol-' + fileBase; })),
                    summary
                );

                // 3. Rekap kategori x resiko (canvas, selebar modal).
                var recapWrap = document.createElement('div');
                recapWrap.className = 'recap-wrap';
                var recapCanvas = null;
                content.append(
                    sectionHead(__t('Rekap per Kategori & Resiko'), imageActions(function () {
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
        var SERIES = [{ key: 'open', label: __t('Open'), color: '#eb6834' }, { key: 'close', label: __t('Close'), color: '#1baf7a' }];
        var MONTHS = {{ Js::from(array_map(fn (string $bulan) => __($bulan), \App\Modules\Sms\Support\GrafikKecelakaan::MONTHS)) }};
        var MONTH_NAMES = {{ Js::from(collect(range(1, 12))->map(fn (int $bulan) => \Illuminate\Support\Carbon::create(2000, $bulan, 1)->translatedFormat('F'))) }};
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
                    total.append(html('span', '', __t('Total')), html('span', '', totals[i]));
                    tip.appendChild(total);
                    var details = (chart.details || [])[i] || [];
                    if (details.length) {
                        tip.appendChild(html('div', 'tip-sep', __t('Per departemen')));
                        details.slice(0, 6).forEach(function (d) {
                            var row = html('div');
                            row.append(html('span', '', d.name), html('span', '', d.count));
                            tip.appendChild(row);
                        });
                        if (details.length > 6) tip.appendChild(html('div', 'tip-sep', __t('+:count departemen lain', { count: details.length - 6 })));
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
            headRow.appendChild(html('th', '', __t('Jumlah')));
            table.appendChild(html('thead')).appendChild(headRow);
            var body = table.appendChild(html('tbody'));
            SERIES.concat([{ key: 'total', label: __t('Total') }]).forEach(function (s) {
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
                wrap.appendChild(html('p', 'muted', __t('Belum ada temuan.')));
                return wrap;
            }
            var max = Math.max.apply(null, departments.map(function (d) { return Math.max.apply(null, d.values); }).concat([1]));
            var table = html('table', 'chart-table heat-table');
            var headRow = html('tr');
            headRow.appendChild(html('th', '', __t('Departemen')));
            labels.forEach(function (label) { headRow.appendChild(html('th', '', label)); });
            headRow.appendChild(html('th', '', __t('Jumlah')));
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
            sumRow.appendChild(html('td', 'dept-name', __t('Total')));
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
            scale.append(__t('Sedikit'));
            HEAT.forEach(function (color) { var i = html('i'); i.style.setProperty('--h', color); scale.appendChild(i); });
            scale.append(__t('Banyak'));
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
                var heading = html('h3', 'card-title', __t('Temuan per Departemen'));
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
            monthSelect.setAttribute('aria-label', __t('Bulan'));
            monthSelect.appendChild(new Option(__t('All'), 'all'));
            MONTHS.forEach(function (label, i) { monthSelect.appendChild(new Option(label.charAt(0) + label.slice(1).toLowerCase(), String(i + 1))); });
            var yearSelect = html('select');
            yearSelect.setAttribute('aria-label', __t('Tahun'));
            Object.keys(years).sort().reverse().forEach(function (year) { yearSelect.appendChild(new Option(year, year)); });
            yearSelect.value = String(initialYear);
            // Default bulan berjalan kalau tahun yang dibuka = tahun ini; tahun lain langsung rekap setahun.
            monthSelect.value = Number(initialYear) === today.getFullYear() ? String(today.getMonth() + 1) : 'all';

            var filter = html('div', 'temuan-tools');
            filter.append(monthSelect, yearSelect);
            var wrap = html('div', 'recap-wrap');
            var canvas = null;

            function period() {
                return monthSelect.value === 'all' ? __t('Tahun :tahun', { tahun: yearSelect.value }) : MONTH_NAMES[monthSelect.value - 1] + ' ' + yearSelect.value;
            }

            var actions = PatrolImage.imageActions(function () { return Promise.resolve(canvas); }, function () {
                return 'progress-perbaikan-cross-patrol-' + period().toLowerCase().replace(/[^a-z0-9]+/g, '-');
            });
            right.append(filter, actions);
            section.append(PatrolImage.sectionHead(__t('Progress Perbaikan Cross Patrol'), right), wrap);

            var stop6 = Object.keys(CROSS_KATEGORI['Stop 6']).map(function (key) {
                return { keys: [key], label: CROSS_KATEGORI['Stop 6'][key].split(' (')[0].toUpperCase() };
            });

            section.render = function () {
                var entry = years[yearSelect.value] || {};
                var zonas = entry.zonas ? (monthSelect.value === 'all' ? entry.zonas.all : entry.zonas.months[monthSelect.value]) : {};
                zonas = zonas || {};
                var empty = { total: 0, open: 0, close: 0, cells: {} };
                // Zona 1-9 selalu tampil; data lama tanpa zona (key 0) ditaruh di baris terakhir kalau ada.
                var rows = CROSS_ZONA.map(function (zona) { return Object.assign({ label: __t('ZONA') + ' ' + zona }, zonas[zona] || empty); });
                if (zonas[0]) rows.push(Object.assign({ label: __t('Tanpa zona') }, zonas[0]));

                var next = PatrolImage.recapTable({
                    rowHeader: __t('Zona'),
                    rows: rows,
                    // Ergonomi/Kesehatan tidak punya kolom sendiri di form, jadi dihitung di kolom OTHER.
                    groups: [
                        { label: 'STOP 6', cols: stop6 },
                        { label: __t('LAIN-LAIN'), cols: [{ keys: ['other', 'ergonomi'], label: 'OTHER' }, { keys: ['5r'], label: '5R' }] },
                    ],
                    resiko: [{ code: 'A', color: '#ff0000' }, { code: 'B', color: '#00b0f0' }, { code: 'C', color: '#000000' }],
                    statusGroup: __t('PERBAIKAN STATUS'),
                    colors: { no: '#ddd9c4', label: '#ddd9c4', group: '#ddd9c4', kat: '#ddd9c4', res: '#ffffff', total: '#ddd9c4', openHead: '#ddd9c4', closedHead: '#ddd9c4', openBody: '#ffff00', closedBody: '#00b050' },
                    targetWidth: wrap.clientWidth,
                    ariaLabel: __t('Tabel progress perbaikan Cross Patrol :periode per zona, kategori dan resiko', { periode: period() }),
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
                openModal(__t('Grafik Temuan Cross Patrol'), __t('Per bulan · Tahun :tahun', { tahun: select.value }), crossChart(), heatTable(years[select.value].departments, MONTHS, crossChart().titles));
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
                var chartActions = modalBody.querySelector('.chart-actions');
                chartActions.insertBefore(exportLink, chartActions.querySelector('.icon-action'));
            });
        }
    })();
    // Bar chart kecelakaan per bulan (Work vs Traffic) + tabel detail yang bisa disalin ke Excel / diunduh .xlsx.
    (function () {
        var card = document.querySelector('[data-kecelakaan]');
        if (!card) return;

        var NS = 'http://www.w3.org/2000/svg';
        var years = JSON.parse(card.dataset.kecelakaan);
        var SERIES = JSON.parse(card.dataset.kecelakaanSeries);
        var MONTHS = {{ Js::from(array_map(fn (string $bulan) => __($bulan), \App\Modules\Sms\Support\GrafikKecelakaan::MONTHS)) }};
        var MONTH_NAMES = {{ Js::from(collect(range(1, 12))->map(fn (int $bulan) => \Illuminate\Support\Carbon::create(2000, $bulan, 1)->translatedFormat('F'))) }};
        var select = card.querySelector('[data-kecelakaan-year]');
        var plot = card.querySelector('[data-kecelakaan-plot]');
        var tip = plot.querySelector('.chart-tip');
        var tableBox = card.querySelector('[data-kecelakaan-table]');
        var copyNote = card.querySelector('[data-kecelakaan-copy-note]');
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

        function sum(values) { return values.reduce(function (n, v) { return n + v; }, 0); }

        function current() { return years[select.value]; }

        function totals(entry) {
            return MONTHS.map(function (_, i) {
                return SERIES.reduce(function (n, s) { return n + entry[s.key][i]; }, 0);
            });
        }

        function barPath(x, y, w, h) {
            var r = Math.min(4, w / 2, h);
            return 'M' + x + ' ' + (y + h) + 'V' + (y + r) + 'Q' + x + ' ' + y + ' ' + (x + r) + ' ' + y
                + 'H' + (x + w - r) + 'Q' + (x + w) + ' ' + y + ' ' + (x + w) + ' ' + (y + r) + 'V' + (y + h) + 'Z';
        }

        function showTip(entry, i, center, slot, width, top) {
            tip.innerHTML = '';
            tip.appendChild(html('b', '', MONTH_NAMES[i] + ' ' + select.value));
            SERIES.forEach(function (s) {
                var row = html('div');
                var swatch = html('i', 'swatch');
                swatch.style.setProperty('--c', s.color);
                row.append(swatch, html('span', '', s.label), html('span', '', entry[s.key][i]));
                tip.appendChild(row);
            });
            var total = html('div');
            total.append(html('span', '', __t('Total')), html('span', '', totals(entry)[i]));
            tip.appendChild(total);
            tip.hidden = false;
            var left = center + slot / 2 + 6;
            if (left + tip.offsetWidth > width) left = center - slot / 2 - tip.offsetWidth - 6;
            tip.style.left = Math.max(0, left) + 'px';
            tip.style.top = top + 'px';
        }

        // animateDelay: ms sebelum batang tumbuh, atau null = langsung tampil penuh.
        function draw(animateDelay) {
            var entry = current();
            var old = plot.querySelector('svg');
            if (old) old.remove();
            tip.hidden = true;
            var width = plot.clientWidth, height = plot.clientHeight;
            var pad = { top: 20, right: 8, bottom: 26, left: 28 };
            var innerW = width - pad.left - pad.right, innerH = height - pad.top - pad.bottom;
            if (innerW <= 0 || innerH <= 0) return;

            var max = 5;
            SERIES.forEach(function (s) { entry[s.key].forEach(function (v) { if (v > max) max = v; }); });
            var step = Math.ceil(max / 5);
            var top = Math.ceil(max / step) * step;
            var y = function (v) { return pad.top + innerH - innerH * v / top; };
            var base = pad.top + innerH;
            var slot = innerW / MONTHS.length;
            var barW = Math.max(5, Math.min(22, (slot * 0.62 - 2) / SERIES.length));
            var labelEvery = slot < 24 ? 3 : (slot < 34 ? 2 : 1);
            var nowIndex = Number(select.value) === today.getFullYear() ? today.getMonth() : -1;

            var svg = el('svg', { width: width, height: height, 'aria-hidden': 'true' });
            plot.insertBefore(svg, tip);
            var grid = el('g', { class: 'grid' }, svg), axis = el('g', { class: 'axis' }, svg);
            for (var t = 0; t <= top; t += step) {
                el('line', { x1: pad.left, x2: width - pad.right, y1: y(t), y2: y(t), 'stroke-dasharray': t === 0 ? 'none' : '3 4' }, grid);
                el('text', { x: pad.left - 8, y: y(t) + 4, 'text-anchor': 'end' }, axis).textContent = t;
            }

            MONTHS.forEach(function (month, i) {
                var center = pad.left + slot * i + slot / 2;
                var start = center - (barW * SERIES.length + 2 * (SERIES.length - 1)) / 2;
                if (i % labelEvery === 0) {
                    el('text', { x: center, y: height - 6, 'text-anchor': 'middle', class: i === nowIndex ? 'now' : '' }, axis).textContent = month;
                }

                SERIES.forEach(function (s, k) {
                    var value = entry[s.key][i];
                    if (!value) return;
                    var x = start + k * (barW + 2);
                    var bar = el('path', { d: barPath(x, y(value), barW, base - y(value)), fill: s.color }, svg);
                    if (animateDelay !== null) { bar.setAttribute('class', 'bar-grow'); bar.style.animationDelay = (animateDelay + i * 45 + k * 40) + 'ms'; }
                    el('text', { x: x + barW / 2, y: y(value) - 5, 'text-anchor': 'middle', 'font-weight': 600 }, axis).textContent = value;
                });

                var hit = el('rect', { x: center - slot / 2, y: 0, width: slot, height: base, fill: 'transparent' }, svg);
                hit.addEventListener('mouseenter', function () { showTip(entry, i, center, slot, width, pad.top); });
                hit.addEventListener('mouseleave', function () { tip.hidden = true; });
            });
        }

        function tableRows() {
            var entry = current();
            var rows = SERIES.map(function (s) { return { label: s.label, color: s.color, values: entry[s.key] }; });
            rows.push({ label: __t('Total'), values: totals(entry) });
            return rows;
        }

        function renderTable() {
            var table = html('table', 'chart-table');
            var headRow = html('tr');
            headRow.appendChild(html('th'));
            MONTHS.forEach(function (month) { headRow.appendChild(html('th', '', month)); });
            headRow.appendChild(html('th', '', __t('Jumlah')));
            table.appendChild(html('thead')).appendChild(headRow);
            var body = table.appendChild(html('tbody'));
            tableRows().forEach(function (row) {
                var tr = html('tr');
                var name = html('td', 'series-name');
                if (row.color) {
                    var swatch = html('i', 'swatch');
                    swatch.style.setProperty('--c', row.color);
                    name.appendChild(swatch);
                }
                name.append(row.label);
                tr.appendChild(name);
                row.values.forEach(function (value) { tr.appendChild(html('td', '', value)); });
                var all = html('td', '', sum(row.values));
                all.style.fontWeight = '700';
                tr.appendChild(all);
                body.appendChild(tr);
            });
            tableBox.innerHTML = '';
            tableBox.appendChild(table);
        }

        // Format teks bertab supaya saat di-paste ke Excel langsung terpisah per sel.
        function tableText() {
            var lines = [[''].concat(MONTHS, [__t('Jumlah')]).join('\t')];
            tableRows().forEach(function (row) {
                lines.push([row.label].concat(row.values, [sum(row.values)]).join('\t'));
            });
            return lines.join('\n');
        }

        // Clipboard API hanya ada di HTTPS / localhost; di alamat lain memakai cara lama lewat textarea.
        function copyText(text) {
            if (window.isSecureContext && navigator.clipboard) return navigator.clipboard.writeText(text);
            return new Promise(function (resolve, reject) {
                var area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                var copied = document.execCommand('copy');
                area.remove();
                if (copied) resolve(); else reject(new Error('copy failed'));
            });
        }

        function flashCopy(text) {
            copyNote.textContent = text;
            setTimeout(function () { if (copyNote.textContent === text) copyNote.textContent = ''; }, 3000);
        }

        var exportLink = PatrolImage.exportLink();
        var imageButtons = PatrolImage.imageActions(function () {
            return PatrolImage.chartCanvas(plot, __t('Kecelakaan') + ' · ' + __t('Tahun :tahun', { tahun: select.value }), SERIES);
        }, function () { return 'grafik-kecelakaan-' + select.value; });
        imageButtons.insertBefore(exportLink, imageButtons.querySelector('.icon-action'));
        card.querySelector('[data-kecelakaan-actions]').appendChild(imageButtons);

        card.querySelector('[data-kecelakaan-copy]').addEventListener('click', function () {
            copyText(tableText())
                .then(function () { flashCopy(__t('Tabel tersalin, tinggal paste.')); })
                .catch(function () { flashCopy(__t('Gagal menyalin tabel.')); });
        });

        function refresh(animateDelay) {
            card.querySelector('[data-kecelakaan-subtitle]').textContent = __t('Per bulan · Tahun :tahun', { tahun: select.value });
            exportLink.href = @json(route('sms.dashboard.export.kecelakaan')) + '?' + new URLSearchParams({ year: select.value });
            renderTable();
            draw(reduceMotion ? null : animateDelay);
        }

        select.addEventListener('change', function () { refresh(0); });
        refresh(900);

        if ('ResizeObserver' in window) {
            var lastWidth = plot.clientWidth;
            new ResizeObserver(function () {
                if (plot.isConnected && plot.clientWidth !== lastWidth) { lastWidth = plot.clientWidth; draw(null); }
            }).observe(plot);
        }
    })();
</script>
@endpush
