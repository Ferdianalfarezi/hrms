@extends('layouts.app')

@section('title', __('Detail Absensi'))

@section('styles')
    .det-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .det-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .det-toolbar input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .day-nav a + a { border-left: 1px solid var(--line-strong); }
    .day-nav a:hover { background: #fff; }
    .cuti-toggle { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border: 1px solid var(--line-strong); border-radius: 999px; background: var(--glass-strong); font-size: 13px; font-weight: 600; color: var(--text); }
    .cuti-toggle:hover { background: #fff; }
    .cuti-toggle .switch { position: relative; width: 28px; height: 16px; border-radius: 999px; background: var(--line-strong); transition: background .15s; }
    .cuti-toggle .switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; border-radius: 50%; background: #fff; transition: left .15s; }
    .cuti-toggle.on .switch { background: var(--navy); }
    .cuti-toggle.on .switch::after { left: 14px; }

    .rotasi { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; padding: 10px 16px; margin-bottom: 20px; }
    .rotasi form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .rotasi .rotasi-jadwal { margin-left: auto; }
    .rotasi input[type=date], .rotasi select { width: auto; border-radius: 999px; padding: 5px 10px; font-size: 13px; }
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
    .chart .legend span.bar::before { width: 10px; height: 10px; }
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
    $keepCuti = $hitungCuti ? [] : ['cuti' => 0];
    $statusMp = fn (string $status) => __(str_starts_with($status, 'PKWT') ? 'Kontrak' : $status);
@endphp

<div class="det-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Detail Absensi Harian') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ __('Laporan :tanggal · shift malam :malam & shift pagi :pagi', ['tanggal' => $tanggal->translatedFormat('l, d F Y'), 'malam' => $sections[0]['tanggal']->translatedFormat('d M'), 'pagi' => $tanggal->translatedFormat('d M')]) }}</p>
    </div>
    <form method="GET" class="det-toolbar">
        <span class="day-nav">
            <a href="{{ route('hrms.absensi.detail', ['tanggal' => $prev, ...$keepCuti]) }}" title="{{ __('Hari sebelumnya') }}">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.detail', $keepCuti) }}">{{ __('Hari ini') }}</a>
            <a href="{{ route('hrms.absensi.detail', ['tanggal' => $next, ...$keepCuti]) }}" title="{{ __('Hari berikutnya') }}">&rsaquo;</a>
        </span>
        <a href="{{ route('hrms.absensi.detail', ['tanggal' => $tanggal->toDateString(), ...($hitungCuti ? ['cuti' => 0] : [])]) }}" @class(['cuti-toggle', 'on' => $hitungCuti]) role="switch" aria-checked="{{ $hitungCuti ? 'true' : 'false' }}" title="{{ $hitungCuti ? __('Klik supaya cuti tidak dihitung ke persentase') : __('Klik supaya cuti ikut dihitung ke persentase') }}">
            <span class="switch"></span> {{ __('Hitung cuti') }}
        </a>
        @unless ($hitungCuti)
            <input type="hidden" name="cuti" value="0">
        @endunless
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Tanggal laporan') }}">
        <a href="{{ route('hrms.absensi.detail.export', ['tanggal' => $tanggal->toDateString(), ...$keepCuti]) }}" class="btn btn-ghost btn-sm" data-turbo="false">{{ __('Export Excel') }}</a>
        <a href="{{ route('hrms.absensi.detail.pdf', ['tanggal' => $tanggal->toDateString(), ...$keepCuti]) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" data-turbo="false">{{ __('Unduh PDF') }}</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">{{ __('Cetak') }}</button>
    </form>
</div>

<div @class(['card', 'rotasi', 'warn' => ! $grupPagi])>
    <form method="POST" action="{{ route('hrms.absensi.detail.grup') }}" title="{{ __('Hanya untuk laporan :tanggal. Grup malam dan pagi boleh sama, NS selalu ikut pagi.', ['tanggal' => $tanggal->translatedFormat('d M Y')]) }}">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
        <span class="small muted">{{ __('Malam :tanggal', ['tanggal' => $sections[0]['tanggal']->translatedFormat('d M')]) }}</span>
        <select name="grup_malam" required aria-label="{{ __('Grup shift malam di laporan ini') }}">
            @unless ($grupLaporan['malam']) <option value="" selected disabled>-</option> @endunless
            @foreach ($grupOptions as $grup)
                <option value="{{ $grup }}" @selected($grupLaporan['malam'] === $grup)>{{ $grup }}</option>
            @endforeach
        </select>
        <span class="small muted">{{ __('Pagi :tanggal', ['tanggal' => $tanggal->translatedFormat('d M')]) }}</span>
        <select name="grup_pagi" required aria-label="{{ __('Grup shift pagi di laporan ini') }}">
            @unless ($grupLaporan['pagi']) <option value="" selected disabled>-</option> @endunless
            @foreach ($grupOptions as $grup)
                <option value="{{ $grup }}" @selected($grupLaporan['pagi'] === $grup)>{{ $grup }}</option>
            @endforeach
        </select>
        <span class="small muted">{{ __('+ NS') }}</span>
        <button type="submit" class="btn btn-sm">{{ __('Simpan') }}</button>
    </form>
    @if ($grupLaporan['diatur'])
        <form method="POST" action="{{ route('hrms.absensi.detail.grup.destroy') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
            <button type="submit" class="btn btn-ghost btn-sm" title="{{ __('Kembali mengikuti jadwal rotasi') }}">{{ __('Reset') }}</button>
        </form>
    @endif

    <form method="POST" action="{{ route('hrms.absensi.rotasi') }}" class="rotasi-jadwal" title="{{ $grupPagi ? __('Acuan terakhir: mulai :tanggal grup :grup pagi, bertukar tiap minggu.', ['tanggal' => $rotasiTerakhir->tanggal_mulai->translatedFormat('d M Y'), 'grup' => $rotasiTerakhir->grup_pagi]) : __('Isi tanggal mulai dan grup yang masuk pagi supaya laporan shift A/B bisa dibuat.') }}">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
        @unless ($grupPagi) <strong class="small">{{ __('Jadwal rotasi grup A/B belum diatur.') }}</strong> @endunless
        <span class="small muted">{{ __('Rotasi mulai') }}</span>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $tanggal->toDateString()) }}" required aria-label="{{ __('Tanggal mulai rotasi') }}">
        <span class="small muted">{{ __('pagi') }}</span>
        <select name="grup_pagi" aria-label="{{ __('Grup shift pagi') }}">
            @foreach ($grupOptions as $grup)
                <option value="{{ $grup }}" @selected(old('grup_pagi', $grupPagi) === $grup)>{{ $grup }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost btn-sm">{{ __('Simpan rotasi') }}</button>
    </form>
    @error('tanggal_mulai') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
    @error('grup_malam') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
    @error('grup_pagi') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
</div>

@foreach ($sections as $section)
    <section class="card det-section">
        <div class="det-title">
            <div>
                <h2>{{ $section['tanggal']->translatedFormat('l') }} : {{ $section['tanggal']->translatedFormat('d F Y') }} ({{ __($section['judul']) }})</h2>
                <span class="muted small">{{ __($section['keterangan']) }} · {{ __('grup') }} {{ $section['grups'] ? implode(', ', $section['grups']) : '-' }}</span>
            </div>
            <div class="stats">
                <span class="badge">MP {{ $section['headcount'] }}</span>
                <span class="badge">{{ __('Tidak hadir') }} {{ $section['rows']->count() }}</span>
                @if ($section['persen'] !== null)
                    <span @class(['badge', 'bad' => $section['persen'] > $section['grafik']['target'], 'ok' => $section['persen'] <= $section['grafik']['target']])>{{ number_format($section['persen'], 1, ',', '.') }}%</span>
                @endif
            </div>
        </div>
        <div class="table-wrap">
            <table class="det">
                <thead>
                    <tr>
                        <th rowspan="2">{{ __('No') }}</th>
                        <th rowspan="2">NIK</th>
                        <th rowspan="2">{{ __('Nama') }}</th>
                        <th rowspan="2">{{ __('Group') }}</th>
                        <th rowspan="2">Shift</th>
                        <th colspan="{{ count($kategoris) }}">{{ __('Kategori Absensi') }}</th>
                        <th rowspan="2">{{ __('Keterangan') }}</th>
                        <th rowspan="2">{{ __('Durasi Libur') }}</th>
                        <th rowspan="2">{{ __('Status MP') }}</th>
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
                            <td>{{ __(':count Hari', ['count' => $row['durasi']]) }}</td>
                            <td>{{ $statusMp($karyawan->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 8 + count($kategoris) }}" class="empty">
                            {{ $section['grups'] ? __('Semua karyawan hadir.') : __('Grup shift belum diketahui — atur jadwal rotasi di atas.') }}
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
        <div class="card chart">
            @include('hrms.absensi.partials.grafik-detail', ['section' => $section, 'hitungCuti' => $hitungCuti])
        </div>
    @endforeach
</div>
@endsection
