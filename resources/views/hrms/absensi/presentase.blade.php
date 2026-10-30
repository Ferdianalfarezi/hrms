@extends('layouts.app')

@section('title', 'Presentase Absensi')

@section('styles')
    .pst-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .pst-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .pst-toolbar input[type=month] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .day-nav a + a { border-left: 1px solid var(--line-strong); }
    .day-nav a:hover { background: #fff; }

    .pst-section { padding: 0; overflow: hidden; }
    table.pst { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    table.pst th, table.pst td { border: 1px solid var(--line-strong); padding: 5px 10px; white-space: nowrap; }
    table.pst thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; text-align: left; }
    table.pst thead tr.sum th { background: #eef1f9; font-size: 13px; text-align: right; }
    table.pst thead tr.sum th:first-child { text-align: left; }
    table.pst td.n, table.pst th.n { text-align: right; font-variant-numeric: tabular-nums; }
    table.pst td.day { font-weight: 700; }
    table.pst td.grp { font-weight: 700; font-size: 12px; }
    table.pst td.bad { color: #b91c1c; font-weight: 700; }
    table.pst tbody.day-group { border-top: 2px solid rgba(15, 35, 80, .35); }
    table.pst tbody.day-group:hover td { background: #f7faff; }
    table.pst .empty { color: var(--muted); padding: 20px; text-align: center; }

    @media print {
        .app-nav, .pst-toolbar, .success, .alert { display: none !important; }
        .shell { padding: 0; }
        .card { box-shadow: none; border: 0; backdrop-filter: none; }
        body { background: #fff; }
    }
@endsection

@section('content')
@php
    $rate = fn (?float $value) => $value === null ? '-' : number_format($value, 1, ',', '.').'%';
    $jumlahPerGrup = array_count_values(array_column($kelompok, 'grup'));
@endphp

<div class="pst-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Presentase Absensi</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ $bulan->translatedFormat('F Y') }} · hari kerja Senin - Jumat · rate = (Cuti + S+I) / Total</p>
    </div>
    <form method="GET" class="pst-toolbar">
        <span class="day-nav">
            <a href="{{ route('hrms.absensi.presentase', ['bulan' => $bulan->copy()->subMonth()->format('Y-m')]) }}" title="Bulan sebelumnya">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.presentase') }}">Bulan ini</a>
            <a href="{{ route('hrms.absensi.presentase', ['bulan' => $bulan->copy()->addMonth()->format('Y-m')]) }}" title="Bulan berikutnya">&rsaquo;</a>
        </span>
        <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="Bulan">
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">Cetak</button>
    </form>
</div>

<section class="card pst-section">
    <div class="table-wrap">
        <table class="pst">
            <thead>
                <tr class="sum">
                    <th colspan="4">{{ Str::upper($bulan->translatedFormat('F')) }}</th>
                    <th class="n">{{ number_format($total['total'], 0, ',', '.') }}</th>
                    <th class="n">{{ number_format($total['hadir'], 0, ',', '.') }}</th>
                    <th class="n">{{ number_format($total['cuti'], 0, ',', '.') }}</th>
                    <th class="n">{{ number_format($total['si'], 0, ',', '.') }}</th>
                    <th @class(['n', 'bad' => $total['rate'] > $target])>{{ $rate($total['rate']) }}</th>
                </tr>
                <tr>
                    <th>Day</th>
                    <th colspan="3">Date</th>
                    <th class="n">Total</th>
                    <th class="n">Hadir</th>
                    <th class="n">Cuti</th>
                    <th class="n" title="Sakit, izin, dan alpa">S+I</th>
                    <th class="n">Rate</th>
                </tr>
            </thead>
            @forelse ($days as $day)
                <tbody class="day-group">
                    @foreach ($kelompok as $kode => $info)
                        @php
                            $row = $day['rows'][$kode];
                            $grupBaru = $loop->first || $kelompok[array_keys($kelompok)[$loop->index - 1]]['grup'] !== $info['grup'];
                        @endphp
                        <tr>
                            @if ($loop->first)
                                <td rowspan="{{ count($kelompok) }}" class="day">{{ $day['tanggal']->translatedFormat('l') }}</td>
                                <td rowspan="{{ count($kelompok) }}" class="day">{{ $day['tanggal']->format('d/m') }}</td>
                            @endif
                            @if ($grupBaru)
                                <td rowspan="{{ $jumlahPerGrup[$info['grup']] }}" class="grp">{{ $info['grup'] }}</td>
                            @endif
                            <td>{{ $info['status'] }}</td>
                            <td class="n">{{ $row['total'] }}</td>
                            <td class="n">{{ $row['hadir'] }}</td>
                            <td class="n">{{ $row['cuti'] }}</td>
                            <td class="n">{{ $row['si'] }}</td>
                            <td @class(['n', 'bad' => $row['rate'] > $target])>{{ $rate($row['rate']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody><tr><td colspan="9" class="empty">Belum ada hari kerja di bulan ini.</td></tr></tbody>
            @endforelse
        </table>
    </div>
</section>
@endsection
