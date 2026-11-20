@extends('layouts.app')

@section('title', __('Japan Resume'))

@section('styles')
    .jr-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .jr-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .jr-toolbar input[type=month] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .month-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .month-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .month-nav a + a { border-left: 1px solid var(--line-strong); }
    .month-nav a:hover { background: #fff; }

    .jr-card { padding: 0; overflow: hidden; }
    table.jr { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; font-variant-numeric: tabular-nums; }
    table.jr th, table.jr td { border: 1px solid var(--line-strong); padding: 7px 12px; text-align: center; white-space: nowrap; }
    table.jr thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; }
    table.jr td.l { text-align: left; }
    table.jr td.num { text-align: right; }
    table.jr td.rate { background: #eef1f6; font-weight: 700; }
    table.jr tr.jr-total td { background: #fff7d6; font-weight: 700; font-size: 14px; }
    table.jr tr.jr-total td.rate { background: #fdeba6; }
    table.jr .jr-pct { margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: #fdeba6; color: #7c5a00; font-size: 11px; font-weight: 700; }
    table.jr tbody tr:hover td { background: #f7faff; }
    table.jr tbody tr:hover td.rate { background: #e6eaf2; }
    table.jr .empty { color: var(--muted); padding: 28px; }
    .auto-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: var(--soft); color: var(--navy); font-size: 10px; font-weight: 700; vertical-align: 1px; }
    .jr-note { margin: 12px 4px 0; font-size: 12px; color: var(--muted); }

    @media print {
        .app-nav, .jr-toolbar, .success, .alert, .jr-note { display: none !important; }
        .shell { padding: 0; }
        .card { box-shadow: none; border: 0; backdrop-filter: none; }
        body { background: #fff; }
    }
@endsection

@section('content')
@php
    $persen = fn (?float $value) => $value === null ? '-' : number_format($value, 1, ',', '.').'%';
    $angka = fn (int $value) => number_format($value, 0, ',', '.');
@endphp

<div class="jr-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Japan Resume') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ __('Rekap kehadiran harian :bulan · Total = MP Shift 1 + Shift 2', ['bulan' => $bulan->translatedFormat('F Y')]) }}</p>
    </div>
    <form method="GET" class="jr-toolbar">
        <span class="month-nav">
            <a href="{{ route('hrms.absensi.japan-resume', ['bulan' => $bulan->copy()->subMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan sebelumnya') }}">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.japan-resume') }}">{{ __('Bulan ini') }}</a>
            <a href="{{ route('hrms.absensi.japan-resume', ['bulan' => $bulan->copy()->addMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan berikutnya') }}">&rsaquo;</a>
        </span>
        <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Bulan') }}">
        <a href="{{ route('hrms.absensi.japan-resume.pdf', ['bulan' => $bulan->format('Y-m')]) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" data-turbo="false">{{ __('Unduh PDF') }}</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">{{ __('Cetak') }}</button>
    </form>
</div>

<section class="card jr-card">
    <div class="table-wrap">
        <table class="jr">
            <thead>
                <tr>
                    <th>{{ __('Hari') }}</th>
                    <th colspan="2">{{ __('Tanggal') }}</th>
                    <th>{{ __('Total') }}</th>
                    <th>{{ __('Hadir') }}</th>
                    <th>{{ __('Cuti') }}</th>
                    <th>S+I</th>
                    <th>{{ __('Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                {{-- Baris total di atas seperti sheet Excel; persentase Cuti dan S+I terhadap total MP di kanan angkanya. --}}
                <tr class="jr-total">
                    <td colspan="3" class="l">{{ $bulan->translatedFormat('M') }} {{ __('Total') }}</td>
                    <td class="num">{{ $angka($total['total']) }}</td>
                    <td class="num">{{ $angka($total['hadir']) }}</td>
                    <td class="num">{{ $angka($total['cuti']) }} <span class="jr-pct">{{ $persen($total['persen_cuti']) }}</span></td>
                    <td class="num">{{ $angka($total['sakit_izin']) }} <span class="jr-pct">{{ $persen($total['persen_sakit_izin']) }}</span></td>
                    <td class="rate">{{ $persen($total['rate']) }}</td>
                </tr>
                @forelse ($rows as $row)
                    <tr>
                        <td class="l">{{ $row['tanggal']->translatedFormat('l') }}</td>
                        <td>
                            {{ $row['tanggal']->format('n/j') }}
                            @if ($row['otomatis'])
                                <span class="auto-badge" title="{{ __('Dihitung otomatis dari data absensi') }}">{{ __('Otomatis') }}</span>
                            @endif
                        </td>
                        <td>{{ $row['shift'] }}</td>
                        <td class="num">{{ $angka($row['total']) }}</td>
                        <td class="num">{{ $angka($row['hadir']) }}</td>
                        <td class="num">{{ $angka($row['cuti']) }}</td>
                        <td class="num">{{ $angka($row['sakit_izin']) }}</td>
                        <td class="rate">{{ $persen($row['rate']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">{{ __('Belum ada data kehadiran di bulan ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<p class="jr-note">
    @if ($dataSampai)
        {{ __('Data sampai :tanggal dari file HRD (2026 summary). Hari setelahnya dihitung otomatis dari absensi: Cuti = CT/CK/CH/CB/IMP, S+I = Sakit + Izin + Alpa, WFH dihitung hadir.', ['tanggal' => $dataSampai->translatedFormat('d F Y')]) }}
    @else
        {{ __('Dihitung otomatis dari absensi: Cuti = CT/CK/CH/CB/IMP, S+I = Sakit + Izin + Alpa, WFH dihitung hadir.') }}
    @endif
    {{ __('両直 = dua shift masuk, 一直 = satu shift.') }}
</p>
@endsection
