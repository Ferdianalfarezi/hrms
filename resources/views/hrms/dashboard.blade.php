{{-- Dashboard HRMS memakai template dashboard modul; kartu & susunan grid diganti lewat section di bawah. --}}
@extends('dashboard.module')

@section('styles')
    @parent
    /* Susunan dashboard HRMS: dua baris supaya muat satu layar. Kartu info akun dan kalender tidak dipakai. */
    .dash-grid {
        grid-template-areas:
            "profile progress progress side"
            "manhour telat ultah side";
    }
    .a-manhour { grid-area: manhour; } .a-telat { grid-area: telat; } .a-ultah { grid-area: ultah; }
    .profile-card { min-height: 0; }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-areas: "profile side" "progress progress" "manhour telat" "ultah ultah"; }
        .profile-card { min-height: 280px; }
    }
    @media (max-width: 640px) {
        .dash-grid { grid-template-areas: "profile" "side" "progress" "manhour" "telat" "ultah"; }
    }
    @if (! $persenShift)
        {{-- Admin Departemen: tanpa kartu persen per shift, kartu keterlambatan memakai tempatnya. --}}
        .dash-grid { grid-template-areas: "profile progress progress side" "manhour telat telat side"; }
        @media (max-width: 1100px) { .dash-grid { grid-template-areas: "profile side" "progress progress" "manhour telat"; } }
        @media (max-width: 640px) { .dash-grid { grid-template-areas: "profile" "side" "progress" "manhour" "telat"; } }
    @endif
    .dash-grid > .card { min-width: 0; }
    /* Kolom kanan hanya kartu absensi (Shift 1 di atas, Shift 2 di bawah). Layar lebar: tingginya mengikuti dua baris di kirinya,
       isi yang lebih panjang di-scroll di dalam kartu. */
    .a-side > .card { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; }
    @media (min-width: 1101px) { .a-side { contain: size; } }
    .shift-list { flex: 1 1 0; min-height: 0; overflow-y: auto; display: grid; align-content: start; gap: 12px; margin-top: 12px; padding-right: 4px; }
    .shift-block + .shift-block { padding-top: 12px; border-top: 1px solid var(--line); }
    .shift-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .shift-head b { display: block; font-size: 13px; }
    .shift-head small { display: block; font-size: 11px; color: var(--faint); }
    .shift-head .shift-val { font-size: 20px; font-weight: 500; letter-spacing: -.02em; line-height: 1; font-variant-numeric: tabular-nums; }
    .shift-block .stackbar { height: 8px; margin: 8px 0; }
    .shift-block .legend { gap: 4px; }
    .shift-block .legend-row, .shift-block .legend-value { font-size: 12px; }
    .shift-block .legend-row .dot { width: 8px; height: 8px; }
    /* Persen tidak hadir hari ini per shift (baris bawah). */
    .a-ultah { display: flex; flex-direction: column; }
    /* Lebar kolom tetap & judul shift boleh turun baris, supaya tabel tidak keluar dari kartu. */
    table.persen-shift { margin-top: 4px; font-size: 13px; table-layout: fixed; }
    table.persen-shift th { font-size: 11px; color: var(--text); white-space: normal; overflow-wrap: anywhere; line-height: 1.3; vertical-align: bottom; }
    table.persen-shift th:first-child { width: 36%; }
    table.persen-shift td { padding: 7px 8px; font-weight: 600; }
    table.persen-shift td:first-child { font-weight: 500; color: var(--muted); }
    table.persen-shift td.over { color: #dc2626; }
    table.persen-shift + .card-foot { margin-top: auto; }
    .card-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .card-head.top { align-items: flex-start; }

    /* Angka besar + satuan kecil. */
    .metric { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
    .metric b { font-size: 34px; font-weight: 500; letter-spacing: -.03em; line-height: 1; font-variant-numeric: tabular-nums; }
    .metric span { font-size: 13px; color: var(--muted); }

    /* Man-hour: bar jam efektif vs hilang. */
    .mh-bar { display: flex; gap: 3px; height: 12px; margin: 14px 0 12px; }
    .mh-bar span { border-radius: 999px; min-width: 6px; transform-origin: left center; animation: dash-grow-x .8s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(var(--d) + .3s); }

    /* Baris kecil label - nilai. */
    .mini-rows { display: grid; gap: 8px; }
    .mini-row { display: flex; align-items: center; gap: 8px; font-size: 13px; min-width: 0; }
    .mini-row .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
    .mini-row .kode { flex: none; min-width: 38px; padding: 2px 6px; border-radius: 6px; background: #fff4e5; color: #b45309; font-size: 11px; font-weight: 700; text-align: center; }
    .mini-row .lbl { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; color: var(--muted); }
    .mini-row .val { margin-left: auto; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .card-foot { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
    .card-foot b { color: var(--text); }

    /* Tren kehadiran 7 hari (gabungan tren + produktivitas mingguan): tombol pilihan tampilan (garis / batang / pie / tabel). */
    .tren-card .card-head { flex-wrap: wrap; margin-bottom: 10px; }
    .tren-card .card-sub b { font-size: 13px; color: var(--text); }
    .tren-card .card-sub b.over { color: #dc2626; }
    .tren-actions { display: flex; align-items: center; gap: 8px; }
    .tren-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 8px; font-size: 12px; color: var(--muted); }
    .tren-legend span { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .tren-legend .dot { width: 10px; height: 10px; border-radius: 50%; }
    /* Semua panel sama tinggi, supaya ganti tampilan tidak menggeser susunan dashboard. */
    .tren-panel { height: 214px; }
    .tren-pct { font-size: 11px; font-weight: 600; font-variant-numeric: tabular-nums; color: #15803d; }
    .tren-pct.over { color: #dc2626; }
    .tren-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: var(--soft); border: 1px solid var(--line); }
    .tren-tabs button { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border: 0; border-radius: 8px; background: transparent;
                        font: inherit; font-size: 12px; font-weight: 600; color: var(--muted); cursor: pointer; }
    .tren-tabs button svg { width: 13px; height: 13px; }
    .tren-tabs button[aria-selected="true"] { background: #fff; color: var(--text); box-shadow: 0 1px 4px rgba(15, 35, 80, .1); }
    .tren-panel[hidden] { display: none; }

    /* Grafik garis: kolom label sumbu Y + area plot; garis bantu & titik hover memakai persen supaya ikut lebar kartu. */
    .tren-line { display: grid; grid-template-columns: 40px minmax(0, 1fr); grid-template-rows: 160px auto; column-gap: 8px; padding-top: 6px; }
    .tren-y { position: relative; }
    .tren-y span { position: absolute; right: 0; transform: translateY(50%); font-size: 11px; color: var(--faint); font-variant-numeric: tabular-nums; }
    .tren-plot { position: relative; margin-right: 12px; }
    .tren-plot .grid-h { position: absolute; left: 0; right: 0; border-top: 1px dashed var(--line-strong); }
    .tren-plot .grid-h.base { border-top-style: solid; }
    .tren-plot .grid-v { position: absolute; top: 0; bottom: 0; border-left: 1px dashed var(--line); }
    .tren-plot svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible;
                     animation: tren-reveal 1.4s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(var(--d) + .3s); }
    .tren-plot path { fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; vector-effect: non-scaling-stroke; }
    @keyframes tren-reveal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
    .tren-hit { position: absolute; top: 0; bottom: 0; width: calc(100% / 6); transform: translateX(-50%); }
    .tren-hit::after { content: ""; position: absolute; top: 0; bottom: 0; left: 50%; border-left: 1px solid transparent; }
    .tren-hit:hover::after { border-left-color: var(--primary); }
    .tren-x { grid-column: 2; position: relative; height: 48px; margin-right: 12px; }
    .tren-x span { position: absolute; top: 6px; transform: translateX(-50%); font-size: 11px; color: var(--faint); text-align: center; line-height: 1.25; white-space: nowrap; }
    .tren-x span b { display: block; color: var(--muted); font-weight: 600; }
    .tren-x span.today b { color: var(--primary); }

    /* Grafik batang: tiap hari dua batang, hadir dan tidak hadir (ditumpuk per kategori); persen hadir di atasnya. */
    .tren-bars { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; height: 100%; }
    .tren-bars .col { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 0; }
    .tren-bars .plot { flex: 1; min-height: 0; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 4px; border-bottom: 1px solid var(--line-strong); }
    .tren-bars .tren-bar { width: min(26px, 44%); height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 2px; }
    .tren-bars .tren-bar em { font-style: normal; font-size: 10px; font-weight: 600; line-height: 1; color: var(--muted); font-variant-numeric: tabular-nums; }
    .tren-bars .stack { width: 100%; display: flex; flex-direction: column-reverse; gap: 2px; border-radius: 8px 8px 3px 3px; overflow: hidden;
                        transform-origin: bottom; animation: dash-grow-y .8s cubic-bezier(.22, 1, .36, 1) backwards; animation-delay: calc(var(--j, 0) * 50ms); }
    .tren-bars .stack span { min-height: 3px; }
    .tren-bars small { font-size: 11px; color: var(--faint); line-height: 1.2; text-align: center; }
    .tren-bars small b { display: block; color: var(--muted); font-weight: 600; }

    /* Pie (donat): total 7 hari per jenis kehadiran. */
    .tren-pie { display: flex; align-items: center; gap: 28px; height: 100%; }
    .tren-pie .mini-rows { gap: 5px; }
    .tren-donut { width: 180px; height: 180px; flex: none; display: grid; place-items: center; position: relative;
                  animation: dash-pop .6s cubic-bezier(.34, 1.4, .64, 1) backwards; }
    /* Lubang donat memakai mask, jadi latar kartu (kaca) tetap terlihat. */
    .tren-donut .ring { position: absolute; inset: 0; border-radius: 50%;
                        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 30px), #000 calc(100% - 29px));
                        mask: radial-gradient(farthest-side, transparent calc(100% - 30px), #000 calc(100% - 29px)); }
    .tren-donut div { position: relative; text-align: center; }
    .tren-donut b { display: block; font-size: 26px; font-weight: 500; letter-spacing: -.03em; line-height: 1; }
    .tren-donut small { font-size: 11px; color: var(--muted); }
    .tren-pie .mini-rows { flex: 1; min-width: 220px; }

    /* Tabel. */
    .tren-table-wrap { overflow: auto; height: 100%; }
    table.tren-table { width: 100%; font-size: 12px; border-collapse: collapse; }
    table.tren-table th { font-size: 11px; font-weight: 600; color: var(--muted); text-align: right; padding: 3px 8px; border-bottom: 1px solid var(--line); white-space: nowrap; }
    table.tren-table th small { display: block; font-weight: 400; color: var(--faint); }
    table.tren-table td { padding: 4px 8px; text-align: right; border-bottom: 1px solid var(--line); font-variant-numeric: tabular-nums; white-space: nowrap; }
    table.tren-table th:first-child, table.tren-table td:first-child { text-align: left; }
    table.tren-table td.zero { color: var(--faint); }
    table.tren-table td .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
    table.tren-table .total { font-weight: 700; }
    table.tren-table tfoot td { border-bottom: 0; color: var(--muted); font-weight: 600; }

    /* Legenda hanya untuk garis & batang. */
    .tren-card[data-aktif="pie"] .tren-legend, .tren-card[data-aktif="tabel"] .tren-legend { visibility: hidden; }
    .empty-note { color: var(--faint); font-size: 13px; padding: 12px 0; }

    @media (prefers-reduced-motion: reduce) { .mh-bar span, .tren-plot svg, .tren-bars .stack, .tren-donut { animation: none; } }
@endsection

@php
    $angka = fn (float|int $value, int $desimal = 0) => number_format($value, $desimal, ',', '.');
    $lihat = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 17 17 7M8 7h9v9"/></svg>';
@endphp

{{-- Admin HRMS: kartu "Karyawan Masuk" diganti tren kehadiran 7 hari kerja. Admin Departemen tetap memakai kartu bawaan (Belum Ada Keterangan). --}}
@if ($tren)
@section('progress')
    @php
        // Warna garis / pie mengikuti kelas .tone-* di template dashboard modul.
        $warna = ['success' => '#22c55e', 'warning' => '#f59e0b', 'primary' => 'var(--primary)', 'primary-soft' => '#5b8def',
            'soft' => '#b9cbef', 'navy' => 'var(--navy)', 'danger' => '#ef4444'];
        $jumlahHari = count($tren['days']);
        $posisiX = fn (int $index) => $jumlahHari > 1 ? round($index / ($jumlahHari - 1) * 100, 3) : 50;
        $totalSemua = collect($tren['series'])->sum('total');
        $catatanPersen = __('Persen = kehadiran per hari. Merah = tidak hadir di atas :target%.', ['target' => $tren['target']]);
        $hadirSeries = $tren['series'][0];

        // Pie: sudut kumulatif tiap jenis kehadiran.
        $sudut = 0;
        $irisan = collect($tren['series'])->filter(fn ($item) => $item['total'] > 0)->map(function ($item) use (&$sudut, $totalSemua, $warna) {
            $mulai = $sudut;
            $sudut += $item['total'] / $totalSemua * 100;

            return $warna[$item['tone']].' '.round($mulai, 3).'% '.round($sudut, 3).'%';
        });
        $ikon = [
            'garis' => '<path d="M3 3v18h18"/><path d="m7 15 4-5 4 3 5-7"/>',
            'batang' => '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-8"/>',
            'pie' => '<path d="M21 12A9 9 0 1 1 12 3v9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/>',
            'tabel' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/>',
        ];
    @endphp
    <div class="card a-progress tren-card" style="--i: 1;" data-tren data-aktif="batang">
        <div class="card-head top">
            <div style="min-width: 0;">
                <h2 class="card-title">{{ __('Tren Kehadiran (7 Hari)') }}</h2>
                <div class="card-sub"><b>{{ $tren['value'] }}</b> {{ __('rata-rata hadir') }} · {{ $tren['periode'] }}</div>
            </div>
            <div class="tren-actions">
                <div class="tren-tabs" role="tablist">
                    @foreach (['garis' => 'Garis', 'batang' => 'Batang', 'pie' => 'Pie', 'tabel' => 'Tabel'] as $view => $label)
                        <button type="button" role="tab" data-view="{{ $view }}" aria-selected="{{ $view === 'batang' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $ikon[$view] !!}</svg>{{ __($label) }}
                        </button>
                    @endforeach
                </div>
                <a href="{{ $tren['link'] }}" class="mini-btn" title="{{ __('Lihat detail') }}">{!! $lihat !!}</a>
            </div>
        </div>

        {{-- Garis --}}
        <div class="tren-panel" data-panel="garis" hidden>
            <div class="tren-line" role="img" aria-label="{{ __('Grafik garis jumlah karyawan per jenis kehadiran, 7 hari kerja terakhir.') }}">
                <div class="tren-y">
                    @foreach ($tren['ticks'] as $tick)
                        <span style="bottom: {{ $tick / $tren['max'] * 100 }}%;">{{ $angka($tick) }}</span>
                    @endforeach
                </div>
                <div class="tren-plot">
                    @foreach ($tren['ticks'] as $tick)
                        <i @class(['grid-h', 'base' => $loop->first]) style="bottom: {{ $tick / $tren['max'] * 100 }}%;"></i>
                    @endforeach
                    @foreach ($tren['days'] as $day)
                        <i class="grid-v" style="left: {{ $posisiX($loop->index) }}%;"></i>
                    @endforeach
                    <svg viewBox="0 0 {{ \App\Modules\Hrms\Support\HrmsDashboard::TREN_LEBAR }} {{ \App\Modules\Hrms\Support\HrmsDashboard::TREN_TINGGI }}" preserveAspectRatio="none" aria-hidden="true">
                        {{-- Hadir digambar terakhir supaya berada di atas garis lain. --}}
                        @foreach (array_reverse($tren['series']) as $item)
                            <path d="{{ $item['path'] }}" style="stroke: {{ $warna[$item['tone']] }};"></path>
                        @endforeach
                    </svg>
                    @foreach ($tren['days'] as $day)
                        @php($index = $loop->index)
                        <div class="tren-hit" style="left: {{ $posisiX($index) }}%;"
                             title="{{ $day['label'] }}, {{ $day['date'] }} · {{ __(':jumlah karyawan', ['jumlah' => $day['total']]) }}&#10;{{ collect($tren['series'])->map(fn ($item) => __($item['label']).': '.$item['counts'][$index])->join("\n") }}"></div>
                    @endforeach
                </div>
                <div class="tren-x">
                    @foreach ($tren['days'] as $day)
                        <span @class(['today' => $day['today']]) style="left: {{ $posisiX($loop->index) }}%;" title="{{ $catatanPersen }}">
                            <b>{{ $day['label'] }}</b>{{ $day['date'] }}
                            <em @class(['tren-pct', 'over' => $day['diAtasTarget']]) style="display: block; font-style: normal;">{{ $day['persen'] !== null ? $day['persen'].'%' : '-' }}</em>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Batang --}}
        <div class="tren-panel" data-panel="batang">
            {{-- Tiap hari dua batang dengan skala yang sama: Hadir, lalu Tidak hadir yang ditumpuk per kategori. --}}
            @php($maxBatang = max(collect($tren['days'])->map(fn ($day, $index) => max($hadirSeries['counts'][$index], $day['tidakHadir']))->max(), 1))
            @php($tinggiBatang = fn (int $jumlah) => $jumlah > 0 ? 'calc((100% - 12px) * '.max(round($jumlah / $maxBatang, 4), 0.03).')' : '0')
            <div class="tren-bars" role="img" aria-label="{{ __('Grafik kehadiran per hari kerja, batang kiri = hadir, batang kanan = tidak hadir per kategori.') }}">
                @foreach ($tren['days'] as $day)
                    @php($index = $loop->index)
                    @php($hadirHari = $hadirSeries['counts'][$index])
                    <div class="col" style="--j: {{ $index }};"
                         title="{{ $day['label'] }}, {{ $day['date'] }}&#10;{{ collect($tren['series'])->map(fn ($item) => __($item['label']).': '.$item['counts'][$index])->join("\n") }}&#10;{{ $catatanPersen }}">
                        <span @class(['tren-pct', 'over' => $day['diAtasTarget']])>{{ $day['persen'] !== null ? $day['persen'].'%' : '-' }}</span>
                        <div class="plot">
                            <div class="tren-bar">
                                <em>{{ $angka($hadirHari) }}</em>
                                @if ($hadirHari > 0)
                                    <div class="stack" style="height: {{ $tinggiBatang($hadirHari) }};"><span class="tone-{{ $hadirSeries['tone'] }}" style="flex: 1;"></span></div>
                                @endif
                            </div>
                            <div class="tren-bar">
                                <em @if ($day['diAtasTarget']) style="color: #dc2626;" @endif>{{ $angka($day['tidakHadir']) }}</em>
                                @if ($day['tidakHadir'] > 0)
                                    <div class="stack" style="height: {{ $tinggiBatang($day['tidakHadir']) }};">
                                        @foreach (array_slice($tren['series'], 1) as $item)
                                            @if ($item['counts'][$index] > 0)
                                                <span class="tone-{{ $item['tone'] }}" style="flex: {{ $item['counts'][$index] }};"></span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <small @if ($day['today']) style="color: var(--primary);" @endif><b>{{ $day['label'] }}</b>{{ $day['date'] }}</small>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pie --}}
        <div class="tren-panel" data-panel="pie" hidden>
            <div class="tren-pie">
                <div class="tren-donut">
                    <span class="ring" style="background: {{ $irisan->isNotEmpty() ? 'conic-gradient('.$irisan->join(', ').')' : 'var(--soft)' }};"></span>
                    <div>
                        <b>{{ $hadirSeries['persen'] }}%</b>
                        <small>{{ __('hadir') }}</small>
                    </div>
                </div>
                <div class="mini-rows">
                    @foreach ($tren['series'] as $item)
                        <div class="mini-row">
                            <i class="dot tone-{{ $item['tone'] }}"></i>
                            <span class="lbl">{{ __($item['label']) }}</span>
                            <span class="val">{{ $angka($item['total']) }} · {{ $item['persen'] }}%</span>
                        </div>
                    @endforeach
                    <div class="card-foot" style="margin-top: 4px;">{{ __('Total :jumlah hari-orang, 7 hari kerja.', ['jumlah' => $angka($totalSemua)]) }}</div>
                </div>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="tren-panel" data-panel="tabel" hidden>
            <div class="tren-table-wrap">
                <table class="tren-table">
                    <thead>
                        <tr>
                            <th>{{ __('Jenis') }}</th>
                            @foreach ($tren['days'] as $day)
                                <th>{{ $day['label'] }}<small>{{ $day['date'] }}</small></th>
                            @endforeach
                            <th>{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tren['series'] as $item)
                            <tr>
                                <td><i class="dot tone-{{ $item['tone'] }}"></i>{{ __($item['label']) }}</td>
                                @foreach ($item['counts'] as $count)
                                    <td @class(['zero' => $count === 0])>{{ $angka($count) }}</td>
                                @endforeach
                                <td class="total">{{ $angka($item['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>{{ __('Karyawan') }}</td>
                            @foreach ($tren['days'] as $day)
                                <td>{{ $angka($day['total']) }}</td>
                            @endforeach
                            <td>{{ $angka($totalSemua) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="tren-legend">
            @foreach ($tren['series'] as $item)
                <span @class(['hadir' => $loop->first])><i class="dot tone-{{ $item['tone'] }}"></i>{{ __($item['label']) }}</span>
            @endforeach
        </div>
    </div>
@endsection
@endif

{{-- Admin HRMS: absensi hari ini dirinci per shift, Shift 1 di atas dan Shift 2 di bawah. Admin Departemen tetap memakai kartu bawaan. --}}
@if (! empty($composition['shifts']))
@section('side-top')
    <div class="card" style="--i: 3;">
        <div class="status-top">
            <h2 class="card-title">{{ $composition['title'] }}</h2>
            <b data-count>{{ $composition['value'] }}</b>
        </div>
        <div class="shift-list">
            @foreach ($composition['shifts'] as $shift)
                <section class="shift-block">
                    <div class="shift-head">
                        <div style="min-width: 0;">
                            <b>{{ $shift['label'] }}</b>
                            @if ($shift['sub'])<small>{{ $shift['sub'] }}</small>@endif
                        </div>
                        <span class="shift-val">{{ $shift['value'] }}</span>
                    </div>
                    <div class="stackbar">
                        @foreach (collect($shift['segments'])->where('count', '>', 0)->reject(fn ($segment) => ($segment['stack'] ?? true) === false) as $segment)
                            <span class="tone-{{ $segment['tone'] }}" style="flex: {{ $segment['count'] }}; --j: {{ $loop->index }};" title="{{ __($segment['label']) }}: {{ $segment['count'] }}"></span>
                        @endforeach
                    </div>
                    <div class="legend">
                        @foreach ($shift['segments'] as $segment)
                            <div class="legend-row" style="--j: {{ min($loop->index, 10) }};">
                                <i class="dot tone-{{ $segment['tone'] }}"></i>
                                <span class="legend-label">{{ __($segment['label']) }}</span>
                                <span class="legend-value">{{ $segment['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        @if ($composition['note'])
            <div class="muted small" style="margin-top: 10px;">{{ $composition['note'] }}</div>
        @endif
    </div>
@endsection
@endif

@section('clock')
    {{-- Kartu "Jam Kerja" diganti man-hour hari ini: jam kerja efektif dibanding jam tersedia. --}}
    @php($manHour = $hariIni['manHour'])
    <div class="card a-manhour" style="--i: 2;">
        <div class="card-head top">
            <div style="min-width: 0;">
                <h2 class="card-title">{{ __('Man-hour Hari Ini') }}</h2>
                <div class="card-sub">{{ __('Jam kerja efektif') }}</div>
            </div>
            <span class="badge">{{ $manHour['persen'] !== null ? $angka($manHour['persen'], $manHour['persen'] == floor($manHour['persen']) ? 0 : 1).'%' : '-' }}</span>
        </div>
        <div class="metric">
            <b data-count>{{ $angka($manHour['efektif']) }}</b>
            <span>/ {{ $angka($manHour['tersedia']) }} {{ __('jam') }}</span>
        </div>
        <div class="mh-bar">
            <span class="tone-success" style="flex: {{ max($manHour['efektif'], 0.0001) }};" title="{{ __('Efektif') }}: {{ $angka($manHour['efektif'], 1) }} {{ __('jam') }}"></span>
            @foreach ($manHour['hilang'] as $item)
                @if ($item['jam'] > 0)
                    <span class="tone-{{ $item['tone'] }}" style="flex: {{ $item['jam'] }};" title="{{ __($item['label']) }}: {{ $angka($item['jam'], 1) }} {{ __('jam') }}"></span>
                @endif
            @endforeach
        </div>
        <div class="mini-rows">
            @foreach ($manHour['hilang'] as $item)
                <div class="mini-row">
                    <i class="dot tone-{{ $item['tone'] }}"></i>
                    <span class="lbl">{{ __('Hilang karena :alasan', ['alasan' => mb_strtolower(__($item['label']))]) }}</span>
                    <span class="val">{{ $angka($item['jam'], $item['jam'] == floor($item['jam']) ? 0 : 1) }} {{ __('jam') }}</span>
                </div>
            @endforeach
        </div>
        <div class="card-foot">{{ __(':jam jam per karyawan. WFH dihitung bekerja.', ['jam' => \App\Modules\Hrms\Support\ProduktivitasHariIni::JAM_KERJA]) }}</div>
    </div>
@endsection


@section('grid-bottom')
    {{-- Keterlambatan hari ini --}}
    @php($telat = $hariIni['keterlambatan'])
    <div class="card a-telat" style="--i: 5;">
        <div class="card-head top">
            <div style="min-width: 0;">
                <h2 class="card-title">{{ __('Keterlambatan Hari Ini') }}</h2>
                <div class="card-sub">{{ __('Datang terlambat & pulang cepat') }}</div>
            </div>
        </div>
        <div class="metric" style="margin-bottom: 14px;">
            <b data-count>{{ $telat['orang'] }}</b>
            <span>{{ __('orang') }} · {{ __(':menit menit terlambat', ['menit' => $angka($telat['menit'])]) }}</span>
        </div>
        <div class="mini-rows">
            @foreach ($telat['rows'] as $row)
                <div class="mini-row">
                    <span class="kode">{{ $row['kode'] }}</span>
                    <span class="lbl" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <span class="val">{{ $row['count'] }}</span>
                </div>
            @endforeach
        </div>
        @if ($telat['departemen'])
            <div class="card-foot">{{ __('Terbanyak') }}:
                {!! collect($telat['departemen'])->map(fn ($jumlah, $departemen) => '<b>'.e($departemen).'</b> '.$jumlah)->join(', ') !!}
            </div>
        @endif
    </div>

    {{-- Admin HRMS: angka grafik Laporan Detail hari ini (persen tidak hadir) per shift. --}}
    @if ($persenShift)
        @php($persenText = fn (?float $value) => $value === null ? '-' : $angka($value, $value == floor($value) ? 0 : 1).'%')
        <div class="card a-ultah" style="--i: 6;">
            <div class="card-head top">
                <div style="min-width: 0;">
                    <h2 class="card-title">{{ __('Tidak Hadir per Shift') }}</h2>
                    <div class="card-sub">{{ __('Hari ini') }} · {{ now()->translatedFormat('d M') }}</div>
                </div>
                <a href="{{ route('hrms.absensi.detail') }}" class="mini-btn" title="{{ __('Lihat detail') }}">{!! $lihat !!}</a>
            </div>
            <table class="tren-table persen-shift">
                <thead>
                    <tr>
                        <th></th>
                        @foreach ($persenShift as $shift)
                            {{-- Label "Shift 1 · Pagi · 20 Nov": tanggal di baris kedua. --}}
                            <th title="{{ $shift['label'] }} {{ $shift['sub'] }}">{{ \Illuminate\Support\Str::beforeLast($shift['label'], ' · ') }}<small>{{ collect([\Illuminate\Support\Str::afterLast($shift['label'], ' · '), $shift['sub']])->filter()->join(' · ') }}</small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (['target' => 'Target', 'actual' => 'Actual', 'tanpa_cuti' => 'Tanpa cuti', 'leader' => 'Asst. Leader up'] as $key => $label)
                        <tr>
                            <td>{{ __($label) }}</td>
                            @foreach ($persenShift as $shift)
                                <td @class(['over' => $key !== 'target' && $shift[$key] !== null && $shift[$key] > $shift['target']])>{{ $persenText($shift[$key]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="card-foot">{{ __('Persen tidak hadir, sama seperti grafik di Laporan Detail. Merah = di atas target.') }}</div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    // Tren kehadiran: tombol Garis / Batang / Pie / Tabel menampilkan panel yang sesuai.
    (function () {
        document.querySelectorAll('[data-tren]').forEach(function (card) {
            var tombol = card.querySelectorAll('[data-view]');
            tombol.forEach(function (button) {
                button.addEventListener('click', function () {
                    card.dataset.aktif = button.dataset.view;
                    tombol.forEach(function (lain) { lain.setAttribute('aria-selected', lain === button ? 'true' : 'false'); });
                    card.querySelectorAll('[data-panel]').forEach(function (panel) { panel.hidden = panel.dataset.panel !== button.dataset.view; });
                });
            });
        });
    })();
</script>
@endpush
