@extends('layouts.app')

@section('title', 'Detail Absensi')

@section('styles')
    .det-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .det-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .det-toolbar input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .day-nav a + a { border-left: 1px solid var(--line-strong); }
    .day-nav a:hover { background: #fff; }

    .rotasi { display: flex; align-items: center; justify-content: space-between; gap: 12px 20px; flex-wrap: wrap; padding: 14px 20px; margin-bottom: 20px; }
    .rotasi-info { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; }
    .rotasi form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .rotasi input[type=date], .rotasi select { width: auto; border-radius: 999px; padding: 7px 12px; font-size: 13px; }
    .rotasi.warn { background: #fff8e6; }

    .det-section { margin-bottom: 24px; padding: 0; overflow: hidden; }
    .det-title { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; padding: 16px 20px 12px; }
    .det-title h2 { font-size: 16px; }
    .det-title .stats { display: flex; gap: 6px; flex-wrap: wrap; }
    .det-title .stats .badge.bad { background: #fee2e2; color: #b91c1c; }
    .det-title .stats .badge.ok { background: var(--success-bg); color: var(--success); }

    table.det { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    table.det th, table.det td { border: 1px solid var(--line-strong); padding: 6px 8px; text-align: center; white-space: nowrap; }
    table.det thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; padding: 4px 8px; }
    table.det td.l { text-align: left; }
    table.det td.nama { font-weight: 600; }
    table.det td.ket { white-space: normal; min-width: 220px; }
    table.det td.kat { width: 46px; font-weight: 700; color: var(--navy); }
    table.det tfoot td { background: #f5f8fe; font-weight: 700; }
    table.det tbody tr:hover td { background: #f7faff; }
    table.det .empty { color: var(--muted); padding: 20px; }

    .charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px; }
    @media (max-width: 520px) { .charts { grid-template-columns: 1fr; } }
    .chart h3 { font-size: 14px; margin-bottom: 8px; }
    .chart svg { width: 100%; height: auto; display: block; }
    .chart .grid line { stroke: var(--line-strong); }
    .chart text { font-size: 10px; fill: var(--muted); font-family: inherit; }
    .chart .lbl { fill: #c2410c; font-weight: 700; }
    .chart .legend { display: flex; gap: 16px; font-size: 12px; margin-top: 6px; }
    .chart .legend span::before { content: ""; display: inline-block; width: 14px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 6px; background: var(--c); }
    table.chart-data { border-collapse: collapse; font-size: 11px; margin-top: 8px; width: 100%; }
    table.chart-data th, table.chart-data td { border: 1px solid var(--line-strong); padding: 3px 4px; text-align: center; text-transform: none; letter-spacing: 0; font-size: 11px; color: var(--text); }
    table.chart-data th:first-child { text-align: left; white-space: nowrap; }
    table.chart-data .sel { background: #eef4ff; font-weight: 700; }

    @media print {
        .app-nav, .det-toolbar, .rotasi, .success, .alert { display: none !important; }
        .shell { padding: 0; }
        .card { box-shadow: none; border: 0; backdrop-filter: none; }
        body { background: #fff; }
    }
@endsection

@section('content')
@php
    $prev = $tanggal->copy()->subDay()->toDateString();
    $next = $tanggal->copy()->addDay()->toDateString();
    $statusMp = fn (string $status) => str_starts_with($status, 'PKWT') ? 'Kontrak' : $status;
@endphp

<div class="det-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Detail Absensi Harian</h1>
        <p class="muted" style="margin: 2px 0 0;">Laporan {{ $tanggal->translatedFormat('l, d F Y') }} · shift malam {{ $sections[0]['tanggal']->translatedFormat('d M') }} & shift pagi {{ $tanggal->translatedFormat('d M') }}</p>
    </div>
    <form method="GET" class="det-toolbar">
        <span class="day-nav">
            <a href="{{ route('hrms.absensi.detail', ['tanggal' => $prev]) }}" title="Hari sebelumnya">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.detail') }}">Hari ini</a>
            <a href="{{ route('hrms.absensi.detail', ['tanggal' => $next]) }}" title="Hari berikutnya">&rsaquo;</a>
        </span>
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="Tanggal laporan">
        <a href="{{ route('hrms.absensi.detail.export', ['tanggal' => $tanggal->toDateString()]) }}" class="btn btn-ghost btn-sm">Export Excel</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">Cetak</button>
    </form>
</div>

<div @class(['card', 'rotasi', 'warn' => ! $grupPagi])>
    <div class="rotasi-info">
        @if ($grupPagi)
            <span>Rotasi pada {{ $tanggal->translatedFormat('d M Y') }}:</span>
            <span class="badge">Grup {{ $grupPagi }} · pagi</span>
            <span class="badge">Grup {{ \App\Modules\Hrms\Models\ShiftRotasi::lawan($grupPagi) }} · malam</span>
            <span class="badge badge-off">NS · 07.00 - 16.00</span>
            <span class="muted small">Acuan terakhir: mulai {{ $rotasiTerakhir->tanggal_mulai->translatedFormat('d M Y') }} grup {{ $rotasiTerakhir->grup_pagi }} pagi, bertukar tiap minggu.</span>
        @else
            <strong>Jadwal rotasi grup A/B belum diatur.</strong>
            <span class="muted">Isi tanggal mulai dan grup yang masuk pagi supaya laporan shift A/B bisa dibuat.</span>
        @endif
    </div>
    <form method="POST" action="{{ route('hrms.absensi.rotasi') }}">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
        <span class="small muted">Mulai</span>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $tanggal->toDateString()) }}" required aria-label="Tanggal mulai rotasi">
        <span class="small muted">grup pagi</span>
        <select name="grup_pagi" aria-label="Grup shift pagi">
            @foreach ($grupOptions as $grup)
                <option value="{{ $grup }}" @selected(old('grup_pagi', $grupPagi) === $grup)>{{ $grup }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm">Simpan rotasi</button>
    </form>
    @error('tanggal_mulai') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
    @error('grup_pagi') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
</div>

@foreach ($sections as $section)
    <section class="card det-section">
        <div class="det-title">
            <div>
                <h2>{{ $section['tanggal']->translatedFormat('l') }} : {{ $section['tanggal']->translatedFormat('d F Y') }} ({{ $section['judul'] }})</h2>
                <span class="muted small">{{ $section['keterangan'] }} · grup {{ $section['grups'] ? implode(', ', $section['grups']) : '-' }}</span>
            </div>
            <div class="stats">
                <span class="badge">MP {{ $section['headcount'] }}</span>
                <span class="badge">Tidak hadir {{ $section['rows']->count() }}</span>
                @if ($section['persen'] !== null)
                    <span @class(['badge', 'bad' => $section['persen'] > $section['grafik']['target'], 'ok' => $section['persen'] <= $section['grafik']['target']])>{{ number_format($section['persen'], 1, ',', '.') }}%</span>
                @endif
            </div>
        </div>
        <div class="table-wrap">
            <table class="det">
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">NIK</th>
                        <th rowspan="2">Nama</th>
                        <th rowspan="2">Group</th>
                        <th rowspan="2">Shift</th>
                        <th colspan="{{ count($kategoris) }}">Kategori Absensi</th>
                        <th rowspan="2">Keterangan</th>
                        <th rowspan="2">Durasi Libur</th>
                        <th rowspan="2">Status MP</th>
                    </tr>
                    <tr>
                        @foreach ($kategoris as $kategori)
                            <th>{{ $kategori }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        @php
                            $karyawan = $row['karyawan'];
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $karyawan->nik }}</td>
                            <td class="l nama">{{ $karyawan->nama }}</td>
                            <td class="l">{{ trim(($karyawan->department?->name ?? '').' '.($karyawan->jabatan ?? '')) ?: '-' }}</td>
                            <td>{{ $karyawan->group }}</td>
                            @foreach ($kategoris as $kategori)
                                <td class="kat" @if ($row['kategori'] === $kategori) title="{{ $kodes[$row['kode']]['label'] ?? $row['kode'] }}" @endif>{{ $row['kategori'] === $kategori ? 1 : '' }}</td>
                            @endforeach
                            <td class="l ket">{{ $row['keterangan'] ?: ($kodes[$row['kode']]['label'] ?? $row['kode']) }}</td>
                            <td>{{ $row['durasi'] }} Hari</td>
                            <td>{{ $statusMp($karyawan->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 8 + count($kategoris) }}" class="empty">
                            {{ $section['grups'] ? 'Semua karyawan hadir.' : 'Grup shift belum diketahui — atur jadwal rotasi di atas.' }}
                        </td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5"></td>
                        @foreach ($kategoris as $kategori)
                            <td>{{ $section['totals'][$kategori] }}</td>
                        @endforeach
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endforeach

<div class="charts">
    @foreach ($sections as $section)
        @php
            $grafik = $section['grafik'];
            $points = $grafik['points'];
            $values = array_filter(array_column($points, 'actual'), fn ($v) => $v !== null);
            $yMax = max(8, (int) ceil((max($values ?: [0]) + 1) / 2) * 2);
            [$left, $right, $top, $bottom] = [30, 552, 14, 176];
            $n = max(count($points), 1);
            $step = ($right - $left) / $n;
            $x = fn (int $i) => round($left + $step * ($i + .5), 1);
            $y = fn (float $v) => round($bottom - ($v / $yMax) * ($bottom - $top), 1);
            $targetLine = collect($points)->keys()->map(fn ($i) => $x($i).','.$y($grafik['target']))->implode(' ');
            $actualLine = collect($points)->filter(fn ($p) => $p['actual'] !== null)->map(fn ($p, $i) => $x($i).','.$y($p['actual']))->implode(' ');
            $fmt = fn (?float $v) => $v === null ? '' : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
        @endphp
        <div class="card chart">
            <h3>Grafik ketidakhadiran {{ $section['judul'] }} ({{ $section['tanggal']->translatedFormat('d F Y') }})</h3>
            <svg viewBox="0 0 560 196" role="img" aria-label="Persentase ketidakhadiran {{ $section['judul'] }} bulan {{ $grafik['bulan']->translatedFormat('F Y') }}">
                <g class="grid">
                    @for ($v = 0; $v <= $yMax; $v += 2)
                        <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}"/>
                        <text x="{{ $left - 6 }}" y="{{ $y($v) + 3 }}" text-anchor="end">{{ $v }}</text>
                    @endfor
                </g>
                @foreach ($points as $i => $point)
                    <text x="{{ $x($i) }}" y="{{ $bottom + 14 }}" text-anchor="middle" @if ($point['selected']) style="fill: var(--navy); font-weight: 700;" @endif>{{ $point['day'] }}</text>
                @endforeach
                <polyline points="{{ $targetLine }}" fill="none" stroke="#3b82f6" stroke-width="2"/>
                @foreach ($points as $i => $point)
                    <path d="M{{ $x($i) }} {{ $y($grafik['target']) - 3.5 }} l3.5 6 h-7 z" fill="#3b82f6"/>
                @endforeach
                @if ($actualLine !== '')
                    <polyline points="{{ $actualLine }}" fill="none" stroke="#ea580c" stroke-width="2" stroke-linejoin="round"/>
                    @foreach ($points as $i => $point)
                        @if ($point['actual'] !== null)
                            <rect x="{{ $x($i) - 3 }}" y="{{ $y($point['actual']) - 3 }}" width="6" height="6" fill="#ea580c"><title>Tgl {{ $point['day'] }}: {{ $fmt($point['actual']) }}%</title></rect>
                        @endif
                    @endforeach
                @endif
            </svg>
            <div class="legend"><span style="--c: #3b82f6">Target {{ $grafik['target'] }}%</span><span style="--c: #ea580c">Actual (%)</span></div>
            <div class="table-wrap">
                <table class="chart-data">
                    <tr><th>Tgl</th>@foreach ($points as $point)<th @class(['sel' => $point['selected']])>{{ $point['day'] }}</th>@endforeach</tr>
                    <tr><th>Target</th>@foreach ($points as $point)<td>{{ $grafik['target'] }}</td>@endforeach</tr>
                    <tr><th>Actual</th>@foreach ($points as $point)<td @class(['sel' => $point['selected']])>{{ $fmt($point['actual']) }}</td>@endforeach</tr>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
