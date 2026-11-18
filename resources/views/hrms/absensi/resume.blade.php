@extends('layouts.app')

@section('title', __('Resume Absensi'))

@section('styles')
    .rsm-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .rsm-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .rsm-toolbar input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }
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

    .rsm-section { margin-bottom: 24px; padding: 0; overflow: hidden; }
    .rsm-title { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; padding: 16px 20px 12px; }
    .rsm-title h2 { font-size: 16px; }

    table.rsm { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    table.rsm th, table.rsm td { border: 1px solid var(--line-strong); padding: 7px 10px; text-align: center; white-space: nowrap; }
    table.rsm thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; padding: 5px 8px; }
    table.rsm td.l { text-align: left; font-weight: 600; }
    table.rsm td.pct { background: #eef1f6; font-weight: 700; }
    table.rsm td.pct.bad { color: #b91c1c; }
    table.rsm tbody tr:hover td { background: #f7faff; }
    table.rsm tbody tr:hover td.pct { background: #e6eaf2; }
    table.rsm tfoot td { background: #f5f8fe; font-weight: 700; font-size: 14px; }
    table.rsm .empty { color: var(--muted); padding: 20px; }

    @media print {
        .app-nav, .rsm-toolbar, .success, .alert { display: none !important; }
        .shell { padding: 0; }
        .card { box-shadow: none; border: 0; backdrop-filter: none; }
        body { background: #fff; }
    }
@endsection

@section('content')
@php
    $keepCuti = $hitungCuti ? [] : ['cuti' => 0];
    $persen = fn (?float $value, int $decimals) => $value === null ? '-' : number_format($value, $decimals, ',', '.');
@endphp

<div class="rsm-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Resume Absensi') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ __('Laporan :tanggal · MP = man power (karyawan)', ['tanggal' => $tanggal->translatedFormat('l, d F Y')]) }}</p>
    </div>
    <form method="GET" class="rsm-toolbar">
        <span class="day-nav">
            <a href="{{ route('hrms.absensi.resume', ['tanggal' => $tanggal->copy()->subDay()->toDateString(), ...$keepCuti]) }}" title="{{ __('Hari sebelumnya') }}">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.resume', $keepCuti) }}">{{ __('Hari ini') }}</a>
            <a href="{{ route('hrms.absensi.resume', ['tanggal' => $tanggal->copy()->addDay()->toDateString(), ...$keepCuti]) }}" title="{{ __('Hari berikutnya') }}">&rsaquo;</a>
        </span>
        <a href="{{ route('hrms.absensi.resume', ['tanggal' => $tanggal->toDateString(), ...($hitungCuti ? ['cuti' => 0] : [])]) }}" @class(['cuti-toggle', 'on' => $hitungCuti]) role="switch" aria-checked="{{ $hitungCuti ? 'true' : 'false' }}" title="{{ $hitungCuti ? __('Klik supaya cuti tidak dihitung ke persentase') : __('Klik supaya cuti ikut dihitung ke persentase') }}">
            <span class="switch"></span> {{ __('Hitung cuti') }}
        </a>
        @unless ($hitungCuti)
            <input type="hidden" name="cuti" value="0">
        @endunless
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Tanggal laporan') }}">
        <a href="{{ route('hrms.absensi.resume.pdf', ['tanggal' => $tanggal->toDateString(), ...$keepCuti]) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" data-turbo="false">{{ __('Unduh PDF') }}</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">{{ __('Cetak') }}</button>
    </form>
</div>

{{-- Urut kronologis seperti versi PDF / format kertas HRD: Shift 2 (malam sebelumnya) di atas, lalu Shift 1. --}}
@foreach (collect($sections)->sortBy(fn (array $section) => $section['tanggal']->toDateString())->values() as $section)
    <section class="card rsm-section">
        <div class="rsm-title">
            <h2>{{ $section['tanggal']->translatedFormat('l, d F Y') }} ({{ __($section['judul']) }})</h2>
            <span class="muted small">{{ __('Grup') }} {{ $section['grups'] ? implode(', ', $section['grups']) : '-' }}</span>
        </div>
        <div class="table-wrap">
            <table class="rsm">
                <thead>
                    <tr>
                        <th rowspan="2">{{ __('Plant') }}</th>
                        <th rowspan="2">{{ __('Status') }}</th>
                        <th rowspan="2">{{ __('Jumlah Kary') }}</th>
                        <th colspan="4">{{ __('Kategori Absen') }}</th>
                        <th rowspan="2">{{ __('Total Absen') }}</th>
                        <th rowspan="2">{{ __('Total %') }}<br>{{ __('dgn Cuti') }}</th>
                        <th rowspan="2">{{ __('Total %') }}<br>{{ __('tanpa Cuti') }}</th>
                        <th rowspan="2">{{ __('Jml Kary. Kerja') }}</th>
                        <th rowspan="2" title="{{ __('Sakit dengan surat dokter') }}">SKD</th>
                        <th rowspan="2" title="{{ __('Cuti tahunan') }}">CT</th>
                    </tr>
                    <tr>
                        <th>{{ __('Cuti') }}</th>
                        <th>{{ __('Sakit') }}</th>
                        <th>{{ __('Ijin') }}</th>
                        <th>{{ __('Alpa') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($section['grups'])
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <td>{{ $row['no'] }}</td>
                                <td class="l">{{ $row['label'] }}</td>
                                <td>{{ $row['jumlah'] }}</td>
                                <td>{{ $row['cuti'] }}</td>
                                <td>{{ $row['sakit'] }}</td>
                                <td>{{ $row['ijin'] }}</td>
                                <td>{{ $row['alpa'] }}</td>
                                <td>{{ $row['total_absen'] }}</td>
                                <td @class(['pct', 'bad' => $row['persen'] > $target])>{{ $persen($row['persen'], 2) }}</td>
                                <td @class(['pct', 'bad' => $row['persen_tanpa_cuti'] > $target])>{{ $persen($row['persen_tanpa_cuti'], 2) }}</td>
                                <td>{{ $row['kerja'] }}</td>
                                <td>{{ $row['skd'] ?: '' }}</td>
                                <td>{{ $row['ct'] ?: '' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="13" class="empty">{{ __('Grup shift belum diketahui — atur jadwal rotasi di menu Detail.') }}</td></tr>
                    @endif
                </tbody>
                @if ($section['grups'])
                    <tfoot>
                        <tr>
                            <td colspan="2">{{ __('TOTAL') }}</td>
                            <td>{{ $section['total']['jumlah'] }}</td>
                            <td>{{ $section['total']['cuti'] }}</td>
                            <td>{{ $section['total']['sakit'] }}</td>
                            <td>{{ $section['total']['ijin'] }}</td>
                            <td>{{ $section['total']['alpa'] }}</td>
                            <td>{{ $section['total']['total_absen'] }}</td>
                            <td @class(['pct', 'bad' => $section['total']['persen'] > $target])>{{ $persen($section['total']['persen'], 1) }}</td>
                            <td @class(['pct', 'bad' => $section['total']['persen_tanpa_cuti'] > $target])>{{ $persen($section['total']['persen_tanpa_cuti'], 1) }}</td>
                            <td>{{ $section['total']['kerja'] }}</td>
                            <td>{{ $section['total']['skd'] ?: '' }}</td>
                            <td>{{ $section['total']['ct'] ?: '' }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endforeach
@endsection
